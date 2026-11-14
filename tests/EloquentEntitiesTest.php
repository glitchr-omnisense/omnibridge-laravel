<?php

namespace Omnibridge\Laravel\Tests;

use App\Entity\Destination;
use Base\Database\Schema\EntitySchema;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Omnibridge\Laravel\Persistence;

/**
 * The entities omnibridge/eloquent is given (1.1.1): the folders the application declares (omnibridge.doctrine.entities,
 * Persistence::ENTITIES), the ones Doctrine's entity manager maps, beside the core's and the omnibase project's
 * src/Entity - one configuration, the same entities on both persistences. Until 1.1.1 only project_dir/src/Entity and
 * the core's were described: an entity declared elsewhere was "no entity of the description" on Eloquent
 * (omnibridge/eloquent's Mapping::of()), a 500, while Doctrine mapped it.
 *
 * The description is built as EloquentPersistence::create() builds it from these options, omnibridge/eloquent not
 * being installed beside omnibridge/doctrine here.
 */
final class EloquentEntitiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(EntitySchema::class)) {
            self::markTestSkipped('omnibase 4.x does not describe its entities without a persistence.');
        }
    }

    public function testTheDeclaredFoldersAreDescribedBesideTheCoresAndTheProjects(): void
    {
        // A project folder without src/Entity: the application's entity is only in the declared folder.
        $options = Persistence::eloquentEntities($this->application(), ['project_dir' => self::FIXTURES]);

        $this->assertSame(self::FIXTURES, $options['project_dir']);
        $this->assertSame([self::FIXTURES.'/Entity'], $options['directories']);

        $entities = self::describe($options);
        $this->assertContains(Destination::class, $entities);
        $this->assertContains(\Base\Entity\User::class, $entities);
    }

    public function testEloquentDescribesWhatDoctrineMaps(): void
    {
        $doctrine = $this->application()->make(EntityManagerInterface::class)->getConfiguration()->getMetadataDriverImpl()?->getAllClassNames() ?? [];
        $this->assertContains(Destination::class, $doctrine);

        $this->assertSame([], array_values(array_diff($doctrine, self::describe(Persistence::eloquentEntities($this->application())))));
    }

    /**
     * On omnibridge/eloquent itself, where it is installed (this package's CI installs omnibridge/doctrine only): the
     * persistence the container makes maps the declared entity.
     */
    public function testTheEloquentPersistenceMapsTheDeclaredEntity(): void
    {
        if (!class_exists(\Omnibridge\Eloquent\Omnibase\EloquentPersistence::class)) {
            self::markTestSkipped('omnibridge/eloquent is not installed.');
        }
        $this->config()->set('base.persistence', 'eloquent');

        $persistence = $this->application()->make(\Omnibridge\Eloquent\Omnibase\EloquentPersistence::class);
        // Unknown to the analysis where omnibridge/eloquent is not installed (this package's CI).
        $this->assertTrue($persistence->store()->mapping()->has(Destination::class)); // @phpstan-ignore method.nonObject, method.nonObject
    }

    public function testWithoutOmnibaseTheProjectIsTheApplicationsFolder(): void
    {
        $this->config()->set(Persistence::ENTITIES, [self::FIXTURES.'/Entity', '', self::FIXTURES.'/Entity']);

        $this->assertSame(['project_dir' => $this->application()->basePath(), 'directories' => [self::FIXTURES.'/Entity']], Persistence::eloquentEntities($this->application()));
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
     * @param array{project_dir: string, directories: list<string>} $options
     *
     * @return list<string> the classes described, as omnibridge/eloquent's EloquentPersistence::create() describes them
     */
    private static function describe(array $options): array
    {
        return array_keys(EntitySchema::describe(EntitySchema::directories($options['project_dir'], $options['directories']), $options)->entities);
    }
}
