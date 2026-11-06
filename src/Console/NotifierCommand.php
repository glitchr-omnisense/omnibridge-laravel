<?php

namespace Omnibridge\Laravel\Console;

use Base\Console\Task\NotifierOverview;
use Base\Console\Task\NotifierOverviewInput;

/** app:notifier on artisan: omnibase's NotifierOverview task, the same Report as Symfony's command. */
final class NotifierCommand extends TaskCommand
{
    protected $signature = 'app:notifier';

    protected $description = 'This command gives an overview of the templated notifications';

    public function handle(): int
    {
        return (new NotifierOverview())->run(new NotifierOverviewInput(), $this->sink())->status();
    }
}
