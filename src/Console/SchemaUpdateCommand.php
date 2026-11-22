<?php

namespace Omnibridge\Laravel\Console;

use Base\Database\Schema\EntitySchema;
use Illuminate\Console\Command;
use Omnibridge\Laravel\Omnibase\OmnibaseOption;
use Omnibridge\Laravel\Persistence;
use Omnibridge\Schema\Database\SchemaCreator;

/**
 * omnibridge:schema:update on artisan (2.1): an existing database brought to the installed entities' description -
 * omnibase's Base\Database\Schema\EntitySchema and omnisense/omnibridge's SchemaCreator::update() (AlterStatements,
 * MySQL and SQLite), no ORM -, the same work and statements as omnibridge/symfony's command. For an application without
 * Doctrine, where an omnipack's or the application's upgrade adds a table or a column; with Doctrine, Doctrine
 * Migrations stays the tool that versions the changes.
 *
 *     php artisan omnibridge:schema:update --dump-sql   # prints the statements, runs nothing
 *     php artisan omnibridge:schema:update --force      # runs them
 *
 * The tables the description does not hold are never dropped (Laravel's own: migrations, sessions, jobs...); the
 * columns, indexes and foreign keys a described table no longer holds are. Where the entities are and on which
 * connection: as omnibridge:schema:create.
 */
final class SchemaUpdateCommand extends Command
{
    protected $signature = 'omnibridge:schema:update
        {--dump-sql : Prints the statements, one a line, and runs nothing}
        {--f|force : Runs the statements}';

    protected $description = 'Brings an existing database to the installed entities\' description: prints the statements (--dump-sql) or runs them (--force), no ORM';

    public function handle(): int
    {
        $dump = (bool) $this->option('dump-sql');
        $force = (bool) $this->option('force');
        if (!$dump && !$force) {
            $this->warn('Nothing run: --dump-sql prints the statements, --force runs them.');

            return self::INVALID;
        }

        $config = $this->laravel->make('config');
        \assert($config instanceof \Illuminate\Contracts\Config\Repository);
        /** @var array<string, mixed> $options */
        $options = OmnibaseOption::options($config) + ['project_dir' => $this->laravel->basePath()];
        $options = Persistence::eloquentEntities($this->laravel, $options) + $options;
        $description = EntitySchema::describe(Persistence::describedDirectories($this->laravel, $options), $options);

        $name = $config->get('omnibridge.doctrine.connection') ?? $config->get('database.default');
        $name = \is_string($name) ? $name : null;
        $connection = (array) $config->get('database.connections.'.$name, []);
        $database = $this->laravel->make('db');
        \assert($database instanceof \Illuminate\Database\DatabaseManager);
        $updater = new SchemaCreator($database->connection($name)->getPdo(), EntitySchema::columnTypes($options), [
            'charset' => \is_string($connection['charset'] ?? null) ? $connection['charset'] : 'utf8mb4',
            'collation' => \is_string($connection['collation'] ?? null) ? $connection['collation'] : null,
            'engine' => \is_string($connection['engine'] ?? null) ? $connection['engine'] : 'InnoDB',
        ]);

        $statements = $force ? $updater->update($description) : $updater->alterations($description);
        if ($dump) {
            foreach ($statements as $statement) {
                $this->output->writeln($statement.';', \Symfony\Component\Console\Output\OutputInterface::OUTPUT_RAW);
            }
        }
        if ([] === $statements) {
            $dump ? $this->line('The database is up to date: nothing to run.', null, 'v') : $this->line('The database is up to date: nothing to run.');
        } elseif ($force) {
            $this->line(\sprintf('The database brought to the description (%d statements).', \count($statements)));
        }

        return self::SUCCESS;
    }
}
