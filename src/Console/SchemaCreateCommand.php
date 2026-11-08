<?php

namespace Omnibridge\Laravel\Console;

use Base\Database\Schema\EntitySchema;
use Omnibridge\Laravel\Omnibase\OmnibaseOption;
use Illuminate\Console\Command;
use Omnibridge\Schema\Database\SchemaCreator;
use Omnibridge\Schema\Database\SchemaNotEmpty;

/**
 * omnibridge:schema:create on artisan (omnisense/omnibase's plan "omnibase agnostic", step F7a): the tables of the
 * installed entities created in an empty database from their description - omnibase's Base\Database\Schema\EntitySchema
 * and omnisense/omnibridge's SchemaCreator, no ORM -, the same work and statements as omnibridge/symfony's command.
 *
 *     php artisan omnibridge:schema:create [--dump-sql]
 *
 * Where the entities are, as this bridge maps them (omnibridge.doctrine.entities beside the core's, the options of
 * omnibridge.omnibase: the project's folder, its enumerations, the targets), on the database connection
 * omnibridge.doctrine.connection names (Laravel's default one otherwise), through Laravel's own PDO.
 */
final class SchemaCreateCommand extends Command
{
    protected $signature = 'omnibridge:schema:create
        {--dump-sql : Prints the statements, one a line, and runs nothing}';

    protected $description = 'Creates the tables of the installed entities in an empty database, from their description (no ORM)';

    public function handle(): int
    {
        $config = $this->laravel['config'];
        $options = OmnibaseOption::options($config) + ['project_dir' => $this->laravel->basePath()];
        $paths = array_values(array_unique([...(array) $config->get('omnibridge.doctrine.entities', []), EntitySchema::coreDirectory('Entity')]));
        $description = EntitySchema::describe($paths, $options);

        $name = $config->get('omnibridge.doctrine.connection') ?? $config->get('database.default');
        $connection = (array) $config->get('database.connections.'.$name, []);
        $creator = new SchemaCreator($this->laravel['db']->connection($name)->getPdo(), EntitySchema::columnTypes($options), [
            'charset' => $connection['charset'] ?? 'utf8mb4',
            'collation' => $connection['collation'] ?? null,
            'engine' => $connection['engine'] ?? 'InnoDB',
        ]);

        if ($this->option('dump-sql')) {
            foreach ($creator->statements($description) as $statement) {
                $this->output->writeln($statement.';', \Symfony\Component\Console\Output\OutputInterface::OUTPUT_RAW);
            }

            return self::SUCCESS;
        }

        try {
            $statements = $creator->create($description);
        } catch (SchemaNotEmpty $refusal) {
            $this->error($refusal->getMessage());

            return self::FAILURE;
        }
        $this->line(\sprintf('%d tables created (%d statements).', \count($description->tables), \count($statements)));

        return self::SUCCESS;
    }
}
