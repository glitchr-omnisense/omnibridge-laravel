<?php

namespace Omnibridge\Laravel\Media;

use Base\Contract\MediaRouter;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Omnibridge\Contract\Urls;

/**
 * Base\Contract\MediaRouter over Laravel's router (audit "lesringostars face
 * au master plan", R6): what omnibase's MediaService asks a router, for the
 * image routes this package declares under their Symfony names
 * (Base\Service\ImageRoute::ROUTES) - a named route's address, the route and
 * parameters an address matches, a public file's address (the Urls
 * contract's asset()).
 *
 * The reference types are Symfony's: 0 an absolute URL, 1 an absolute path,
 * 3 a network path; a relative path (2) is given as an absolute path.
 */
final class LaravelMediaRouter implements MediaRouter
{
    public function __construct(
        private readonly Router $router,
        private readonly UrlGenerator $urls,
        private readonly Urls $assets,
    ) {
    }

    public function generate(string $name, array $parameters = [], int $referenceType = 1): string
    {
        $parameters = array_filter($parameters, static fn ($value) => null !== $value);
        $url = $this->urls->route($name, $parameters, 0 === $referenceType || 3 === $referenceType);

        return 3 === $referenceType ? (string) preg_replace('#^[a-z][a-z0-9+.-]*:#i', '', $url) : $url;
    }

    /**
     * The first route an address matches, in the order they were declared
     * (fallback routes last, as Laravel matches them), on a copy of the route:
     * the matched route of the request being served keeps its own parameters.
     */
    public function getRouteMatch(?string $routeUrl = null): ?array
    {
        if (null === $routeUrl || '' === $routeUrl) {
            return null;
        }
        $path = parse_url($routeUrl, \PHP_URL_PATH);
        if (!\is_string($path) || '' === $path) {
            return null;
        }

        $request = Request::create($path);
        $fallback = null;
        foreach ($this->router->getRoutes()->get('GET') as $route) {
            if (!$route->matches($request)) {
                continue;
            }
            if ($route->isFallback) {
                $fallback ??= $route;
                continue;
            }

            return self::match($route, $request);
        }

        return null === $fallback ? null : self::match($fallback, $request);
    }

    public function getRouteName(?string $routeUrl = null): ?string
    {
        return $this->getRouteMatch($routeUrl)['_route'] ?? null;
    }

    public function getAssetUrl(string $nameOrUrl, ?string $packageName = null): string
    {
        return $this->assets->asset($nameOrUrl);
    }

    public function getUrl(?string $nameOrUrl = null, array $parameters = [], int $referenceType = 1): string
    {
        $nameOrUrl ??= '/';
        if ($this->router->getRoutes()->hasNamedRoute($nameOrUrl)) {
            return $this->generate($nameOrUrl, $parameters, $referenceType);
        }

        return 0 === $referenceType && str_starts_with($nameOrUrl, '/') ? $this->urls->to($nameOrUrl) : $nameOrUrl;
    }

    /** @return array<string, mixed> */
    private static function match(\Illuminate\Routing\Route $route, Request $request): ?array
    {
        $route = (clone $route)->bind($request);

        return null === $route->getName() ? null : ['_route' => $route->getName()] + $route->parameters();
    }
}
