<?php

namespace Omnibridge\Laravel\Map;

use Omnimap\Exception\InvalidArgumentException;
use Omnimap\Snapshot\Snapshots;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A tile of a map's picture kept in omnimap's cache (MapServiceProvider's
 * tile route), as omnibridge/symfony's SnapshotController serves it: as
 * WebP when the route's path ends in .webp (fitted within cache.public_size;
 * GD with WebP, else Imagick, else as kept), public and cacheable
 * (cache.max_age); with cache.public_directory, written there too at the
 * route's own path, so that the web server serves it without PHP the next
 * time.
 */
final class TileController
{
    /** Seconds a tile is cached for by default (a day). */
    public const MAX_AGE = 86400;

    public function __construct(
        private readonly Snapshots $snapshots,
        private readonly string $tilePath = MapServiceProvider::ROUTES['tile']['path'],
        private readonly ?string $publicDirectory = null,
        private readonly ?int $publicSize = null,
        private readonly int $maxAge = self::MAX_AGE,
    ) {
    }

    public function tile(Request $request, string $signature, int|string $id = 0): Response
    {
        try {
            $bytes = $this->snapshots->store()->tile($signature, (int) $id);
        } catch (InvalidArgumentException) {
            $bytes = null;
        }
        if (null === $bytes) {
            throw new NotFoundHttpException('No such map tile.');
        }
        $format = $this->snapshots->store()->format();
        $type = 'image/'.('jpg' === $format ? 'jpeg' : $format);
        if (str_ends_with($this->tilePath, '.webp') && 'image/webp' !== $type && null !== ($webp = self::webp($bytes, $this->publicSize))) {
            $bytes = $webp;
            $type = 'image/webp';
        }
        if (null !== $this->publicDirectory) {
            $file = rtrim($this->publicDirectory, '/').str_replace(['{signature}', '{id}'], [$signature, (string) (int) $id], $this->tilePath);
            if (is_dir(\dirname($file)) || @mkdir(\dirname($file), 0775, true)) {
                @file_put_contents($file, $bytes);
            }
        }

        $response = new Response($bytes, 200, ['Content-Type' => $type]);
        $response->setPublic();
        $response->setMaxAge($this->maxAge);
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->setEtag(md5($bytes));
        $response->isNotModified($request);

        return $response;
    }

    /** The tile as WebP, fitted within $size, at quality 75 (omnibridge/symfony's): GD with WebP, else Imagick; null when neither can. */
    private static function webp(string $bytes, ?int $size): ?string
    {
        if (\function_exists('imagewebp')) {
            $image = @imagecreatefromstring($bytes);
            if (false === $image) {
                return null;
            }
            $width = imagesx($image);
            $height = imagesy($image);
            if (null !== $size && $size > 0 && max($width, $height) > $size) {
                $ratio = $size / max($width, $height);
                $scaled = imagescale($image, max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio)), \IMG_BICUBIC);
                $image = false === $scaled ? $image : $scaled;
            }
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            ob_start();
            imagewebp($image, null, 75);

            return (string) ob_get_clean();
        }
        if (class_exists(\Imagick::class) && [] !== \Imagick::queryFormats('WEBP')) {
            try {
                $image = new \Imagick();
                $image->readImageBlob($bytes);
                if (null !== $size && $size > 0 && max($image->getImageWidth(), $image->getImageHeight()) > $size) {
                    $image->thumbnailImage($size, $size, true);
                }
                $image->setImageFormat('webp');
                $image->setImageCompressionQuality(75);

                return $image->getImageBlob();
            } catch (\ImagickException) {
                return null;
            }
        }

        return null;
    }
}
