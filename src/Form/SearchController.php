<?php

namespace Omnibridge\Laravel\Form;

use Base\Field\Search\RemoteSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * omnibase's remote search under Laravel (plan "omnibase agnostic", step F5d): each route of
 * Base\Field\Search\RemoteSearch::ROUTES, declared under its Symfony name and path, is answered
 * by RemoteSearch's action for it - a select's records (ux_autocomplete), an icon picker's icons
 * (ux_autocomplete_icons) -, the request's method, query and body handed as they are. The answer
 * is RemoteSearch's, sent as JSON with its status: a token that is not the visitor's, a method the
 * environment does not take, an unknown payload are refused as omnibase's AutocompleteController
 * refuses them.
 *
 * In the web group (the session holds the token the payload carries) without Laravel's CSRF
 * middleware: the search checks that token itself, as Symfony's controller does.
 */
final class SearchController
{
    public function __construct(private readonly RemoteSearch $search)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $route = $request->route();
        [, $action] = RemoteSearch::ROUTES[$route->getName()] ?? throw new \LogicException(\sprintf('"%s" is not a search route.', $route->getName()));
        $answer = 'icons' === $action
            ? $this->search->icons((string) $route->parameter('provider'), (int) $route->parameter('pageSize'), (string) $route->parameter('data'), $request->getMethod(), $request->query->all(), $request->request->all())
            : $this->search->records((string) $route->parameter('data'), $request->getMethod(), $request->query->all(), $request->request->all());

        return new JsonResponse($answer->body, $answer->status);
    }
}
