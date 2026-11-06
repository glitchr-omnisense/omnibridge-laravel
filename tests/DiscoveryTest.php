<?php

namespace Omnibridge\Laravel\Tests;

use Base\Discovery;
use Base\Service\Obfuscator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Omnibridge\Laravel\Omnibase\LazyListener;

/**
 * What the core declares is discovered among its services (Base\Discovery, its plan "omnibase agnostic", step F5e slice
 * 4c), applied to what Laravel's container binds as Symfony's container compiles it: the obfuscator handed the
 * compressions the declaration tags, the admin's links on the null generator (no back office here), and the Doctrine
 * listeners of omnibase - the entity dispatchers, ThreadSubscriber - registered on the events the core's registries
 * give them, bound from the core's declaration.
 */
final class DiscoveryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        if (!class_exists(Discovery::class)) {
            self::markTestSkipped('omnisense/omnibase 4.x declares no registries of discovery.');
        }
        $this->root = sys_get_temp_dir().'/omnibridge-laravel-discovery-'.getmypid();
        @mkdir($this->root, 0777, true);
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        exec('rm -rf '.escapeshellarg($this->root));
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('omnibridge.media', true);
        $app['config']->set('filesystems.disks.omnibase-cache', ['driver' => 'local', 'root' => $this->root.'/cache']);
        $app['config']->set('omnibridge.storages', ['local.cache' => 'omnibase-cache']);
        $app['config']->set('base.obfuscator.cache_dir', $this->root.'/obfuscator');
        $app['config']->set('omnibridge.doctrine.entities', [self::FIXTURES.'/Omnibase/Entity']);
        $app['config']->set('omnibridge.doctrine.omnibase', ['project_dir' => self::FIXTURES.'/Omnibase']);
        $app['config']->set('base.database.use_custom', true);
        $app['config']->set('base.attributes.use_custom', true);
    }

    public function testTheCollectionsAndFallbacksAreApplied(): void
    {
        $compressions = array_keys($this->app->make(Obfuscator::class)->getCompressions());
        $this->assertSame([
            'Base\Service\Model\Obfuscator\Compression\NullCompression',
            'Base\Service\Model\Obfuscator\Compression\ZlibCompression',
            'Base\Service\Model\Obfuscator\Compression\GzipCompression',
            'Base\Service\Model\Obfuscator\Compression\DeflateCompression',
            'Base\Service\Model\Obfuscator\Compression\HashidsCompression',
        ], $compressions, 'the services tagged obfuscator.compression, in their order');
        $this->assertInstanceOf('Base\Routing\NullAdminUrlGenerator', $this->app->make('Base\Routing\AdminUrlGeneratorInterface'));
    }

    public function testTheEntityListenersListenToTheEventsTheRegistriesGive(): void
    {
        $events = $this->app->make(EntityManagerInterface::class)->getEventManager();
        // Each lazy listener's service, made (which proves it is built here from the core's declaration).
        $classes = static fn (string $event): array => array_values(array_map(
            static fn (LazyListener $listener): string => \get_class((fn () => ($this->factory)())->call($listener)),
            array_filter($events->getListeners($event), static fn (object $listener): bool => $listener instanceof LazyListener),
        ));
        foreach ([Events::preUpdate, Events::postFlush] as $event) {
            $this->assertContains('Base\EntityDispatcher\Event\UserEventDispatcher', $classes($event), $event);
            $this->assertContains('Base\EntityDispatcher\Event\ThreadEventDispatcher', $classes($event), $event);
        }
        $this->assertContains('Base\EntitySubscriber\ThreadSubscriber', $classes(Events::onFlush));
        $this->assertNotContains('Base\EntitySubscriber\UserSubscriber', $classes(Events::preFlush), 'no notifier: nothing of it');
    }
}
