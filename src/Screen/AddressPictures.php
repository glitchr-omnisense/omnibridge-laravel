<?php

namespace Omnibridge\Laravel\Screen;

use Base\Contract\Pictures;
use Base\Service\MediaService;
use Omnibridge\Contract\Urls;

/**
 * omnibase's Pictures contract under Laravel: what its `|picture` filter
 * answers (CommonsTwigExtension::picture()) - a file under the public folder
 * by its thumbnail at the size asked, MediaService's (omnibridge.media on,
 * audit R6), or by its public path without it; an address or a path as it
 * is; anything else an asset of the build (assets/...). An application that
 * serves its stored pictures itself says where (stored()), and that answer
 * comes first.
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
        private readonly ?MediaService $media = null,
    ) {
    }

    /**
     * The same pictures, a stored one at the address $stored gives (null: the rule below).
     *
     * @param \Closure(string $image, ?int $width, ?int $height): ?string $stored
     */
    public function stored(\Closure $stored): self
    {
        return new self($this->urls, $this->publicDir, $stored, $this->media);
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
            $thumbnail = $this->media?->thumbnail($image, $width, $height);

            return \is_string($thumbnail) ? $thumbnail : substr($image, \strlen($this->publicDir));
        }
        if (str_starts_with($image, '/') || preg_match('#^https?://#', $image)) {
            return $image;
        }

        return $this->urls->asset('assets/'.$image);
    }
}
