<?php

namespace Omnibridge\Laravel\Tests;

use Base\Database\Schema\EntitySchema;
use Illuminate\Contracts\Config\Repository;
use Omnibridge\Laravel\Persistence;

/**
 * The entities omnibridge/eloquent and Doctrine's entity manager are given since 2.1: the core's, the Composer
 * packages' that require it (omnisense/omnibase 7.2's Base\Discovery), the folders config/base.php's entity_folders
 * includes and leaves out - and omnibridge.doctrine.entities, now a deprecated alias of its `include`, still read.
 */
final class EntityFoldersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(\Base\Discovery::class) || !\in_array('entities', \Base\Discovery::KINDS, true)) { // @phpstan-ignore function.alreadyNarrowedType
            self::markTestSkipped('omnisense/omnibase 7.2 discovers the entities.');
        }
    }

    private function config(): Repository
    {
        \assert(null !== $this->app);
        $config = $this->app->make('config');
        \assert($config instanceof Repository);

        return $config;
    }

    public function testTheIncludedAndExcludedFolders(): void
    {
        $this->config()->set(Persistence::ENTITY_FOLDERS, ['include' => [self::FIXTURES.'/Entity' => 'App\Entity'], 'exclude' => [self::FIXTURES.'/Entity/Destination.php']]);
        \assert(null !== $this->app);

        $options = Persistence::eloquentEntities($this->app, ['project_dir' => self::FIXTURES]);

        $this->assertContains(self::FIXTURES.'/Entity', $options['directories']);
        $this->assertNotContains(EntitySchema::coreDirectory('Entity'), $options['directories'], 'the core\'s is EntitySchema::directories()\'s own');
        $this->assertSame([self::FIXTURES.'/Entity/Destination.php'], $options['exclude']);
        $entities = array_keys(EntitySchema::describe(EntitySchema::directories($options['project_dir'], $options['directories']), $options)->entities);
        $this->assertNotContains('App\Entity\Destination', $entities, 'left out');
        $this->assertContains(\Base\Entity\User::class, $entities);
        $this->assertSame(Persistence::doctrineDirectories($options), [...$options['directories'], EntitySchema::coreDirectory('Entity')]);
    }

    public function testTheFormerKeyIsStillReadAndDeprecated(): void
    {
        $this->config()->set(Persistence::ENTITIES, [self::FIXTURES.'/Entity']);
        \assert(null !== $this->app);
        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            $this->assertSame([self::FIXTURES.'/Entity'], Persistence::entityDirectories($this->app));
        } finally {
            restore_error_handler();
        }
        $this->assertLessThanOrEqual(1, \count($deprecations), 'said once a process');
    }

    public function testThePackagesRequiringTheCoreAreDiscovered(): void
    {
        \assert(null !== $this->app);
        $discovered = Persistence::discoveredEntities($this->app, self::FIXTURES);

        $this->assertSame(\Base\Discovery::packages(), $discovered['packages']);
        $this->assertSame('Base\Entity', $discovered['paths'][EntitySchema::coreDirectory('Entity')]);
        foreach ($discovered['packages'] as $folder => $namespace) {
            if (is_dir($folder.'/src/Entity')) {
                $this->assertSame($namespace.'\Entity', $discovered['paths'][$folder.'/src/Entity']);
            }
        }
    }
}
