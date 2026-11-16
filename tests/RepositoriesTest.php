<?php

namespace Omnibridge\Laravel\Tests;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * A repository by its class, as Symfony autowires a ServiceEntityRepository
 * (step R10 of the audit "lesringostars face au master plan": an action
 * written once type-hints the site's repositories): the entity manager's own
 * repository of the entity it is named after, through the core's registry, one for the application; a
 * repository of another entity than the one the mapping names is refused, saying so.
 */
final class RepositoriesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('omnibridge.doctrine.entities', [self::FIXTURES.'/Omnibase/Entity']);
        $app['config']->set('omnibridge.omnibase', ['project_dir' => self::FIXTURES.'/Omnibase']);
        $app['config']->set('base', [
            'database' => ['use_custom' => true],
            'attributes' => ['use_custom' => true],
            'twig' => ['editor' => ['storage' => 'local.wysiwyg']],
            'uploader' => ['storage' => 'local.uploads'],
        ]);
    }

    public function testARepositoryIsTheEntityManagersOwn(): void
    {
        $repository = $this->app->make(UserRepository::class);

        $this->assertSame($this->app->make(\Base\Database\Repository\Repositories::class)->of(User::class), $repository);
        $this->assertSame($repository, $this->app->make(UserRepository::class), 'one for the application');
        $this->assertSame($repository, $this->app->make(NeedsUsers::class)->users, 'a type-hint gets it');
    }

    /** What a lifecycle listener or a command reaches - UnitOfWorks::repositories() on the entity manager - is the same registry. */
    public function testTheUnitOfWorksRegistryIsTheMappingsOwn(): void
    {
        $repository = $this->app->make(UserRepository::class);
        $unitOfWork = \Base\Database\UnitOfWorks::of($this->app->make(\Doctrine\ORM\EntityManagerInterface::class));

        $this->assertSame($repository, \Base\Database\UnitOfWorks::repositories($unitOfWork)->of(User::class));
    }

    public function testARepositoryNoEntityNamesIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->app->make(UnnamedRepository::class);
    }
}

final class NeedsUsers
{
    public function __construct(public readonly UserRepository $users)
    {
    }
}

/** Named after Base\Entity\Unnamed, which no mapping has. */
final class UnnamedRepository extends \Base\Database\Repository\ServiceEntityRepository
{
}
