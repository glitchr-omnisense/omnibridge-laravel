<?php

namespace Omnibridge\Laravel\Parameters;

use Base\Options;
use Illuminate\Contracts\Foundation\Application;
use Omnibridge\Options\Placeholders;

/**
 * omnibase's options in this application: config/base.php (Laravel's config under "base") validated against the
 * core's schema (Base\Options, its plan "omnibase agnostic", step F5e slice 4b) - an option the schema refuses
 * stops the application with Symfony's own message, a deprecated one is said (E_USER_DEPRECATED) - and completed
 * with the defaults it leaves out, their placeholders resolved by the application (%kernel.project_dir%: its base
 * path; %kernel.cache_dir%; %env(NAME)%). Done once, when the provider boots: the parameters
 * (Runtime::parameters(), ConfigParameters) then read every option, as a Symfony site's container has them all.
 *
 * A configuration processed already is not processed again: `php artisan config:cache` writes the configuration as the
 * booted application holds it - the options processed, PROCESSED set beside them -, and processing that output again
 * refused the defaults it holds (base.obfuscator.encoding: null where the schema asks an int, as Symfony's IntegerNode
 * refuses an explicit null; omnisense/omnibase's plan "omnibase agnostic", step F7d-2).
 */
final class BaseOptions
{
    /** Bound once the options are applied. */
    public const APPLIED = 'omnibridge.options';

    /** Laravel's config key set once omnibase's options are processed (true): a cached configuration carries it. */
    public const PROCESSED = 'omnibridge.base_options_processed';

    /**
     * @param \Closure(string $name): mixed $parameter a placeholder's value ('kernel.project_dir', 'env(APP_ENV)'...)
     *
     * @throws \Omnibridge\Options\InvalidOption an option the core's schema refuses
     */
    public static function apply(Application $app, \Closure $parameter): void
    {
        // omnisense/omnibase 4.x declares no options: the defaults this bridge wrote for it are laid where they are read
        // (Legacy\Parameters\OptionDefaults).
        if ($app->bound(self::APPLIED) || !class_exists(Options::class)) {
            return;
        }
        $app->instance(self::APPLIED, true);
        $config = $app['config'];
        if (true === $config->get(self::PROCESSED)) {
            return;
        }
        $config->set(Options::ROOT, Placeholders::resolve(Options::process((array) $config->get(Options::ROOT, [])), $parameter));
        $config->set(self::PROCESSED, true);
    }
}
