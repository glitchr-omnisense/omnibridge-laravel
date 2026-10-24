<?php

namespace Omnibridge\Laravel\Screen;

use Base\Screen\Answer;
use Base\Screen\File;
use Base\Screen\Frame;
use Base\Screen\Json;
use Base\Screen\Negotiation;
use Base\Screen\Redirect;
use Base\Screen\Shell;
use Base\Screen\Template;
use Base\Screen\View;
use Base\Screen\Visit;
use Illuminate\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * An action's result answered under Laravel: Laravel's adapter of omnibase's
 * Base\Screen\Negotiation (step R10 of the audit "lesringostars face au
 * master plan"; Base\Bridge\Symfony\ScreenResponder is Symfony's). What an
 * application's action hands back - a View, a Template, Json, a Shell, a
 * File, a Redirect, or null for no such page (404) - becomes Laravel's response: the
 * request read as a Visit, the Answer made a Response (a JsonResponse for
 * JSON data, a BinaryFileResponse for a file), its caching set.
 *
 * The templates are rendered by the bound Negotiation: by default the
 * container's Twig Environment for every kind; an application whose pages
 * carry more (its page context, what it adds once rendered) binds its own,
 * rendering Negotiation::PAGE, PART and ALONE each its way.
 *
 * A route runs an action written once for every host by action():
 *
 *     Route::get('/{destination}/articles/{slug}', Responder::action(Article::class))->name('app_article');
 *
 * The action's parameters are the route's by name, `$query` the query
 * string's values, `$body` what a POST sent (its JSON object, else its form
 * fields: step R29), its services the container's.
 */
final class Responder
{
    public function __construct(private readonly Negotiation $negotiation)
    {
    }

    /** @param array<string, mixed> $legacy a View's variables of before, for a site's own template */
    public function respond(Request $request, View|Template|Json|Shell|File|Redirect|null $result, array $legacy = [], ?Frame $frame = null): HttpResponse
    {
        if (null === $result) {
            throw new NotFoundHttpException();
        }

        return self::response($this->negotiation->answer($result, self::visit($request), $legacy, $frame));
    }

    /**
     * A route's action: the class (or a method of it) called with the route's
     * parameters by name, `query` the query string's values, `body` the
     * posted values, its other
     * parameters from the container; its result answered.
     *
     * @param class-string $class
     */
    public static function action(string $class, string $method = '__invoke'): \Closure
    {
        return static function (Request $request) use ($class, $method): HttpResponse {
            $app = Container::getInstance();
            $parameters = ($request->route()?->parameters() ?? []) + ['query' => $request->query(), 'body' => self::body($request)];

            /** @var View|Template|Json|Shell|File|Redirect|null $result an action's result, as its signature declares it */
            $result = $app->call([$app->make($class), $method], $parameters);

            return $app->make(self::class)->respond($request, $result);
        };
    }

    /**
     * What a request's body carries, as omnibase's ActionArguments gives it under Symfony: its JSON object
     * (a script's request), else its form fields.
     *
     * @return array<string, mixed>
     */
    public static function body(Request $request): array
    {
        $values = json_decode((string) $request->getContent(), true);

        return array_filter(\is_array($values) ? $values : $request->request->all(), '\is_string', \ARRAY_FILTER_USE_KEY);
    }

    /** What the client asked, off Laravel's request. */
    public static function visit(Request $request): Visit
    {
        return new Visit($request->getRequestUri(), (string) $request->headers->get('Accept', ''), $request->headers->get('X-Omnibridge'), $request->headers->get('X-Omnibridge-Version'));
    }

    /** Laravel's response of an Answer. */
    public static function response(Answer $answer): HttpResponse
    {
        if (null !== $answer->file) {
            $response = new BinaryFileResponse($answer->file, $answer->status, $answer->headers);
        } elseif ($answer->json) {
            $response = new JsonResponse($answer->body, $answer->status, $answer->headers, 0, true);
        } else {
            $response = new Response($answer->body, $answer->status, $answer->headers);
        }

        $cache = $answer->cache;
        if ($cache->vary) {
            $response->setVary($cache->vary, false);
        }
        if (false === $cache->shared) {
            $response->setPrivate();
        } elseif (true === $cache->shared) {
            $response->setPublic();
        }
        if (null !== $cache->maxAge) {
            $response->setMaxAge($cache->maxAge);
        }
        if ($cache->mustRevalidate) {
            $response->headers->addCacheControlDirective('must-revalidate');
        }
        if ($cache->noStore) {
            $response->headers->addCacheControlDirective('no-store');
        }

        return $response;
    }
}
