<?php

namespace Omnibridge\Laravel\Contract;

use Illuminate\Contracts\Filesystem\Factory as Disks;
use Omnibridge\Contract\Files;

/**
 * omnibase's storages as Laravel's disks: a storage ("local.uploads",
 * "s3.private"...) is the disk the configuration maps it to
 * (omnibridge.storages), else the disk of that name, else the default disk.
 * A local disk is a file on this machine; one with an address (url, as the
 * public disk has) is served, from the public folder when it is local (the
 * storage:link).
 */
final class DiskFiles implements Files
{
    /** @param array<string, string> $storages omnibase's storage name => Laravel's disk name */
    public function __construct(private readonly Disks $disks, private readonly array $storages = [])
    {
    }

    public function publicPath(string $path, ?string $storage = null): ?string
    {
        $url = $this->publicUrl($path, $storage);
        if (null === $url || $this->isRemote($storage)) {
            return $url;
        }

        return public_path(ltrim((string) parse_url($url, \PHP_URL_PATH), '/'));
    }

    public function publicUrl(string $path, ?string $storage = null): ?string
    {
        $name = $this->disk($storage);
        if (null === config("filesystems.disks.$name.url") && 'local' === $this->driver($storage)) {
            return null;
        }
        $disk = $this->disks->disk($name);
        if (!$disk->exists($path)) {
            return null;
        }
        $url = $disk->url($path);

        return $this->isRemote($storage) ? $url : (string) (parse_url($url, \PHP_URL_PATH) ?? $url);
    }

    public function localPath(string $path, ?string $storage = null): ?string
    {
        return $this->isRemote($storage) ? null : $this->disks->disk($this->disk($storage))->path($path);
    }

    public function isRemote(?string $storage = null): bool
    {
        return 'local' !== $this->driver($storage);
    }

    private function disk(?string $storage): string
    {
        if (null !== $storage && isset($this->storages[$storage])) {
            return $this->storages[$storage];
        }
        if (null !== $storage && null !== config("filesystems.disks.$storage")) {
            return $storage;
        }

        return $this->storages['default'] ?? (string) config('filesystems.default');
    }

    private function driver(?string $storage): string
    {
        return (string) config('filesystems.disks.'.$this->disk($storage).'.driver', 'local');
    }
}
