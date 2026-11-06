<?php

namespace Omnibridge\Laravel\Console;

use Base\Console\Task\CacheClear;
use Base\Console\Task\CacheClearInput;
use Base\Notifier\Abstract\BaseNotifierInterface;
use Base\Service\Flysystem;
use Base\Service\ParameterBagInterface;
use Illuminate\Support\Facades\Artisan;

/**
 * omnibase:cache:clear on artisan: omnibase's CacheClear task (the lock, the checks, the counter of clears) around
 * Laravel's own optimize:clear, as omnibridge/symfony's cache:clear wraps FrameworkBundle's. Laravel's cache:clear
 * keeps its name; no second clear (Symfony's container needs one), no phpinfo.php (a Symfony runtime file).
 */
final class CacheClearCommand extends TaskCommand
{
    protected $signature = 'omnibase:cache:clear
        {--sessions : Kill all existing sessions}
        {--no-extension : Skip base extension}
        {--no-warmup : Do not warm up the cache}
        {--no-optional-warmers : Skip optional cache warmers (faster)}';

    protected $description = 'Clears the caches (optimize:clear) with omnibase\'s checks';

    public function handle(): int
    {
        $app = $this->laravel;

        return (new CacheClear($app->make(ParameterBagInterface::class), $app->bound(Flysystem::class) ? $app->make(Flysystem::class) : null, $app->bound(BaseNotifierInterface::class) ? $app->make(BaseNotifierInterface::class) : null))->run(
            new CacheClearInput(
                $app->basePath(),
                $app->storagePath('framework/cache'),
                checks: !$this->option('no-extension'),
                optionalWarmers: !$this->option('no-optional-warmers') && !$this->option('no-warmup'),
                sessions: (bool) $this->option('sessions'),
            ),
            fn (): int => Artisan::call('optimize:clear', [], $this->output),
            null,
            fn () => Artisan::call('cache:clear:sessions', [], $this->output),
            $this->sink(),
        )->status();
    }
}
