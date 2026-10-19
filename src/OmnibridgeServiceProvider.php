<?php

namespace Omnibridge\Laravel;

use Base\Contract;
use Base\Bridge\Doctrine\Mapping;
use Base\Contract\Runtime;
use Base\Screen\ContextProvider;
use Base\Service\HotParameters;
use Base\Service\Model\EntityNouns;
use Base\Service\ParameterBagInterface;
use Base\Imagine\Svg\Imagine as SvgImagine;
use Base\Repository\Layout\ImageCropRepository;
use Base\Service\Flysystem;
use Base\Service\ImageRoute;
use Base\Service\MediaService;
use Base\Service\Model\Obfuscator\Compression\DeflateCompression;
use Base\Service\Model\Obfuscator\Compression\GzipCompression;
use Base\Service\Model\Obfuscator\Compression\HashidsCompression;
use Base\Service\Model\Obfuscator\Compression\NullCompression;
use Base\Service\Model\Obfuscator\Compression\ZlibCompression;
use Base\Service\Obfuscator;
use Imagine\Image\Metadata\ExifMetadataReader;
use Omnibridge\Laravel\Media\DiskStorages;
use Omnibridge\Laravel\Media\ImageController;
use Omnibridge\Laravel\Media\LaravelMediaRouter;
use Omnibridge\Laravel\Media\ServiceMedia;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Omnibridge\Contract as Host;
use Omnibridge\Doctrine\EntityManagers;
use Omnibridge\Laravel\Contract\AppEnvironment;
use Omnibridge\Laravel\Contract\AppLocales;
use Omnibridge\Laravel\Contract\DateClock;
use Omnibridge\Laravel\Contract\DiskFiles;
use Omnibridge\Laravel\Contract\DispatcherEvents;
use Omnibridge\Laravel\Contract\GateAccess;
use Omnibridge\Laravel\Contract\GuardSessions;
use Omnibridge\Laravel\Contract\RouterRoutes;
use Omnibridge\Laravel\Contract\SessionTokens;
use Omnibridge\Laravel\Contract\TransportMailer;
use Omnibridge\Laravel\Doctrine\Connections;
use Omnibridge\Laravel\Parameters\ConfigParameters;
use Omnibridge\Laravel\Parameters\DatabaseSettings;
use Omnibridge\Laravel\View\Twig;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;

/**
 * omniscient/omnibase hosted by Laravel: each contract of its Fondation bound
 * to Laravel's services under the contract's interface, and the Fondation's
 * registry (Base\Contract\Runtime, omniscient/omnibridge's Omnibridge\Runtime)
 * on Laravel's container, so that Runtime::urls(), ::parameters()... and
 * app(Urls::class) are one object.
 *
 * The host's contracts are omniscient/omnibridge's (Omnibridge\Contract\*), bound
 * under their own names; their former names (Base\Contract\Urls...,
 * omnibridge/twig's) are Laravel aliases of them, whichever the registry knows
 * (Runtime::formerNames()): the same binding under either. omnibase's own
 * (Nouns, Pictures, Texts, the parameters, the screen context) keep Base\.
 *
 * Bound: Urls (and omnibridge/twig's) on the router and the asset manifest,
 * Translations (and omnibridge/twig's) on the ICU catalogues, Locales,
 * Nouns, Access (the gate), Sessions (the guard), Tokens (the session's),
 * Environment, Routes, Files (the disks), the parameters (Laravel's config,
 * the database settings laid over it), PSR-20's clock, PSR-14's dispatcher,
 * symfony/mailer's mailer; and Doctrine DBAL and ORM (omnibridge/doctrine)
 * and Twig (omnibridge/twig) on the configuration; for omnibase's screens,
 * Pictures (a picture's address, the application's stored ones where it says),
 * Texts (|wysiwyg's passes on omnibase's own views) and the screen context
 * (Base\Screen\ContextProvider: locale, site, links, viewer, theme, flashes).
 * With omnibridge.media on (R6), omnibase's media rule: MediaService on
 * Laravel's router, Media bound to it, Pictures' thumbnails, and the image
 * routes (/images/...) answered by Base\Service\ImageRoute - the same
 * obfuscated addresses, thumbnails and formats as under Symfony.
 */
final class OmnibridgeServiceProvider extends ServiceProvider
{
    private ?Mapping $mapping = null;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/omnibridge.php', 'omnibridge');

        $this->app->singleton(RouterUrls::class, fn (Application $app) => new RouterUrls(null, $app['config']->get('omnibridge.assets.manifest')));
        $this->app->alias(RouterUrls::class, Host\Urls::class);

        $this->app->singleton(CatalogueTranslations::class, fn (Application $app) => new CatalogueTranslations(
            $app['config']->get('omnibridge.translations.paths', []),
            null,
            (string) ($app['config']->get('omnibridge.translations.fallback') ?? $app['config']->get('app.fallback_locale', 'en')),
        ));
        $this->app->alias(CatalogueTranslations::class, Host\Translations::class);

        $this->app->singleton(Host\Locales::class, fn (Application $app) => new AppLocales(
            $app['config']->get('omnibridge.locales.available', []),
            $app['config']->get('omnibridge.locales.default'),
        ));
        $this->app->singleton(Contract\Nouns::class, fn (Application $app) => new EntityNouns($app->make(Host\Translations::class), $app['config']->get('omnibridge.entity_namespaces', [])));
        $this->app->singleton(Host\Access::class, fn (Application $app) => new GateAccess($app->make(\Illuminate\Contracts\Auth\Access\Gate::class), $app->make('auth')));
        $this->app->singleton(Host\Sessions::class, fn (Application $app) => new GuardSessions($app->make('auth')));
        $this->app->singleton(Host\Tokens::class, fn (Application $app) => new SessionTokens($app));
        $this->app->singleton(Host\Environment::class, fn (Application $app) => new AppEnvironment(
            (string) $app->environment(),
            (bool) $app['config']->get('app.debug'),
            (string) $app['config']->get('app.key'),
        ));
        $this->app->singleton(Host\Routes::class, fn (Application $app) => new RouterRoutes($app->make('router')));
        $this->app->singleton(Host\Files::class, fn (Application $app) => new DiskFiles($app->make('filesystem'), $app['config']->get('omnibridge.storages', [])));
        $this->app->singleton(ClockInterface::class, DateClock::class);
        $this->app->singleton(EventDispatcherInterface::class, fn (Application $app) => new DispatcherEvents($app->make('events')));
        $this->app->singleton(MailerInterface::class, fn (Application $app) => new TransportMailer($app));

        $this->app->singleton(Contract\Pictures::class, fn (Application $app) => new Screen\AddressPictures($app->make(Host\Urls::class), $app->publicPath(), null, self::media($app) ? $app->make(MediaService::class) : null));
        $this->app->singleton(Contract\Texts::class, fn (Application $app) => new Screen\WysiwygTexts(static fn () => $app->make(Environment::class)));
        $this->app->bind(ContextProvider::class, fn (Application $app) => new Screen\ScreenContext($app));

        $this->registerParameters();
        $this->registerDoctrine();
        $this->registerTwig();
        $this->registerFormerNames();
    }

    /**
     * The former names of the host's contracts, Laravel aliases of their own
     * (until omnibase 4's final pass): an application or a package that makes
     * Base\Contract\Urls gets the bound Urls, the same object.
     */
    private function registerFormerNames(): void
    {
        foreach ([Host\Urls::class, Host\Translations::class, Host\Locales::class, Host\Access::class, Host\Sessions::class, Host\Tokens::class, Host\Environment::class, Host\Routes::class, Host\Files::class] as $contract) {
            foreach (Runtime::formerNames($contract) as $former) {
                $this->app->alias($contract, $former);
            }
        }
    }

    public function boot(): void
    {
        Runtime::setContainer($this->app);

        if (self::media($this->app)) {
            $this->registerMedia();
        }
        if (self::media($this->app) && false !== ($this->app['config']->get('omnibridge.media.routes') ?? true)) {
            // omnibase's image routes under their Symfony names, in Symfony's order (ImageRoute::ROUTES).
            $router = $this->app->make('router');
            foreach (ImageRoute::ROUTES as $name => [$path]) {
                $router->get($path, ImageController::class)->name($name)->where('data', '.+');
            }
        }

        $this->publishes([__DIR__.'/../config/omnibridge.php' => config_path('omnibridge.php')], 'omnibridge-config');
    }

    /**
     * Laravel's config as omnibase's parameters, the settings stored in the
     * database laid over it (HotParameters): read once, when the parameters
     * are first asked, as omnibase's HotParameterBagSubscriber reads them on
     * the first request; a database that does not answer leaves the config.
     */
    private function registerParameters(): void
    {
        $this->app->singleton(ParameterBagInterface::class, function (Application $app) {
            $parameters = new HotParameters(new ConfigParameters($app['config']), $app['config']->get('omnibridge.parameters.hot'));
            if ($parameters->isEnabled() && null !== $app['config']->get('omnibridge.doctrine.connection')) {
                try {
                    $parameters->add($app->make(DatabaseSettings::class)->parameters($app->make(Host\Locales::class)->getDefaultLocale()));
                    $parameters->markAsReady();
                } catch (DBALException) {
                }
            }

            return $parameters;
        });
    }

    /**
     * Doctrine DBAL on the Laravel connection omnibridge.doctrine.connection
     * names, the settings read through it, and omnibridge/doctrine's entity
     * manager over omnibridge.doctrine.entities. Asked without a connection
     * configured, they say so.
     *
     * With omnibridge.doctrine.omnibase on, the entity manager is omnibase's,
     * as under Symfony: built with Base\Bridge\Doctrine\Mapping::configure()
     * (omnibridge/doctrine's configure:, the repositories from omnibase's
     * registry), over the core's entities beside the application's, then
     * Mapping::register() - the class convention (App\Entity\X is
     * Base\Entity\X where the application has none), the column types and
     * enumerations, the naming strategy, the discriminator maps, #[Alias],
     * the translations, the listeners and the filters - before the first
     * metadata is read.
     */
    private function registerDoctrine(): void
    {
        $this->app->singleton(Connection::class, function (Application $app) {
            $name = $app['config']->get('omnibridge.doctrine.connection') ?? throw new \LogicException('No Doctrine connection: omnibridge.doctrine.connection names none of config/database.php.');

            return Connections::create($app['config']->get('database.connections.'.$name) ?? throw new \InvalidArgumentException(\sprintf('No database connection "%s" (omnibridge.doctrine.connection).', $name)));
        });
        $this->app->singleton(DatabaseSettings::class, fn (Application $app) => new DatabaseSettings($app->make(Connection::class)));
        $this->app->singleton(EntityManagerInterface::class, function (Application $app) {
            $paths = $app['config']->get('omnibridge.doctrine.entities', []);
            $options = self::omnibase($app);
            if (null === $options) {
                return EntityManagers::create($app->make(Connection::class), $paths, (bool) $app['config']->get('app.debug'));
            }

            // omnibase's own entities, as BaseBundle maps them under Symfony, beside the application's.
            $core = \dirname((string) (new \ReflectionClass(\Base\Entity\User::class))->getFileName());
            $entityManager = EntityManagers::create(
                $app->make(Connection::class),
                array_values(array_unique([...$paths, $core])),
                (bool) $app['config']->get('app.debug'),
                configure: Mapping::configure(...),
            );
            $this->mapping = Mapping::register($entityManager, $options);

            return $entityManager;
        });
        // What the mapping registered (its registry, reader, repositories), once the entity manager is built.
        $this->app->singleton(Mapping::class, function (Application $app) {
            $app->make(EntityManagerInterface::class);

            return $this->mapping ?? throw new \LogicException('omnibase\'s Doctrine mapping is off: omnibridge.doctrine.omnibase.');
        });
    }

    /** Laravel's environments by omnibase's names (the vault filter's, the rows Symfony wrote). */
    private const ENVIRONMENTS = ['production' => 'prod', 'local' => 'dev', 'development' => 'dev', 'testing' => 'test'];

    /**
     * Base\Bridge\Doctrine\Mapping::register()'s options when
     * omnibridge.doctrine.omnibase is on (true, or the options themselves),
     * null when off: the project's folder (its src/Enum, src/Attribute),
     * the environment by omnibase's name, a cache folder under storage/, and
     * the parameters - Laravel's config under "base" by full name
     * (base.extension.max_revisions...), those given over them.
     *
     * @return array<string, mixed>|null
     */
    private static function omnibase(Application $app): ?array
    {
        $option = $app['config']->get('omnibridge.doctrine.omnibase', false);
        if (null === $option || false === $option) {
            return null;
        }
        $options = \is_array($option) ? $option : [];
        $environment = (string) $app->environment();
        $environment = $options['environment'] ?? self::ENVIRONMENTS[$environment] ?? $environment;

        return [
            'project_dir' => $options['project_dir'] ?? $app->basePath(),
            'environment' => $environment,
            'cache_dir' => $options['cache_dir'] ?? $app->storagePath('framework/cache/omnibase-doctrine/'.$environment),
            'parameters' => ($options['parameters'] ?? []) + self::flatten((array) $app['config']->get('base', []), 'base'),
        ] + $options;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<string, mixed> the values by their full dotted name, a list kept whole
     */
    private static function flatten(array $values, string $prefix): array
    {
        $flat = [];
        foreach ($values as $key => $value) {
            if (\is_array($value) && !array_is_list($value)) {
                $flat += self::flatten($value, $prefix.'.'.$key);
            } else {
                $flat[$prefix.'.'.$key] = $value;
            }
        }

        return $flat;
    }

    /**
     * omnibase's media rule hosted by Laravel (audit "lesringostars face au
     * master plan", R6), when omnibridge.media is on: MediaService - the
     * image route's obfuscated addresses, thumbnails and formats - built on
     * Laravel's router (LaravelMediaRouter, Base\Contract\MediaRouter),
     * omnibase's Obfuscator (its identifiers' map in base.obfuscator.cache_dir,
     * the Symfony site's to read the addresses it wrote), omnibase's Flysystem
     * over Laravel's disks (DiskStorages: omnibridge.storages, local.cache
     * required) and Imagine; Base\Contract\Media bound to it (ServiceMedia),
     * Pictures' thumbnails made by it; the image routes declared under their
     * Symfony names (ImageRoute::ROUTES, boot()) and answered by
     * Base\Service\ImageRoute (ImageController). The parameters are the
     * config's "base" (base.images.*, base.obfuscator.*, base.uploader.storage,
     * base.twig.editor.storage), omnibase's defaults where it says nothing.
     * Bound at boot, once the configuration is read: off, none of it is
     * bound (Runtime::has(Media::class) is false).
     */
    private function registerMedia(): void
    {
        $this->app->singleton(LaravelMediaRouter::class, fn (Application $app) => new LaravelMediaRouter($app->make('router'), $app->make('url'), $app->make(Host\Urls::class)));
        $this->app->singleton(Obfuscator::class, function (Application $app) {
            $parameters = self::mediaParameters($app);
            $obfuscator = new Obfuscator($parameters, (string) $parameters->get('base.obfuscator.cache_dir'));
            foreach ([new NullCompression(), new ZlibCompression(), new GzipCompression(), new DeflateCompression(), new HashidsCompression((string) ($app['config']->get('omnibridge.media.secret') ?? $app['config']->get('app.key')))] as $compression) {
                $obfuscator->addCompression($compression);
            }

            return $obfuscator;
        });
        $this->app->singleton(Flysystem::class, fn (Application $app) => new Flysystem(new DiskStorages($app->make('filesystem'), $app['config']->get('omnibridge.storages', []))));
        $this->app->singleton(MediaService::class, fn (Application $app) => new MediaService(
            $app->make(Environment::class),
            $app->make(LaravelMediaRouter::class),
            $app->make(Obfuscator::class),
            $app->make(Flysystem::class),
            self::mediaParameters($app),
            self::imagine(),
            (new SvgImagine())->setMetadataReader(new ExifMetadataReader()),
            null,
            $app->make('log'),
        ));
        $this->app->singleton(ImageRoute::class, fn (Application $app) => new ImageRoute(
            $app->make(MediaService::class),
            $app->make(Flysystem::class),
            null !== self::omnibase($app) ? self::crops($app) : null,
        ));
        $this->app->singleton(Contract\Media::class, fn (Application $app) => new ServiceMedia($app->make(MediaService::class)));
    }

    /**
     * The parameters, omnibase's defaults for the media laid under the
     * config's "base" first (once); asked with omnibridge.media off, says so.
     */
    private static function mediaParameters(Application $app): ParameterBagInterface
    {
        if (!self::media($app)) {
            throw new \LogicException('omnibase\'s media are off: omnibridge.media.');
        }
        $config = $app['config'];
        if (!$app->bound('omnibridge.media.defaults')) {
            $config->set('base', array_replace_recursive(self::mediaDefaults($app), (array) $config->get('base', [])));
            $app->instance('omnibridge.media.defaults', true);
        }

        return $app->make(ParameterBagInterface::class);
    }

    /** omnibridge.media: on with true or its options, off (the default) with false or null. */
    private static function media(Application $app): bool
    {
        $media = $app['config']->get('omnibridge.media', false);

        return null !== $media && false !== $media;
    }

    /** Imagick's Imagine where the extension is loaded (omnibase's imagine.bitmap), GD's otherwise. */
    private static function imagine(): \Imagine\Image\ImagineInterface
    {
        $imagine = \extension_loaded('imagick') ? new \Imagine\Imagick\Imagine() : new \Imagine\Gd\Imagine();
        $imagine->setMetadataReader(new ExifMetadataReader());

        return $imagine;
    }

    /** The crops' repository on omnibase's entity manager (crops by slug, ratio or size), when it is one. */
    private static function crops(Application $app): ?ImageCropRepository
    {
        $repository = $app->make(EntityManagerInterface::class)->getRepository(\Base\Entity\Layout\ImageCrop::class);

        return $repository instanceof ImageCropRepository ? $repository : null;
    }

    /**
     * omnibase's defaults for the media parameters (BaseBundle's
     * configuration), under the config's "base": an application says what
     * its Symfony site's base.yaml says over them.
     *
     * @return array<string, mixed>
     */
    private static function mediaDefaults(Application $app): array
    {
        $noImage = static fn (string $file) => $app->publicPath('bundles/base/images/'.$file);

        return [
            'images' => [
                'max_resolution' => \Base\Imagine\Filter\Basic\Definition\UltraHighDefinitionFilter::class,
                'debug' => false,
                'max_quality' => 1,
                'timeout' => 60,
                'enable_webp' => true,
                'fallback' => false,
                'no_image' => [
                    ['extension' => 'webp', 'path' => $noImage('image.webp')],
                    ['extension' => 'jpg', 'path' => $noImage('image.jpg')],
                    ['extension' => 'jpeg', 'path' => $noImage('image.jpg')],
                    ['extension' => 'png', 'path' => $noImage('image.png')],
                    ['extension' => 'svg', 'path' => $noImage('image.svg')],
                    ['extension' => 'gif', 'path' => $noImage('image.gif')],
                ],
            ],
            'obfuscator' => [
                'level' => -1,
                'uuid' => \Symfony\Component\Uid\Uuid::NAMESPACE_URL,
                'encoding' => null,
                'max_length' => 0,
                'compression' => 'null',
                'cache_dir' => $app->storagePath('framework/cache/omnibase-obfuscator'),
            ],
            'uploader' => ['storage' => null],
            'twig' => ['editor' => ['storage' => null]],
        ];
    }

    /** Twig over omnibridge.twig.paths, omnibridge/twig's contract answered by the bound Urls and Translations. */
    private function registerTwig(): void
    {
        $this->app->singleton(Environment::class, fn (Application $app) => Twig::create(
            $app['config']->get('omnibridge.twig.paths', []),
            $app->make(RouterUrls::class),
            $app->make(CatalogueTranslations::class),
            $app->getLocale(),
            $app['config']->get('omnibridge.twig.timezone') ?? $app['config']->get('app.timezone'),
            [],
            ['cache' => $app['config']->get('omnibridge.twig.cache') ?? $app->storagePath('framework/twig')],
        ));
    }
}
