<?php

namespace Omnibridge\Laravel\Console;

use Base\Console\Task\UploaderEntities;
use Base\Console\Task\UploaderEntitiesInput;
use Base\Database\Repository\Repositories;

/** uploader:entities on artisan: omnibase's UploaderEntities task, the same Report as Symfony's command. */
final class UploaderEntitiesCommand extends TaskCommand
{
    protected $signature = 'uploader:entities
        {--entity= : Should I consider only a specific entity ?}
        {--property= : Should I consider only a specific property ?}
        {--uuid= : Should I consider a specific uuid ?}
        {--orphans : Do you want to get orphans ?}
        {--delete-orphans : Do you want to delete orphans ?}';

    protected $description = 'The uploaded files of the entities, and their orphans (omnibase)';

    public function handle(): int
    {
        return (new UploaderEntities($this->laravel->make(Repositories::class)))->run(new UploaderEntitiesInput(
            $this->source(),
            $this->option('entity'),
            $this->option('property'),
            $this->option('uuid'),
            (bool) $this->option('orphans'),
            (bool) $this->option('delete-orphans'),
        ), $this->sink())->status();
    }
}
