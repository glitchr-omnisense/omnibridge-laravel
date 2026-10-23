<?php

namespace Omnibridge\Laravel\Tests;

use Base\Service\Flysystem;
use Omnidrive\Drives;
use Omnidrive\Flysystem\FlysystemDrive;

/**
 * Laravel's disks as omnidrive's drives: Omnidrive\Drives by the storage
 * names omnibridge.storages maps (and the disks' own names), a file written
 * through a drive on the disk behind it, a local disk's files files of this
 * machine, a bucket's not; omnibase's Flysystem reads the same drives.
 */
final class DrivesTest extends TestCase
{
    private string $root;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $config = $app['config'];
        \assert($config instanceof \Illuminate\Contracts\Config\Repository);
        $this->root = sys_get_temp_dir().'/omnibridge-drives-'.bin2hex(random_bytes(4));
        $config->set('filesystems.disks', [
            'omnibase-cache' => ['driver' => 'local', 'root' => $this->root.'/cache'],
            'omnibase-uploads' => ['driver' => 'local', 'root' => $this->root.'/uploads'],
            'cloud' => ['driver' => 's3', 'bucket' => 'b', 'region' => 'eu-west-3', 'key' => 'k', 'secret' => 's'],
        ]);
        $config->set('omnibridge.storages', ['local.cache' => 'omnibase-cache', 'local.uploads' => 'omnibase-uploads', 's3.private' => 'cloud']);
        $config->set('omnibridge.media', true);
    }

    public function testTheDisksAreTheDrivesByTheStoragesNames(): void
    {
        $drives = $this->app?->make(Drives::class);
        self::assertInstanceOf(Drives::class, $drives);

        self::assertSame(['local.cache', 'local.uploads', 's3.private', 'omnibase-cache', 'omnibase-uploads', 'cloud'], $drives->names());
        $uploads = $drives->get('local.uploads');
        self::assertInstanceOf(FlysystemDrive::class, $uploads);
        $uploads->write('avatars/1.txt', 'ringo');
        $disks = $this->app?->make('filesystem');
        self::assertInstanceOf(\Illuminate\Contracts\Filesystem\Factory::class, $disks);
        self::assertSame('ringo', $disks->disk('omnibase-uploads')->get('avatars/1.txt'), 'the disk behind it');
        self::assertSame($this->root.'/uploads/avatars/1.txt', $uploads->localPath('avatars/1.txt'));
        self::assertTrue($uploads->capabilities()->local);
        if (class_exists(\League\Flysystem\AwsS3V3\AwsS3V3Adapter::class)) {
            self::assertFalse($drives->get('s3.private')->capabilities()->local);
        }
        self::assertFalse($drives->has('local.wysiwyg'));
        exec('rm -rf '.escapeshellarg($this->root));
    }

    public function testOmnibasesFlysystemReadsTheSameDrives(): void
    {
        $flysystem = $this->app?->make(Flysystem::class);
        self::assertInstanceOf(Flysystem::class, $flysystem);
        self::assertSame($this->app?->make(Drives::class), $flysystem->getDrives());
    }
}
