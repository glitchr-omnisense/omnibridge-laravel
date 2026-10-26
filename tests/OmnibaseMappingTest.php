<?php

namespace Omnibridge\Laravel\Tests;

use Base\Bridge\Doctrine\Mapping;
use Base\Bridge\Doctrine\MetadataSnapshot;
use Base\Bridge\Doctrine\RepositoryFactory;
use Doctrine\ORM\EntityManagerInterface;

/**
 * omnibase's Doctrine mapping under Laravel (omnibridge.doctrine.omnibase): the
 * entity manager built with Base\Bridge\Doctrine\Mapping, as omnibase's own is
 * under Symfony, keeps omnibase's Base/App invariants (master plan §5):
 *
 * 1. an application's own App\ class wins over the convention's alias;
 * 4. the metadata are Symfony's, overrides included - those omnisense/omnibase
 *    records from its harness's Symfony application (its
 *    tests/Bridge/Doctrine/fixtures/doctrine-metadata.json, kept equal to
 *    DoctrineBundle's by its HandBuiltEntityManagerTest), whose App\Entity\User
 *    is this suite's (Fixtures/Omnibase), class by class;
 * 8. an alias is not a subclass: App\Entity\Thread, which the application
 *    lacks, is Base\Entity\Thread itself - its reflection, its attributes, its
 *    Doctrine identity.
 */
final class OmnibaseMappingTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('omnibridge.doctrine.entities', [self::FIXTURES.'/Omnibase/Entity']);
        $app['config']->set('omnibridge.doctrine.omnibase', ['project_dir' => self::FIXTURES.'/Omnibase']);
        // omnibase's parameters the entities read (Runtime::parameters(), Laravel's config): those of
        // the harness whose metadata Symfony recorded (its config/packages/base.yaml).
        $app['config']->set('base', [
            'database' => ['use_custom' => true],
            'attributes' => ['use_custom' => true],
            'twig' => ['editor' => ['storage' => 'local.wysiwyg']],
            'uploader' => ['storage' => 'local.uploads'],
        ]);
    }

    public function testTheEntityManagerIsOmnibases(): void
    {
        $entities = $this->app->make(EntityManagerInterface::class);

        $this->assertInstanceOf(RepositoryFactory::class, $entities->getConfiguration()->getRepositoryFactory(), 'Mapping::configure(), before the entity manager existed');
        $mapping = $this->app->make(Mapping::class);
        $this->assertSame($entities, $mapping->entityManager, 'Mapping::register() on the bound entity manager');
        $this->assertSame('test', $mapping->attributeReader->getEnvironment(), 'Laravel\'s "testing", by omnibase\'s name');
        $this->assertTrue($entities->getFilters()->isEnabled('trash_filter'));
        $this->assertTrue($entities->getFilters()->isEnabled('vault_filter'));
    }

    /** Invariant 1: the application's App\Entity\User and its repository are its own, over the core's. */
    public function testTheApplicationsOwnClassWins(): void
    {
        $entities = $this->app->make(EntityManagerInterface::class);

        $this->assertSame('App\\Entity\\User', (new \ReflectionClass('App\\Entity\\User'))->getName());
        $this->assertSame('Base\\Entity\\User', get_parent_class('App\\Entity\\User'));
        $metadata = $entities->getClassMetadata('App\\Entity\\User');
        $this->assertSame('App\\Entity\\User', $metadata->getName());
        $this->assertSame('userProfile', $metadata->getTableName());
        $this->assertSame('user', $metadata->discriminatorValue);
        $this->assertArrayHasKey('username', $metadata->fieldMappings);

        // Its repository, which extends the core's users' repository: it loads without Security (audit R2).
        $repository = $entities->getRepository('App\\Entity\\User');
        $this->assertSame('App\\Repository\\UserRepository', $repository::class);
        $this->assertInstanceOf(\Base\Repository\UserRepository::class, $repository);
    }

    /** Invariants 1 and 8: what the application lacks is the core's class by alias, not a subclass. */
    public function testAClassTheApplicationLacksIsTheCoresByAlias(): void
    {
        $entities = $this->app->make(EntityManagerInterface::class);

        $this->assertTrue(class_exists('App\\Entity\\Thread'), 'the convention');
        $this->assertSame('Base\\Entity\\Thread', (new \ReflectionClass('App\\Entity\\Thread'))->getName(), 'an alias: its reflection is the core\'s class');
        $this->assertFalse(is_subclass_of('App\\Entity\\Thread', 'Base\\Entity\\Thread'), 'not a subclass');
        $this->assertSame(
            array_map(fn ($attribute) => $attribute->getName(), (new \ReflectionClass('Base\\Entity\\Thread'))->getAttributes()),
            array_map(fn ($attribute) => $attribute->getName(), (new \ReflectionClass('App\\Entity\\Thread'))->getAttributes()),
            'the same attributes',
        );
        $this->assertSame('Base\\Entity\\Thread', $entities->getClassMetadata('App\\Entity\\Thread')->getName(), 'one entity for Doctrine');
        $this->assertSame($entities->getClassMetadata('Base\\Entity\\Thread')->getTableName(), $entities->getClassMetadata('App\\Entity\\Thread')->getTableName());
        $this->assertSame($entities->getRepository('Base\\Entity\\Thread'), $entities->getRepository('App\\Entity\\Thread'), 'one repository');
    }

    /**
     * Invariant 4: the metadata of every class Symfony maps and this application has (the core's
     * entities and its App\Entity\User) are Symfony's. Set aside, being what this application does
     * not install: the omnibase/* bundles' classes in Symfony's discriminator maps and subclasses
     * (the harness registers every bundle), the core's entities that are entities only with a
     * package installed (Base\Entity\Signature\Envelope, glitchr/omnisign), and the fields Base\Entity\User\Passkey inherits from
     * web-auth's CredentialRecord, which WebauthnBundle maps in XML under Symfony (as the core's
     * HandBuiltEntityManagerTest sets them aside).
     */
    public function testTheMetadataAreSymfonys(): void
    {
        $symfony = json_decode((string) file_get_contents(self::symfonyFixture()), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(\Doctrine\DBAL\Platforms\SQLitePlatform::class, $symfony['platform'], 'recorded on SQLite, as this suite runs');
        $entities = $this->app->make(EntityManagerInterface::class);
        $this->assertSame($symfony['platform'], $entities->getConnection()->getDatabasePlatform()::class);

        $laravel = json_decode((string) json_encode(MetadataSnapshot::of($entities)), true);
        $here = array_keys($laravel['classes']);
        $this->assertSame([], array_values(array_diff($here, array_keys($symfony['classes']))), 'no class Symfony does not map');
        $missing = array_values(array_filter(array_diff(array_keys($symfony['classes']), $here), fn (string $class) => !self::bundles($class) && !self::optional($class)));
        $this->assertSame([], $missing, 'every class of the core Symfony maps');

        $differences = [];
        foreach ($here as $className) {
            $differences = array_merge($differences, self::differences(self::withoutBundles($symfony['classes'][$className], $here), $laravel['classes'][$className], $className));
        }
        $this->assertSame([], $differences, 'the metadata of '.\count($here).' classes');
        $this->assertSame($symfony['filters'], $laravel['filters'], 'the filters');
        foreach ($laravel['types'] as $name => $className) {
            $this->assertSame($symfony['types'][$name] ?? null, $className, 'type '.$name);
        }
    }

    public function testOffByDefault(): void
    {
        $this->app['config']->set('omnibridge.doctrine.omnibase', false);
        $this->app->forgetInstance(EntityManagerInterface::class);
        $this->app->forgetInstance(Mapping::class);
        $provider = new \Omnibridge\Laravel\OmnibridgeServiceProvider($this->app);
        $provider->register();

        $this->assertNotInstanceOf(RepositoryFactory::class, $this->app->make(EntityManagerInterface::class)->getConfiguration()->getRepositoryFactory());
        $this->expectException(\LogicException::class);
        $this->app->make(Mapping::class);
    }

    /** The core's entities that are entities only where a package is installed, by that package's class. */
    private const OPTIONAL = ['Base\\Entity\\Signature\\Envelope' => 'Omnisign\\Registry'];

    /** A class of the core that is an entity only with a package this application does not install (glitchr/omnisign's envelope). */
    private static function optional(string $className): bool
    {
        return isset(self::OPTIONAL[$className]) && !class_exists(self::OPTIONAL[$className]);
    }

    /** The core's record of Symfony's metadata, in the core installed here. */
    private static function symfonyFixture(): string
    {
        return \dirname((string) (new \ReflectionClass(\Base\Entity\User::class))->getFileName(), 3).'/tests/Bridge/Doctrine/fixtures/doctrine-metadata.json';
    }

    /** A class of an omnibase/* bundle (Base\Marketplace\, Base\Forum\...), which this application does not install. */
    private static function bundles(string $className): bool
    {
        return str_starts_with($className, 'Base\\') && !str_starts_with($className, 'Base\\Entity\\');
    }

    /**
     * Symfony's metadata of a class without the bundles' classes in its discriminator map and subclasses,
     * nor the fields declared by a class mapped outside the attributes (web-auth's CredentialRecord).
     *
     * @param list<string> $here
     */
    private static function withoutBundles(array $metadata, array $here): array
    {
        if (isset($metadata['discriminatorMap'])) {
            $metadata['discriminatorMap'] = array_filter($metadata['discriminatorMap'], fn ($class) => !self::bundles($class));
        }
        if (isset($metadata['subClasses'])) {
            $metadata['subClasses'] = array_values(array_filter($metadata['subClasses'], fn ($class) => !self::bundles($class)));
        }
        foreach ($metadata['fieldMappings'] ?? [] as $field => $mapping) {
            if (isset($mapping['declared']) && !\in_array($mapping['declared'], $here, true) && !str_starts_with($mapping['declared'], 'Base\\') && !str_starts_with($mapping['declared'], 'App\\')) {
                unset($metadata['fieldMappings'][$field], $metadata['columnNames'][$field], $metadata['propertyAccessors'][$field]);
                $metadata['fieldNames'] = array_diff($metadata['fieldNames'], [$field]);
            }
        }

        return $metadata;
    }

    /** @return list<string> where two values differ: its path, Symfony's and this one */
    private static function differences(mixed $expected, mixed $actual, string $path): array
    {
        if (\is_array($expected) && \is_array($actual)) {
            if (array_is_list($expected) !== array_is_list($actual) || (array_is_list($expected) && \count($expected) !== \count($actual))) {
                return [$path.': '.json_encode($expected, \JSON_UNESCAPED_SLASHES).' / '.json_encode($actual, \JSON_UNESCAPED_SLASHES)];
            }
            if (array_keys($expected) !== array_keys($actual)) {
                $missing = array_diff_key($expected, $actual);
                $extra = array_diff_key($actual, $expected);

                return [$path.': keys '.json_encode(array_keys($missing)).' missing, '.json_encode(array_keys($extra)).' more'];
            }
            $differences = [];
            foreach ($expected as $key => $value) {
                $differences = array_merge($differences, self::differences($value, $actual[$key], $path.'.'.$key));
            }

            return $differences;
        }

        return $expected === $actual ? [] : [$path.': '.json_encode($expected, \JSON_UNESCAPED_SLASHES).' / '.json_encode($actual, \JSON_UNESCAPED_SLASHES)];
    }
}
