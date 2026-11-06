<?php

namespace Omnibridge\Laravel\Console;

use Base\Console\Task\BaseMapping;
use Base\Console\Task\BaseMappingInput;

/** base:mapping on artisan: omnibase's BaseMapping task, the same Report as Symfony's command. */
final class BaseMappingCommand extends TaskCommand
{
    protected $signature = 'base:mapping';

    protected $description = 'The core\'s classes aliased to App\\ and those the application overrides (omnibase)';

    public function handle(): int
    {
        return (new BaseMapping())->run(new BaseMappingInput($this->source()), $this->sink())->status();
    }
}
