<?php

namespace Omnibridge\Laravel\Page;

use Illuminate\Http\Request;

/**
 * app.request with the words of Symfony's Request: getLocale(), uri,
 * headers.get(), attributes.get('_route').
 */
final class RequestView
{
    public readonly Bag $headers;

    public readonly Bag $attributes;

    public function __construct(private readonly Request $request, private readonly string $locale)
    {
        $this->headers = new Bag(static fn (string $key) => $request->headers->get($key));
        // As Symfony's request attributes: the route's name and parameters, then the request's own.
        $this->attributes = new Bag(static function (string $key) use ($request): mixed {
            $route = ($request->getRouteResolver())();
            if (!$route instanceof \Illuminate\Routing\Route) {
                return '_route_params' === $key ? [] : $request->attributes->get($key);
            }

            return match (true) {
                '_route' === $key => $route->getName(),
                '_route_params' === $key => $route->parameters(),
                $route->hasParameter($key) => $route->parameter($key),
                default => $request->attributes->get($key),
            };
        });
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getUri(): string
    {
        // Symfony's (the site's root with its slash, the query normalized), not Laravel's url(), which drops it.
        return $this->request->getUri();
    }

    public function getPathInfo(): string
    {
        return $this->request->getPathInfo();
    }
}
