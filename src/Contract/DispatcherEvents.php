<?php

namespace Omnibridge\Laravel\Contract;

use Illuminate\Contracts\Events\Dispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;

/** PSR-14's dispatcher on Laravel's: an event object goes to the listeners of its class, and comes back. */
final class DispatcherEvents implements EventDispatcherInterface
{
    public function __construct(private readonly Dispatcher $events)
    {
    }

    public function dispatch(object $event): object
    {
        $this->events->dispatch($event);

        return $event;
    }
}
