<?php

namespace Base;

use Base\Contract;
use Omnibridge\Laravel\Console;
use Omnibridge\Laravel\Container;
use Omnibridge\Laravel\Map;
use Omnibridge\Laravel\Omnibase;
use Omnibridge\Laravel\Parameters;
use Omnibridge\Laravel\Persistence;
use Omnibridge\Laravel\Screen;
use Omnibridge\Laravel\Shield;
use Omnibridge\Laravel\Static\BuildCommand;
use Omnibridge\Laravel\RouterUrls;
use Omnibridge\Laravel\CatalogueTranslations;
use Omnibridge\Doctrine\Omnibase\Mapping;
use Base\Contract\Runtime;
use Base\Screen\ContextProvider;
use Base\Screen\Negotiation;
use Base\Service\HotParameters;
use Base\Service\Model\ClassAliases;
use Base\Service\Model\EntityNouns;
use Base\Service\ParameterBagInterface;
use Base\Imagine\Svg\Imagine as SvgImagine;
use Base\Repository\Layout\ImageCrops;
use Base\Service\Flysystem;
use Base\Service\ImageRoute;
use Base\Service\MediaService;
use Base\Service\Obfuscator;
use Base\Service\Model\Wysiwyg\MediaEnhancer;
use Base\Service\Model\Wysiwyg\MediaEnhancerInterface;
use Imagine\Image\Metadata\ExifMetadataReader;
use Omnibridge\Laravel\Media\DiskStorages;
use Omnibridge\Laravel\Omnibase\OmnibaseOption;
use Omnidrive\Drives;
use Omnidrive\Flysystem\OperatorDrives;
use Omnibridge\Laravel\Media\ImageController;
use Omnibridge\Laravel\Media\LaravelMediaRouter;
use Omnibridge\Laravel\Media\ServiceMedia;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Base\Database\Repository\Repositories;
use Base\Database\Repository\ServiceEntityRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Filesystem\Factory as DiskFactory;
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
use Omnibridge\Laravel\Contract\LaravelRequests;
use Omnibridge\Laravel\Contract\RouterRoutes;
use Omnibridge\Laravel\Contract\LaravelValidation;
use Illuminate\View\Compilers\BladeCompiler;
use Base\Form\FormRenderer;
use Omnibridge\Laravel\Contract\SessionTokens;
use Omnibridge\Laravel\Contract\TransportMailer;
use Omnibridge\Laravel\Doctrine\Connections;
use Omnibridge\Laravel\Parameters\ConfigParameters;
use Omnibridge\Laravel\Parameters\DatabaseSettings;
use Base\View\Twig;
use Psr\Clock\ClockInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\CacheInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;
use Omnibridge\Twig\TwigViews;

/**
 * omnisense/omnibase hosted by Laravel: each contract of its Foundation bound
 * to Laravel's services under the contract's interface, and the Foundation's
 * registry (Base\Contract\Runtime, omnisense/omnibridge's Omnibridge\Runtime)
 * on Laravel's container, so that Runtime::urls(), ::parameters()... and
 * app(Urls::class) are one object.
 *
 * The host's contracts are omnisense/omnibridge's (Omnibridge\Contract\*), bound
 * under their own names (their former names, Base\Contract\Urls... and
 * omnibridge/twig's, were removed at omnibase 4's final pass). omnibase's own
 * (Nouns, Pictures, Texts, the parameters, the screen context) keep Base\.
 *
 * Bound: Urls on the router and the asset manifest,
 * Translations on the ICU catalogues, Locales,
 * Nouns, Access (the gate), Sessions (the guard), Tokens (the session's),
 * Environment, Routes, Files (the disks), the parameters (Laravel's config,
 * the database settings laid over it), PSR-20's clock, PSR-14's dispatcher,
 * symfony/mailer's mailer; and Doctrine DBAL and ORM (omnibridge/doctrine)
 * and Twig (omnibridge/twig) on the configuration; for omnibase's screens,
 * Pictures (a picture's address, the application's stored ones where it says),
 * Texts (|wysiwyg's passes on omnibase's own views) and the screen context
 * (Base\Screen\ContextProvider: locale, site, links, viewer, theme, flashes);
 * and an action's result answered (R10): omnibase's Negotiation on the
 * container's Twig, Screen\Responder its Laravel side (Responder::action()
 * for a route).
 * With omnibridge.media on (R6), omnibase's media rule: MediaService on
 * Laravel's router, Media bound to it, Pictures' thumbnails, and the image
 * routes (/images/...) answered by Base\Service\ImageRoute - the same
 * obfuscated addresses, thumbnails and formats as under Symfony; with omnibase's
 * mapping too (R7), the attribute reader's Flysystem (an entity's #[Uploader]:
 * uploads, audio), and the editor's pictures by omnibase's MediaEnhancer
 * (Texts, MediaEnhancerInterface).
 */
final class BaseServiceProvider extends ServiceProvider
{
    private ?Mapping $mapping = null;

    /**
     * The Blade side of the generic form theme: @form, @formStart, @formRow, @formWidget, @formLabel,
     * @formErrors, @formRest and @formEnd print a form view (omnibase's Base\Form\Definition\FormView or
     * Form\FormView) through FormRenderer, the markup Twig's form_*() functions print under Laravel.
     */
    private static function formDirectives(BladeCompiler $blade): void
    {
        foreach (['form' => 'form', 'formStart' => 'start', 'formRow' => 'row', 'formWidget' => 'widget', 'formLabel' => 'fieldLabel', 'formErrors' => 'errors', 'formRest' => 'rest', 'formEnd' => 'end'] as $directive => $method) {
            $blade->directive($directive, static fn (string $expression): string => '<?php echo app('.FormRenderer::class.'::class)->'.$method.'('.$expression.'); ?>');
        }
    }

    /**
     * The catalogues' folders, Symfony's validator's first where it is installed: its messages in each
     * language (the `validators` domain), what a kept constraint says on every host.
     *
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private static function translationPaths(array $paths): array
    {
        if (class_exists(\Symfony\Component\Validator\Constraints\NotBlank::class)) {
            array_unshift($paths, \dirname((string) (new \ReflectionClass(\Symfony\Component\Validator\Validation::class))->getFileName()).'/Resources/translations');
        }

        return $paths;
    }

    public function register(): void
    {
        $this->mergeConfigFrom(\dirname(__DIR__, 2).'/config/omnibridge.php', 'omnibridge');
        // omnibase's class convention (App\Entity\X, App\Enum\X... is the Base\ class the application does not define),
        // from the start, as omnibase's BaseBundle answers it under Symfony: not only once Doctrine's entity manager is built.
        ClassAliases::register();

        // base.router.fallback: a service answering the route names this host lacks (another site's routes), its URLs
        // and its routes; base.router.fallback_locale: a name declared per language read in that language.
        $this->app->singleton(RouterUrls::class, static function (Application $app): RouterUrls {
            $elsewhere = self::routerFallback($app);
            $urls = new RouterUrls($elsewhere instanceof Host\Urls ? static fn (string $name, array $parameters, string $locale): string => $elsewhere->url($name, $parameters) : null, $app['config']->get('omnibridge.assets.manifest'));
            $fallback = $app['config']->get('base.router.fallback_locale');

            return \is_string($fallback) && '' !== $fallback ? $urls->localized($fallback) : $urls;
        });
        $this->app->alias(RouterUrls::class, Host\Urls::class);

        $this->app->singleton(CatalogueTranslations::class, fn (Application $app) => new CatalogueTranslations(
            self::translationPaths($app['config']->get('omnibridge.translations.paths', [])),
            null,
            (string) ($app['config']->get('omnibridge.translations.fallback') ?? $app['config']->get('app.fallback_locale', 'en')),
        ));
        $this->app->alias(CatalogueTranslations::class, Host\Translations::class);

        $this->app->singleton(Host\Locales::class, fn (Application $app) => new AppLocales(
            $app['config']->get('omnibridge.locales.available', []),
            $app['config']->get('omnibridge.locales.default'),
            (string) ($app['config']->get('app.timezone') ?: 'UTC'),
        ));
        // The visitor's zone cookies are the browser's, written by omnibase's script: read as they are.
        if (class_exists(\Illuminate\Cookie\Middleware\EncryptCookies::class)) {
            \Illuminate\Cookie\Middleware\EncryptCookies::except([AppLocales::TIMEZONE_COOKIE, AppLocales::INFO_COOKIE]);
        }
        $this->app->singleton(Contract\Nouns::class, fn (Application $app) => new EntityNouns($app->make(Host\Translations::class), $app['config']->get('omnibridge.entity_namespaces', [])));
        $this->app->singleton(Host\Access::class, fn (Application $app) => new GateAccess($app->make(\Illuminate\Contracts\Auth\Access\Gate::class), $app->make('auth')));
        $this->app->singleton(Host\Sessions::class, fn (Application $app) => new GuardSessions($app->make('auth')));
        if (interface_exists(Host\Requests::class)) {
            $this->app->singleton(Host\Requests::class, fn (Application $app) => new LaravelRequests($app));
        }
        $this->app->singleton(Host\Tokens::class, fn (Application $app) => new SessionTokens($app));
        // omnibase's unit of work on the entity manager this package gives, where omnibridge/doctrine's adapter is installed.
        if (class_exists(\Omnibridge\Doctrine\Omnibase\DoctrineUnitOfWork::class)) {
            $this->app->singleton(\Omnibridge\Persistence\UnitOfWork::class, fn (Application $app) => new \Omnibridge\Doctrine\Omnibase\DoctrineUnitOfWork($app->make(\Doctrine\ORM\EntityManagerInterface::class)));
        }
        $this->app->singleton(Host\Validation::class, fn (Application $app) => new LaravelValidation(
            $app->make('validator'),
            $app->make(Host\Translations::class),
            // Unique's lookup: omnibase's over its repositories, when it is here.
            class_exists(\Base\Form\Definition\UniqueLookup::class) ? static fn (string $class, array $criteria, ?object $record): bool => (new \Base\Form\Definition\UniqueLookup($app->make(\Base\Database\Repository\Repositories::class)))($class, $criteria, $record) : null,
        ));
        $this->app->singleton(Host\Environment::class, fn (Application $app) => new AppEnvironment(
            (string) $app->environment(),
            (bool) $app['config']->get('app.debug'),
            (string) $app['config']->get('app.key'),
        ));
        $this->app->singleton(Host\Routes::class, static function (Application $app): RouterRoutes {
            $routes = new RouterRoutes($app->make('router'));
            $elsewhere = self::routerFallback($app);

            return $elsewhere instanceof Host\Routes ? $routes->elsewhere($elsewhere) : $routes;
        });
        $this->app->singleton(Host\Files::class, fn (Application $app) => new DiskFiles($app->make('filesystem'), $app['config']->get('omnibridge.storages', [])));
        // The storages as omnisense/omnidrive's drives: each disk's Flysystem operator, by the name
        // omnibridge.storages maps to it, else by the disk's own name.
        if (class_exists(OperatorDrives::class)) {
            $this->app->singleton(OperatorDrives::class, fn (Application $app) => new OperatorDrives(self::diskStorages($app)));
            $this->app->alias(OperatorDrives::class, Drives::class);
        }
        $this->app->singleton(ClockInterface::class, DateClock::class);
        $this->app->singleton(EventDispatcherInterface::class, fn (Application $app) => new DispatcherEvents($app->make('events')));
        $this->app->singleton(MailerInterface::class, fn (Application $app) => new TransportMailer($app));

        $this->app->singleton(Contract\Pictures::class, fn (Application $app) => new Screen\AddressPictures($app->make(Host\Urls::class), $app->publicPath(), null, self::media($app) ? $app->make(MediaService::class) : null));
        $this->app->singleton(Contract\Texts::class, fn (Application $app) => new Screen\WysiwygTexts(static fn () => $app->make(Environment::class)));
        $this->app->bind(ContextProvider::class, fn (Application $app) => new Screen\ScreenContext($app));
        // An action's result answered (omnibase's Negotiation, framework-free; Screen\Responder its Laravel side):
        // the container's Twig renders every template; an application whose pages carry more binds its own.
        $this->app->bind(Negotiation::class, fn (Application $app) => new Negotiation(
            static fn (string $template, array $context): string => $app->make(Environment::class)->render($template, $context),
            $app->make(ContextProvider::class),
            $app['config']->get('omnibridge.screens.version'),
        ));
        $this->app->bind(Screen\Responder::class, fn (Application $app) => new Screen\Responder($app->make(Negotiation::class)));

        // omnibase's cache pool (what its repositories and the sites' keep a count or a list in,
        // cache.app under Symfony), as PSR-6 and Symfony's cache contracts: Symfony's cache
        // component in storage/framework/cache/omnibase-pool. Not Laravel's store through a
        // PSR-16 adapter: its values are objects, which Laravel's cache refuses to unserialize
        // (cache.serializable_classes false, Laravel's default).
        $this->app->singleton(FilesystemAdapter::class, fn (Application $app) => new FilesystemAdapter('omnibase', 0, $app->storagePath('framework/cache/omnibase-pool')));
        $this->app->alias(FilesystemAdapter::class, CacheItemPoolInterface::class);
        $this->app->alias(FilesystemAdapter::class, CacheInterface::class);

        $this->registerParameters();
        $this->registerDoctrine();
        // The persistence (omnisense/omnibase's plan "omnibase agnostic", step F7d): base.persistence, omnibridge/eloquent's
        // services behind the same contracts when it is the one chosen.
        Persistence::register($this->app, static fn (Application $app): ?array => self::omnibase($app), static fn (Application $app): array => class_exists(\Base\Services::class) ? Omnibase\OmnibaseServices::entityDispatchers($app) : []);
        $this->registerTwig();
        // omnibridge's Views over the container's Twig (omnisense/omnibase's step F5a): what omnibase's
        // services render through - the media tags, the mails, the sitemap, the sharing buttons.
        $this->app->singleton(TwigViews::class, fn (Application $app) => new TwigViews(static fn (): Environment => $app->make(Environment::class)));
        $this->app->alias(TwigViews::class, Host\Views::class);
        // Signing in through omniauth (plan "omniauth", step AU8), where omnisense/omniauth is
        // installed: Auth\AuthServiceProvider, which wires nothing unless omnibridge.auth is on
        // (the configuration may be set after the providers register: Testbench's defineEnvironment).
        if (class_exists(\Omniauth\Omniauth::class)) {
            $this->app->register(Auth\AuthServiceProvider::class);
        }
        // Maps through omnimap, where omnisense/omnimap is installed: Map\MapServiceProvider, which
        // wires nothing unless config/omnimap.php names a provider.
        if (class_exists(\Omnimap\Registry::class)) {
            $this->app->register(Map\MapServiceProvider::class);
        }
        // Forms guarded by omnishield (captchas, spam, reputation), where omnisense/omnishield is installed:
        // Shield\ShieldServiceProvider, which wires nothing unless config/omnishield.php names a gateway.
        if (class_exists(\Omnishield\Registry::class)) {
            $this->app->register(Shield\ShieldServiceProvider::class);
        }
        // The field conformance suite's host (omnisense/omnibase's Base\Field\Conformance\Host), as omnibridge/symfony binds
        // it: a definition drawn by FormRenderer, bound and validated by omnibase's FormProcessor - what a site's or a
        // bundle's conformance test asks the container for.
        $this->app->singleton(\Base\Field\Conformance\Host::class, static fn (Application $app): \Base\Field\Conformance\Host => new \Omnibridge\Laravel\Form\Conformance\LaravelHost(
            $app->make(FormRenderer::class),
            $app->bound(\Base\Form\Definition\FormProcessor::class) ? $app->make(\Base\Form\Definition\FormProcessor::class) : new \Base\Form\Definition\FormProcessor($app->make(Host\Validation::class)),
        ));
        // The host's contracts under the names an application writes (Base\Contract\Contracts::NAMES: one type, two
        // names), as Symfony's ContractNamesPass: a parameter typed Base\Contract\Backend is answered by what answers
        // Omnibridge\Persistence\Backend. An application's own binding of the Base\ name replaces the alias.
        if (class_exists(\Base\Contract\Contracts::class)) {
            foreach (\Base\Contract\Contracts::NAMES as $name => $contract) {
                if (!$this->app->bound($name)) {
                    $this->app->alias($contract, $name);
                }
            }
        }
    }

    public function boot(): void
    {
        $this->callAfterResolving('blade.compiler', self::formDirectives(...));
        Routing\TrailingSlash::register();

        Runtime::setContainer($this->app);
        // The application's and its bundles' form types read as omnibase's neutral forms (FormDefinition::of(), Marco's
        // "forms once"): each type made by the container (its constructor's services), the repositories when a select
        // lists records at once.
        if (class_exists(\Base\Form\Definition\TypeReader::class) && interface_exists(\Symfony\Component\Form\FormTypeInterface::class)) {
            $app = $this->app;
            \Base\Form\Definition\TypeReader::register(
                static fn (string $type): object => $app->make($type),
                static fn (): \Base\Database\Repository\Repositories => $app->make(\Base\Database\Repository\Repositories::class),
            );
        }
        // Refused aloud now rather than at the first query: two persistences installed and base.persistence naming none.
        Persistence::name($this->app);
        Persistence::boot($this->app, null !== self::omnibase($this->app));
        // omnibase's options (config/base.php) checked against the core's schema and completed with its defaults (Base\Options,
        // its plan's step F5e slice 4b), before anything reads them.
        Parameters\BaseOptions::apply($this->app, fn (string $name): mixed => self::optionParameter($this->app, $name));
        // omnibase's services an account's pages need (its Notifier, its entity events...), with its mapping on:
        // bound once the configuration is final, what an application bound before kept.
        if (self::omnibaseServices($this->app)) {
            Omnibase\OmnibaseServices::register($this->app);
            Omnibase\OmnibaseServices::boot($this->app);
            Omnibase\Redirections::boot($this->app);
        }

        if (self::media($this->app)) {
            $this->registerMedia();
        }
        // omnibase's list of services (Base\Services::definitions(), its step F5a), as Symfony's container and
        // the plain-PHP Omnibridge\Container read it: what is not bound above (or by the application) is bound
        // from it - the paginator, the translator, the screens' builders... - its parameters omnibase's.
        if (class_exists(\Base\Services::class)) {
            // Media off, nothing of it is bound (registerMedia()): the list's would only fail when built.
            $media = self::media($this->app);
            // The remote search hands its payload through the obfuscator: not bound without it either.
            $definitions = array_filter(\Base\Services::definitions(), static fn (array $definition): bool => $media || !\in_array($definition['class'] ?? null, [MediaService::class, Obfuscator::class, 'Base\Field\Search\RemoteSearch', 'Base\Field\Upload\Dropzone', 'Base\Field\Editor\EditorJs'], true));
            if (class_exists(\Base\Service\Icons::class)) {
                View\OmnibaseExtensions::bindIcons($this->app, $this->app['config']->get('omnibridge.icons.metadata'));
            }
            Container\Definitions::bind($this->app, $definitions, fn (string $name): mixed => self::listParameter($this->app, $name), self::ENVIRONMENTS[$this->app->environment()] ?? $this->app->environment(), fn (string $name): bool => self::hasListParameter($this->app, $name));
            // What is discovered among them (Base\Discovery, step F5e slice 4c): the autoconfigured tags, each collection
            // handed to its service (the obfuscator's compressions, the sharing adapters...), the admin's links.
            if (class_exists(\Base\Discovery::class)) {
                Container\Definitions::discover($this->app, $definitions, \Base\Discovery::registries(), self::ENVIRONMENTS[$this->app->environment()] ?? $this->app->environment());
            }
        }
        if (self::media($this->app) && false !== ($this->app['config']->get('omnibridge.media.routes') ?? true)) {
            // omnibase's image routes under their Symfony names, in Symfony's order (ImageRoute::ROUTES).
            $router = $this->app->make('router');
            foreach (ImageRoute::ROUTES as $name => [$path]) {
                $router->get($path, ImageController::class)->name($name)->where('data', '.+');
            }
        }

        if (self::media($this->app) && class_exists(\Base\Field\Search\RemoteSearch::class) && false !== $this->app['config']->get('omnibridge.forms.search', true)) {
            // omnibase's remote search under its Symfony names (RemoteSearch::ROUTES): a select's records, an icon
            // picker's icons. In the web group for the session's token, which the search checks itself. Its pages
            // are the Paginator's, on omnibase's defaults where the config says nothing (base.paginator).
            $router = $this->app->make('router');
            $router->middleware('web')->withoutMiddleware(Auth\Routes::CSRF_MIDDLEWARE)->group(static function (\Illuminate\Routing\Router $router): void {
                foreach (\Base\Field\Search\RemoteSearch::ROUTES as $name => [$path]) {
                    $router->match(['GET', 'POST'], $path, Form\SearchController::class)->name($name);
                }
            });
        }

        if (self::media($this->app) && class_exists(\Base\Field\Upload\Dropzone::class) && false !== $this->app['config']->get('omnibridge.forms.uploads', true)) {
            // omnibase's dropzone under its Symfony names (Dropzone::ROUTES): a file field's files sent as they are dropped,
            // kept until the form is sent. In the web group for the session's token, which the dropzone checks itself.
            $router = $this->app->make('router');
            $router->middleware('web')->withoutMiddleware(Auth\Routes::CSRF_MIDDLEWARE)->group(static function (\Illuminate\Routing\Router $router): void {
                foreach (\Base\Field\Upload\Dropzone::ROUTES as $name => [$path]) {
                    $router->match(['GET', 'POST'], $path, Form\UploadController::class)->name($name);
                }
            });
        }

        if (self::media($this->app) && class_exists(\Base\Field\Editor\EditorJs::class) && false !== $this->app['config']->get('omnibridge.forms.editor', true)) {
            // omnibase's Editor.js routes under their Symfony names (EditorJs::ROUTES, the collaboration's first): the
            // mentions, a text's pictures, the relay's ticket, a field's autosave; a mention's page optional, as Symfony's
            // controller defaults it. In the web group for the session's tokens, which EditorJs checks itself.
            $router = $this->app->make('router');
            $router->middleware('web')->withoutMiddleware(Auth\Routes::CSRF_MIDDLEWARE)->group(static function (\Illuminate\Routing\Router $router): void {
                foreach (\Base\Field\Editor\EditorJs::ROUTES as $name => [$path, , $methods]) {
                    $router->match($methods, str_replace('{page}', '{page?}', $path), Form\EditorController::class)->name($name);
                }
            });
        }

        $this->publishes([\dirname(__DIR__, 2).'/config/omnibridge.php' => config_path('omnibridge.php')], 'omnibridge-config');

        // omnibridge/static's build as an artisan command, where it is installed (php artisan omnibridge:static:build).
        if ($this->app->runningInConsole() && class_exists(\Omnibridge\Static\Console\Cli::class)) {
            $this->commands([BuildCommand::class]);
        }

        // omnibase's console tasks as artisan commands (its plan "omnibase agnostic", step F5e, slice 3), where the core
        // has them (5.0): the same tasks and Reports as omnibridge/symfony's commands of the same names.
        if ($this->app->runningInConsole() && class_exists(\Base\Console\Task\TranslationCrud::class)) {
            $this->commands([
                Console\TranslationCrudCommand::class, Console\TranslationEntitiesCommand::class, Console\TranslationEnumsCommand::class,
                Console\TranslationControllersCommand::class, Console\TranslationSettingsCommand::class,
                Console\CacheClearCommand::class, Console\CacheClearSessionsCommand::class, Console\DemoResetCommand::class,
                Console\UploaderEntitiesCommand::class, Console\UploaderImagesCommand::class, Console\UploaderImagesCropCommand::class,
            ]);
        }
        // Slice 3b's (core 5.x with BaseMapping): the listings, the notifications, the scheduled threads, the trash and the
        // accounts; base:mailer:trap, timemachine:* and doctrine:* stay Symfony's (README: what has no sense here).
        if ($this->app->runningInConsole() && class_exists(\Base\Console\Task\BaseMapping::class)) {
            $this->commands([
                Console\BaseMappingCommand::class, Console\IconEnumsCommand::class, Console\IconEntitiesCommand::class,
                Console\AutocompleteEntitiesCommand::class, Console\EntityDiscriminatorCommand::class, Console\NotifierCommand::class,
                Console\NotifierMailTestCommand::class, Console\UserNotificationCommand::class, Console\ThreadPublishableCommand::class,
                Console\TrashPurgeCommand::class, Console\UserCreateCommand::class,
            ]);
        }
        // omnibridge:schema:create (step F7a): the installed entities' tables from their description, with omnibase's
        // mapping on, where the core describes them with no persistence (5.x) and omnisense/omnibridge writes them.
        if ($this->app->runningInConsole() && class_exists(\Base\Database\Schema\EntitySchema::class) && class_exists(\Omnibridge\Schema\Database\SchemaCreator::class) && null !== self::omnibase($this->app)) {
            $this->commands([Console\SchemaCreateCommand::class]);
        }
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
            if ($parameters->isEnabled() && null !== self::settingsConnection($app)) {
                try {
                    $parameters->add($app->make(DatabaseSettings::class)->parameters($app->make(Host\Locales::class)->getDefaultLocale()));
                    $parameters->markAsReady();
                } catch (\Base\Database\Exception\TableNotFound|DBALException|\Illuminate\Database\QueryException|\PDOException) {
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
     * With omnibridge.omnibase on, the entity manager is omnibase's,
     * as under Symfony: built with Omnibridge\Doctrine\Omnibase\Mapping::configure()
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
        // The settings, through Doctrine's connection where Doctrine is there (as before), else through Laravel's own
        // (omnisense/omnibase's plan "omnibase agnostic", step B3: Doctrine a suggestion of this bridge).
        $this->app->singleton(DatabaseSettings::class, fn (Application $app) => new DatabaseSettings(Persistence::hasDoctrine() ? $app->make(Connection::class) : $app['db']->connection(self::settingsConnection($app) ?? throw new \LogicException('No database connection for the settings: omnibridge.eloquent.connection and omnibridge.doctrine.connection name none.'))));
        if (!Persistence::hasDoctrine()) {
            return;
        }

        $this->app->singleton(Connection::class, function (Application $app) {
            $name = $app['config']->get('omnibridge.doctrine.connection') ?? throw new \LogicException('No Doctrine connection: omnibridge.doctrine.connection names none of config/database.php.');

            return Connections::create($app['config']->get('database.connections.'.$name) ?? throw new \InvalidArgumentException(\sprintf('No database connection "%s" (omnibridge.doctrine.connection).', $name)));
        });
        $this->app->singleton(EntityManagerInterface::class, function (Application $app) {
            $paths = Persistence::entityDirectories($app);
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
            // omnibase's Flysystem with the media on (R6): what an entity's #[Uploader] reads (an
            // upload's public address, an article's audio) goes through the attribute reader's.
            if (self::media($app) && $app->bound(Flysystem::class)) {
                $options['flysystem'] ??= $app->make(Flysystem::class);
            }
            // What a repository takes beside the registry (a site's cache pool...), from Laravel's container.
            // A repository itself is the registry's to build (it would ask this host, which asks the registry: a loop).
            $services = Persistence::services($app);
            $options['services'] ??= static fn (string $type): ?object => $services->has($type) ? $services->get($type) : null;
            $this->mapping = Mapping::register($entityManager, $options);
            // UnitOfWorks::repositories() on this entity manager (a lifecycle listener's, a command's): the mapping's
            // registry, a repository's services from Laravel's container.
            if (class_exists(\Omnibridge\Doctrine\Omnibase\Behaviour\DoctrineBehaviours::class)) {
                $mapping = $this->mapping;
                \Omnibridge\Doctrine\Omnibase\Behaviour\DoctrineBehaviours::host(static fn (EntityManagerInterface $manager): ?\Base\Database\Repository\Repositories => $manager === $entityManager ? $mapping->repositories : null);
            }
            // The neutral lifecycle events relayed from Doctrine's to the listeners tagged for them (the application's
            // src/EntitySubscriber, the packages'), as on Symfony.
            if (class_exists(\Omnibridge\Doctrine\Omnibase\LifecycleRelay::class)) {
                \Omnibridge\Doctrine\Omnibase\LifecycleRelay::attach($entityManager, static fn (): iterable => $app->tagged(\Base\Contract\Lifecycle\Listeners::TAG));
            }

            return $entityManager;
        });
        // A repository by its class (a type-hint of an action, a service), as Symfony autowires one: the core's
        // registry builds it for the entity it is named after (App\Repository\UserRepository: App\Entity\User), on
        // whichever persistence, one for the application.
        $this->app->beforeResolving(static function ($abstract, $parameters, Application $app): void {
            if (\is_string($abstract) && str_contains($abstract, '\\') && !$app->bound($abstract) && is_subclass_of($abstract, ServiceEntityRepository::class)) {
                $app->singleton($abstract, static function (Application $app) use ($abstract): ServiceEntityRepository {
                    $entity = $abstract::getFqcnEntityName();
                    if (!class_exists($entity)) {
                        throw new \LogicException(\sprintf('%s is named after no entity: %s does not exist.', $abstract, $entity));
                    }
                    $repository = $app->make(Repositories::class)->of($entity);

                    return $repository instanceof $abstract ? $repository : throw new \LogicException(\sprintf('%s is not the repository of %s (its mapping names %s).', $abstract, $abstract::getFqcnEntityName(), $repository::class));
                });
            }
        });
        // The core's repositories by their interfaces, as Symfony aliases them: omnibase's own list
        // (Base\Repository\Aliases, R27; an application's own are its #[AsAlias], Container\Aliases).
        // Only with omnibase on (omnibridge.omnibase): its entities name those repositories.
        if (class_exists(\Base\Repository\Aliases::class) && null !== self::omnibase($this->app)) {
            Container\Aliases::bind($this->app, class_exists(\Base\Discovery::class) ? \Base\Discovery::registry('repositories') : \Base\Repository\Aliases::INTERFACES);
        }
        // What the mapping registered (its registry, reader, repositories), once the entity manager is built.
        $this->app->singleton(Mapping::class, function (Application $app) {
            $app->make(EntityManagerInterface::class);

            return $this->mapping ?? throw new \LogicException('omnibase\'s Doctrine mapping is off: omnibridge.omnibase.');
        });
        // The core's repositories' backend and registry on Doctrine (the mapping's DoctrineFinders and
        // EntityRepositories), where nothing bound them yet; omnibridge/eloquent's take their place when it is the
        // persistence chosen (Persistence::register()).
        foreach (['Base\Database\Repository\Finders' => 'finders', 'Base\Database\Repository\Repositories' => 'repositories'] as $id => $property) {
            if (!$this->app->bound($id)) {
                $this->app->singleton($id, static fn (Application $app): object => $app->make(Mapping::class)->$property);
            }
        }
        // Its former name (Base\Bridge\Doctrine\Mapping, before omnisense/omnibase's step F3 moved it to
        // omnibridge/doctrine): the same binding, a container id only (omnisense/omnibase 6.0 declares no class by
        // that name any more). Kept for the 1.x series; it goes with omnibridge/laravel 2.0.
        $this->app->alias(Mapping::class, 'Base\Bridge\Doctrine\Mapping');
    }

    /**
     * A parameter of omnibase's list of services: the kernel's secret is the media's (omnibridge.media.secret)
     * or the application's key, as the Obfuscator's Hashids takes it; any other is omnibase's parameters'.
     */
    private static function listParameter(Application $app, string $name): mixed
    {
        if ('kernel.secret' === $name) {
            return $app['config']->get('omnibridge.media.secret') ?? $app['config']->get('app.key');
        }
        if ('kernel.cache_dir' === $name) {
            // omnibase's cache folder (a dropzone's uploads wait in its dropzone/): under this application's storage.
            return $app['config']->get('omnibridge.cache_dir') ?? $app->storagePath('framework/cache/omnibase');
        }

        $value = $app->make(ParameterBagInterface::class)->get($name);
        // The project's folder and the environment's name, where the parameters give none, as omnibase's mapping takes
        // them (omnibridge.omnibase): the core's attribute reader declared with them (step B3).
        if (null === $value && ('kernel.project_dir' === $name || 'kernel.environment' === $name) && null !== ($omnibase = self::omnibase($app))) {
            return 'kernel.project_dir' === $name ? $omnibase['project_dir'] : $omnibase['environment'];
        }

        return $value;
    }

    /**
     * A placeholder of omnibase's options' defaults (Parameters\BaseOptions): the project's folder is this application's
     * base path, the cache folder and the environment are listParameter()'s, %env()% the environment's (the core's
     * defaults where it has none), any other parameter the config's.
     */
    private static function optionParameter(Application $app, string $name): mixed
    {
        return match (true) {
            'kernel.project_dir' === $name => $app->basePath(),
            'kernel.cache_dir' === $name, 'kernel.secret' === $name => self::listParameter($app, $name),
            'kernel.environment' === $name => self::ENVIRONMENTS[$app->environment()] ?? $app->environment(),
            str_starts_with($name, 'env(') && str_ends_with($name, ')') => Container\Definitions::env(substr($name, 4, -1)),
            default => $app['config']->get($name),
        };
    }

    /**
     * Whether the application gives one of omnibase's parameters, asked while its list is bound: the config's (the bag
     * itself is not built then - it reads the database's settings once, when first asked), kernel.* as listParameter().
     */
    private static function hasListParameter(Application $app, string $name): bool
    {
        if (str_starts_with($name, 'kernel.')) {
            return true;
        }

        return null !== (new ConfigParameters($app['config']))->get($name);
    }

    /**
     * Whether omnibase's services are given here: omnibase installed and on (omnibridge.omnibase), a
     * persistence to run on - omnibridge/doctrine's mapping, or omnibridge/eloquent chosen (step B3).
     */
    private static function omnibaseServices(Application $app): bool
    {
        return class_exists(\Base\Services::class) && null !== self::omnibase($app)
            && (class_exists(Mapping::class) && Persistence::hasDoctrine() || Persistence::eloquent($app));
    }

    /** The connection the settings are read on: omnibridge.doctrine.connection, else omnibridge.eloquent.connection. */
    private static function settingsConnection(Application $app): ?string
    {
        $name = $app['config']->get('omnibridge.doctrine.connection') ?? $app['config']->get('omnibridge.eloquent.connection');

        return \is_string($name) && '' !== $name ? $name : null;
    }

    /** Laravel's environments by omnibase's names (the vault filter's, the rows Symfony wrote). */
    private const ENVIRONMENTS = ['production' => 'prod', 'local' => 'dev', 'development' => 'dev', 'testing' => 'test'];

    /**
     * Omnibridge\Doctrine\Omnibase\Mapping::register()'s options when
     * omnibridge.omnibase is on (true, or the options themselves; its former name
     * omnibridge.doctrine.omnibase, deprecated: Omnibase\OmnibaseOption),
     * null when off: the project's folder (its src/Enum, src/Attribute),
     * the environment by omnibase's name, a cache folder under storage/, and
     * the parameters - Laravel's config under "base" by full name
     * (base.extension.max_revisions...), those given over them.
     *
     * @return array<string, mixed>|null
     */
    private static function omnibase(Application $app): ?array
    {
        $option = OmnibaseOption::get($app['config']);
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
     * Laravel's disks by omnibase's storage names (omnibridge.storages), as a container of Flysystem operators; for the
     * local storages omnibase declares (Base\Discovery's `storages`, 5.x) and the application gives no disk, the folder
     * the core declares, its placeholders (%kernel.project_dir%, %kernel.environment%) resolved as its options' are.
     */
    private static function diskStorages(Application $app): DiskStorages
    {
        $disks = $app->make('filesystem');
        $config = $app->make('config');
        \assert($disks instanceof DiskFactory && $config instanceof ConfigRepository);
        /** @var array<string, string> $storages */
        $storages = (array) $config->get('omnibridge.storages', []);
        $local = [];
        if (class_exists(\Base\Discovery::class)) {
            $declared = \Base\Discovery::registry('storages')['local'] ?? [];
            foreach (\is_array($declared) ? $declared : [] as $storage => $folder) {
                if (!\is_string($folder)) {
                    continue;
                }
                $local[(string) $storage] = (string) preg_replace_callback('/%([^%]+)%/', static function (array $match) use ($app): string {
                    $value = self::optionParameter($app, $match[1]);

                    return \is_scalar($value) ? (string) $value : $match[0];
                }, $folder);
            }
        }

        return new DiskStorages($disks, $storages, $local);
    }

    /**
     * omnibase's media rule hosted by Laravel (audit "lesringostars face au
     * master plan", R6), when omnibridge.media is on: MediaService - the
     * image route's obfuscated addresses, thumbnails and formats - built on
     * Laravel's router (LaravelMediaRouter, Base\Contract\MediaRouter),
     * omnibase's Obfuscator (its identifiers' map in base.obfuscator.cache_dir,
     * the Symfony site's to read the addresses it wrote), omnibase's Flysystem
     * over Laravel's disks (DiskStorages: omnibridge.storages, local.cache
     * required; Omnidrive\Drives over them where omnidrive is installed) and Imagine; Base\Contract\Media bound to it (ServiceMedia),
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
        // The Obfuscator is the core's declaration's (Base\Services; its compressions handed by Base\Discovery's collect, %base.obfuscator.cache_dir%,
        // %kernel.secret%), bound with the rest of its list now that the options give its parameters (step F5e slice 4b).
        // The same drives as Omnidrive\Drives where the core reads them (omnibase feb6fa08 on); an earlier
        // core, or no omnidrive installed yet, takes the disks' container as before.
        $this->app->singleton(Flysystem::class, fn (Application $app) => new Flysystem(
            $app->bound(Drives::class) && method_exists(Flysystem::class, 'getDrives') // @phpstan-ignore function.alreadyNarrowedType (an earlier core has none)
                ? $app->make(Drives::class)
                : self::diskStorages($app),
        ));
        $this->app->singleton(MediaService::class, fn (Application $app) => new MediaService(
            $app->make(Host\Views::class),
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

        // The editor's pictures as under Symfony (audit R7): omnibase's MediaEnhancer
        // (Symfony's editor_enhancer's media pass), each picture of a text by the image
        // route's address made from its stored origin, in the editor's storage
        // (base.twig.editor.storage), as HtmlTwigExtension asks it.
        // omnibase's stored files by storage name (Base\Service\Storages: an editor's pictures, EditorJs), its Flysystem.
        if (interface_exists(\Base\Service\Storages::class) && !$this->app->bound(\Base\Service\Storages::class)) {
            $this->app->alias(Flysystem::class, \Base\Service\Storages::class);
        }
        $this->app->singleton(MediaEnhancerInterface::class, fn (Application $app) => new MediaEnhancer($app->make(MediaService::class), $app->make(Flysystem::class), $app->make('log')));
        $this->app->extend(Contract\Texts::class, function (Contract\Texts $texts, Application $app) {
            if (!$texts instanceof Screen\WysiwygTexts) {
                return $texts;
            }
            $storage = self::mediaParameters($app)->get('base.twig.editor.storage');

            return $texts->images(static function (string $source) use ($app, $storage): ?string {
                $address = $app->make(MediaEnhancerInterface::class)->enhance($source, ['storage' => $storage], [], ['class' => 'markdown-media']);

                return \is_string($address) && '' !== $address ? $address : null;
            });
        });
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

    /**
     * The crops' repository (crops by slug, ratio or size) by omnibase's interface, from the persistence chosen
     * (omnisense/omnibase's plan "omnibase agnostic", step F7d-2): omnibase's entity manager's on Doctrine (its aliases,
     * Container\Aliases), omnibridge/eloquent's on Eloquent (Persistence) - never Doctrine's beside Eloquent.
     */
    private static function crops(Application $app): ?ImageCrops
    {
        $repository = $app->bound(ImageCrops::class) ? $app->make(ImageCrops::class) : null;

        return $repository instanceof ImageCrops ? $repository : null;
    }

    /** The service base.router.fallback names (another site's routes), null without one. */
    private static function routerFallback(Application $app): ?object
    {
        $id = $app['config']->get('base.router.fallback');

        return \is_string($id) && '' !== $id ? $app->make($id) : null;
    }

    /**
     * The Twig paths configured, and omnibase's templates as @Base where the core is installed and none of them is
     * (as Symfony's BaseBundle declares them): its paginator, its form theme, its mails.
     *
     * @param array<mixed> $paths
     *
     * @return list<array{string, string|null}>
     */
    private static function twigPaths(array $paths): array
    {
        $paths = array_values(array_filter($paths, \is_array(...)));
        if (class_exists(\Base\Services::class) && [] === array_filter($paths, static fn (array $path): bool => 'Base' === ($path[1] ?? null))) {
            $templates = \dirname((string) (new \ReflectionClass(\Base\Services::class))->getFileName(), 2).'/templates';
            if (is_dir($templates)) {
                $paths[] = [$templates, 'Base'];
            }
        }

        return $paths;
    }

    /** Twig over omnibridge.twig.paths, omnibridge/twig's contract answered by the bound Urls and Translations. */
    private function registerTwig(): void
    {
        $this->app->singleton(Environment::class, fn (Application $app) => Twig::create(
            self::twigPaths((array) $app['config']->get('omnibridge.twig.paths', [])),
            $app->make(RouterUrls::class),
            $app->make(CatalogueTranslations::class),
            $app->getLocale(),
            $app['config']->get('omnibridge.twig.timezone') ?? $app['config']->get('app.timezone'),
            [],
            ['cache' => $app['config']->get('omnibridge.twig.cache') ?? $app->storagePath('framework/twig')],
        ));
        // omnibase's own Twig extensions (View\OmnibaseExtensions, audit R24), as the core registers them under Symfony.
        $this->app->singleton(View\OmnibaseExtensions::class, fn (Application $app) => new View\OmnibaseExtensions($app));
        $this->app->afterResolving(Environment::class, static function (Environment $twig, Application $app): void {
            $app->make(View\OmnibaseExtensions::class)->extend($twig);
        });
    }
}
