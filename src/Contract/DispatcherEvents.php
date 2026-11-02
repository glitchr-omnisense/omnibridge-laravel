<?php

namespace Omnibridge\Laravel\Contract;

use Illuminate\Contracts\Events\Dispatcher;
use Omnibridge\Event\NamedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * PSR-14's dispatcher on Laravel's: an event object goes to the listeners of its class, and comes back.
 * A NamedEvent (omnibase's user.register...) goes to the listeners of its name as well, given the event:
 * `Event::listen('user.register', fn (UserEvent $event) => ...)`, as Symfony's subscribers listen by name.
 */
final class DispatcherEvents implements EventDispatcherInterface
{
    public function __construct(private readonly Dispatcher $events)
    {
    }

    public function dispatch(object $event): object
    {
        $this->events->dispatch($event);
        if (interface_exists(NamedEvent::class) && $event instanceof NamedEvent && $event->eventName() !== $event::class) {
            $this->events->dispatch($event->eventName(), [$event]);
        }

        return $event;
    }
}
