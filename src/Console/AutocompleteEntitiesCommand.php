<?php

namespace Omnibridge\Laravel\Console;

use Base\Console\Task\AutocompleteEntities;
use Base\Console\Task\EntitiesInput;
use Omnibridge\Persistence\UnitOfWork;

/** autocomplete:entities on artisan: omnibase's AutocompleteEntities task, the same Report as Symfony's command. */
final class AutocompleteEntitiesCommand extends TaskCommand
{
    protected $signature = 'autocomplete:entities
        {--id= : Which entity should I pick up ?}
        {--entity= : Which class should I pick up?}';

    protected $description = 'What the entities answer a picker (omnibase)';

    public function handle(): int
    {
        return (new AutocompleteEntities($this->laravel->make(UnitOfWork::class)))->run(new EntitiesInput($this->source(), $this->option('entity') ?: null, $this->option('id') ?: null), $this->sink())->status();
    }
}
