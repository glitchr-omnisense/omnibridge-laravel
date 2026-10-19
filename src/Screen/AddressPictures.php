<?php

namespace Omnibridge\Laravel\Screen;

use Base\Contract\Pictures;
use Omnibridge\Contract\Urls;

/**
 * omnibase's Pictures contract under Laravel: what its `|picture` filter
 * answers, but for the thumbnails - a file under the public folder by its
 * public path, an address or a path as it is, anything else an asset of the
 * build (assets/...). Laravel has no image route to make a thumbnail of a
 * stored picture at a size: an application that serves its stored pictures
 * says where (stored()), and that answer comes first.
 */
final class AddressPictures implements Pictures
{
    /**
     * @param (\Closure(string $image, ?int $width, ?int $height): ?string)|null $stored the address the application serves a stored picture at, null for none
     */
    public function __construct(
        private readonly Urls $urls,
        private readonly string $publicDir = '',
        private readonly ?\Closure $stored = null,
    ) {
    }

    /**
     * The same pictures, a stored one at the address $stored gives (null: the rule below).
     *
     * @param \Closure(string $image, ?int $width, ?int $height): ?string $stored
     */
    public function stored(\Closure $stored): self
    {
        return new self($this->urls, $this->publicDir, $stored);
    }

    public function picture(?string $image, ?int $width = null, ?int $height = null): ?string
    {
        if (null === $image || '' === $image) {
            return null;
        }
        if (null !== $this->stored && null !== $address = ($this->stored)($image, $width, $height)) {
            return $address;
        }
        if ('' !== $this->publicDir && str_starts_with($image, $this->publicDir.'/')) {
            return substr($image, \strlen($this->publicDir));
        }
        if (str_starts_with($image, '/') || preg_match('#^https?://#', $image)) {
            return $image;
        }

        return $this->urls->asset('assets/'.$image);
    }
}
