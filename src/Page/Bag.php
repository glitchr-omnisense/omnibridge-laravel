<?php

namespace Omnibridge\Laravel\Page;

/** A read-only bag with Symfony's get(). */
final class Bag
{
    /** @param \Closure(string): mixed $read */
    public function __construct(private readonly \Closure $read)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return ($this->read)($key) ?? $default;
    }

    /** Everything in it, as a flash bag gives it: nothing here. */
    public function all(): array
    {
        return [];
    }
}
