<?php

namespace Omnibridge\Laravel\Auth;

use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Cookie\QueueingFactory as Cookies;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Session\Session;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Omniauth\Contract\Accounts;
use Omniauth\Contract\Sender;
use Omniauth\Contract\State;
use Omnibridge\Contract\Translations;
use Omnibridge\Contract\Urls;
use Omnibridge\Laravel\Auth\Controllers\SignIn;
use Omnibridge\Laravel\Auth\Listener\WelcomeFlash;
use Omnibridge\Laravel\Mail\LaravelMailer;
use Omnibridge\Laravel\Parameters\DatabaseSettings;
use Omnibridge\Laravel\Shield\ShieldServiceProvider;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\Psr16Adapter;
use Symfony\Component\HttpFoundation\Cookie;
use Twig\Environment;

/**
 * What a sign-in does around omniauth under Symfony, done under Laravel by
 * the same omnibase classes (plan "omniauth", step AU8b; R25 and R26 of the
 * master plan's gaps) - each where its classes are there and the stores are
 * omnibase's, as omnibase's config/services/auth.php wires them under
 * Symfony:
 *
 * - the browsers an account signs in from (Base\Security\ConnectionTracker:
 *   userConnection rows, the browser's PHPUSERID in the session and its
 *   cookie, the request's host), marked at each sign-in and failure
 *   (TrackConnections) - not at each later request: a Symfony site that
 *   loads its users with Doctrine's entity provider does not either (only
 *   omnibase's UserProvider::refreshUser() does; an application wanting it
 *   listens to the guard's Authenticated event);
 * - the new device warned of (WarnNewDevice: the e-mail, the offer of a
 *   second factor), on the administrator's settings (Base\Service\SecurityPolicy
 *   over the stored settings: ReadSettings; its role hierarchy config/omniauth.php's);
 * - the captcha after failed sign-ins from an address (GuardSignIn on
 *   Base\Security\SignInCaptcha, its widget in the sign-in form), counted
 *   in the pool config/omniauth.php's captcha.pool names - the Symfony
 *   site's own, so a captcha one host asks for is asked by the other;
 * - "welcome back" to a disabled account (WelcomeBack);
 * - the e-mails - the sign-in link, the second factor's code, the new
 *   device, welcome back - written by Base\Notifier\Mail\SignInMails with
 *   the Notifier's templates and words, sent by Laravel's mailer
 *   (Mail\LaravelMailer), and omnibase's NotifierSender as omniauth's Sender.
 */
final class OmnibaseEffects
{
    public const TRACKER = 'Base\Security\ConnectionTracker';
    public const POLICY = 'Base\Service\SecurityPolicy';
    public const CAPTCHA = 'Base\Security\SignInCaptcha';
    public const FORM_GUARD = 'Base\Service\FormGuard';
    public const MAILS = 'Base\Notifier\Mail\SignInMails';
    public const COMPOSER = 'Base\Notifier\Mail\EmailComposer';
    public const VARIABLES = 'Base\Notifier\Mail\MailVariables';
    public const SETTINGS = 'omnibridge.omnibase.settings';
    /** The cookies omnibase writes and reads plain (UserTracker::PHPUSERID, SecurityPolicy::COOKIE_NEW_DEVICE_PROMPT_DISMISSED). */
    public const PLAIN_COOKIES = ['PHPUSERID', 'base_2fa_offer'];

    /** Whether the stores are omnibase's and its sign-in's classes are there (omniscient/omnibase 4.x with AU8b). */
    public static function on(Application $app): bool
    {
        return 'omnibase' === $app->make(Settings::class)->stores && class_exists(self::TRACKER) && class_exists('Base\Auth\Listener\TrackConnections');
    }

    public static function register(Application $app): void
    {
        $app->singleton(self::TRACKER, static function (Application $app) {
            $class = self::TRACKER;
            $entities = $app->make(EntityManagerInterface::class);

            return new $class(
                $entities,
                self::connections($app),
                static fn (): string => self::browser($app),
                static fn (): ?string => self::request($app)?->getHost(),
            );
        });

        // The administrator's settings, read from their table (omnibase's SettingBag under Symfony).
        $app->singleton(self::SETTINGS, static fn (Application $app) => new \Base\Service\ReadSettings(static function (string $path, ?string $locale) use ($app): mixed {
            $default = $locale ?? $app->make(\Omnibridge\Contract\Locales::class)->getDefaultLocale();

            return $app->make(DatabaseSettings::class)->value($path, $default);
        }));

        if (!$app->bound(self::POLICY)) {
            $app->singleton(self::POLICY, static function (Application $app) {
                $settings = $app->make(self::SETTINGS);
                \assert($settings instanceof \Base\Service\SettingBagInterface);
                $class = self::POLICY;
                $roles = $app->make(Roles::class);

                $reachable = static fn (array $names): array => $roles->reachable(self::list($names));

                return new $class($settings, null, self::list(self::parameter($app, 'base.security.two_factor.required_roles')), (bool) (self::parameter($app, 'base.security.two_factor.postpone') ?? true), null, null, $reachable);
            });
        }

        $app->singleton(self::COMPOSER, static function (Application $app) {
            $class = self::COMPOSER;

            return new $class(
                $app->make(Environment::class),
                static fn (): string => self::technicalAddress($app),
                static fn (string $to): bool => self::isTest($app, $to),
                static fn (string $id): string => $app->make(Translations::class)->trans($id),
                // `app` as the e-mail templates read it (app.site.domain, app.email.title...): no omnibase AppVariable here.
                class_exists(self::VARIABLES) ? static function () use ($app): array {
                    $variables = self::VARIABLES;
                    $settings = $app->make(self::SETTINGS);
                    \assert($settings instanceof \Base\Service\SettingBagInterface);

                    return ['app' => new $variables(
                        $settings,
                        static function () use ($app): ?string {
                            // The request's domain as omnibase parses an address (AdvancedRouter::getDomain(): parse_url2()).
                            $request = self::request($app);
                            $url = null !== $request && \function_exists('parse_url2') ? parse_url2($request->getUri()) : null;
                            $domain = \is_array($url) ? ($url['domain'] ?? null) : null;

                            return \is_string($domain) && '' !== $domain ? $domain : $request?->getHost();
                        },
                        static fn (): ?string => self::request($app)?->getSchemeAndHttpHost(),
                    )];
                } : null,
            );
        });
        $app->singleton(self::MAILS, static function (Application $app) {
            $class = self::MAILS;

            return new $class($app->make(self::COMPOSER), $app->make(LaravelMailer::class), $app->make(Translations::class), $app->make(Urls::class));
        });
        $app->singleton(LaravelMailer::class, static function (Application $app): LaravelMailer {
            $mail = $app->make('mail.manager');
            \assert($mail instanceof \Illuminate\Contracts\Mail\Factory);

            return new LaravelMailer($mail, Settings::string(self::mail($app)['mailer'] ?? null) ?: null);
        });

        if (class_exists(self::CAPTCHA) && class_exists(self::FORM_GUARD) && class_exists('Omnishield\Registry')) {
            $app->singleton(self::FORM_GUARD, static function (Application $app) {
                $class = self::FORM_GUARD;
                /** @var array{challenge?: bool|string|null, fallback?: string|null, reputation?: list<string>, classifier?: string|null, unreachable?: string, min_delay?: int, sign_in_after?: int, captcha_after?: int} $guard */
                $guard = \is_array($parameter = self::parameter($app, 'base.guard')) ? $parameter : [];

                return new $class(
                    $app->make(Settings::class)->secret,
                    $guard,
                    $app->make('Omnishield\Registry'),
                    null,
                    ShieldServiceProvider::defaultChallenge($app),
                    $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null,
                    ShieldServiceProvider::acceptsUnreachable($app),
                    self::pool($app, 'captcha'),
                );
            });
            $app->singleton(self::CAPTCHA, static function (Application $app) {
                $class = self::CAPTCHA;

                return new $class($app->make(self::FORM_GUARD), self::pool($app, 'captcha'), false, $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null);
            });
            // Its widget in the sign-in form, when the visitor's address must pass it (SecurityController::Login's guard).
            $app->instance(SignIn::FORM_EXTRA, static function (Request $request) use ($app): string {
                $captcha = $app->make(self::CAPTCHA);

                return $captcha->widgetHtml($request->ip(), $app->make(Translations::class), $app->getLocale());
            });
        }

        $app->singleton('Base\Auth\Listener\TrackConnections', static fn (Application $app) => new \Base\Auth\Listener\TrackConnections($app->make(self::TRACKER), $app->make(EntityManagerInterface::class), $app->make(Accounts::class)));
        $app->singleton('Base\Auth\Listener\WarnNewDevice', static fn (Application $app) => new \Base\Auth\Listener\WarnNewDevice(
            $app->make(self::POLICY),
            $app->make(self::TRACKER),
            self::connections($app),
            $app->make(State::class),
            static fn (): bool => (bool) self::request($app)?->cookies->has('base_2fa_offer'),
            $app->make(self::MAILS),
        ));
        $app->singleton('Base\Auth\Listener\WelcomeBack', static fn (Application $app) => new \Base\Auth\Listener\WelcomeBack($app->make(EntityManagerInterface::class), null, $app->make(self::MAILS)));
        if (class_exists(self::CAPTCHA) && class_exists('Omnishield\Registry')) {
            $app->singleton('Base\Auth\Listener\GuardSignIn', static fn (Application $app) => new \Base\Auth\Listener\GuardSignIn(
                $app->make(self::CAPTCHA),
                // The captcha's answer: at the top of the request, where the widget writes it, or under the form.
                static function (string $field) use ($app): ?string {
                    $request = self::request($app);
                    if (null === $request) {
                        return null;
                    }
                    $form = Settings::string($app->make(Settings::class)->password['form'] ?? null);
                    $fields = '' !== $form ? $request->request->all($form) : [];
                    $answer = $request->request->get($field) ?? ($fields[\Base\Service\FormGuard::CHALLENGE_FIELD] ?? null);

                    return \is_string($answer) ? $answer : null;
                },
                self::list($app->make(Config::class)->get('omniauth.captcha.firewalls', ['main'])) ?: ['main'],
            ));
        }
    }

    /**
     * omnibase's Sender (NotifierSender) on SignInMails, where no Notifier is
     * bound and the application binds no Sender of its own.
     */
    public static function sender(Application $app): ?object
    {
        if (!class_exists('Base\Auth\NotifierSender') || !class_exists(self::MAILS) || $app->bound('Base\Notifier\Notifier')) {
            return null;
        }
        $class = 'Base\Auth\NotifierSender';

        return new $class($app->make(self::MAILS));
    }

    /** The listeners, in the order of config/services/auth.php's priorities; the plain cookies. */
    public static function boot(Application $app, Dispatcher $events): void
    {
        EncryptCookies::except(self::PLAIN_COOKIES);

        $events->listen('Omniauth\Event\SignedIn', ['Base\Auth\Listener\TrackConnections', 'onSignedIn']);
        $events->listen('Omniauth\Event\SignInFailed', ['Base\Auth\Listener\TrackConnections', 'onSignInFailed']);
        $events->listen('Omniauth\Event\SignedIn', ['Base\Auth\Listener\WarnNewDevice', 'onSignedIn']);
        $events->listen('Omniauth\Event\SignedIn', ['Base\Auth\Listener\UpdateAccount', 'onSignedIn']);
        $events->listen('Omniauth\Event\SignedIn', [WelcomeFlash::class, 'onSignedIn']);
        $events->listen('Omniauth\Event\AccountRefused', ['Base\Auth\Listener\WelcomeBack', 'onAccountRefused']);
        if ($app->bound('Base\Auth\Listener\GuardSignIn')) {
            $events->listen('Omniauth\Event\Verifying', ['Base\Auth\Listener\GuardSignIn', 'onVerifying']);
            $events->listen('Omniauth\Event\SignInFailed', ['Base\Auth\Listener\GuardSignIn', 'onSignInFailed']);
            $events->listen('Omniauth\Event\SignedIn', ['Base\Auth\Listener\GuardSignIn', 'onSignedIn']);
        }

        // omnibase's e-mail templates: Twig's Inky and CSS inliner, embed(), |url and |inline_css_email, the public folder as @Public.
        $app->afterResolving(Environment::class, static function (Environment $twig) use ($app): void {
            self::twig($app, $twig);
        });
        if ($app->resolved(Environment::class)) {
            try {
                self::twig($app, $app->make(Environment::class));
            } catch (\LogicException) {
                // Already used before the providers booted (its extensions initialized): no e-mail from it then.
            }
        }
    }

    /** The browser's identifier (UserTracker::getUniqid()): the session's, else its cookie's, else a new one kept in both. */
    public static function browser(Application $app): string
    {
        $session = $app->make(Session::class);
        $request = self::request($app);
        $uniqid = $session->get('PHPUSERID') ?? $request?->cookies->get('PHPUSERID');
        if (!\is_string($uniqid) || '' === $uniqid) {
            $uniqid = uniqid('', true);
            $session->put('PHPUSERID', $uniqid);
            // As Symfony's setcookie(): for the browser's session, the whole site, readable by the page.
            $app->make(Cookies::class)->queue(Cookie::create('PHPUSERID', $uniqid, 0, '/', null, false, false, false, null));
        }

        return $uniqid;
    }

    private static function twig(Application $app, Environment $twig): void
    {
        if ($twig->hasExtension('Base\Twig\Extension\MailTwigExtension') || !class_exists('Base\Twig\Extension\MailTwigExtension')) {
            return;
        }
        $project = Settings::string(self::mail($app)['project_dir'] ?? null) ?: $app->basePath();
        $loader = $twig->getLoader();
        if ($loader instanceof \Twig\Loader\FilesystemLoader && !\in_array('Public', $loader->getNamespaces(), true) && is_dir($project.'/public')) {
            $loader->addPath($project.'/public', 'Public');
        }
        foreach (['Twig\Extra\Inky\InkyExtension', 'Twig\Extra\CssInliner\CssInlinerExtension'] as $extension) {
            if (class_exists($extension) && !$twig->hasExtension($extension)) {
                $twig->addExtension(new $extension());
            }
        }
        $class = 'Base\Twig\Extension\MailTwigExtension';
        $twig->addExtension(new $class($project, static function (string $name, array $parameters) use ($app): string {
            if (str_contains($name, '://') || str_starts_with($name, '//')) {
                return $name;
            }
            if (str_starts_with($name, '/')) {
                $url = $app->make('url');
                \assert($url instanceof \Illuminate\Contracts\Routing\UrlGenerator);

                return $url->to($name);
            }

            return $app->make(Urls::class)->url($name, $parameters);
        }));
    }

    /**
     * The technical address, as the Notifier writes it: the settings' address
     * and name (base.settings.mail, base.settings.mail.name), else
     * config/omniauth.php's mail.from, else base.notifier.technical_recipient.email.
     */
    private static function technicalAddress(Application $app): string
    {
        $locale = $app->make(\Omnibridge\Contract\Locales::class)->getDefaultLocale();
        $settings = $app->make(DatabaseSettings::class);
        $default = Settings::string(self::mail($app)['from'] ?? null) ?: (Settings::string(self::parameter($app, 'base.notifier.technical_recipient.email')) ?: 'postmaster@'.(self::request($app)?->getHost() ?? 'localhost'));
        $composer = self::COMPOSER;

        return $composer::technicalAddress(Settings::string($settings->value('base.settings.mail', $locale)) ?: null, Settings::string($settings->value('base.settings.mail.name', $locale)) ?: null, $default);
    }

    /** Whether an e-mail to that address goes to the technical address (the Notifier's isTest()): mail.loopback, else mail.test_recipients in debug. */
    private static function isTest(Application $app, string $to): bool
    {
        $mail = self::mail($app);
        if ((bool) ($mail['loopback'] ?? false)) {
            return true;
        }
        if (!(bool) $app->make(Config::class)->get('app.debug')) {
            return false;
        }
        $email = array_keys(mailparse($to));
        foreach (self::list($mail['test_recipients'] ?? []) as $test) {
            if (preg_match('/'.$test.'/', (string) ($email[0] ?? ''))) {
                return true;
            }
        }

        return false;
    }

    /** The PSR-6 pool config/omniauth.php's $key.pool names (a binding), else Laravel's cache store as one. */
    public static function pool(Application $app, string $key): CacheItemPoolInterface
    {
        $binding = Settings::string($app->make(Config::class)->get('omniauth.'.$key.'.pool'));
        if ('' !== $binding) {
            $pool = $app->make($binding);
            \assert($pool instanceof CacheItemPoolInterface);

            return $pool;
        }

        return new Psr16Adapter($app->make(CacheFactory::class)->store());
    }

    /** The request being answered; null outside one (a command). */
    private static function request(Application $app): ?Request
    {
        $request = $app->bound('request') ? $app->make('request') : null;

        return $request instanceof Request ? $request : null;
    }

    /** omnibase's repository of the browsers' connections. */
    private static function connections(Application $app): \Base\Repository\User\ConnectionRepository
    {
        $repository = $app->make(EntityManagerInterface::class)->getRepository('Base\Entity\User\Connection');
        \assert($repository instanceof \Base\Repository\User\ConnectionRepository);

        return $repository;
    }

    /** @return array<string, mixed> */
    private static function mail(Application $app): array
    {
        $mail = $app->make(Config::class)->get('omniauth.mail', []);

        return \is_array($mail) ? Settings::stringKeys($mail) : [];
    }

    private static function parameter(Application $app, string $name): mixed
    {
        if (!$app->bound('Base\Service\ParameterBagInterface')) {
            return null;
        }
        try {
            return $app->make('Base\Service\ParameterBagInterface')->get($name);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<string> */
    private static function list(mixed $value): array
    {
        return array_values(array_filter(array_map(Settings::string(...), \is_array($value) ? $value : []), static fn (string $s) => '' !== $s));
    }
}
