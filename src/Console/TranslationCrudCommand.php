<?php

namespace Omnibridge\Laravel\Console;

use Base\Console\Task\TranslationCrud;
use Base\Console\Task\TranslationCrudInput;

/** translation:crud on artisan: omnibase's TranslationCrud task, the same Report as Symfony's command. */
final class TranslationCrudCommand extends TaskCommand
{
    protected $signature = 'translation:crud
        {--crud= : Should I consider only a specific CRUD controller ?}
        {--action=index : Should I consider only a specific CRUD action ?}
        {--suffix=title : Should I add a specific translation suffix to the default path ?}
        {--locale= : Should I display only a specific locale ?}';

    protected $description = 'The translation keys of the back office\'s CRUD pages (omnibase)';

    public function handle(): int
    {
        return $this->translationTask(TranslationCrud::class)
            ->run(new TranslationCrudInput($this->source(), $this->text('crud'), $this->text('action'), $this->text('suffix'), $this->option('locale')), $this->sink())
            ->status();
    }
}
