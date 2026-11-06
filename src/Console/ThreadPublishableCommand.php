<?php

namespace Omnibridge\Laravel\Console;

use Base\Console\Task\ThreadPublishable;
use Base\Console\Task\ThreadPublishableInput;
use Base\Repository\Threads;
use Base\Service\TranslatorInterface;
use Omnibridge\Persistence\UnitOfWork;

/** thread:publishable on artisan: omnibase's ThreadPublishable task, the same Report as Symfony's command. */
final class ThreadPublishableCommand extends TaskCommand
{
    protected $signature = 'thread:publishable
        {--publish : Should I publish them ?}';

    protected $description = 'The scheduled threads, published when their time has come (omnibase)';

    public function handle(): int
    {
        return (new ThreadPublishable($this->laravel->make(Threads::class), $this->laravel->make(UnitOfWork::class), $this->laravel->make(TranslatorInterface::class)))->run(new ThreadPublishableInput((bool) $this->option('publish')), $this->sink())->status();
    }
}
