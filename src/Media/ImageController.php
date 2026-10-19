<?php

namespace Omnibridge\Laravel\Media;

use Base\Service\ImageRoute;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * omnibase's image route under Laravel (audit "lesringostars face au master
 * plan", R6): each route of Base\Service\ImageRoute::ROUTES, declared under
 * its name, is answered by ImageRoute's action for it - the route's
 * parameters (data, identifier, extension) given by name, the cacheless
 * routes without the local cache. The answer is ImageRoute's HttpFoundation
 * response, which Laravel sends as it is; an image that does not resolve is
 * its NotFoundHttpException, a 404.
 */
final class ImageController
{
    public function __construct(private readonly ImageRoute $route)
    {
    }

    public function __invoke(Request $request): Response
    {
        $route = $request->route();
        [, $action, $cacheless] = ImageRoute::ROUTES[$route->getName()] ?? throw new \LogicException(\sprintf('"%s" is not an image route.', $route->getName()));
        $parameters = array_intersect_key($route->parameters(), ['data' => true, 'identifier' => true, 'extension' => true]);

        return ($cacheless ? $this->route->cacheless() : $this->route)->{$action}(...$parameters);
    }
}
