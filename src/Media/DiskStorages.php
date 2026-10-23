<?php

namespace Omnibridge\Laravel\Media;

use Illuminate\Contracts\Filesystem\Factory as Disks;
use League\Flysystem\FilesystemOperator;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * omnibase's storages by name (local.cache, local.uploads...) as Laravel's
 * disks, for Base\Service\Flysystem (audit "lesringostars face au master
 * plan", R6): a PSR-11 container of each disk's Flysystem operator - the
 * disk omnibridge.storages names for a storage, else the disk of the same
 * name. A local disk is omnibase's local storage (its root, its files).
 * Omnidrive\Drives is read over it (OperatorDrives): each disk an omnidrive
 * drive by the storage's name.
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

    /**
     * The storages it gives, as a Symfony service locator lists them (what
     * Omnidrive\Flysystem\OperatorDrives reads their names from): those
     * omnibridge.storages maps, then every disk of config/filesystems.php.
     *
     * @return array<string, string>
     */
    public function getProvidedServices(): array
    {
        $disks = config('filesystems.disks', []);
        $names = [...array_keys($this->storages), ...array_keys(\is_array($disks) ? $disks : [])];

        return array_fill_keys(array_values(array_unique(array_map('strval', $names))), FilesystemOperator::class);
    }

    private function disk(string $id): string
    {
        return $this->storages[$id] ?? $id;
    }
}
