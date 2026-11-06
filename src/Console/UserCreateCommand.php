<?php

namespace Omnibridge\Laravel\Console;

use Base\Console\Task\UserCreate;
use Base\Console\Task\UserCreateInput;
use Base\Notifier\NotifierInterface;
use Omnibridge\Persistence\UnitOfWork;

/** user:create on artisan: omnibase's UserCreate task, the same Report as Symfony's command. */
final class UserCreateCommand extends TaskCommand
{
    protected $signature = 'user:create
        {username : Its username}
        {email : Its e-mail address}
        {--r|role=* : Its role(s): USER, ADMIN, SUPERADMIN...}
        {--p|password= : Its password (otherwise generated)}
        {--keep-password : Do not ask for a new password at the first sign-in}
        {--no-email : Do not e-mail the account its username and password}';

    protected $description = 'An account, its password to change at the first sign-in';

    public function handle(): int
    {
        $notifier = $this->laravel->bound(NotifierInterface::class) ? $this->laravel->make(NotifierInterface::class) : null;

        return (new UserCreate($this->laravel->make(UnitOfWork::class), $notifier))->run(new UserCreateInput(
            (string) $this->argument('username'),
            (string) $this->argument('email'),
            array_values((array) $this->option('role')) ?: ['USER'],
            $this->option('password') ?: null,
            (bool) $this->option('keep-password'),
            (bool) $this->option('no-email'),
        ), $this->sink())->status();
    }
}
