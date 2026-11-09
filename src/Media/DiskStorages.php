<?php

namespace Omnibridge\Laravel\Media;

use Illuminate\Contracts\Filesystem\Factory as Disks;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * omnibase's storages by name (local.cache, local.uploads...) as Laravel's
 * disks, for Base\Service\Flysystem (audit "lesringostars face au master
 * plan", R6): a PSR-11 container of each disk's Flysystem operator - the
 * disk omnibridge.storages names for a storage, else the disk of the same
 * name, else, for a storage omnibase declares as local (Base\Discovery's
 * `storages`: local.cache, local.uploads, local.wysiwyg and their .public),
 * a local operator on the folder it declares, as omnibridge/symfony registers
 * one where the application names none (the core's declaration compiled, its
 * placeholders resolved by the provider). A local disk is omnibase's local
 * storage (its root, its files). Omnidrive\Drives is read over it
 * (OperatorDrives): each disk an omnidrive drive by the storage's name.
 */
final class DiskStorages implements ContainerInterface
{
    /** @var array<string, FilesystemOperator> */
    private array $declared = [];

    /**
     * @param array<string, string> $storages omnibase's storage => Laravel's disk
     * @param array<string, string> $local    omnibase's local storage => its folder, as the core declares it (resolved)
     */
    public function __construct(private readonly Disks $disks, private readonly array $storages = [], private readonly array $local = [])
    {
    }

    public function get(string $id): mixed
    {
        if ($this->hasDisk($id)) {
            return $this->disks->disk($this->disk($id))->getDriver();
        }
        if ($this->isDeclared($id)) {
            // As omnibridge/symfony's: no lock, links refused; the folder made when first written to.
            return $this->declared[$id] ??= new Filesystem(new LocalFilesystemAdapter($this->local[$id], null, 0, LocalFilesystemAdapter::DISALLOW_LINKS, null, true));
        }

        throw new class(\sprintf('No storage "%s": omnibridge.storages names no disk for it, config/filesystems.php has no disk of that name, and omnibase declares no local storage by it.', $id)) extends \InvalidArgumentException implements NotFoundExceptionInterface {
        };
    }

    public function has(string $id): bool
    {
        return $this->hasDisk($id) || $this->isDeclared($id);
    }

    /**
     * The storages it gives, as a Symfony service locator lists them (what
     * Omnidrive\Flysystem\OperatorDrives reads their names from): those
     * omnibridge.storages maps, then every disk of config/filesystems.php,
     * then omnibase's local storages that neither gives.
     *
     * @return array<string, string>
     */
    public function getProvidedServices(): array
    {
        $disks = config('filesystems.disks', []);
        $names = [...array_keys($this->storages), ...array_keys(\is_array($disks) ? $disks : []), ...array_keys(array_filter($this->local, fn (string $folder, string $storage): bool => $this->isDeclared($storage), \ARRAY_FILTER_USE_BOTH))];

        return array_fill_keys(array_values(array_unique(array_map('strval', $names))), FilesystemOperator::class);
    }

    private function hasDisk(string $id): bool
    {
        return null !== config('filesystems.disks.'.$this->disk($id));
    }

    /** A local storage of omnibase's that the application leaves to its declaration: mapped to no disk, no disk of its name. */
    private function isDeclared(string $id): bool
    {
        return isset($this->local[$id]) && !isset($this->storages[$id]) && !$this->hasDisk($id);
    }

    private function disk(string $id): string
    {
        return $this->storages[$id] ?? $id;
    }
}
