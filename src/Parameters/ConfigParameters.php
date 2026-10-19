<?php

namespace Omnibridge\Laravel\Parameters;

use Base\Service\ParameterBagInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Arr;
use UnitEnum;

/**
 * Laravel's configuration as omnibase's parameters: a parameter is read by
 * the same dotted path Laravel's config() reads ("app.name", and
 * "base.user.delay" for a site that has config/base.php). A $bag given to
 * get() is laid over it by key, as omnibase's parameter bags do: the bag's
 * value wins, values under a path are merged key by key, a value of null is
 * no value. all() is every parameter by its full name.
 *
 * Omnibase's HotParameters lays the database settings over this one
 * (OmnibridgeServiceProvider): Runtime::parameters() is that overlay.
 */
final class ConfigParameters implements ParameterBagInterface
{
    public function __construct(private readonly Repository $config)
    {
    }

    public function get(string $key = '', ?array $bag = null): array|bool|string|int|float|UnitEnum|null
    {
        $value = '' === $key ? $this->config->all() : $this->config->get($key);
        if (!$bag) {
            return $value;
        }
        $over = Arr::undot(array_filter($bag, static fn ($entry) => null !== $entry));
        $above = '' === $key ? $over : Arr::get($over, $key);
        if (null === $above) {
            return $value;
        }

        return \is_array($value) && \is_array($above) ? array_replace_recursive($value, $above) : $above;
    }

    public function set(string $path, array|bool|string|int|float|UnitEnum|null $value, ?array &$bag = null): void
    {
        if (null !== $bag) {
            Arr::set($bag, $path, $value);

            return;
        }
        $this->config->set($path, $value);
    }

    public function has(string $path, ?array $bag = null): bool
    {
        return null !== $this->get($path, $bag);
    }

    public function all(): array
    {
        return Arr::dot($this->config->all());
    }
}
