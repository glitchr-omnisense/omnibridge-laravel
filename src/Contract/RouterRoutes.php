<?php

namespace Omnibridge\Laravel\Contract;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Omnibridge\Contract\Routes;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * What Laravel's routes are beyond their addresses: the path a route is
 * declared with ("/articles/{slug}"), the route an address leads to (GET),
 * and its icons - none of Laravel's own: omnibase's #[Iconize] is read on
 * Symfony's controllers; where the routes are also answered elsewhere
 * (elsewhere()), a route's icons are the ones declared there.
 */
final class RouterRoutes implements Routes
{
    public function __construct(private readonly Router $router, private readonly ?Routes $elsewhere = null)
    {
    }

    /**
     * The same routes, one Laravel does not have answered by $elsewhere (an
     * application serving part of a site: the other part's routes, as
     * RouterUrls::elsewhere() gives their addresses) - its pattern, its
     * icons.
     */
    public function elsewhere(Routes $elsewhere): self
    {
        return new self($this->router, $elsewhere);
    }

    public function pattern(string $name): ?string
    {
        $route = $this->router->getRoutes()->getByName($name);

        return null === $route ? $this->elsewhere?->pattern($name) : '/'.ltrim($route->uri(), '/');
    }

    public function match(string $url): ?string
    {
        try {
            return $this->router->getRoutes()->match(Request::create($url))->getName();
        } catch (HttpException) {
            return null;
        }
    }

    public function icons(string $name): ?array
    {
        // Laravel's own routes declare none: a route's icons are its declaration's elsewhere, served here or not.
        return $this->elsewhere?->icons($name);
    }
}
