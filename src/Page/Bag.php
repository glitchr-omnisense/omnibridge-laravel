<?php

namespace Omnibridge\Laravel\Page;

/**
 * A bag read by a closure, with the words of Symfony's ParameterBag and
 * FlashBag: get(key, default) and all().
 */
final class Bag
{
    /**
     * @param \Closure(string): mixed    $read the value under a key
     * @param (\Closure(): array<mixed>)|null $all  every value, [] when the bag gives none
     */
    public function __construct(private readonly \Closure $read, private readonly ?\Closure $all = null)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return ($this->read)($key) ?? $default;
    }

    /** @return array<mixed> */
    public function all(): array
    {
        return null !== $this->all ? ($this->all)() : [];
    }
}
