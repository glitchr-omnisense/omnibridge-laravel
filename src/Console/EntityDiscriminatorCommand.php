<?php

namespace Omnibridge\Laravel\Console;

use Base\Console\Task\EntityDiscriminator;
use Base\Console\Task\EntityDiscriminatorInput;
use Omnibridge\Persistence\UnitOfWork;

/** entity:discriminator on artisan: omnibase's EntityDiscriminator task, the same Report as Symfony's command. */
final class EntityDiscriminatorCommand extends TaskCommand
{
    protected $signature = 'entity:discriminator
        {entity : Which entity should be considered ?}';

    protected $description = 'A class that is no entity refused (omnibase)';

    public function handle(): int
    {
        return (new EntityDiscriminator($this->laravel->make(UnitOfWork::class)))->run(new EntityDiscriminatorInput((string) $this->argument('entity')), $this->sink())->status();
    }
}
