<?php

namespace Omnibridge\Laravel\View;

use Base\Bridge\Symfony\AccountScreens;
use Base\Contract\Texts;
use Base\Screen\Builder\Accounts;
use Base\Demo\DemoMode;
use Base\Repository\User\Notifications;
use Base\Service\Icons;
use Base\Service\MediaService;
use Base\Service\Model\IconProvider\Adapter\BootstrapTwitterAdapter;
use Base\Service\Model\IconProvider\Adapter\FontAwesomeAdapter;
use Base\Service\Push\WebPushService;
use Base\Service\Obfuscator;
use Base\Service\ThemizerInterface;
use Base\Twig\Extension\AccountTwigExtension;
use Base\Twig\Extension\ClassTwigExtension;
use Base\Twig\Extension\DemoTwigExtension;
use Base\Twig\Extension\DeployVersionExtension;
use Base\Twig\Extension\EnumTwigExtension;
use Base\Twig\Extension\IconTwigExtension;
use Base\Twig\Extension\LinkTwigExtension;
use Base\Twig\Extension\MenuTwigExtension;
use Base\Twig\Extension\NotificationTwigExtension;
use Base\Twig\Extension\PictureTwigExtension;
use Base\Twig\Extension\SecurityPolicyTwigExtension;
use Base\Twig\Extension\TextsTwigExtension;
use Base\Twig\Variable\AdminLinks;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Contracts\Foundation\Application;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Locales;
use Omnibridge\Contract\Routes;
use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Translations;
use Omnibridge\Contract\Urls;
use Omnibridge\Laravel\CatalogueTranslations;
use Omnibridge\Laravel\Page\Theme;
use Omnibridge\Laravel\Screen\WysiwygTexts;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Twig\Extension\ExtensionInterface;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

/**
 * omnibase's own Twig extensions in a Laravel page (audit "lesringostars face
 * au master plan", R24): the classes the core registers under Symfony,
 * built here on what Laravel binds - the same functions and filters from the
 * same code, nothing of them rewritten by an application:
 *
 * - MenuTwigExtension (render_locale(), render_theme_*()): the Locales
 *   contract and the visitor's theme (omnibridge.theme, Theme::of());
 * - EnumTwigExtension (enum(), |colorify, |trans_enum): the page's
 *   Translations, once the entity manager has registered the DBAL types;
 * - PictureTwigExtension (image(), |thumbnail, |soundify, |mimetype...):
 *   omnibase's MediaService, omnibridge.media on;
 * - LinkTwigExtension (|urlify, |linkify); IconTwigExtension (|iconify):
 *   omnibase's icon adapters over its metadata (public/bundles/base) and the
 *   Routes contract's icons;
 * - TextsTwigExtension (|wysiwyg, |wysiwyg_toc): the Texts contract, on the
 *   page's own environment;
 * - NotificationTwigExtension (the bell): Access, Tokens, the bound
 *   Notifications repository, WebPushService on omnibridge.push's keys;
 * - SecurityPolicyTwigExtension (security_policy): the bound SecurityPolicy
 *   (omnibridge.auth with omnibase's stores);
 * - DeployVersionExtension (deploy_version()): omnibridge.deploy.project_dir;
 * - DemoTwigExtension (demo_mode(), demo_accounts()): the application's
 *   environment, never omnibase's `demo` here;
 * - omnibase/consent's ConsentExtension (consent_options(), consent_settings()),
 *   where it is installed and omnibridge.consent.config names the site's
 *   configuration (a YAML file with a consent: key, or the array);
 * - HostExtension: what the templates take from Symfony's bundles (Encore's
 *   tags over omnibridge.encore, stimulus_controller(), absolute_url(), |url).
 *
 * Each where the core has it (omnisense/omnibase 4 from R24) and what it
 * stands on is bound. The container's Environment gets them all
 * (OmnibridgeServiceProvider); an application that renders a page in an
 * environment of its own (its globals, its forms) extends it with extend().
 */
final class OmnibaseExtensions
{
    public function __construct(private readonly Application $app)
    {
    }

    /** Whether the core has its framework-free Twig extensions (omnisense/omnibase 4 from R24). */
    public static function available(): bool
    {
        return class_exists(EnumTwigExtension::class);
    }

    /**
     * omnibase's extensions added to an environment not yet used.
     *
     * @param Translations|null       $translations the page's (its language), the bound ones otherwise
     * @param ThemizerInterface|null  $theme        the visitor's theme, Theme::of(omnibridge.theme) otherwise
     */
    public function extend(Environment $twig, ?Translations $translations = null, ?ThemizerInterface $theme = null): Environment
    {
        foreach ($this->extensions($twig, $translations, $theme) as $extension) {
            if (!$twig->hasExtension($extension::class)) {
                $twig->addExtension($extension);
            }
        }
        // account_profile(user) (AccountTwigExtension): a member's profile as its view model, by omnibase's builder
        // over the contracts (AccountScreens, the extension's runtime), for a page rendering the core's profile template.
        if (self::available() && class_exists(AccountTwigExtension::class) && !$twig->hasExtension(AccountTwigExtension::class)) {
            $app = $this->app;
            $twig->addExtension(new AccountTwigExtension());
            $twig->addRuntimeLoader(new FactoryRuntimeLoader([AccountScreens::class => static fn (): AccountScreens => new AccountScreens(
                new Accounts($app->bound(self::POLICY) ? $app->make(self::POLICY) : null, $app->make(Urls::class), $app->make(Tokens::class)),
                $twig,
                $app->make(Access::class),
            )]));
        }

        return $twig;
    }

    /** @return list<ExtensionInterface> */
    public function extensions(Environment $twig, ?Translations $translations = null, ?ThemizerInterface $theme = null): array
    {
        if (!self::available()) {
            return [$this->host()];
        }
        $app = $this->app;
        $translations ??= $app->make(Translations::class);
        /** @var array{modes?: array<string, array<string, string>>, filters?: array<string, array<string, string>>, no_filter?: array<string, mixed>} $themes */
        $themes = (array) $this->config('omnibridge.theme', []);
        $theme ??= Theme::of($themes, $this->request());

        /** @var list<ExtensionInterface> $extensions */
        $extensions = [$this->host()];
        if (null !== $theme) {
            $extensions[] = new MenuTwigExtension($app->make(Locales::class), $theme);
        }
        // The DBAL types enum() names (thread_state) are registered with the entity manager.
        if ($app->bound(EntityManagerInterface::class)) {
            $app->make(EntityManagerInterface::class);
        }
        $extensions[] = new EnumTwigExtension($translations);
        // get_class(), class_exists(), get_short_class(): omnibase's, framework-free; |trans_entity and
        // |obfuscate by omnibase's rules over what Laravel binds (OmnibaseFilters, R27).
        $extensions[] = new ClassTwigExtension();
        $extensions[] = new OmnibaseFilters($translations, $app->bound(Obfuscator::class) ? static fn (): Obfuscator => $app->make(Obfuscator::class) : null);
        $extensions[] = new LinkTwigExtension();
        $extensions[] = new IconTwigExtension($this->icons());
        if ($app->bound(MediaService::class)) {
            $extensions[] = new PictureTwigExtension($app->make(MediaService::class));
        }
        if ($app->bound(Texts::class)) {
            $texts = $app->make(Texts::class);
            $extensions[] = new TextsTwigExtension($texts instanceof WysiwygTexts ? $texts->on($twig) : $texts);
        }
        if ($app->bound(Notifications::class)) {
            $extensions[] = new NotificationTwigExtension($app->make(Access::class), $app->make(Notifications::class), $this->push(), $app->make(Tokens::class));
        }
        if ($app->bound(self::POLICY)) {
            $extensions[] = new SecurityPolicyTwigExtension($app->make(self::POLICY));
        }
        $project = $this->config('omnibridge.deploy.project_dir');
        $extensions[] = new DeployVersionExtension(\is_string($project) && '' !== $project ? $project : $app->basePath());
        $extensions[] = new DemoTwigExtension(new DemoMode(self::environment($app)));
        if (null !== ($consent = $this->consent($translations))) {
            $extensions[] = $consent;
        }

        return $extensions;
    }

    /** app.admin: a record's back-office page by omnibase/admin's names, on the Routes and Urls contracts. */
    public function admin(): ?object
    {
        return self::available() ? new AdminLinks($this->app->make(Routes::class), $this->app->make(Urls::class)) : null;
    }

    private const POLICY = 'Base\Service\SecurityPolicy';

    /** @var array<string, list<\Base\Service\Model\IconProvider\IconAdapterInterface>> omnibase's icon adapters by metadata folder: their contents read once a process */
    private static array $adapters = [];

    private static function environment(Application $app): string
    {
        $environment = $app->environment();

        return \is_string($environment) ? $environment : 'production';
    }

    private function config(string $key, mixed $default = null): mixed
    {
        $config = $this->app->make('config');
        \assert($config instanceof \Illuminate\Contracts\Config\Repository);

        return $config->get($key, $default);
    }

    private function request(): ?\Illuminate\Http\Request
    {
        $request = $this->app->bound('request') ? $this->app->make('request') : null;

        return $request instanceof \Illuminate\Http\Request ? $request : null;
    }

    private function host(): HostExtension
    {
        $encore = (array) $this->config('omnibridge.encore', []);
        /** @var list<array{entrypoints: string, manifest: string}> $builds */
        $builds = (array) ($encore['builds'] ?? []);
        /** @var array<string, string> $breakpoints */
        $breakpoints = (array) ($encore['breakpoints'] ?? []);
        /** @var array<string, list<string>> $optional */
        $optional = (array) ($encore['optional'] ?? []);

        return new HostExtension(
            $this->app->make(Urls::class),
            $this->request(...),
            [] === $builds ? null : new Encore($builds, $this->app->publicPath(), $breakpoints, $optional),
        );
    }

    /** omnibase's icon adapters over its metadata, a route's icons by the Routes contract. */
    private function icons(): Icons
    {
        $app = $this->app;
        if (!$app->bound(Icons::class)) {
            $metadata = $this->config('omnibridge.icons.metadata');
            $metadata = \is_string($metadata) && '' !== $metadata ? $metadata : $app->publicPath('bundles/base/metadata');
            $app->singleton(Icons::class, static function (Application $app) use ($metadata): Icons {
                $cache = $app->storagePath('framework/cache/omnibase-icons');
                $routes = $app->make(Routes::class);

                return new Icons(
                    // Made once in the process for a folder, when a first icon is asked (each reads its metadata through its cache).
                    static fn (): array => self::$adapters[$metadata] ??= [
                        new FontAwesomeAdapter($metadata.'/icons.yml', $cache),
                        new BootstrapTwitterAdapter($metadata.'/bootstrap-icons.json', $cache),
                    ],
                    static fn (mixed $route): ?array => \is_string($route) && '' !== $route ? $routes->icons($route) : null,
                );
            });
        }

        return $app->make(Icons::class);
    }

    /** omnibase's WebPushService on omnibridge.push's keys; none without them. */
    private function push(): ?WebPushService
    {
        $push = (array) $this->config('omnibridge.push', []);
        $public = $push['public_key'] ?? null;
        if (!\is_string($public) || '' === $public || !$this->app->bound(EntityManagerInterface::class)) {
            return null;
        }
        $private = $push['private_key'] ?? null;
        $subject = $push['subject'] ?? null;
        $entities = $this->app->make(EntityManagerInterface::class);
        $repository = $entities->getRepository(\Base\Entity\User\PushSubscription::class);
        $logger = $this->app->make('log');

        return $repository instanceof \Base\Repository\User\PushSubscriptionRepository
            ? new WebPushService($entities, $repository, $logger instanceof \Psr\Log\LoggerInterface ? $logger : null, $public, \is_string($private) ? $private : null, \is_string($subject) ? $subject : null)
            : null;
    }

    /**
     * omnibase/consent's panel on the site's configuration, the page's words, Laravel's addresses
     * (Base\Consent\ConsentConfig, ConsentPanel, Bridge\Twig\ConsentExtension: suggested, named).
     */
    private function consent(Translations $translations): ?ExtensionInterface
    {
        $site = $this->config('omnibridge.consent.config');
        if (null === $site || !class_exists('Base\Consent\ConsentConfig')) {
            return null;
        }
        if (\is_string($site)) {
            $site = is_file($site) ? ((array) Yaml::parseFile($site))['consent'] ?? [] : [];
        }
        $urls = $this->app->make(Urls::class);
        $translator = ($translations instanceof CatalogueTranslations ? $translations : $this->app->make(CatalogueTranslations::class))->translator();
        [$config, $panel, $extension] = [self::named('Base\Consent\ConsentConfig'), self::named('Base\Consent\ConsentPanel'), self::named('Base\Consent\Bridge\Twig\ConsentExtension')];
        $normalize = [$config, 'normalize'];
        $features = [$panel, 'features'];
        \assert(\is_callable($normalize) && \is_callable($features));
        $consent = new $extension(new $panel(
            $normalize((array) $site),
            $translator,
            $features(),
            static function (string $route, array $parameters = []) use ($urls): string {
                /** @var array<string, mixed> $parameters */
                return $urls->path($route, $parameters);
            },
        ));

        return $consent instanceof ExtensionInterface ? $consent : null;
    }

    /**
     * A suggested package's class, by its name.
     *
     * @return class-string
     */
    private static function named(string $class): string
    {
        return class_exists($class) ? $class : throw new \LogicException($class.' is not installed.');
    }
}
