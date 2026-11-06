<?php

namespace Omnibridge\Laravel\Tests;

use Base\Database\Schema\EntitySchema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Omnibridge\Schema\Database\SchemaCreator;
use Omnibridge\Schema\Database\SqliteSchema;

/**
 * omnibridge:schema:create on artisan (omnisense/omnibase's plan "omnibase agnostic", step F7a): the installed
 * entities - the core's beside omnibridge.doctrine.entities, omnibase's mapping on - created in the empty database of
 * omnibridge.doctrine.connection from their description, through Laravel's PDO, no ORM: the statements printed are
 * SchemaCreator's, the tables read back are the description and the ones Doctrine's SchemaTool makes from the entity
 * manager's mapping, and a second run is refused.
 */
final class SchemaCreateTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('omnibridge.doctrine.entities', [self::FIXTURES.'/Omnibase/Entity']);
        $app['config']->set('omnibridge.doctrine.omnibase', ['project_dir' => self::FIXTURES.'/Omnibase']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(EntitySchema::class)) {
            self::markTestSkipped('omnibase 4.x does not describe its entities without a persistence.');
        }
    }

    /** @return array{0: \Omnibridge\Schema\Description, 1: \Omnibridge\Schema\Database\ColumnTypes} */
    private static function schema(): array
    {
        $options = ['project_dir' => self::FIXTURES.'/Omnibase'];

        return [EntitySchema::describe([self::FIXTURES.'/Omnibase/Entity', EntitySchema::coreDirectory('Entity')], $options), EntitySchema::columnTypes($options)];
    }

    public function testTheStatementsPrintedAreSchemaCreatorsNothingRun(): void
    {
        [$description, $types] = self::schema();

        $this->assertSame(0, Artisan::call('omnibridge:schema:create', ['--dump-sql' => true]));
        $statements = (new SchemaCreator(DB::connection('testing')->getPdo(), $types))->statements($description);
        $this->assertGreaterThan(100, \count($statements));
        $this->assertSame(implode('', array_map(static fn (string $statement) => $statement.";\n", $statements)), Artisan::output());
        $this->assertSame([], DB::select("SELECT name FROM sqlite_master WHERE type = 'table'"));
    }

    public function testTheTablesAreTheDescriptionAndSchemaToolsThenRefused(): void
    {
        [$description, $types] = self::schema();

        $this->assertSame(0, Artisan::call('omnibridge:schema:create'));
        $this->assertSame(\sprintf('%d tables created', \count($description->tables)), explode(' (', trim(Artisan::output()))[0]);
        $created = SqliteSchema::read(DB::connection('testing')->getPdo(), [], $types);
        $this->assertSame([], $created->differences($description));

        $entityManager = $this->app->make(EntityManagerInterface::class);
        $tool = new \PDO('sqlite::memory:');
        foreach ((new SchemaTool($entityManager))->getCreateSchemaSql($entityManager->getMetadataFactory()->getAllMetadata()) as $statement) {
            $tool->exec($statement);
        }
        $this->assertSame([], SqliteSchema::read($tool)->against(SqliteSchema::read(DB::connection('testing')->getPdo())), 'SchemaTool\'s database against the description\'s');

        $this->assertSame(1, Artisan::call('omnibridge:schema:create'));
        $this->assertStringContainsString('already holds', Artisan::output());
    }
}
