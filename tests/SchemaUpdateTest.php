<?php

namespace Omnibridge\Laravel\Tests;

use Base\Database\Schema\EntitySchema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Omnibridge\Laravel\Persistence;
use Omnibridge\Schema\Database\SqliteSchema;

/**
 * omnibridge:schema:update on artisan (2.1): an existing database brought to the installed entities' description
 * through Laravel's PDO, no ORM - nothing run without --force, the statements printed with --dump-sql, a database one
 * table and one column behind brought up to date (the description's again), a table of Laravel's own kept, and none
 * once it is up to date.
 */
final class SchemaUpdateTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $config = $app->make('config');
        \assert($config instanceof \Illuminate\Contracts\Config\Repository);
        $config->set('base.entity_folders.include', [self::FIXTURES.'/Omnibase/Entity' => null]);
        $config->set('omnibridge.omnibase', ['project_dir' => self::FIXTURES.'/Omnibase']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (!interface_exists(\Omnibridge\Schema\Database\AlterStatements::class)) {
            self::markTestSkipped('omnisense/omnibridge 2.1 writes the ALTERs.');
        }
    }

    public function testAnExistingDatabaseIsBroughtToTheDescription(): void
    {
        $this->assertSame(0, Artisan::call('omnibridge:schema:create'));
        $pdo = DB::connection('testing')->getPdo();
        $pdo->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY, migration VARCHAR(255))');
        $statement = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name <> 'migrations' ORDER BY name");
        $this->assertNotFalse($statement);
        $tables = $statement->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertContains('user', $tables);
        // One table and one column behind.
        $dropped = 'thread';
        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->exec('DROP TABLE "'.$dropped.'"');

        $this->assertSame(2, Artisan::call('omnibridge:schema:update'), 'nothing run without --dump-sql or --force');

        $this->assertSame(0, Artisan::call('omnibridge:schema:update', ['--dump-sql' => true]));
        $printed = Artisan::output();
        $this->assertStringContainsString('CREATE TABLE "'.$dropped.'"', $printed);
        $this->assertStringNotContainsString('migrations', $printed, 'a table the description does not hold is kept');

        $this->assertSame(0, Artisan::call('omnibridge:schema:update', ['--force' => true]));
        $this->assertStringContainsString('brought to the description', Artisan::output());

        \assert(null !== $this->app);
        $options = Persistence::eloquentEntities($this->app, ['project_dir' => self::FIXTURES.'/Omnibase']) + ['project_dir' => self::FIXTURES.'/Omnibase'];
        $types = EntitySchema::columnTypes($options);
        $description = EntitySchema::describe(Persistence::describedDirectories($this->app, $options), $options);
        $this->assertSame([], SqliteSchema::read($pdo, ['migrations'], $types)->differences($description));

        $this->assertSame(0, Artisan::call('omnibridge:schema:update', ['--dump-sql' => true]));
        $this->assertSame('', trim(Artisan::output()), 'up to date: nothing printed');
    }
}
