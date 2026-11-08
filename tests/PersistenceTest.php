<?php

namespace Omnibridge\Laravel\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Omnibridge\Doctrine\Omnibase\DoctrineUnitOfWork;
use Omnibridge\Exception\PersistenceException;
use Omnibridge\Laravel\Persistence;
use Omnibridge\Persistence\Persistences;
use Omnibridge\Persistence\UnitOfWork;

/**
 * The persistence chosen as every host chooses it (omnisense/omnibase's plan "omnibase agnostic", step F7d): omnibase's
 * option base.persistence (config/base.php), Doctrine the only one installed here; one not installed, or a name no
 * persistence has, refused aloud, naming the option; several installed and none named, refused (R8). On Doctrine, the
 * unit of work is omnibridge/doctrine's on the entity manager, as before.
 */
final class PersistenceTest extends TestCase
{
    protected function tearDown(): void
    {
        Persistences::unregister('acme');
        parent::tearDown();
    }

    public function testTheOnlyPersistenceInstalledIsDoctrine(): void
    {
        $this->assertSame(['doctrine'], Persistences::installed());
        $this->assertSame('doctrine', Persistence::name($this->app));
        $this->assertFalse(Persistence::eloquent($this->app));

        $unitOfWork = $this->app->make(UnitOfWork::class);
        $this->assertInstanceOf(DoctrineUnitOfWork::class, $unitOfWork);
        $this->assertSame($this->app->make(EntityManagerInterface::class), $unitOfWork->entityManager());
    }

    /**
     * Doctrine's wiring is given where omnibridge/doctrine and doctrine/orm are installed (step B3: a suggestion of this
     * bridge), and the attribute reader stays the one omnibase's Doctrine mapping makes when Doctrine is chosen.
     */
    public function testDoctrineInstalledGivesItsWiring(): void
    {
        $this->assertTrue(Persistence::hasDoctrine());
        $this->assertFalse(Persistence::neutralReader($this->app));
        $this->assertTrue($this->app->bound(EntityManagerInterface::class));
    }

    public function testDoctrineNamedIsDoctrine(): void
    {
        $this->app['config']->set('base.persistence', 'doctrine');
        $this->assertSame('doctrine', Persistence::name($this->app));
    }

    public function testEloquentNotInstalledIsRefused(): void
    {
        $this->app['config']->set('base.persistence', 'eloquent');
        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('The persistence "eloquent" (base.persistence (config/base.php)) is not installed: composer require omnibridge/eloquent.');
        Persistence::name($this->app);
    }

    public function testSeveralInstalledAndNoneNamedIsRefused(): void
    {
        Persistences::register('acme', 'acme/persistence', self::class);
        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('acme/persistence and omnibridge/doctrine are installed: base.persistence (config/base.php) (or the environment variable OMNIBRIDGE_PERSISTENCE) names the one to run on (acme|doctrine)');
        Persistence::name($this->app);
    }
}
