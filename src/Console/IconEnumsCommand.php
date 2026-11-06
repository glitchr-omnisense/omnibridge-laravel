<?php

namespace Omnibridge\Laravel\Console;

use Base\Console\Task\IconEnums;
use Base\Console\Task\IconEnumsInput;

/** icon:enums on artisan: omnibase's IconEnums task, the same Report as Symfony's command. */
final class IconEnumsCommand extends TaskCommand
{
    protected $signature = 'icon:enums
        {--enum= : Should I consider only a specific enum ?}';

    protected $description = 'The enumerations\' icons (omnibase)';

    public function handle(): int
    {
        return (new IconEnums())->run(new IconEnumsInput($this->source(), $this->text('enum')), $this->sink())->status();
    }
}
