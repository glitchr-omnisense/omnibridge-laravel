<?php

namespace Omnibridge\Laravel\Map;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Urls;
use Omnibridge\Twig\MapExtension;
use Omnimap\MapHtml;
use Omnimap\MapPrinter;
use Omnimap\MapRendererInterface;
use Omnimap\Model\Size;
use Omnimap\ProviderFactoryInterface;
use Omnimap\Registry;
use Omnimap\Signer;
use Omnimap\Snapshot\DirectoryStorage;
use Omnimap\Snapshot\Snapshots;
use Omnimap\Snapshot\SnapshotStore;
use Omnimap\Snapshot\Storage;
use Omnimap\StaticMapInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * omniscient/omnimap wired into a Laravel application, from config/omnimap.php
 * - the same keys as omnibridge/symfony's omnimap.yaml:
 *
 *     return [
 *         'provider' => 'openstreetmap',
 *         'secret' => env('MAP_SECRET'),
 *         'providers' => ['openstreetmap' => ['user_agent' => 'MySite/1.0 (+https://example.org)']],
 *         'cache' => ['storage' => storage_path('app/omnimap'), 'directory' => 'omnimap', 'make' => 'auto'],
 *         'routes' => ['tile' => ['path' => '/omnimap/{signature}/{id}.webp', 'name' => 'omnimap_tile', 'register' => true]],
 *     ];
 *
 * cache.storage is a directory (DirectoryStorage) or the container's name
 * of an Omnimap\Snapshot\Storage. Binds Registry, Snapshots, MapPrinter and
 * omnibridge/twig's MapExtension, which omnibridge/laravel's Twig adds to a
 * page's environment; routes the tiles (GET) unless routes.tile.register is
 * false - a host whose web server serves the tiles from the public folder,
 * or that shares another host's cache, read only. The capture and the
 * deletion of a picture are routed by omnibridge/symfony; a Laravel host
 * names them (routes.export.name, routes.suppress.name) for the buttons.
 * The HTTP client is the one bound as "omnimap.http_client", Symfony's
 * otherwise.
 *
 * Registered by OmnibridgeServiceProvider when omnimap is installed; wires
 * nothing unless config/omnimap.php names a provider.
 */
final class MapServiceProvider extends ServiceProvider
{
    public const ROUTES = [
        'tile' => ['path' => '/omnimap/{signature}/{id}.webp', 'name' => 'omnimap_tile', 'register' => true],
        'metadata' => ['path' => '/omnimap/{signature}', 'name' => 'omnimap_metadata'],
        'export' => ['path' => '/omnimap/{signature}/export', 'name' => 'omnimap_export'],
        'suppress' => ['path' => '/omnimap/{signature}/suppress', 'name' => 'omnimap_suppress'],
    ];

    public const FACTORIES = ['Omnimap\Google\GoogleFactory', 'Omnimap\OpenStreetMap\OpenStreetMapFactory'];

    public function register(): void
    {
        $this->app->singleton(Registry::class, static function (Application $app): Registry {
            $config = self::config($app);
            $http = $app->bound('omnimap.http_client') ? $app->make('omnimap.http_client') : HttpClient::create();
            \assert($http instanceof HttpClientInterface);
            $cache = new Psr16Cache(new FilesystemAdapter('omnimap', 0, $app->storagePath('framework/cache/omnimap')));
            $psr18 = class_exists(\Nyholm\Psr7\Factory\Psr17Factory::class) || class_exists(\Http\Discovery\Psr17FactoryDiscovery::class) ? new Psr18Client($http) : null;
            $factories = [];
            foreach (self::FACTORIES as $class) {
                if (null !== ($factory = self::factory($class, $http, $psr18, $cache))) {
                    $factories[] = $factory;
                }
            }
            $providers = [];
            foreach ($config['providers'] as $name => $options) {
                $settings = [];
                foreach (\is_array($options) ? $options : [] as $key => $value) {
                    $settings[(string) $key] = $value;
                }
                $factory = $settings['factory'] ?? $name;
                unset($settings['factory']);
                $providers[(string) $name] = ['factory' => \is_string($factory) ? $factory : (string) $name, 'options' => $settings];
            }

            return new Registry($factories, $providers);
        });

        $this->app->singleton(Snapshots::class, static function (Application $app): Snapshots {
            $config = self::config($app);
            $cache = $config['cache'];
            $registry = $app->make(Registry::class);
            $storage = self::string($cache['storage'] ?? null, '');
            $storage = '' !== $storage && $app->bound($storage) ? $app->make($storage) : new DirectoryStorage('' === $storage ? $app->storagePath('app/omnimap') : $storage);
            \assert($storage instanceof Storage);
            $make = $cache['make'] ?? 'auto';
            $static = null;
            if (false !== $make && 'false' !== $make) {
                $map = $registry->get($config['provider']);
                $static = $map instanceof StaticMapInterface && (true === $make || 'true' === $make || $map->capabilities()->staticMap) ? $map : null;
            }
            $tilesize = self::int($cache['tilesize'] ?? null, 512);

            return new Snapshots(
                $registry->renderer($config['provider']),
                new Signer(self::string($config['secret'] ?? null, '')),
                new SnapshotStore($storage, self::string($cache['directory'] ?? null, 'omnimap'), self::string($cache['pattern'] ?? null, '{signature}/image-{id}'), self::string($cache['format'] ?? null, 'png')),
                $static,
                Size::fromString(self::string($cache['size'] ?? null, '1512x938')),
                self::int($cache['scale'] ?? null, 1),
                $tilesize > 0 ? $tilesize : null,
            );
        });
        $this->app->bind(MapRendererInterface::class, static fn (Application $app) => $app->make(Snapshots::class)->renderer());

        $this->app->singleton(MapPrinter::class, static function (Application $app): MapPrinter {
            $config = self::config($app);
            $logger = $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null;
            $html = array_map(static fn (mixed $value) => self::string($value, ''), $config['html']) + ['src' => $config['routes']['tile']['path']];

            return new MapPrinter(
                $app->make(Snapshots::class),
                new MapHtml($html),
                false !== ($config['cache_only'] ?? true),
                static function (\Throwable $e) use ($logger): void {
                    if ($logger instanceof LoggerInterface) {
                        $logger->warning('omnimap: the map\'s picture could not be made: '.$e->getMessage(), ['exception' => $e]);
                    }
                },
            );
        });

        $this->app->singleton(MapExtension::class, static function (Application $app): MapExtension {
            $config = self::config($app);
            $access = $app->bound(Access::class) ? $app->make(Access::class) : null;
            $tokens = $app->bound(Tokens::class) ? $app->make(Tokens::class) : null;

            return new MapExtension(
                $app->make(MapPrinter::class),
                $app->make(Urls::class),
                $access instanceof Access ? $access : null,
                $tokens instanceof Tokens ? $tokens : null,
                self::string($config['control'] ?? null, 'ROLE_ADMIN'),
                ['export' => $config['routes']['export']['name'], 'suppress' => $config['routes']['suppress']['name']],
            );
        });
    }

    /** The Twig extension, when omnimap is installed and config/omnimap.php names a provider; null otherwise. */
    public static function extension(): ?MapExtension
    {
        $app = Container::getInstance();
        if (!class_exists(MapExtension::class) || !$app instanceof Application || !$app->bound(MapExtension::class) || '' === self::config($app)['provider']) {
            return null;
        }
        $extension = $app->make(MapExtension::class);

        return $extension instanceof MapExtension ? $extension : null;
    }

    public function boot(Router $router): void
    {
        $config = self::config($this->app);
        if ('' === $config['provider']) {
            return;
        }
        $tile = $config['routes']['tile'];
        if (false !== ($tile['register'] ?? true)) {
            $router->get($tile['path'], [TileController::class, 'tile'])
                ->where(['signature' => '[A-Za-z0-9_-]{1,128}', 'id' => '\d+'])
                ->name($tile['name']);
        }
    }

    /**
     * @return array{provider: string, secret: mixed, providers: array<array-key, mixed>, cache: array<array-key, mixed>, html: array<array-key, mixed>, routes: array<string, array{path: string, name: string, register: mixed}>, cache_only: mixed, control: mixed}
     */
    public static function config(Application $app): array
    {
        $repository = $app->make('config');
        $config = $repository instanceof Repository ? $repository->get('omnimap', []) : [];
        $config = \is_array($config) ? $config : [];
        $routes = [];
        $given = \is_array($config['routes'] ?? null) ? $config['routes'] : [];
        foreach (self::ROUTES as $name => $default) {
            $route = \is_array($given[$name] ?? null) ? $given[$name] : [];
            $routes[$name] = ['path' => self::string($route['path'] ?? null, $default['path']), 'name' => self::string($route['name'] ?? null, $default['name']), 'register' => $route['register'] ?? $default['register'] ?? true];
        }

        return [
            'provider' => self::string($config['provider'] ?? null, ''),
            'secret' => $config['secret'] ?? null,
            'providers' => \is_array($config['providers'] ?? null) ? $config['providers'] : [],
            'cache' => \is_array($config['cache'] ?? null) ? $config['cache'] : [],
            'html' => \is_array($config['html'] ?? null) ? $config['html'] : [],
            'routes' => $routes,
            'cache_only' => $config['cache_only'] ?? true,
            'control' => $config['control'] ?? null,
        ];
    }

    /** A provider's factory, when its package is installed. */
    private static function factory(string $class, HttpClientInterface $http, ?Psr18Client $psr18, Psr16Cache $cache): ?ProviderFactoryInterface
    {
        if (!class_exists($class)) {
            return null;
        }
        $factory = new $class($http, $psr18, $cache);

        return $factory instanceof ProviderFactoryInterface ? $factory : null;
    }

    private static function string(mixed $value, string $default): string
    {
        return \is_scalar($value) && '' !== (string) $value ? (string) $value : $default;
    }

    private static function int(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }
}
