<?php

namespace Omnibridge\Laravel\Console;

use Base\Console\Task\TranslationEnums;
use Base\Console\Task\TranslationEnumsInput;

/** translation:enums on artisan: omnibase's TranslationEnums task, the same Report as Symfony's command. */
final class TranslationEnumsCommand extends TaskCommand
{
    protected $signature = 'translation:enums
        {--enum= : Should I consider only a specific enum ?}
        {--suffix=_singular : Should I add a specific translation suffix to the default path ?}
        {--locale= : Should I display only a specific locale ?}';

    protected $description = 'The translation keys of the enumerations and their values (omnibase)';

    public function handle(): int
    {
        return $this->translationTask(TranslationEnums::class)
            ->run(new TranslationEnumsInput($this->source(), $this->text('enum'), $this->text('suffix'), $this->option('locale')), $this->sink())
            ->status();
    }
}
