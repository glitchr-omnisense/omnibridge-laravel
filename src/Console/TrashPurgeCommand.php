<?php

namespace Omnibridge\Laravel\Console;

use Base\Console\Task\TrashPurge;
use Base\Console\Task\TrashPurgeInput;
use Base\Repository\Extension\TrashBalls;
use Base\Service\TrashManager;
use Omnibridge\Persistence\UnitOfWork;

/** trash:purge on artisan: omnibase's TrashPurge task, the same Report as Symfony's command. */
final class TrashPurgeCommand extends TaskCommand
{
    protected $signature = 'trash:purge
        {--force : Actually destroy them (without this, only reports)}
        {--limit= : Destroy at most N entries in this run}';

    protected $description = 'Destroy trashed entities past their grace period';

    public function handle(): int
    {
        $trash = $this->laravel->bound(TrashManager::class) ? $this->laravel->make(TrashManager::class) : new TrashManager($this->laravel->make(UnitOfWork::class), $this->laravel->make(TrashBalls::class));

        return (new TrashPurge($trash))->run(new TrashPurgeInput((bool) $this->option('force'), null !== $this->option('limit') ? (int) $this->option('limit') : null), $this->sink())->status();
    }
}
