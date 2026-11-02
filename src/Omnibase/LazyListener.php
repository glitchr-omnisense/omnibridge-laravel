<?php

namespace Omnibridge\Laravel\Omnibase;

/**
 * A Doctrine listener built when its first event comes (Doctrine's EventManager calls the event's
 * name as a method on it): omnibase's entity dispatchers and subscribers take services - the
 * Notifier, the mentions' enhancer - whose repositories read the mapping, which is not ready while
 * the entity manager is being built. What Symfony's ContainerAwareEventManager does with service ids.
 */
final class LazyListener
{
    private ?object $listener = null;

    /** @param \Closure(): object $factory */
    public function __construct(private readonly \Closure $factory)
    {
    }

    /** @param list<mixed> $arguments */
    public function __call(string $event, array $arguments): mixed
    {
        $this->listener ??= ($this->factory)();

        return method_exists($this->listener, $event) ? $this->listener->$event(...$arguments) : null;
    }
}
