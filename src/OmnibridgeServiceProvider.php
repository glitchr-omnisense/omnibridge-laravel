<?php

namespace Omnibridge\Laravel;

use Base\Contract;
use Base\Contract\Runtime;
use Base\Service\HotParameters;
use Base\Service\Model\EntityNouns;
use Base\Service\ParameterBagInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
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
 * registry (Base\Contract\Runtime) on Laravel's container, so that
 * Runtime::urls(), ::parameters()... and app(Urls::class) are one object.
 *
 * Bound: Urls (and omnibridge/twig's) on the router and the asset manifest,
 * Translations (and omnibridge/twig's) on the ICU catalogues, Locales,
 * Nouns, Access (the gate), Sessions (the guard), Tokens (the session's),
 * Environment, Routes, Files (the disks), the parameters (Laravel's config,
 * the database settings laid over it), PSR-20's clock, PSR-14's dispatcher,
 * symfony/mailer's mailer; and Doctrine DBAL and ORM (omnibridge/doctrine)
 * and Twig (omnibridge/twig) on the configuration. Not bound: Media, Pictures and
 * Texts (omnibase's image route and text enhancers, Symfony's for now).
 */
final class OmnibridgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/omnibridge.php', 'omnibridge');

        $this->app->singleton(RouterUrls::class, fn (Application $app) => new RouterUrls(null, $app['config']->get('omnibridge.assets.manifest')));
        $this->app->alias(RouterUrls::class, Contract\Urls::class);
        $this->app->alias(RouterUrls::class, \Omnibridge\Twig\Contract\Urls::class);

        $this->app->singleton(CatalogueTranslations::class, fn (Application $app) => new CatalogueTranslations(
            $app['config']->get('omnibridge.translations.paths', []),
            null,
            (string) ($app['config']->get('omnibridge.translations.fallback') ?? $app['config']->get('app.fallback_locale', 'en')),
        ));
        $this->app->alias(CatalogueTranslations::class, Contract\Translations::class);
        $this->app->alias(CatalogueTranslations::class, \Omnibridge\Twig\Contract\Translations::class);

        $this->app->singleton(Contract\Locales::class, fn (Application $app) => new AppLocales(
            $app['config']->get('omnibridge.locales.available', []),
            $app['config']->get('omnibridge.locales.default'),
        ));
        $this->app->singleton(Contract\Nouns::class, fn (Application $app) => new EntityNouns($app->make(Contract\Translations::class), $app['config']->get('omnibridge.entity_namespaces', [])));
        $this->app->singleton(Contract\Access::class, fn (Application $app) => new GateAccess($app->make(\Illuminate\Contracts\Auth\Access\Gate::class), $app->make('auth')));
        $this->app->singleton(Contract\Sessions::class, fn (Application $app) => new GuardSessions($app->make('auth')));
        $this->app->singleton(Contract\Tokens::class, fn (Application $app) => new SessionTokens($app));
        $this->app->singleton(Contract\Environment::class, fn (Application $app) => new AppEnvironment(
            (string) $app->environment(),
            (bool) $app['config']->get('app.debug'),
            (string) $app['config']->get('app.key'),
        ));
        $this->app->singleton(Contract\Routes::class, fn (Application $app) => new RouterRoutes($app->make('router')));
        $this->app->singleton(Contract\Files::class, fn (Application $app) => new DiskFiles($app->make('filesystem'), $app['config']->get('omnibridge.storages', [])));
        $this->app->singleton(ClockInterface::class, DateClock::class);
        $this->app->singleton(EventDispatcherInterface::class, fn (Application $app) => new DispatcherEvents($app->make('events')));
        $this->app->singleton(MailerInterface::class, fn (Application $app) => new TransportMailer($app));

        $this->registerParameters();
        $this->registerDoctrine();
        $this->registerTwig();
    }

    public function boot(): void
    {
        Runtime::setContainer($this->app);

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
                    $parameters->add($app->make(DatabaseSettings::class)->parameters($app->make(Contract\Locales::class)->getDefaultLocale()));
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
     */
    private function registerDoctrine(): void
    {
        $this->app->singleton(Connection::class, function (Application $app) {
            $name = $app['config']->get('omnibridge.doctrine.connection') ?? throw new \LogicException('No Doctrine connection: omnibridge.doctrine.connection names none of config/database.php.');

            return Connections::create($app['config']->get('database.connections.'.$name) ?? throw new \InvalidArgumentException(\sprintf('No database connection "%s" (omnibridge.doctrine.connection).', $name)));
        });
        $this->app->singleton(DatabaseSettings::class, fn (Application $app) => new DatabaseSettings($app->make(Connection::class)));
        $this->app->singleton(EntityManagerInterface::class, fn (Application $app) => EntityManagers::create(
            $app->make(Connection::class),
            $app['config']->get('omnibridge.doctrine.entities', []),
            (bool) $app['config']->get('app.debug'),
        ));
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
