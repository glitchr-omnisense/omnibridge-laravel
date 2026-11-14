<?php

namespace Omnibridge\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Omnibridge\Persistence\Persistences;

/**
 * The persistence of an omnibase application under Laravel (omnisense/omnibase's plan "omnibase agnostic", step F7d):
 * omnibase's option `persistence` (config/base.php), chosen as every host chooses it (omnisense/omnibridge's
 * Persistences: the only one installed, else the one named; several installed and none named, refused aloud), read
 * when the first persistence service is made and when the application boots - the configuration may still change
 * while the providers register (Testbench's defineEnvironment).
 *
 * On Doctrine nothing changes: the entity manager omnibridge.doctrine configures, omnibridge/doctrine's unit of work,
 * repositories and backend. On Eloquent (register()), omnibridge/eloquent's on Laravel's own connection
 * (omnibridge.eloquent.connection, else omnibridge.doctrine.connection, else the default one), the entities of the
 * folders the application declares (omnibridge.doctrine.entities, the ones Doctrine's entity manager maps: ENTITIES),
 * the omnibase project's src/Entity and the core's, described with the options omnibase's Doctrine mapping is given
 * (SCHEMA_OPTIONS: types, enumerations, resolve_target_entities...), omnibase's lifecycle from the container (EloquentPersistence::host(): the
 * AttributeReader omnibase's Doctrine mapping makes, the entity dispatchers its listeners list), the trash and vault
 * scopes on as omnibase's Doctrine mapping enables its filters: the unit of work, Snapshots, the backend,
 * Repositories and the core's repository interfaces answer from it.
 */
final class Persistence
{
    public const OPTION = 'base.persistence (config/base.php)';

    private const ELOQUENT = 'Omnibridge\Eloquent\Omnibase\EloquentPersistence';

    /**
     * The configuration key of the application's entity folders, read by both persistences: the folders Doctrine's
     * entity manager maps beside the core's, and the ones omnibridge/eloquent describes beside the core's and the
     * omnibase project's src/Entity (its `directories`), as omnibridge:schema:create reads them. Its name is Doctrine's
     * for what came first; there is no second key.
     */
    public const ENTITIES = 'omnibridge.doctrine.entities';

    /**
     * The entity folders the application declares (ENTITIES), whichever persistence runs.
     *
     * @return list<string>
     */
    public static function entityDirectories(Application $app): array
    {
        $config = $app->make('config');
        \assert($config instanceof \Illuminate\Contracts\Config\Repository);
        $directories = [];
        foreach ((array) $config->get(self::ENTITIES, []) as $directory) {
            if (\is_string($directory) && '' !== $directory) {
                $directories[] = $directory;
            }
        }

        return array_values(array_unique($directories));
    }

    /** The persistence chosen (doctrine, eloquent), null when none is installed. */
    public static function name(Application $app): ?string
    {
        // An omnisense/omnibridge that predates the choice (a 4.x application's lock): Doctrine, as before.
        if (!class_exists(Persistences::class)) {
            return class_exists(\Omnibridge\Doctrine\EntityManagers::class) ? 'doctrine' : null;
        }
        $configured = $app['config']->get('base.persistence');

        return Persistences::choose(\is_string($configured) && '' !== $configured ? $configured : null, self::OPTION);
    }

    public static function eloquent(Application $app): bool
    {
        return 'eloquent' === self::name($app);
    }

    /**
     * Whether Doctrine's wiring is given (omnisense/omnibase's plan "omnibase agnostic", step B3: Doctrine a suggestion
     * of this bridge): omnibridge/doctrine installed, doctrine/orm with it.
     */
    public static function hasDoctrine(): bool
    {
        if (class_exists(Persistences::class)) {
            return Persistences::isInstalled(Persistences::DOCTRINE) && interface_exists(\Doctrine\ORM\EntityManagerInterface::class);
        }

        return class_exists(\Omnibridge\Doctrine\EntityManagers::class);
    }

    /**
     * Whether the attribute reader is the core's own, on its MetadataManipulator over the persistence's shapes (step E3),
     * bound from omnibase's list of services (Base\Services), rather than the one omnibase's Doctrine mapping makes
     * (Mapping::register()): on Eloquent with a core that has it.
     */
    public static function neutralReader(Application $app): bool
    {
        return class_exists(\Base\Database\Mapping\MetadataManipulator::class) && self::eloquent($app);
    }

    /**
     * On Eloquent, with omnibase's mapping on (omnisense/omnibase's plan "omnibase agnostic", step F7d-2): the
     * AttributeReader the entities' attributes are read through (#[Uploader]'s public address of an avatar read before
     * any query, the behaviours) and the enumerations Twig's enum filters name, made once the application has booted:
     * the core's reader and omnibridge/eloquent's persistence (its casts register the enumerations) where the core has
     * its MetadataManipulator (step B3: no entity manager made, Doctrine need not be installed), else omnibase's entity
     * manager, for what its mapping registers. On Doctrine nothing: the entity manager is made by its first user, as
     * before. Neither unit of work is cleared between two requests one application serves (a test's):
     * Laravel keeps its guards' accounts across them, as it keeps Doctrine's entity manager.
     */
    public static function boot(Application $app, bool $omnibase): void
    {
        if (!$omnibase || !class_exists(self::ELOQUENT) || !self::eloquent($app)) {
            return;
        }
        if (self::neutralReader($app)) {
            $app->booted(static function (Application $app): void {
                $app->make(self::ELOQUENT);
                $app->make(\Base\Attributes\AttributeReader::class);
            });

            return;
        }
        if (!$app->bound(\Doctrine\ORM\EntityManagerInterface::class)) {
            return;
        }
        $app->booted(static function (Application $app): void {
            $app->make(\Doctrine\ORM\EntityManagerInterface::class);
        });
    }

    /**
     * omnibase's options that describe the entities (omnisense/omnibase's Base\Database\Schema\EntitySchema): the column
     * types by name (a bundle's enumerations), more folders of enumerations, the targets resolved (interface => class),
     * more rules and column types. Doctrine's entity manager gets the first three through Mapping::register(), with all
     * of omnibase's options; omnibridge/eloquent's persistence and omnibridge:schema:create describe the entities with
     * them (since 1.2.1 on Eloquent: until then its persistence was given none of them).
     */
    public const SCHEMA_OPTIONS = ['types', 'enumerations', 'resolve_target_entities', 'rules', 'column_types'];

    /**
     * Where omnibridge/eloquent finds the entities and how it describes them (EloquentPersistence's options project_dir
     * and directories, which it describes with EntitySchema::directories(), and EntitySchema's options of omnibase's,
     * SCHEMA_OPTIONS): the core's, the omnibase project's src/Entity, and the folders the application declares
     * (ENTITIES), the ones Doctrine's entity manager maps, with the enumerations and targets omnibase's options give
     * Doctrine's mapping - so that one configuration gives both persistences the same entities, the same relations and
     * the same column types. Until 1.1.1 the declared folders were left out (an entity elsewhere than
     * project_dir/src/Entity was "no entity of the description" on Eloquent, mapped on Doctrine); until 1.2.1 the
     * options (a relation toward an interface kept the interface, an enumeration of a folder given was no column type).
     *
     * @param array<string, mixed>|null $omnibase omnibase's mapping options (project_dir, types, enumerations,
     *                                            resolve_target_entities...), null when off
     *
     * @return array{project_dir: string, directories: list<string>}&array<string, mixed>
     */
    public static function eloquentEntities(Application $app, ?array $omnibase = null): array
    {
        $projectDir = $omnibase['project_dir'] ?? null;

        return [
            'project_dir' => \is_string($projectDir) && '' !== $projectDir ? $projectDir : $app->basePath(),
            'directories' => self::entityDirectories($app),
        ] + array_intersect_key($omnibase ?? [], array_flip(self::SCHEMA_OPTIONS));
    }

    /**
     * The entities' folders the chosen persistence describes, as omnibridge:schema:create reads them: on Eloquent its
     * persistence's (EntitySchema::directories() of eloquentEntities(): the core's, the project's src/Entity, the
     * declared ones), else those Doctrine's entity manager maps (the declared ones, then the core's). Until 1.2.1 the
     * command read Doctrine's on Eloquent too: an entity of project_dir/src/Entity had no table.
     *
     * @param array<string, mixed>|null $omnibase omnibase's mapping options (project_dir), null when off
     *
     * @return list<string>
     */
    public static function describedDirectories(Application $app, ?array $omnibase = null): array
    {
        $entities = self::eloquentEntities($app, $omnibase);
        if (self::eloquent($app)) {
            return \Base\Database\Schema\EntitySchema::directories($entities['project_dir'], $entities['directories']);
        }

        return array_values(array_unique([...$entities['directories'], \Base\Database\Schema\EntitySchema::coreDirectory('Entity')]));
    }

    /** omnisense/omnibridge's Lifecycle\Listeners::TAG (by value: an omnisense/omnibridge that predates it still runs). */
    public const LIFECYCLE_LISTENER = 'omnibridge.lifecycle_listener';

    /**
     * omnibridge/eloquent's services, bound where it is installed, each answering from Eloquent when it is the one
     * chosen and from what was bound before otherwise (Doctrine's).
     *
     * @param \Closure(Application): ?array<string, mixed> $omnibase omnibase's mapping options (project_dir, environment)
     * @param \Closure(Application): list<string>         $dispatchers the entity dispatchers' ids
     */
    public static function register(Application $app, \Closure $omnibase, \Closure $dispatchers): void
    {
        if (!class_exists(self::ELOQUENT)) {
            return;
        }
        $app->singleton(self::ELOQUENT, static function (Application $app) use ($omnibase, $dispatchers): object {
            $config = $app['config'];
            $connection = \Omnibridge\Eloquent\Connections::laravel($app['db'], $config->get('omnibridge.eloquent.connection') ?? $config->get('omnibridge.doctrine.connection'));
            $options = $omnibase($app) ?? ['project_dir' => $app->basePath(), 'environment' => (string) $app->environment()];
            $parameters = $app->bound('Base\Service\ParameterBagInterface') ? $app->make('Base\Service\ParameterBagInterface') : null;
            $parameter = static fn (string $name, mixed $default): mixed => null !== $parameters && null !== ($value = $parameters->get($name)) ? $value : $default;
            $persistence = \Omnibridge\Eloquent\Omnibase\EloquentPersistence::host($connection, $app, self::eloquentEntities($app, $options) + [
                'lifecycle' => [
                    'empty_trash' => $parameter('base.extension.empty_trash', '+7 days'),
                    'max_revisions' => (int) $parameter('base.extension.max_revisions', 5),
                    // The core's reader, bound from omnibase's list (step B3); else the one omnibase's Doctrine mapping
                    // makes, with its entity manager (Mapping::register()).
                    'attribute_reader' => static function () use ($app): ?object {
                        if (self::neutralReader($app) && $app->bound(\Base\Attributes\AttributeReader::class)) {
                            return $app->make(\Base\Attributes\AttributeReader::class);
                        }
                        $app->bound(\Doctrine\ORM\EntityManagerInterface::class) && $app->make(\Doctrine\ORM\EntityManagerInterface::class);

                        return \Base\Attributes\AttributeReader::hasInstance() ? \Base\Attributes\AttributeReader::getInstance(false) : null;
                    },
                    'entity_dispatchers' => static fn (): array => array_map($app->make(...), $dispatchers($app)),
                ],
                // The packages' listeners of the Lifecycle events (omnisense/omnibridge's Lifecycle\Listeners, step B2):
                // what the application tagged omnibridge.lifecycle_listener, made at the first event.
                'listeners' => static fn (): iterable => $app->tagged(self::LIFECYCLE_LISTENER),
            ]);
            $persistence->backend()->enableScope('trash');
            $persistence->backend()->enableScope('vault', ['environment' => $options['environment'] ?? (string) $app->environment()]);

            return $persistence;
        });

        // Doctrine's answer, when Eloquent is not the one chosen: what was bound before, else what omnibase's aliases
        // name (the repository interfaces: OmnibaseServices binds them so at boot), else Doctrine's backend.
        $registry = class_exists(\Base\Discovery::class) ? \Base\Discovery::registry('repositories') : \Base\Repository\Aliases::INTERFACES;
        $doctrine = static function (Application $app, string $id, ?\Closure $before) use ($registry): mixed {
            if (null !== $before) {
                return $before($app);
            }
            if (isset($registry[$id])) {
                return $app->make($registry[$id]);
            }
            if ('Omnibridge\Persistence\Backend' === $id && class_exists(\Omnibridge\Doctrine\Omnibase\DoctrineBackend::class)) {
                return new \Omnibridge\Doctrine\Omnibase\DoctrineBackend($app->make('Omnibridge\Persistence\UnitOfWork'));
            }
            if ('Omnibridge\Persistence\Snapshots' === $id) {
                return $app->make('Omnibridge\Persistence\UnitOfWork');
            }

            throw new \LogicException(sprintf('%s is answered by no persistence: %s chose doctrine, and omnibridge.doctrine binds none.', $id, self::OPTION));
        };
        $from = static fn (string $method): \Closure => static fn (Application $app): object => $app->make(self::ELOQUENT)->$method();
        foreach ([
            'Omnibridge\Persistence\UnitOfWork' => $from('unitOfWork'),
            'Omnibridge\Persistence\Snapshots' => $from('unitOfWork'),
            'Omnibridge\Persistence\Backend' => $from('backend'),
            'Base\Database\Repository\Repositories' => $from('repositories'),
        ] + self::interfaces() as $id => $eloquent) {
            $before = self::binding($app, $id);
            $app->singleton($id, static fn (Application $app): mixed => self::eloquent($app) ? $eloquent($app) : $doctrine($app, $id, $before));
        }
    }

    /** @return array<class-string, \Closure(Application): object> the core's repository interfaces on Eloquent */
    private static function interfaces(): array
    {
        $interfaces = [];
        foreach (\Omnibridge\Eloquent\Container::interfaces() as $interface => $entity) {
            $interfaces[$interface] = static fn (Application $app): object => $app->make(self::ELOQUENT)->repositories()->implementing($interface, $entity);
        }

        return $interfaces;
    }

    /** What the container bound an id to before (its closure), null when nothing. */
    private static function binding(Application $app, string $id): ?\Closure
    {
        $bindings = $app->getBindings();
        if (isset($bindings[$id]['concrete']) && $bindings[$id]['concrete'] instanceof \Closure) {
            return $bindings[$id]['concrete'];
        }

        return null;
    }
}
