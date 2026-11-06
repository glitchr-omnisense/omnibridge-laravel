<?php

namespace Omnibridge\Laravel\Legacy\Omnibase;

use Base\Database\Entity\EntityHydratorInterface;
use Base\Notifier\Notifier;
use Base\Service\Model\Wysiwyg\MentionEnhancerInterface;
use Doctrine\ORM\Events;
use Illuminate\Foundation\Application;
use Omnibridge\Contract\Requests;
use Omnibridge\Persistence\UnitOfWork;

/**
 * omnibase's Doctrine listeners as OmnibaseServices bound and registered them by hand until omnisense/omnibase's step F5e
 * slice 4c, for a core without Base\Discovery (4.x): since then the core's declaration binds them and its registries say
 * which events each listens to. Removed once omnibase 4.x is no longer served.
 */
final class EntityListeners
{
    /** omnibase's entity dispatchers (Doctrine listeners raising the user.* and thread.* events). */
    public const DISPATCHERS = ['Base\EntityDispatcher\Event\UserEventDispatcher', 'Base\EntityDispatcher\Event\ThreadEventDispatcher'];

    /** The Doctrine events an entity dispatcher listens to (omnibase's BaseExtension tags them so). */
    public const DISPATCHER_EVENTS = [Events::prePersist, Events::preUpdate, Events::preRemove, Events::postPersist, Events::postUpdate, Events::postRemove, Events::postFlush];

    public static function bind(Application $app): void
    {
        foreach (self::DISPATCHERS as $dispatcher) {
            if (!$app->bound($dispatcher)) {
                $app->singleton($dispatcher, static fn (Application $app) => new $dispatcher(
                    $app->make(\Psr\EventDispatcher\EventDispatcherInterface::class),
                    $app->make(EntityHydratorInterface::class),
                    $app->make(UnitOfWork::class),
                    $app->bound(Requests::class) ? $app->make(Requests::class) : null,
                ));
            }
        }
        if (!$app->bound('Base\EntitySubscriber\ThreadSubscriber')) {
            $app->singleton('Base\EntitySubscriber\ThreadSubscriber', static fn (Application $app) => new \Base\EntitySubscriber\ThreadSubscriber(static fn () => $app->make(MentionEnhancerInterface::class)));
        }
    }

    /**
     * @param list<string> $own an application's dispatchers
     *
     * @return array<string, list<string>> class => events
     */
    public static function listeners(Application $app, array $own): array
    {
        $listeners = [];
        foreach ([...self::DISPATCHERS, ...$own] as $class) {
            if (class_exists($class)) {
                $listeners[$class] = self::DISPATCHER_EVENTS;
            }
        }
        if (class_exists('Base\EntitySubscriber\ThreadSubscriber')) {
            $listeners['Base\EntitySubscriber\ThreadSubscriber'] = [Events::prePersist, Events::onFlush];
        }
        if (class_exists('Base\EntitySubscriber\UserSubscriber') && $app->bound(Notifier::class)) {
            $listeners['Base\EntitySubscriber\UserSubscriber'] = [Events::preFlush];
        }

        return $listeners;
    }
}
