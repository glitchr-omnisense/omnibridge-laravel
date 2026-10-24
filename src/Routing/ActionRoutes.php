<?php

namespace Omnibridge\Laravel\Routing;

use Illuminate\Routing\Router;
use Omnibridge\Laravel\Screen\Responder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Loader\AttributeClassLoader;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The routes an application's actions declare, registered in Laravel (step
 * R27 of the audit "lesringostars face au master plan"): the same #[Route]
 * attributes of symfony/routing that Symfony's router reads on the same
 * classes - one declaration, both hosts -, read by symfony/routing's own
 * attribute loader, so that names, localized paths ("app_timeline.fr" with
 * its `_locale`), requirements, defaults, priorities and environments are
 * the ones Symfony gives them.
 *
 *     ActionRoutes::register(app('router'), ActionRoutes::load([base_path('src/Action')], 'dev'));
 *
 * Each route runs its action through Screen\Responder::action() (the
 * route's parameters by name, the query string, the services); its
 * `_controller` is the action as Symfony's FrameworkBundle names it (the
 * class for an invokable one, "Class::method" otherwise). The routes are
 * registered in the order Symfony matches them (by priority, then as
 * declared): an address answers with the same route on both hosts.
 *
 * A route that names no method answers GET and HEAD here: an action a host
 * shares is read; posting a form is the Symfony host's (D3).
 */
final class ActionRoutes
{
    /** Laravel's environments by the name Symfony gives them (a route's `env`). */
    public const ENVIRONMENTS = ['local' => 'dev', 'testing' => 'test', 'production' => 'prod'];

    /**
     * The routes the classes of these folders declare, in Symfony's order.
     *
     * @param list<string> $directories
     * @param string|null  $env         the environment by its Symfony name: a route declared for others is left out
     */
    public static function load(array $directories, ?string $env = null): RouteCollection
    {
        $loader = new AttributeDirectoryLoader(new FileLocator(), self::classLoader($env));
        $routes = new RouteCollection();
        foreach ($directories as $directory) {
            $routes->addCollection($loader->load($directory, 'attribute') ?? new RouteCollection());
        }

        return $routes;
    }

    /** Laravel's environment by its Symfony name (local is dev, testing is test, production is prod). */
    public static function environment(string $laravel): string
    {
        return self::ENVIRONMENTS[$laravel] ?? $laravel;
    }

    /** Each route of the collection in Laravel's router, in the collection's order. */
    public static function register(Router $router, RouteCollection $routes): void
    {
        foreach ($routes->all() as $name => $route) {
            self::add($router, $name, $route);
        }
    }

    /**
     * The action a route runs: its class and method.
     *
     * @return array{class-string, string}
     */
    public static function action(Route $route): array
    {
        $controller = $route->getDefault('_controller');
        if (\is_array($controller) && \is_string($controller[0] ?? null) && \is_string($controller[1] ?? null)) {
            $controller = $controller[0].'::'.$controller[1];
        }
        if (!\is_string($controller) || '' === $controller) {
            throw new \InvalidArgumentException(\sprintf('The route "%s" names no action.', $route->getPath()));
        }
        [$class, $method] = explode('::', $controller, 2) + [1 => '__invoke'];
        if (!class_exists($class)) {
            throw new \InvalidArgumentException(\sprintf('The route "%s" names no action: no class "%s".', $route->getPath(), $class));
        }

        return [$class, $method];
    }

    private static function add(Router $router, string $name, Route $route): void
    {
        [$class, $method] = self::action($route);
        $variables = $route->compile()->getPathVariables();

        // A variable Symfony gives a default is optional, as Laravel writes it.
        $uri = $route->getPath();
        foreach ($variables as $variable) {
            if (\is_string($variable) && $route->hasDefault($variable)) {
                $uri = str_replace('{'.$variable.'}', '{'.$variable.'?}', $uri);
            }
        }

        $laravel = $router->match($route->getMethods() ?: ['GET', 'HEAD'], $uri, Responder::action($class, $method))->name($name);
        foreach ($route->getRequirements() as $variable => $pattern) {
            if (\in_array($variable, $variables, true) && \is_string($pattern)) {
                $laravel->where($variable, $pattern);
            }
        }
        // The path's defaults, and a localized route's language (Http\Localize reads it); Symfony's own
        // (_controller, _canonical_route, a host's flags) stay Symfony's.
        foreach ($route->getDefaults() as $key => $value) {
            if ('_locale' === $key || \in_array($key, $variables, true)) {
                $laravel->defaults($key, $value);
            }
        }
        if ('' !== $route->getHost()) {
            $laravel->domain($route->getHost());
        }
    }

    private static function classLoader(?string $env): AttributeClassLoader
    {
        return new class($env) extends AttributeClassLoader {
            /**
             * As Symfony's FrameworkBundle names an action (AttributeRouteControllerLoader).
             *
             * @param \ReflectionClass<object> $class
             */
            protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method, object $attr): void
            {
                $route->setDefault('_controller', '__invoke' === $method->getName() ? $class->getName() : $class->getName().'::'.$method->getName());
            }
        };
    }
}
