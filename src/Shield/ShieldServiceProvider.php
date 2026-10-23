<?php

namespace Omnibridge\Laravel\Shield;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Omnishield\GatewayFactoryInterface;
use Omnishield\Registry;
use Omnishield\Replay\ReplayStoreInterface;
use Omnishield\WidgetPrinter;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * glitchr/omnishield wired into a Laravel application, from
 * config/omnishield.php - the keys of its Symfony bundle (plan "omniauth",
 * step AU8b: omnibase's captcha after failed sign-ins, under both hosts):
 * the Registry of the gateways named (each built by its package's factory,
 * the HTTP-free ones: ALTCHA, the disposable domains, the tests' fixed
 * one...), the WidgetPrinter, and ALTCHA's widget served by the
 * application at the address the bundle serves it (serve_scripts), its
 * gateways' default script then. Registered by OmnibridgeServiceProvider
 * when omnishield is installed; wires nothing without a gateway.
 */
final class ShieldServiceProvider extends ServiceProvider
{
    /** The factories built here: those that need no HTTP client (as the bundle lists them). */
    public const FACTORIES = [
        'Omnishield\Altcha\AltchaGatewayFactory',
        'Omnishield\Disposable\DisposableGatewayFactory',
        'Omnishield\Testing\FixedGatewayFactory',
    ];

    public const ALTCHA = 'Omnishield\Altcha\AltchaGatewayFactory';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/omnishield.php', 'omnishield');

        $this->app->singleton(ReplayStoreInterface::class, static function (Application $app): ReplayStoreInterface {
            $replay = self::config($app)['replay'] ?? null;
            $pool = \is_array($replay) ? ($replay['pool'] ?? null) : null;
            $pool = \is_string($pool) && '' !== $pool ? $app->make($pool) : null;
            $cache = 'Omnishield\Replay\CacheReplayStore';
            $memory = 'Omnishield\Replay\InMemoryReplayStore';

            return $pool instanceof CacheItemPoolInterface ? new $cache($pool) : new $memory();
        });
        $this->app->singleton(Registry::class, static function (Application $app): Registry {
            $factories = [];
            foreach (self::FACTORIES as $class) {
                if (!class_exists($class)) {
                    continue;
                }
                // The tokens already used, for a factory that remembers them (ALTCHA's: its first argument).
                $first = (new \ReflectionClass($class))->getConstructor()?->getParameters()[0] ?? null;
                $type = $first?->getType();
                $factory = $type instanceof \ReflectionNamedType && ReplayStoreInterface::class === $type->getName() ? (new \ReflectionClass($class))->newInstance($app->make(ReplayStoreInterface::class)) : (new \ReflectionClass($class))->newInstance();
                /** @var GatewayFactoryInterface $factory every factory of FACTORIES is one */
                $factories[] = $factory;
            }

            return new Registry($factories, self::gateways($app));
        });
        $this->app->singleton(WidgetPrinter::class, static fn () => new WidgetPrinter());
    }

    public function boot(): void
    {
        if ([] === self::gateways($this->app) || !self::servesScript($this->app)) {
            return;
        }
        $class = self::ALTCHA;
        $file = $class::SCRIPT_FILE;
        $this->app->make(Router::class)->get($class::SCRIPT_PATH, static fn () => new BinaryFileResponse($file, 200, ['Content-Type' => 'text/javascript; charset=utf-8', 'Cache-Control' => 'public, max-age=31536000, immutable']))->name('omnishield_altcha_script');

        $this->publishes([__DIR__.'/../../config/omnishield.php' => $this->app->configPath('omnishield.php')], 'omnishield-config');
    }

    /** The captcha a form asks by default: challenge.gateway, else the first gateway; null without one. */
    public static function defaultChallenge(Application $app): ?string
    {
        $challenge = self::config($app)['challenge'] ?? null;
        $gateway = \is_array($challenge) ? ($challenge['gateway'] ?? null) : null;
        if (\is_string($gateway) && '' !== $gateway) {
            return $gateway;
        }
        $first = array_key_first(self::gateways($app));

        return null !== $first ? (string) $first : null;
    }

    /** Whether a captcha that does not answer lets a form through (challenge.unreachable: accept). */
    public static function acceptsUnreachable(Application $app): bool
    {
        $challenge = self::config($app)['challenge'] ?? null;

        return 'reject' !== (\is_array($challenge) ? ($challenge['unreachable'] ?? 'accept') : 'accept');
    }

    /**
     * The gateways, by name, as the Registry takes them; ALTCHA's script the
     * application's own address when it serves it (serve_scripts).
     *
     * @return array<string, array{factory: string, options: array<string, mixed>}>
     */
    private static function gateways(Application $app): array
    {
        $gateways = [];
        $serves = self::servesScript($app);
        foreach ((array) (self::config($app)['gateways'] ?? []) as $name => $gateway) {
            if (!\is_array($gateway) || !\is_string($gateway['factory'] ?? null)) {
                continue;
            }
            $options = [];
            foreach (\is_array($gateway['options'] ?? null) ? $gateway['options'] : [] as $key => $value) {
                $options[(string) $key] = $value;
            }
            if ($serves && 'altcha' === $gateway['factory'] && !\array_key_exists('script', $options)) {
                $class = self::ALTCHA;
                $options['script'] = $class::SCRIPT_PATH;
            }
            $gateways[(string) $name] = ['factory' => $gateway['factory'], 'options' => $options];
        }

        return $gateways;
    }

    private static function servesScript(Application $app): bool
    {
        return (bool) (self::config($app)['serve_scripts'] ?? true) && class_exists(self::ALTCHA) && \defined(self::ALTCHA.'::SCRIPT_PATH');
    }

    /** @return array<string, mixed> */
    private static function config(Application $app): array
    {
        $config = $app->make(Config::class)->get('omnishield', []);
        $values = [];
        foreach (\is_array($config) ? $config : [] as $key => $value) {
            $values[(string) $key] = $value;
        }

        return $values;
    }
}
