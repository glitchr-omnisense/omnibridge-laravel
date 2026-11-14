<?php

namespace Omnibridge\Laravel\Tests;

use App\Entity\Destination;
use App\Entity\Visit;
use Base\Database\Schema\EntitySchema;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Omnibridge\Laravel\Persistence;
use Omnibridge\Laravel\Tests\Fixtures\Enumerations\VisitState;
use Omnibridge\Laravel\Tests\Fixtures\Targets\TravellerInterface;
use Omnibridge\Schema\Database\SchemaCreator;
use Omnibridge\Schema\Description;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * omnibase's options that describe the entities (1.2.1): the types, enumerations and resolve_target_entities of
 * omnibridge.omnibase, which Doctrine's mapping gets through Mapping::register(), given to omnibridge/eloquent's
 * persistence too (Persistence::SCHEMA_OPTIONS) - one configuration, the same relations and column types on both. And
 * omnibridge:schema:create describing the folders of the chosen persistence: on Eloquent the project's src/Entity,
 * which its persistence describes. Until 1.2.1 a relation toward an interface kept the interface on Eloquent, an
 * enumeration of a folder given was no column type there, and the command left the project's src/Entity out.
 *
 * Where omnibridge/eloquent is not installed (this package's CI installs omnibridge/doctrine only), the description is
 * built as EloquentPersistence::create() builds it from the options; where it is, the container's persistence is read.
 * Each test in its own process: the enumeration of the fixtures, once registered as a column type (Doctrine's types,
 * omnibase's Enumerations), would stay in the suite's registries.
 */
#[RunTestsInSeparateProcesses]
final class EloquentSchemaOptionsTest extends TestCase
{
    private const PROJECT = self::FIXTURES.'/Project';

    /** @return array<string, mixed> */
    private static function omnibase(): array
    {
        return [
            'project_dir' => self::PROJECT,
            'enumerations' => [self::FIXTURES.'/Enumerations'],
            'resolve_target_entities' => [TravellerInterface::class => Destination::class],
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $config = $app->make('config');
        \assert($config instanceof Repository);
        $config->set('omnibridge.omnibase', self::omnibase());
        $config->set(Persistence::ENTITIES, [self::FIXTURES.'/Entity', self::PROJECT.'/src/Entity']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(EntitySchema::class)) {
            self::markTestSkipped('omnibase 4.x does not describe its entities without a persistence.');
        }
    }

    public function testEloquentIsGivenTheSchemaOptionsOfOmnibase(): void
    {
        $options = Persistence::eloquentEntities($this->application(), self::omnibase() + ['environment' => 'test', 'parameters' => [], 'types' => ['visit_state' => VisitState::class]]);

        // What is not EntitySchema's (the environment, the parameters) stays Doctrine's mapping's.
        $this->assertSame([
            'project_dir' => self::PROJECT,
            'directories' => [self::FIXTURES.'/Entity', self::PROJECT.'/src/Entity'],
            'enumerations' => [self::FIXTURES.'/Enumerations'],
            'resolve_target_entities' => [TravellerInterface::class => Destination::class],
            'types' => ['visit_state' => VisitState::class],
        ], $options);
    }

    public function testEloquentDescribesTheRelationsAndColumnTypesDoctrineMaps(): void
    {
        $metadata = $this->application()->make(EntityManagerInterface::class)->getClassMetadata(Visit::class);
        $this->assertSame(Destination::class, $metadata->getAssociationTargetClass('traveller'));
        $this->assertSame('visit_state', $metadata->getTypeOfField('state'));

        $options = Persistence::eloquentEntities($this->application(), self::omnibase());
        $visit = self::describe($options)->entities[Visit::class];
        $this->assertSame(Destination::class, $visit->relations['traveller']->target);
        $this->assertSame('visit_state', $visit->fields['state']->type);
        $this->assertSame(VisitState::class, EntitySchema::types($options)['visit_state'] ?? null);
    }

    public function testOnDoctrineSchemaCreateDescribesWhatTheEntityManagerMaps(): void
    {
        $this->config()->set('base.persistence', 'doctrine');

        $this->assertSame([self::FIXTURES.'/Entity', self::PROJECT.'/src/Entity', EntitySchema::coreDirectory('Entity')], Persistence::describedDirectories($this->application(), self::omnibase()));
    }

    /** On omnibridge/eloquent itself, where it is installed: the persistence the container makes reads the options. */
    public function testTheEloquentPersistenceResolvesTheTargetsAndKnowsTheEnumerations(): void
    {
        $this->eloquent();

        // Its classes are unknown to the analysis where omnibridge/eloquent is not installed (this package's CI).
        $store = $this->application()->make('Omnibridge\Eloquent\Omnibase\EloquentPersistence')->store(); // @phpstan-ignore method.nonObject
        $description = $store->mapping()->description; // @phpstan-ignore method.nonObject, property.nonObject
        \assert($description instanceof Description);
        $this->assertSame(Destination::class, $description->entities[Visit::class]->relations['traveller']->target);
        $this->assertTrue($store->casts()->knows('visit_state')); // @phpstan-ignore method.nonObject, method.nonObject
    }

    /** On Eloquent, omnibridge:schema:create creates the tables of the project's src/Entity, which its persistence describes. */
    public function testOnEloquentSchemaCreateDescribesWhatThePersistenceDescribes(): void
    {
        $this->eloquent();
        // Only the fixtures' folder declared: the project's src/Entity is Eloquent's own.
        $this->config()->set(Persistence::ENTITIES, [self::FIXTURES.'/Entity']);
        $options = Persistence::eloquentEntities($this->application(), self::omnibase());
        $description = self::describe($options);
        $this->assertArrayHasKey(Visit::class, $description->entities);

        $this->assertSame(0, Artisan::call('omnibridge:schema:create', ['--dump-sql' => true]));
        $statements = (new SchemaCreator(DB::connection('testing')->getPdo(), EntitySchema::columnTypes($options)))->statements($description);
        $this->assertSame(implode('', array_map(static fn (string $statement) => $statement.";\n", $statements)), Artisan::output());
    }

    private function eloquent(): void
    {
        if (!class_exists(\Omnibridge\Eloquent\Omnibase\EloquentPersistence::class)) {
            self::markTestSkipped('omnibridge/eloquent is not installed.');
        }
        $this->config()->set('base.persistence', 'eloquent');
    }

    private function application(): Application
    {
        \assert(null !== $this->app);

        return $this->app;
    }

    private function config(): Repository
    {
        $config = $this->application()->make('config');
        \assert($config instanceof Repository);

        return $config;
    }

    /**
     * @param array{project_dir: string, directories: list<string>}&array<string, mixed> $options
     *
     * @return Description as omnibridge/eloquent's EloquentPersistence::create() describes the entities
     */
    private static function describe(array $options): Description
    {
        return EntitySchema::describe(EntitySchema::directories($options['project_dir'], $options['directories']), $options);
    }
}
