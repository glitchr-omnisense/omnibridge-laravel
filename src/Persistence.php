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
 * omnibase project's folder and the core's, omnibase's lifecycle from the container (EloquentPersistence::host(): the
 * AttributeReader omnibase's Doctrine mapping makes, the entity dispatchers its listeners list), the trash and vault
 * scopes on as omnibase's Doctrine mapping enables its filters: the unit of work, Snapshots, the backend,
 * Repositories and the core's repository interfaces answer from it.
 */
final class Persistence
{
    public const OPTION = 'base.persistence (config/base.php)';

    private const ELOQUENT = 'Omnibridge\Eloquent\Omnibase\EloquentPersistence';

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
     * On Eloquent, with omnibase's mapping on (omnisense/omnibase's plan "omnibase agnostic", step F7d-2): omnibase's
     * entity manager made once the application has booted, for what it registers beside Doctrine - the AttributeReader
     * the entities' attributes are read through (#[Uploader]'s public address of an avatar read before any query, the
     * behaviours), the column types Twig's enum filters name. On Doctrine nothing: the entity manager is made by its
     * first user, as before. Neither unit of work is cleared between two requests one application serves (a test's):
     * Laravel keeps its guards' accounts across them, as it keeps Doctrine's entity manager.
     */
    public static function boot(Application $app, bool $omnibase): void
    {
        if (!$omnibase || !class_exists(self::ELOQUENT) || !self::eloquent($app) || !$app->bound(\Doctrine\ORM\EntityManagerInterface::class)) {
            return;
        }
        $app->booted(static function (Application $app): void {
            $app->make(\Doctrine\ORM\EntityManagerInterface::class);
        });
    }

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
            $persistence = \Omnibridge\Eloquent\Omnibase\EloquentPersistence::host($connection, $app, [
                'project_dir' => $options['project_dir'],
                'lifecycle' => [
                    'empty_trash' => $parameter('base.extension.empty_trash', '+7 days'),
                    'max_revisions' => (int) $parameter('base.extension.max_revisions', 5),
                    // The reader omnibase's Doctrine mapping makes, with its entity manager (Mapping::register()).
                    'attribute_reader' => static function () use ($app): ?object {
                        $app->bound(\Doctrine\ORM\EntityManagerInterface::class) && $app->make(\Doctrine\ORM\EntityManagerInterface::class);

                        return \Base\Attributes\AttributeReader::hasInstance() ? \Base\Attributes\AttributeReader::getInstance(false) : null;
                    },
                    'entity_dispatchers' => static fn (): array => array_map($app->make(...), $dispatchers($app)),
                ],
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
