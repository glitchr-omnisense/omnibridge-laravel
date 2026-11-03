<?php

namespace Omnibridge\Laravel\Omnibase;

use Base\Database\Entity\EntityHydrator;
use Base\Database\Entity\EntityHydratorInterface;
use Base\EntityDispatcher\AbstractEventDispatcher;
use Base\Notifier\Abstract\BaseNotifierInterface;
use Base\Notifier\Channel\BrowserPlusChannel;
use Base\Notifier\Channel\EmailPlusChannel;
use Base\Notifier\Channel\InAppChannel;
use Base\Notifier\Channel\PushChannel;
use Base\Notifier\Notifier;
use Base\Notifier\NotifierInterface;
use Base\Service\FormGuard;
use Base\Service\Localizer;
use Base\Service\LocalizerInterface;
use Base\Service\Model\Wysiwyg\MentionEnhancer;
use Base\Service\Model\Wysiwyg\MentionEnhancerInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\Push\WebPushService;
use Base\Service\SettingBag;
use Base\Service\SettingBagInterface;
use Base\Service\SpamChecker;
use Base\Service\SpamCheckerInterface;
use Base\Service\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Events\LocaleUpdated;
use Omnibridge\Contract\Environment;
use Omnibridge\Contract\Requests;
use Omnibridge\Contract\Urls;
use Omnibridge\Contract\Views;
use Omnibridge\Doctrine\Omnibase\Mapping;
use Omnibridge\Laravel\CatalogueTranslations;
use Omnibridge\Persistence\UnitOfWork;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Notifier\Channel\ChannelPolicy;
use Symfony\Component\Notifier\Channel\EmailChannel;
use Symfony\Component\Notifier\Notifier as SymfonyNotifier;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * omnibase's services that its Symfony configuration builds (config/services/*.php) and that a page
 * of an account needs here as well (omnisense/omnibase's plan "omnibase agnostic", step F5c): the
 * translator under omnibase's (Base\Services' Translator, on the catalogues' own Symfony translator,
 * in the page's language), the Localizer, the SettingBag, the entity hydrator over the mapping, the
 * spam checker, the mentions' enhancer, and - when omnibridge.notifier gives a channel policy -
 * omnibase's Notifier on Symfony's Notifier, with omnibase's channels: email and email+ by Laravel's
 * mailer's transport, browser+ (the session's flashes, Requests), inapp (the bell's rows) and push.
 *
 * The entity events too, as Symfony's container wires them: omnibase's entity dispatchers (the
 * user's and the thread's, and an application's, omnibridge.omnibase.entity_dispatchers) and the
 * Doctrine hooks of omnibase's ThreadSubscriber and UserSubscriber on the entity manager's events;
 * what they raise heard by name on Laravel's dispatcher (omnibase's subscribers, an application's:
 * omnibridge.omnibase.subscribers); what they leave to flush flushed once the page is answered, as
 * omnibase's EntityDispatcherFlushSubscriber does on Symfony's kernel.response.
 *
 * Each one only where nothing is bound under its name: an application binds its own first.
 */
final class OmnibaseServices
{
    /** omnibase's own subscribers of the entity events, by name (getSubscribedEvents()). */
    public const SUBSCRIBERS = ['Base\EntitySubscriber\UserSubscriber', 'Base\EntitySubscriber\ThreadSubscriber'];

    /** omnibase's entity dispatchers (Doctrine listeners raising the user.* and thread.* events). */
    public const DISPATCHERS = ['Base\EntityDispatcher\Event\UserEventDispatcher', 'Base\EntityDispatcher\Event\ThreadEventDispatcher'];

    /** The Doctrine events an entity dispatcher listens to (omnibase's BaseExtension tags them so). */
    private const DISPATCHER_EVENTS = [Events::prePersist, Events::preUpdate, Events::preRemove, Events::postPersist, Events::postUpdate, Events::postRemove, Events::postFlush];

    public static function register(Application $app): void
    {
        // The core's repositories by their interfaces, where the provider did not bind them yet (a configuration
        // given after its registration: a test's).
        if (class_exists(\Base\Repository\Aliases::class)) {
            \Omnibridge\Laravel\Container\Aliases::bind($app, array_filter(\Base\Repository\Aliases::INTERFACES, static fn (string $interface): bool => !$app->bound($interface), \ARRAY_FILTER_USE_KEY));
        }
        self::singleton($app, Translator::class, static function (Application $app): Translator {
            $translator = $app->make(CatalogueTranslations::class)->translator();
            $translator->setLocale(self::locale($app, $app->getLocale()));

            return $translator;
        });
        self::singleton($app, Localizer::class, static fn (Application $app) => new Localizer(
            $app->make(ParameterBagInterface::class),
            $app->make(TranslatorInterface::class),
            $app->storagePath('framework/cache/omnibase-localizer'),
        ));
        self::alias($app, Localizer::class, LocalizerInterface::class);
        self::singleton($app, SettingBag::class, static fn (Application $app) => new SettingBag(
            $app->make(ParameterBagInterface::class),
            $app->make(UnitOfWork::class),
            $app->make('Base\Repository\Layout\Settings'),
            $app->make(LocalizerInterface::class),
            $app->make(CacheItemPoolInterface::class),
            $app->make(Environment::class)->name(),
        ));
        self::alias($app, SettingBag::class, SettingBagInterface::class);
        // The entity hydrator: omnibase's list binds it on the unit of work where the core lists it (its step F5c,
        // item 3: Base\Services); over the mapping's entity manager and manipulator for a core that does not.
        if (class_exists(Mapping::class) && !self::listed(EntityHydrator::class)) {
            self::singleton($app, EntityHydratorInterface::class, static function (Application $app): EntityHydrator {
                $mapping = $app->make(Mapping::class);

                return new EntityHydrator($mapping->entityManager, $mapping->classMetadataManipulator);
            });
        }
        self::singleton($app, SpamChecker::class, static fn (Application $app) => new SpamChecker(
            $app->bound(Requests::class) ? $app->make(Requests::class) : null,
            $app->make(SettingBagInterface::class),
            $app->make(ParameterBagInterface::class),
            $app->make(TranslatorInterface::class),
            $app->bound(HttpClientInterface::class) ? $app->make(HttpClientInterface::class) : HttpClient::create(),
            (bool) $app['config']->get('app.debug'),
            $app->bound(FormGuard::class) ? $app->make(FormGuard::class) : null,
            $app->make(Urls::class),
            $app->make('log'),
        ));
        self::alias($app, SpamChecker::class, SpamCheckerInterface::class);
        if (!$app->bound(MentionEnhancerInterface::class)) {
            $app->singleton(MentionEnhancerInterface::class, MentionEnhancer::class);
        }
        foreach (self::DISPATCHERS as $dispatcher) {
            self::singleton($app, $dispatcher, static fn (Application $app) => new $dispatcher(
                $app->make(\Psr\EventDispatcher\EventDispatcherInterface::class),
                $app->make(EntityHydratorInterface::class),
                $app->make(UnitOfWork::class),
                $app->bound(Requests::class) ? $app->make(Requests::class) : null,
            ));
        }
        self::singleton($app, 'Base\EntitySubscriber\ThreadSubscriber', static fn (Application $app) => new \Base\EntitySubscriber\ThreadSubscriber(static fn () => $app->make(MentionEnhancerInterface::class)));

        if (null !== self::policy($app)) {
            self::registerNotifier($app);
        }
    }

    /** Whether omnibase's list of services (Base\Services::definitions()) defines that class. */
    private static function listed(string $class): bool
    {
        return class_exists(\Base\Services::class) && \in_array($class, array_column(\Base\Services::definitions(), 'class'), true);
    }

    public static function boot(Application $app): void
    {
        // The project's folder, as BaseBundle::boot() seeds it (an e-mail's pictures read from its public folder).
        $option = $app['config']->get('omnibridge.doctrine.omnibase');
        \Base\Service\Locator::setProjectDir(\is_array($option) && \is_string($option['project_dir'] ?? null) ? $option['project_dir'] : $app->basePath());

        // The page's language, as omnibase's LocaleSubscriber gives it to the translator under Symfony.
        $app['events']->listen(LocaleUpdated::class, static function (LocaleUpdated $event) use ($app): void {
            if ($app->resolved(Translator::class)) {
                $app->make(Translator::class)->setLocale(self::locale($app, $event->locale));
            }
        });

        // The entity events: the dispatchers and the core's hooks on the entity manager, once it is built.
        $hook = static function (EntityManagerInterface $entities) use ($app): void {
            $events = $entities->getEventManager();
            foreach ([...self::DISPATCHERS, ...(array) $app['config']->get('omnibridge.omnibase.entity_dispatchers', [])] as $class) {
                if (class_exists($class)) {
                    $events->addEventListener(self::DISPATCHER_EVENTS, new LazyListener(static fn () => $app->make($class)));
                }
            }
            if (class_exists('Base\EntitySubscriber\ThreadSubscriber')) {
                $events->addEventListener([Events::prePersist, Events::onFlush], new LazyListener(static fn () => $app->make('Base\EntitySubscriber\ThreadSubscriber')));
            }
            if (class_exists('Base\EntitySubscriber\UserSubscriber') && $app->bound(Notifier::class)) {
                $events->addEventListener([Events::preFlush], new LazyListener(static fn () => $app->make('Base\EntitySubscriber\UserSubscriber')));
            }
        };
        $app->afterResolving(EntityManagerInterface::class, $hook);
        if ($app->resolved(EntityManagerInterface::class)) {
            $hook($app->make(EntityManagerInterface::class));
        }

        // What they raise, heard by name (Laravel's dispatcher gives an omnibridge NamedEvent to the listeners of its name).
        foreach ([...self::SUBSCRIBERS, ...(array) $app['config']->get('omnibridge.omnibase.subscribers', [])] as $class) {
            if (!class_exists($class) || !method_exists($class, 'getSubscribedEvents')) {
                continue;
            }
            // UserSubscriber mails through the Notifier: without it, nothing of it.
            if ('Base\EntitySubscriber\UserSubscriber' === $class && !$app->bound(Notifier::class)) {
                continue;
            }
            foreach ($class::getSubscribedEvents() as $name => $listener) {
                foreach (self::listeners($listener) as $method) {
                    $app['events']->listen($name, static fn (object $event) => $app->make($class)->$method($event));
                }
            }
        }

        // What the events left to flush, flushed once the page is answered (EntityDispatcherFlushSubscriber's kernel.response).
        if (!$app->runningInConsole() || $app->runningUnitTests()) {
            $kernel = $app->make(HttpKernel::class);
            if (method_exists($kernel, 'pushMiddleware') && !(method_exists($kernel, 'hasMiddleware') && $kernel->hasMiddleware(FlushEntityEvents::class))) {
                $kernel->pushMiddleware(FlushEntityEvents::class);
            }
        }
        $app->terminating(static function (): void {
            foreach (AbstractEventDispatcher::live() as $dispatcher) {
                $dispatcher->flushPending();
            }
        });
    }

    /**
     * omnibase's Notifier over Symfony's, omnibridge.notifier.channel_policy its policy (Symfony's
     * framework.notifier.channel_policy), the sender omnibase's technical address.
     */
    private static function registerNotifier(Application $app): void
    {
        self::singleton($app, InAppChannel::class, static fn (Application $app) => new InAppChannel($app->make(UnitOfWork::class), $app->make('Base\Repository\Users')));
        self::singleton($app, WebPushService::class, static fn (Application $app) => new WebPushService(
            $app->make(UnitOfWork::class),
            $app->make('Base\Repository\User\PushSubscriptions'),
            $app->make('log'),
            $app['config']->get('omnibridge.push.public_key'),
            $app['config']->get('omnibridge.push.private_key'),
            $app['config']->get('omnibridge.push.subject'),
        ));
        $channels = [
            'email' => static fn (Application $app) => new EmailChannel(self::transport($app), null, self::from($app)),
            'email+' => static fn (Application $app) => new EmailPlusChannel(self::transport($app), null, self::from($app)),
            'browser+' => static fn (Application $app) => new BrowserPlusChannel($app->bound(Requests::class) ? $app->make(Requests::class) : null),
            'inapp' => static fn (Application $app) => $app->make(InAppChannel::class),
            'push' => static fn (Application $app) => new PushChannel($app->make(WebPushService::class), $app->make(InAppChannel::class)),
        ];
        self::singleton($app, SymfonyNotifier::class, static fn (Application $app) => new SymfonyNotifier(new class($app, $channels) implements ContainerInterface {
            /** @var array<string, object> */
            private array $built = [];

            /** @param array<string, \Closure(Application): object> $channels */
            public function __construct(private readonly Application $app, private readonly array $channels)
            {
            }

            public function has(string $id): bool
            {
                return isset($this->channels[$id]);
            }

            public function get(string $id): object
            {
                return $this->built[$id] ??= ($this->channels[$id] ?? throw new \InvalidArgumentException(\sprintf('No notifier channel "%s".', $id)))($this->app);
            }
        }, new ChannelPolicy(self::policy($app) ?? [])));
        self::singleton($app, Notifier::class, static fn (Application $app) => new Notifier(
            $app->make(SymfonyNotifier::class),
            new ChannelPolicy(self::policy($app) ?? []),
            $app->make(UnitOfWork::class),
            $app->make(ParameterBagInterface::class),
            $app->make(TranslatorInterface::class),
            $app->make(LocalizerInterface::class),
            $app->make(Urls::class),
            $app->make(Views::class),
            $app->make(SettingBag::class),
            (bool) $app['config']->get('app.debug'),
        ));
        self::alias($app, Notifier::class, NotifierInterface::class);
        self::alias($app, Notifier::class, BaseNotifierInterface::class);
        // The user.* events' listener: the verification asked at a sign-up, an approval confirmed...
        self::singleton($app, 'Base\EntitySubscriber\UserSubscriber', static fn (Application $app) => new \Base\EntitySubscriber\UserSubscriber($app->make(Notifier::class), $app->make(\Omnibridge\Contract\Access::class)));
    }

    /** @return array<string, list<string>>|null */
    private static function policy(Application $app): ?array
    {
        $policy = $app['config']->get('omnibridge.notifier.channel_policy');

        return \is_array($policy) && [] !== $policy ? $policy : null;
    }

    private static function transport(Application $app): \Symfony\Component\Mailer\Transport\TransportInterface
    {
        return $app->make('mailer')->getSymfonyTransport();
    }

    private static function from(Application $app): ?string
    {
        $from = $app->make(ParameterBagInterface::class)->get('base.notifier.technical_recipient.email');

        return \is_string($from) && '' !== $from ? $from : null;
    }

    /**
     * Laravel's language (fr, fr-FR) as Symfony's translator names it under omnibase (fr_FR): a
     * language alone takes the country of the first available locale that speaks it
     * (omnibridge.locales.available), as the Symfony site's request has it.
     */
    private static function locale(Application $app, string $locale): string
    {
        if (!str_contains($locale, '-') && !str_contains($locale, '_')) {
            foreach ((array) $app['config']->get('omnibridge.locales.available', []) as $available) {
                if (\is_string($available) && str_starts_with($available, $locale)) {
                    $locale = $available;
                    break;
                }
            }
        }

        return str_replace('-', '_', $locale);
    }

    /**
     * A subscriber's methods for an event, from getSubscribedEvents()'s forms: 'method',
     * ['method', priority], [['method', priority], ...].
     *
     * @return list<string>
     */
    private static function listeners(mixed $listener): array
    {
        if (\is_string($listener)) {
            return [$listener];
        }
        if (\is_array($listener) && \is_string($listener[0] ?? null)) {
            return [$listener[0]];
        }

        return array_values(array_filter(array_map(static fn (mixed $each): ?string => \is_array($each) && \is_string($each[0] ?? null) ? $each[0] : null, (array) $listener)));
    }

    private static function singleton(Application $app, string $abstract, \Closure $concrete): void
    {
        if (!$app->bound($abstract)) {
            $app->singleton($abstract, $concrete);
        }
    }

    private static function alias(Application $app, string $abstract, string $alias): void
    {
        if (!$app->bound($alias)) {
            $app->alias($abstract, $alias);
        }
    }
}
