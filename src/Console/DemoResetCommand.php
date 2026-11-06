<?php

namespace Omnibridge\Laravel\Console;

use Base\Console\Task\DemoReset;
use Base\Console\Task\DemoResetInput;
use Base\Demo\DemoAccountFactory;
use Base\Demo\DemoAccountRegistry;
use Base\Demo\DemoGuard;
use Base\Demo\DemoMode;
use Illuminate\Contracts\Console\Kernel;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

/**
 * demo:reset on artisan: omnibase's DemoReset task - refused outside the `demo` environment, in debug, on the
 * production database (config omnibridge.demo.production_database) - run with artisan's commands (ArtisanCommands),
 * the same Report as Symfony's command. Its lock is symfony/lock's, in storage/framework, where it is installed.
 */
final class DemoResetCommand extends TaskCommand
{
    protected $signature = 'demo:reset {--keep-files : Leave the uploaded files as they are (the database and the caches only)}';

    protected $description = 'Reloads the demonstration: fixtures, uploaded files, caches (demo environment only)';

    public function handle(): int
    {
        $app = $this->laravel;
        $mode = new DemoMode($app->environment());
        $task = new DemoReset(
            $mode,
            new DemoGuard($mode, (bool) config('app.debug'), config('omnibridge.demo.production_database')),
            $app->bound(DemoAccountFactory::class) ? $app->make(DemoAccountFactory::class) : null,
            $app->bound(UnitOfWork::class) ? $app->make(UnitOfWork::class) : null,
            class_exists(LockFactory::class) ? new LockFactory(new FlockStore($app->storagePath('framework'))) : null,
            $app->make('log'),
            $app->bound(DemoAccountRegistry::class) ? $app->make(DemoAccountRegistry::class) : null,
        );

        return $task->run(new DemoResetInput(
            (bool) $this->option('keep-files'),
            (array) config('omnibridge.demo.reset.purge', []),
            (bool) config('omnibridge.demo.reset.orphans', true),
            (array) config('omnibridge.demo.reset.groups', []),
        ), new ArtisanCommands($app->make(Kernel::class), $this->output), $this->sink())->status();
    }
}
