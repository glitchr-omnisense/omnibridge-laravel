<?php

namespace Omnibridge\Laravel\Console;

use Base\Console\Task\CacheClearSessions;
use Base\Console\Task\CacheClearSessionsInput;

/** cache:clear:sessions on artisan: omnibase's CacheClearSessions task on Laravel's session files (config session.files). */
final class CacheClearSessionsCommand extends TaskCommand
{
    protected $signature = 'cache:clear:sessions';

    protected $description = 'Kills every session: the session files removed (omnibase)';

    public function handle(): int
    {
        return (new CacheClearSessions())->run(new CacheClearSessionsInput(rtrim((string) config('session.files', $this->laravel->storagePath('framework/sessions')), '/'), '*'), $this->sink())->status();
    }
}
