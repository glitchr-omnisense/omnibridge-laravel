<?php

namespace Omnibridge\Laravel\Media;

use Base\Contract\Media;
use Base\Service\MediaService;

/**
 * omnibase's Media contract under Laravel (audit "lesringostars face au
 * master plan", R6): MediaService's answers, as Base\Bridge\Symfony\ServiceMedia
 * gives them under Symfony - the extension of a file, the image route's
 * obfuscated data, the route's address -, MediaService built on Laravel's
 * router (LaravelMediaRouter).
 */
final class ServiceMedia implements Media
{
    public function __construct(private readonly MediaService $media)
    {
    }

    public function extension(?string $path): ?string
    {
        return $this->media->getExtension($path);
    }

    public function obfuscate(?string $path, array $config = [], array $filters = []): ?string
    {
        return $this->media->obfuscate($path, $config, $filters);
    }

    public function link(string $route, array $parameters, ?string $path, array $config = []): ?string
    {
        return $this->media->generate($route, $parameters, $path, $config);
    }
}
