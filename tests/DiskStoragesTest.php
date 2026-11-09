<?php

namespace Omnibridge\Laravel\Tests;

use Base\Service\Flysystem;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory;
use League\Flysystem\FilesystemOperator;
use Omnibridge\Laravel\Media\DiskStorages;
use Psr\Container\NotFoundExceptionInterface;

/**
 * omnibase's storages on Laravel's disks (Media\DiskStorages): a storage omnibridge.storages maps is that disk, else
 * the disk of its name, else - for a local storage omnibase declares (Base\Discovery's `storages`) - a local operator on
 * the folder the core declares, as omnibridge/symfony registers one where the application names none; any other name
 * is refused. With omnibase's media on and only its cache and uploads mapped, the editor's storage (local.wysiwyg, which
 * a file or an image field reads) is there.
 */
final class DiskStoragesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/omnibridge-laravel-storages-'.getmypid();
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
        $config = $app['config'];
        \assert($config instanceof Repository);
        $root = sys_get_temp_dir().'/omnibridge-laravel-storages-'.getmypid();
        $config->set('filesystems.disks.omnibase-uploads', ['driver' => 'local', 'root' => $root.'/disk-uploads']);
        $config->set('filesystems.disks.local.cache.public', ['driver' => 'local', 'root' => $root.'/disk-cache-public']);
        if ('testOmnibasesFlysystemHasTheEditorsStorageWithOnlyTheCacheAndTheUploadsMapped' === $this->name()) {
            // omnibase's media on, as FieldConformanceTest has them: its Flysystem bound on the storages.
            $config->set('omnibridge.media', true);
            $config->set('filesystems.disks.omnibase-cache', ['driver' => 'local', 'root' => $root.'/disk-cache']);
            $config->set('omnibridge.storages', ['local.cache' => 'omnibase-cache', 'local.uploads' => 'omnibase-uploads']);
            $config->set('base.obfuscator.cache_dir', $root.'/obfuscator');
        }
    }

    private function application(): \Illuminate\Foundation\Application
    {
        \assert(null !== $this->app);

        return $this->app;
    }

    private static function operator(mixed $storage): FilesystemOperator
    {
        \assert($storage instanceof FilesystemOperator);

        return $storage;
    }

    private function storages(): DiskStorages
    {
        $disks = $this->application()->make('filesystem');
        \assert($disks instanceof Factory);

        return new DiskStorages($disks, ['local.uploads' => 'omnibase-uploads'], [
            'local.uploads' => $this->root.'/declared-uploads',
            'local.cache.public' => $this->root.'/declared-cache-public',
            'local.wysiwyg' => $this->root.'/declared-wysiwyg',
        ]);
    }

    public function testADeclaredLocalStorageIsItsFolderWhereNoDiskIsGiven(): void
    {
        $storages = $this->storages();

        self::assertTrue($storages->has('local.wysiwyg'));
        self::assertDirectoryDoesNotExist($this->root.'/declared-wysiwyg', 'made when first written to, not before');
        self::operator($storages->get('local.wysiwyg'))->write('a.txt', 'quill');
        self::assertSame('quill', file_get_contents($this->root.'/declared-wysiwyg/a.txt'));
        self::assertSame($storages->get('local.wysiwyg'), $storages->get('local.wysiwyg'), 'one operator a storage');
    }

    public function testADiskTheApplicationGivesWinsOverTheDeclaration(): void
    {
        $storages = $this->storages();

        // Mapped by omnibridge.storages.
        self::operator($storages->get('local.uploads'))->write('b.txt', 'mapped');
        self::assertFileExists($this->root.'/disk-uploads/b.txt');
        self::assertDirectoryDoesNotExist($this->root.'/declared-uploads');
        // A disk of the storage's own name.
        self::operator($storages->get('local.cache.public'))->write('c.txt', 'named');
        self::assertFileExists($this->root.'/disk-cache-public/c.txt');
        self::assertDirectoryDoesNotExist($this->root.'/declared-cache-public');
    }

    public function testAnyOtherNameIsRefused(): void
    {
        $storages = $this->storages();

        self::assertFalse($storages->has('s3.private'));
        $this->expectException(NotFoundExceptionInterface::class);
        $storages->get('s3.private');
    }

    public function testTheProvidedStoragesListTheDeclaredOnesLast(): void
    {
        $names = array_keys($this->storages()->getProvidedServices());

        self::assertSame(['local.uploads', 'local.wysiwyg'], array_values(array_intersect($names, ['local.uploads', 'local.wysiwyg'])));
        self::assertSame('local.wysiwyg', end($names));
    }

    public function testOmnibasesFlysystemHasTheEditorsStorageWithOnlyTheCacheAndTheUploadsMapped(): void
    {
        if (!class_exists(\Base\Discovery::class)) {
            self::markTestSkipped('omnisense/omnibase 4.x: no Base\Discovery, its storages are the application\'s disks only.');
        }
        $flysystem = $this->application()->make(Flysystem::class);

        self::assertTrue($flysystem->hasStorage('local.wysiwyg'));
        self::assertTrue($flysystem->hasStorage('local.wysiwyg.public'));
    }
}
