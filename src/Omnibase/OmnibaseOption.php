<?php

namespace Omnibridge\Laravel\Omnibase;

use Illuminate\Contracts\Config\Repository;

/**
 * omnibase in this application: `omnibridge.omnibase` (config/omnibridge.php) - false (off), true, or its options
 * (project_dir, environment, cache_dir, parameters, and with Doctrine Mapping::register()'s types, enumerations...).
 * Whichever persistence is chosen: omnibase's services, its entities' description and, on Doctrine, its mapping on the
 * entity manager (omnisense/omnibase's plan "omnibase agnostic", step B leftovers).
 *
 * Its former name, `omnibridge.doctrine.omnibase`, named it under Doctrine's options although Eloquent reads it too: still
 * read where the new one is not given, said deprecated (E_USER_DEPRECATED, once); removed at 2.0.
 */
final class OmnibaseOption
{
    public const KEY = 'omnibridge.omnibase';

    /** @deprecated since 1.x, the former name of KEY */
    public const FORMER_KEY = 'omnibridge.doctrine.omnibase';

    private static bool $said = false;

    /** The option: KEY's value, else the former key's (deprecated), else false. */
    public static function get(Repository $config): mixed
    {
        $option = $config->get(self::KEY);
        if (null !== $option) {
            return $option;
        }

        $former = $config->get(self::FORMER_KEY);
        if (null === $former) {
            return false;
        }
        if (!self::$said) {
            self::$said = true;
            @trigger_error(sprintf('The option "%s" is deprecated since omnibridge/laravel 1.x and is removed at 2.0: name it "%s" (config/omnibridge.php: "omnibase" beside "doctrine", the same value).', self::FORMER_KEY, self::KEY), \E_USER_DEPRECATED);
        }

        return $former;
    }

    /** The options as given (an array), [] for true or off. */
    public static function options(Repository $config): array
    {
        $option = self::get($config);

        return \is_array($option) ? $option : [];
    }

    /** For the suites: the deprecation said again by the next get() reading the former key. */
    public static function reset(): void
    {
        self::$said = false;
    }
}
