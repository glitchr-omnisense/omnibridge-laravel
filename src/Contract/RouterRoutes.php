<?php

namespace Omnibridge\Laravel\Contract;

use Base\Contract\Routes;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * What Laravel's routes are beyond their addresses: the path a route is
 * declared with ("/articles/{slug}"), the route an address leads to (GET),
 * and its icons - none: omnibase's #[Iconize] is read on Symfony's
 * controllers.
 */
final class RouterRoutes implements Routes
{
    public function __construct(private readonly Router $router)
    {
    }

    public function pattern(string $name): ?string
    {
        $route = $this->router->getRoutes()->getByName($name);

        return null === $route ? null : '/'.ltrim($route->uri(), '/');
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
        return null;
    }
}
