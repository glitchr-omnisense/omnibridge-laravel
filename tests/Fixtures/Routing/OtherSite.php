<?php

namespace Omnibridge\Laravel\Tests\Fixtures\Routing;

use Omnibridge\Contract\Routes;
use Omnibridge\Contract\Urls;

/** Another site's routes (lesringostars' Symfony site for its twin): base.router.fallback's service. */
final class OtherSite implements Routes, Urls
{
    public function path(string $name, array $parameters = []): string
    {
        return $this->url($name, $parameters);
    }

    public function url(string $name, array $parameters = []): string
    {
        return 'https://other.example/'.$name;
    }

    public function asset(string $path): string
    {
        return 'https://other.example/'.ltrim($path, '/');
    }

    public function pattern(string $name): ?string
    {
        return 'elsewhere_only' === $name ? '/elsewhere' : null;
    }

    public function match(string $url): ?string
    {
        return '/elsewhere' === $url ? 'elsewhere_only' : null;
    }

    public function icons(string $name): ?array
    {
        return 'elsewhere_only' === $name ? ['fa-solid fa-globe'] : null;
    }
}
