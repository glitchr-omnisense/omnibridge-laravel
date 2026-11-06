<?php

namespace Omnibridge\Laravel\Console;

use Base\Console\Task\EntitiesInput;
use Base\Console\Task\IconEntities;
use Omnibridge\Persistence\UnitOfWork;

/** icon:entities on artisan: omnibase's IconEntities task, the same Report as Symfony's command. */
final class IconEntitiesCommand extends TaskCommand
{
    protected $signature = 'icon:entities
        {--id= : Should I pick up one specific entityId ?}
        {--entity= : Should I consider only a specific entity ?}';

    protected $description = 'The entities\' icons (omnibase)';

    public function handle(): int
    {
        return (new IconEntities($this->laravel->make(UnitOfWork::class)))->run(new EntitiesInput($this->source(), $this->option('entity') ?: null, $this->option('id') ?: null), $this->sink())->status();
    }
}
