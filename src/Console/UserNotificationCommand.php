<?php

namespace Omnibridge\Laravel\Console;

use Base\Console\Task\UserNotification;
use Base\Console\Task\UserNotificationInput;
use Base\Service\ParameterBagInterface;
use Omnibridge\Persistence\UnitOfWork;

/** user:notifications on artisan: omnibase's UserNotification task, the same Report as Symfony's command. */
final class UserNotificationCommand extends TaskCommand
{
    protected $signature = 'user:notifications
        {--user= : The account (its identifier); every account without it}
        {--expiry= : The age past which a read notification is erased ("+30 days"); base.user.notifications.expiry without it}
        {--show : List them}
        {--clear : Erase the read ones older than the expiry}
        {--clear-all : Erase every read one, whatever its age}';

    protected $description = 'Counts, lists and erases the notifications read';

    public function handle(): int
    {
        return (new UserNotification($this->laravel->make(UnitOfWork::class), $this->laravel->make(ParameterBagInterface::class)))->run(new UserNotificationInput(
            $this->option('user') ?: null,
            $this->option('expiry') ?: null,
            (bool) $this->option('show'),
            (bool) $this->option('clear'),
            (bool) $this->option('clear-all'),
        ), $this->sink())->status();
    }
}
