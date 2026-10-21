<?php

namespace Omnibridge\Laravel\Auth;

use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Cookie\QueueingFactory as Cookies;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Contracts\Session\Session;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Omniauth\BackupCode\BackupCodeMethod;
use Omniauth\CacheThrottle;
use Omniauth\Contract\Accounts;
use Omniauth\Contract\BackupCodes;
use Omniauth\Contract\EmailCodes;
use Omniauth\Contract\Identities;
use Omniauth\Contract\LinkTokens;
use Omniauth\Contract\Passwords;
use Omniauth\Contract\SecondFactorPolicy;
use Omniauth\Contract\Sender;
use Omniauth\Contract\State;
use Omniauth\Contract\Throttle;
use Omniauth\Contract\TotpSecrets;
use Omniauth\Contract\TrustedVersion;
use Omniauth\EmailCode\EmailCodeMethod;
use Omniauth\EnrolledSecondFactors;
use Omniauth\Link\LinkMethod;
use Omniauth\Method\Method;
use Omniauth\OAuth\Provider\ProviderFactory;
use Omniauth\Method\Methods;
use Omniauth\Method\SecondFactor;
use Omniauth\Omniauth;
use Omniauth\Password\PasswordMethod;
use Omniauth\TrustedDevice\TrustedDevices;
use Omnibridge\Laravel\Auth\Listener\WelcomeFlash;
use Omnibridge\Laravel\Auth\Middleware\SecondFactorPending;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * omniauth wired into a Laravel application (plan "omniauth", §3.2, step
 * AU8), from config/omniauth.php - registered by OmnibridgeServiceProvider
 * when config/omnibridge.php says 'auth' => true and omniscient/omniauth is
 * installed; nothing of Auth\ without both.
 *
 * The conductor (Omniauth\Omniauth) with the methods present - each key
 * turns its method on; passkeys, TOTP and identity providers need their
 * package -, the second-factor rule (EnrolledSecondFactors; omnibase's
 * Base\Auth\Policy around it when the application binds its
 * SecurityPolicy), the session as its State, a Laravel cache store as its
 * Throttle (CacheThrottle), Laravel's dispatcher for its events
 * (omnibridge's DispatcherEvents); and around it Laravel's side: the guard
 * and the user provider (config/auth.php: driver "omniauth"), the gate's
 * answers to Security's attributes (Roles), the routes (Routes), the
 * middleware that keeps a visitor between two factors on the second
 * factor's page, the cookies the Symfony site shares left out of Laravel's
 * encryption (trusted_device, the remember-me), and - with omnibase's
 * stores - the listener that brings an account up to date at its sign-in.
 *
 * The stores are the application's: "omnibase" binds Base\Auth\'s on the
 * entity manager, or each contract by the binding config/omniauth.php names.
 */
final class AuthServiceProvider extends ServiceProvider
{
    public const SECOND_FACTORS = 'omniauth.second_factors';
    public const PROVIDER_FACTORY = 'omniauth.oauth_provider_factory';

    /** omnibase's stores by config/omniauth.php's keys, and the contract each answers. */
    private const OMNIBASE_STORES = [
        'accounts' => ['Base\Auth\UserAccounts', Accounts::class],
        'passwords' => ['Base\Auth\UserPasswords', Passwords::class],
        'link_tokens' => ['Base\Auth\UserLinkTokens', LinkTokens::class],
        'email_codes' => ['Base\Auth\UserEmailCodes', EmailCodes::class],
        'backup_codes' => ['Base\Auth\UserBackupCodes', BackupCodes::class],
        'trusted_version' => ['Base\Auth\UserTrustedVersion', TrustedVersion::class],
        'totp_secrets' => ['Base\Auth\UserTotpSecrets', TotpSecrets::class],
        'identities' => ['Base\Auth\UserIdentities', Identities::class],
        'passkeys' => ['Base\Auth\UserPasskeys', 'Omniauth\Passkey\Contract\Passkeys'],
    ];

    /** The contract each key of config/omniauth.php's "stores" names. */
    private const CONTRACTS = [
        'accounts' => Accounts::class,
        'passwords' => Passwords::class,
        'link_tokens' => LinkTokens::class,
        'email_codes' => EmailCodes::class,
        'backup_codes' => BackupCodes::class,
        'trusted_version' => TrustedVersion::class,
        'totp_secrets' => TotpSecrets::class,
        'identities' => Identities::class,
        'passkeys' => 'Omniauth\Passkey\Contract\Passkeys',
        'sender' => Sender::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/omniauth.php', 'omniauth');

        $this->app->singleton(Settings::class, static function (Application $app) {
            $config = $app->make(Config::class);
            $omniauth = $config->get('omniauth', []);

            return new Settings(Settings::stringKeys(\is_array($omniauth) ? $omniauth : []), Settings::string($config->get('app.key')));
        });
        $this->app->singleton(SessionState::class, fn (Application $app) => new SessionState($app));
        $this->app->alias(SessionState::class, State::class);
        $this->app->singleton(Contexts::class, function (Application $app) {
            $settings = $app->make(Settings::class);

            return new Contexts($app, Settings::string($settings->trustedDevice['cookie'] ?? null) ?: 'trusted_device', Settings::string($settings->trustedDevice['firewall'] ?? null) ?: 'main');
        });
        $this->app->singleton(Throttle::class, function (Application $app) {
            $settings = $app->make(Settings::class);
            $throttle = $settings->throttle ?? throw new \LogicException('The throttle is off (config/omniauth.php: throttle).');
            $store = $app->make(CacheFactory::class)->store(Settings::string($throttle['store'] ?? null) ?: null);
            $limits = [];
            foreach (\is_array($throttle['limits'] ?? null) ? $throttle['limits'] : [] as $scope => $limit) {
                $limit = \is_array($limit) ? $limit : [];
                $limits[(string) $scope] = [Settings::int($limit[0] ?? null, 5), Settings::int($limit[1] ?? null, 60)];
            }

            return new CacheThrottle($store, $limits, $app->make(ClockInterface::class));
        });
        // The second factors alone, for the rule and the address linking: no cycle through the first factors.
        $this->app->singleton(self::SECOND_FACTORS, fn (Application $app) => new Methods($this->secondFactors($app, $app->make(Settings::class))));
        $this->app->singleton(SecondFactorPolicy::class, function (Application $app) {
            $rule = new EnrolledSecondFactors(self::secondFactorMethods($app), $app->make(Settings::class)->after);
            // omnibase's rule around omniauth's, where the application binds its SecurityPolicy.
            if ('omnibase' === $app->make(Settings::class)->stores && class_exists('Base\Auth\Policy') && $app->bound('Base\Service\SecurityPolicy')) {
                $policy = $app->make('Base\Service\SecurityPolicy');
                $class = 'Base\Auth\Policy';

                return new $class($policy, $rule);
            }

            return $rule;
        });
        $this->app->singleton(Methods::class, fn (Application $app) => new Methods($this->methods($app, $app->make(Settings::class))));
        $this->app->singleton(Omniauth::class, fn (Application $app) => new Omniauth(
            $app->make(Accounts::class),
            $app->make(Methods::class),
            $app->make(State::class),
            $app->make(SecondFactorPolicy::class),
            null !== $app->make(Settings::class)->throttle ? $app->make(Throttle::class) : null,
            $app->make(EventDispatcherInterface::class),
            $app->make(ClockInterface::class),
        ));
        // The hasher as security.yaml's password_hashers would set it: Symfony's PasswordHasherFactory reads
        // the same keys (algorithm: auto, bcrypt, argon2i, argon2id, sodium, native, plaintext...; cost,
        // time_cost, memory_cost, migrate_from), so the site's configuration is said once. NativePasswordHasher
        // alone takes no such name ("bcrypt" is not a password_hash() algorithm: 500 at the first sign-in).
        $this->app->singleton(PasswordHasherInterface::class, function (Application $app) {
            $hasher = $app->make(Settings::class)->password['hasher'] ?? null;
            if (!\is_array($hasher)) {
                return new NativePasswordHasher();
            }

            return (new PasswordHasherFactory(['omniauth' => Settings::stringKeys($hasher)]))->getPasswordHasher('omniauth');
        });
        $this->app->singleton(RememberMe::class, function (Application $app) {
            $settings = $app->make(Settings::class);
            $remember = $settings->rememberMe ?? throw new \LogicException('The remember-me is off (config/omniauth.php: remember_me).');

            return new RememberMe($app->make(Accounts::class), $app->bound(Passwords::class) ? $app->make(Passwords::class) : null, $settings->secret, Settings::string($remember['cookie'] ?? null) ?: 'REMEMBERME', Settings::int($remember['lifetime'] ?? null, 604800));
        });
        $this->app->singleton(Roles::class, fn (Application $app) => new Roles($app->make(AuthFactory::class), $app->make(Settings::class)->roleHierarchy));
        $this->app->singleton(Pages::class, fn (Application $app) => new Pages($app));
        $this->app->singleton(OmniauthUserProvider::class, fn (Application $app) => new OmniauthUserProvider($app->make(Accounts::class), $app->make(Passwords::class), $app->make(PasswordHasherInterface::class)));
        if (interface_exists('Omniauth\OAuth\Provider\Provider')) {
            $this->app->singleton('Omniauth\OAuth\Provider\Providers', function (Application $app) {
                $settings = $app->make(Settings::class);
                $factories = [];
                if (class_exists('Omniauth\Google\GoogleFactory')) {
                    $class = 'Omniauth\Google\GoogleFactory';
                    $factories[] = new $class($app->make(ClockInterface::class));
                }
                foreach ($app->tagged(self::PROVIDER_FACTORY) as $factory) {
                    $factories[] = $factory;
                }

                return OAuthProviders::create($settings->providers, $app, array_values(array_filter($factories, static fn (mixed $f) => $f instanceof ProviderFactory)));
            });
        }
    }

    public function boot(): void
    {
        if (!self::on($this->app)) {
            return;
        }
        // The stores first: the configuration is complete once every provider has registered.
        $this->registerStores();
        $settings = $this->app->make(Settings::class);

        $auth = $this->app->make(AuthFactory::class);
        if ($auth instanceof AuthManager) {
            $auth->provider('omniauth', static fn (Application $app) => $app->make(OmniauthUserProvider::class));
            $auth->extend('omniauth', function (Application $app, string $name, array $config) use ($auth) {
                $guard = new OmniauthGuard(
                    $name,
                    $auth->createUserProvider(Settings::string($config['provider'] ?? null) ?: null) ?? $app->make(OmniauthUserProvider::class),
                    $app->make(Omniauth::class),
                    $app->make(Accounts::class),
                    $app->make(Contexts::class),
                    $app->make(Session::class),
                    $app->make(Cookies::class),
                    null !== $app->make(Settings::class)->rememberMe ? $app->make(RememberMe::class) : null,
                );
                if ($app->bound('request')) {
                    $guard->setRequest($app->make(Request::class));
                }
                if ($app instanceof \Illuminate\Foundation\Application) {
                    $app->refresh('request', $guard, 'setRequest');
                }

                return $guard;
            });
        }

        // Security's attributes as the gate's abilities (is_granted('ROLE_ADMIN'), the Access contract).
        $this->app->make(Gate::class)->before(fn (?Authenticatable $user, string $ability) => $this->app->make(Roles::class)->decide($user, $ability));

        // The cookies the Symfony site shares, as it writes them: plain.
        $plain = [];
        if (null !== $settings->trustedDevice) {
            $plain[] = Settings::string($settings->trustedDevice['cookie'] ?? null);
        }
        if (null !== $settings->rememberMe) {
            $plain[] = Settings::string($settings->rememberMe['cookie'] ?? null);
        }
        if ([] !== $plain) {
            EncryptCookies::except($plain);
        }

        // Between two factors, the second factor's page and nothing else: in the web group, and
        // before the auth middleware (which would send the visitor to the sign-in page instead).
        $kernel = $this->app->make(HttpKernel::class);
        if (method_exists($kernel, 'appendMiddlewareToGroup')) {
            $kernel->appendMiddlewareToGroup('web', SecondFactorPending::class);
        }
        if (method_exists($kernel, 'addToMiddlewarePriorityBefore')) {
            $kernel->addToMiddlewarePriorityBefore(AuthenticatesRequests::class, SecondFactorPending::class);
        }

        if ($settings->routes) {
            Routes::register($this->app->make(Router::class), $settings);
        }

        // omnibase's listeners of omniauth's events: the account brought up to date at its sign-in, and the
        // word of welcome its SecuritySubscriber flashes under Symfony (Listener\WelcomeFlash).
        if ('omnibase' === $settings->stores && class_exists('Base\Auth\Listener\UpdateAccount')) {
            $events = $this->app->make(Dispatcher::class);
            $events->listen('Omniauth\Event\SignedIn', ['Base\Auth\Listener\UpdateAccount', 'onSignedIn']);
            $events->listen('Omniauth\Event\SignedIn', [WelcomeFlash::class, 'onSignedIn']);
        }

        $this->publishes([__DIR__.'/../../config/omniauth.php' => $this->app->configPath('omniauth.php')], 'omniauth-config');
    }

    /** omnibridge.auth: on with true, off (the default) with false or null. */
    public static function on(Application $app): bool
    {
        $auth = $app->make(Config::class)->get('omnibridge.auth', false);

        return null !== $auth && false !== $auth;
    }

    /** The second factors an account sets up (SECOND_FACTORS), typed. */
    private static function secondFactorMethods(Application $app): Methods
    {
        $methods = $app->make(self::SECOND_FACTORS);
        \assert($methods instanceof Methods);

        return $methods;
    }

    /** The stores: omnibase's on the entity manager, or the bindings named, each contract's own name otherwise. */
    private function registerStores(): void
    {
        $stores = (array) $this->app->make(Config::class)->get('omniauth.stores', 'omnibase');
        if (['omnibase'] === array_values($stores)) {
            foreach (self::OMNIBASE_STORES as [$class, $contract]) {
                if (!class_exists($class) || !interface_exists($contract) || $this->app->bound($contract)) {
                    continue;
                }
                $this->app->singleton($class, static fn (Application $app) => new $class($app->make(EntityManagerInterface::class)));
                $this->app->alias($class, $contract);
            }
            if (class_exists('Base\Auth\NotifierSender') && !$this->app->bound(Sender::class)) {
                $this->app->bind(Sender::class, static function (Application $app) {
                    if (!$app->bound('Base\Notifier\Notifier')) {
                        throw new \LogicException('omniauth\'s Sender (a sign-in link, a code by e-mail) is omnibase\'s NotifierSender, which needs Base\Notifier\Notifier: bind one, or a Sender of your own.');
                    }
                    $class = 'Base\Auth\NotifierSender';

                    return new $class($app->make('Base\Notifier\Notifier'));
                });
            }

            return;
        }
        foreach ($stores as $key => $binding) {
            $contract = self::CONTRACTS[$key] ?? null;
            $binding = Settings::string($binding);
            if (null === $contract || '' === $binding || $binding === $contract) {
                continue;
            }
            $this->app->alias($binding, $contract);
        }
    }

    /**
     * The methods config/omniauth.php turns on (omnibridge/symfony's
     * OmniauthExtension::load() under Symfony): the first factors and the
     * trusted devices, and the second factors already built (SECOND_FACTORS).
     *
     * @return list<Method>
     */
    private function methods(Application $app, Settings $settings): array
    {
        $clock = $app->make(ClockInterface::class);
        $methods = [];
        if ($settings->passwordEnabled()) {
            $methods[] = new PasswordMethod($app->make(Accounts::class), $app->make(Passwords::class), $app->make(PasswordHasherInterface::class));
        }
        if (null !== ($link = $settings->link)) {
            $methods[] = new LinkMethod($app->make(Accounts::class), $app->make(LinkTokens::class), $app->make(Sender::class), Settings::int($link['lifetime'] ?? null, LinkMethod::LIFETIME), $clock);
        }
        foreach (self::secondFactorMethods($app) as $method) {
            if ($method instanceof Method) {
                $methods[] = $method;
            }
        }
        if (null !== ($trusted = $settings->trustedDevice)) {
            $methods[] = new TrustedDevices($app->make(Accounts::class), $app->make(TrustedVersion::class), $settings->secret, Settings::int($trusted['lifetime'] ?? null, TrustedDevices::LIFETIME), $clock);
        }
        if (null !== ($passkey = $settings->passkey)) {
            $methods[] = $this->passkey($app, $passkey);
        }
        if (null !== ($oauth = $settings->oauth)) {
            $methods[] = $this->oauth($app, $oauth, $clock);
        }

        return $methods;
    }

    /**
     * The second factors an account sets up: the e-mailed code, the backup codes, the authenticator application.
     *
     * @return list<SecondFactor>
     */
    private function secondFactors(Application $app, Settings $settings): array
    {
        $clock = $app->make(ClockInterface::class);
        $methods = [];
        if (null !== ($code = $settings->emailCode)) {
            $methods[] = new EmailCodeMethod($app->make(Accounts::class), $app->make(EmailCodes::class), $app->make(Sender::class), $settings->secret, Settings::int($code['digits'] ?? null, 6), Settings::int($code['lifetime'] ?? null, EmailCodeMethod::LIFETIME), $clock);
        }
        if (null !== ($backup = $settings->backupCodes)) {
            $methods[] = new BackupCodeMethod($app->make(BackupCodes::class), Settings::int($backup['count'] ?? null, 8));
        }
        if (null !== ($totp = $settings->totp)) {
            $methods[] = $this->totp($app, $settings, $totp, $clock);
        }

        return $methods;
    }

    /** @param array<string, mixed> $totp */
    private function totp(Application $app, Settings $settings, array $totp, ClockInterface $clock): SecondFactor
    {
        self::needs('Omniauth\Totp\TotpMethod', 'omniauth/totp', 'totp');
        $store = $app->make(CacheFactory::class)->store(Settings::string($settings->throttle['store'] ?? null) ?: null);
        $used = 'Omniauth\Totp\CacheUsedSteps';
        $method = 'Omniauth\Totp\TotpMethod';
        return new $method(
            $app->make(Accounts::class),
            $app->make(TotpSecrets::class),
            new $used($store),
            Settings::int($totp['leeway'] ?? null, 0),
            Settings::int($totp['period'] ?? null, 30),
            Settings::int($totp['digits'] ?? null, 6),
            'sha1',
            Settings::string($totp['issuer'] ?? null) ?: null,
            Settings::string($totp['server_name'] ?? null) ?: null,
            $clock,
        );
    }

    /** @param array<string, mixed> $passkey */
    private function passkey(Application $app, array $passkey): Method
    {
        self::needs('Omniauth\Passkey\PasskeyMethod', 'omniauth/passkey', 'passkey');
        $rp = 'Omniauth\Passkey\RelyingParty';
        $method = 'Omniauth\Passkey\PasskeyMethod';
        $origins = array_values(array_map(Settings::string(...), (array) ($passkey['origins'] ?? [])));
        return new $method(
            $app->make(Accounts::class),
            $app->make('Omniauth\Passkey\Contract\Passkeys'),
            new $rp(Settings::string($passkey['rp_id'] ?? null) ?: null, Settings::string($passkey['rp_name'] ?? null) ?: null, $origins),
            isset($passkey['timeout']) ? Settings::int($passkey['timeout']) : null,
        );
    }

    /** @param array<string, mixed> $oauth */
    private function oauth(Application $app, array $oauth, ClockInterface $clock): Method
    {
        self::needs('Omniauth\OAuth\OAuthMethod', 'omniauth/oauth', 'oauth');
        $byEmail = $oauth['link_by_email'] ?? null;
        if (null === $byEmail) {
            $class = 'Omniauth\OAuth\NoSecondFactorLinking';
            $linking = new $class(self::secondFactorMethods($app));
        } elseif (\is_bool($byEmail)) {
            $linking = $byEmail;
        } else {
            $linking = $app->make(Settings::string($byEmail));
        }
        \assert(\is_bool($linking) || $linking instanceof \Omniauth\OAuth\Contract\EmailLinking);
        $method = 'Omniauth\OAuth\OAuthMethod';
        return new $method($app->make(Accounts::class), $app->make(Identities::class), $app->make('Omniauth\OAuth\Provider\Providers'), $linking, $clock);
    }

    private static function needs(string $class, string $package, string $key): void
    {
        if (!class_exists($class)) {
            throw new \LogicException(\sprintf('config/omniauth.php turns "%s" on, which needs %s: composer require %s.', $key, $package, $package));
        }
    }
}
