<?php

namespace Omnibridge\Laravel\Media;

use Illuminate\Contracts\Filesystem\Factory as Disks;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * omnibase's storages by name (local.cache, local.uploads...) as Laravel's
 * disks, for Base\Service\Flysystem (audit "lesringostars face au master
 * plan", R6): a PSR-11 container of each disk's Flysystem operator - the
 * disk omnibridge.storages names for a storage, else the disk of the same
 * name. A local disk is omnibase's local storage (its root, its files).
 */
final class DiskStorages implements ContainerInterface
{
    /** @param array<string, string> $storages omnibase's storage => Laravel's disk */
    public function __construct(private readonly Disks $disks, private readonly array $storages = [])
    {
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new class(\sprintf('No storage "%s": omnibridge.storages names no disk for it, and config/filesystems.php has no disk of that name.', $id)) extends \InvalidArgumentException implements NotFoundExceptionInterface {
            };
        }

        return $this->disks->disk($this->disk($id))->getDriver();
    }

    public function has(string $id): bool
    {
        return null !== config('filesystems.disks.'.$this->disk($id));
    }

    private function disk(string $id): string
    {
        return $this->storages[$id] ?? $id;
    }
}
