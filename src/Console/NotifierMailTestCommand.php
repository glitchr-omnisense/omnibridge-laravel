<?php

namespace Omnibridge\Laravel\Console;

use Base\Console\Task\NotifierMailTest;
use Base\Console\Task\NotifierMailTestInput;
use Base\Notifier\NotifierInterface;
use Omnibridge\Persistence\UnitOfWork;

/** notifier:mail:test on artisan: omnibase's NotifierMailTest task, the same Report as Symfony's command. */
final class NotifierMailTestCommand extends TaskCommand
{
    protected $signature = 'notifier:mail:test
        {--user= : User ID (or email, or username) to send the notification to}';

    protected $description = 'Send a test email to either a specific user or to the technical recipient';

    public function handle(): int
    {
        return (new NotifierMailTest($this->laravel->make(NotifierInterface::class), $this->laravel->make(UnitOfWork::class)))->run(new NotifierMailTestInput($this->option('user') ?: null), $this->sink())->status();
    }
}
