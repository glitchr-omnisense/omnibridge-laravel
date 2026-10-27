---
title: Routing and actions
order: 30
---

# Routing and actions

## Actions: Screen\Responder

An action written once for every host (no Request, no Response: its inputs, and a result of omnibase's -
`Base\Screen\Template`, `Json`, `Shell`, `File`, `Redirect`, a `View`, or null for no such page) is a
route here, answered by omnibase's `Base\Screen\Negotiation` (HTML, JSON when the template allows it and
the client prefers it, omnibridge/react's protocol and its 409) through `Screen\Responder`, as
`Base\Bridge\Symfony\ScreenResponder` answers it under Symfony.

```php
use Omnibridge\Laravel\Screen\Responder;

Route::get('/{destination}/articles/{slug}', Responder::action(App\Action\Blog\Article::class))->name('app_article');
Route::get('/feed', Responder::action(App\Action\Feed::class, 'page'));
```

| Method | What it does |
|---|---|
| `Responder::action(string $class, string $method = '__invoke'): \Closure` | a route's handler: the action made by the container and called (`$app->call()`) with the route's parameters by name, `query` (the query string's values) and `body` (`Responder::body()`); the other parameters from the container; its result answered |
| `respond(Request $request, $result, array $legacy = [], ?Frame $frame = null)` | the result answered; null is a 404 (`NotFoundHttpException`) |
| `Responder::body(Request $request): array` | the request's JSON object, else its form fields, string keys only |
| `Responder::visit(Request $request): Visit` | what the client asked: the URI, `Accept`, `X-Omnibridge`, `X-Omnibridge-Version` |
| `Responder::response(Answer $answer)` | Laravel's response of omnibase's answer: a `BinaryFileResponse` for a file, a `JsonResponse` for JSON (the body already encoded), a `Response` otherwise; `Vary`, private/public, `max-age`, `must-revalidate`, `no-store` from the answer's cache |

An action that changes something checks its form's token itself, through the `Tokens` contract, as
under Symfony: Laravel's CSRF middleware is left off the routes of the actions that are not GET
([below](#routes-from-the-attributes-actionroutes)). The templates are rendered by the bound
`Negotiation`: the container's Twig for every kind. An application whose pages carry more (a page
context, what it adds once rendered) binds its own `Base\Screen\Negotiation`, rendering
`Negotiation::PAGE` (a page), `PART` (a value of a JSON answer) and `ALONE` (a page whole, its own
markup only) each its way. `omnibridge.screens.version` is the front end's version omnibridge/react's
protocol compares (null: none).

## Routes from the attributes: ActionRoutes

The routes an application's actions declare - symfony/routing's `#[Route]` on the action, the
declaration Symfony's router reads on the same class - are registered here by `Routing\ActionRoutes`, by
symfony/routing's attribute loader's rules (not the loader itself, which stands on symfony/config).

```php
use Omnibridge\Laravel\Routing\ActionRoutes;

// routes/web.php: the application's actions and omnibase's.
ActionRoutes::register(app('router'), ActionRoutes::load([
    'App\\Action\\' => base_path('src/Action'),
    'Base\\Controller\\Action\\' => base_path('vendor/omnisense/omnibase/src/Controller/Action'),
], ActionRoutes::environment(app()->environment())));
```

| Method | What it does |
|---|---|
| `load(array $folders, ?string $env = null): RouteCollection` | namespace prefix => folder (PSR-4); every non-abstract class's routes, in Symfony's order (by priority, then as declared); a route declared for other environments (`env:` on the attribute) left out |
| `environment(string $laravel): string` | `local` => `dev`, `testing` => `test`, `production` => `prod` (`ActionRoutes::ENVIRONMENTS`), anything else as it is |
| `register(Router $router, RouteCollection $routes): void` | each route in Laravel's router, in the collection's order |
| `action(Route $route): array` | `[class, method]` of a route's `_controller`; `InvalidArgumentException` when it names none or no class |

As symfony/routing's loader reads them:

- a method's `#[Route]` under its class's (prefix, name prefix, requirements, defaults, methods, host,
  priority, environments); an invokable class's own `#[Route]` when its methods declare none;
- a name the attribute does not give: the class name lower-cased with `_`, then `_<method>`;
- a path per language: one route per language, `name.fr`, with `_locale` (and its requirement) and
  `_canonical_route`; a localized class prefix needs a path for each language (`LogicException`);
- the defaults of the action's parameters set on the variables of their name;
- `_controller` as FrameworkBundle names an action: the class for `__invoke`, `Class::method` otherwise.

Registered in Laravel: the methods declared, or GET and HEAD for a route that names none; a trailing
variable with a default made optional (`{page?}`); the requirements of the path's variables as `where()`;
the defaults `_locale` and the optional variables'; the host as `domain()`; the CSRF middleware
(`PreventRequestForgery`, `ValidateCsrfToken`, `VerifyCsrfToken`) left off a route with a method other
than GET, HEAD, OPTIONS. Each route runs `Responder::action()`. `Http\Localize` takes a route's
`_locale` for the page's language.

## Localized names: LocalizedNames

`LocalizedNames::resolve(string $name, string $locale, iterable $names, string $fallbackLocale = 'en'): ?string`
- the route a name means in a language, as omnibase's router reads it: `name.<lang>`, `name`,
`name.default.<lang>`, `name.default`, then the first of the routes, in their order, that is
`name.<fallback>` or `name.default.<fallback>` (a German page links to `/japon/travels`). Used by
`RouterUrls::localized()`.

## The language: Http\Localize, Http\SwitchLocale

omnibase's language under Laravel, as its LocalizerSubscriber and LocalizerController give it under
Symfony.

`Http\Localize`, the web group's middleware (`bootstrap/app.php`: `$middleware->web(append:
[Localize::class])`, `LANG` left out of the encrypted cookies):

1. a route of one language (`_locale`) is that language's;
2. else the `LANG` cookie's language when it is one of `omnibridge.locales.available`;
3. else the browser's preferred language among them (`getPreferredLanguage()`);
4. else `omnibridge.locales.default`.

Laravel's locale is set to the two letters. After the page, the language shown is written back to
`LANG` as the site names it (`en-GB`), session cookie, not HTTP only, SameSite Lax - unless the page set
`LANG` itself.

> [!WARNING]
> Known defect: the fourth step reads `config('omnibridge.locales.default', config('app.locale'))`, but
> the merged configuration always has the key (null by default), so `app.locale` is never the fallback:
> with no available locale configured and no browser language, Laravel's locale is set to `''`, where
> `app.locale` is expected.

`Http\SwitchLocale`, the language menu's links:

```php
Route::get('/locale/{_locale?}', Omnibridge\Laravel\Http\SwitchLocale::class)->name('_switch_locale');
```

A language of the site's: the choice kept in `LANG` as Symfony writes it (`en_GB`), back to the page the
visitor came from on this site (its path), else `/`. No language: the choice forgotten, back. A language
the site does not speak: the choice forgotten, the home.

## Bindings from the attributes: Container\Aliases

The services an application aliases with Symfony's `#[AsAlias]` (read by the attribute's name: no
symfony/dependency-injection needed), bound as Symfony's container aliases them:

```php
use Omnibridge\Laravel\Container\Aliases;

// a service provider's register()
Aliases::bind($this->app, Aliases::read(['App\\Repository\\' => base_path('src/Repository')]));
```

| Method | What it does |
|---|---|
| `read(array $folders): array` | interface => class for every `#[AsAlias]` of the folders' non-abstract classes, in the order of their files |
| `of(string $class): array` | the interfaces a class is the alias of: the attribute's `id`, else the class's only interface (`LogicException` when it has none or several, or names no interface nor class) |
| `bind(Container $container, array $aliases): void` | each as a singleton resolving to the container's instance of the class |

With omnibase's mapping on, the core's repositories are bound by their interfaces from omnibase's own
list (`Base\Repository\Aliases::INTERFACES`), and a repository class asked of the container is the
entity manager's repository of the entity that names it ([Doctrine and images](doctrine-media.md)).
