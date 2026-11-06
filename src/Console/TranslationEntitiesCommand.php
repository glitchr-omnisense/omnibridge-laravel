<?php

namespace Omnibridge\Laravel\Console;

use Base\Console\Task\TranslationEntities;
use Base\Console\Task\TranslationEntitiesInput;

/** translation:entities on artisan: omnibase's TranslationEntities task, the same Report as Symfony's command. */
final class TranslationEntitiesCommand extends TaskCommand
{
    protected $signature = 'translation:entities
        {--entity= : Should I consider only a specific entity ?}
        {--suffix=_singular : Should I add a specific translation suffix to the default path ?}
        {--locale= : Should I display only a specific locale ?}';

    protected $description = 'The translation keys of the entities (omnibase)';

    public function handle(): int
    {
        return $this->translationTask(TranslationEntities::class)
            ->run(new TranslationEntitiesInput($this->source(), $this->text('entity'), $this->text('suffix'), $this->option('locale')), $this->sink())
            ->status();
    }
}
