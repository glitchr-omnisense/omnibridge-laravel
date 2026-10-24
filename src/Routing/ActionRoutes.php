<?php

namespace Omnibridge\Laravel\Routing;

use Illuminate\Routing\Router;
use Omnibridge\Laravel\Screen\Responder;
use Symfony\Component\Routing\Attribute\Route as RouteAttribute;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The routes an application's actions declare, registered in Laravel (step
 * R27 of the audit "lesringostars face au master plan"): the same #[Route]
 * attributes of symfony/routing that Symfony's router reads on the same
 * classes - one declaration, both hosts -, read by the rules of
 * symfony/routing's attribute loader (not by the loader itself, which
 * stands on symfony/config), so that names, localized paths
 * ("app_timeline.fr" with its `_locale`), requirements, defaults,
 * priorities and environments are the ones Symfony gives them.
 *
 *     ActionRoutes::register(app('router'), ActionRoutes::load(['App\\Action\\' => base_path('src/Action')], 'dev'));
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
     * @param array<string, string> $folders namespace prefix => folder (PSR-4: a folder's Foo/Bar.php is its namespace's Foo\Bar)
     * @param string|null           $env     the environment by its Symfony name: a route declared for others is left out
     */
    public static function load(array $folders, ?string $env = null): RouteCollection
    {
        $routes = new RouteCollection();
        foreach ($folders as $namespace => $folder) {
            foreach (self::classes(rtrim($namespace, '\\').'\\', $folder) as $class) {
                self::read($routes, new \ReflectionClass($class), $env);
            }
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

        // A variable is optional where Symfony takes it so: given a default, at the end of the path
        // (Symfony sets the defaults of an action's parameters, `?string $destination = null`, on every
        // variable; one followed by more of the path is required all the same).
        $uri = $route->getPath();
        $optional = [];
        while (preg_match('~/\{(\w+)\}$~', $uri, $match) && $route->hasDefault($match[1])) {
            $optional[] = $match[1];
            $uri = substr($uri, 0, -\strlen($match[0]));
        }
        $uri = $route->getPath();
        foreach ($optional as $variable) {
            $uri = str_replace('{'.$variable.'}', '{'.$variable.'?}', $uri);
        }

        $laravel = $router->match($route->getMethods() ?: ['GET', 'HEAD'], $uri, Responder::action($class, $method))->name($name);
        foreach ($route->getRequirements() as $variable => $pattern) {
            if (\in_array($variable, $variables, true) && \is_string($pattern)) {
                $laravel->where($variable, $pattern);
            }
        }
        // The optional variables' defaults, and a localized route's language (Http\Localize reads it); Symfony's own
        // (_controller, _canonical_route, a host's flags) stay Symfony's.
        foreach ($route->getDefaults() as $key => $value) {
            if ('_locale' === $key || \in_array($key, $optional, true)) {
                $laravel->defaults($key, $value);
            }
        }
        if ('' !== $route->getHost()) {
            $laravel->domain($route->getHost());
        }
    }

    /**
     * A class's routes, as symfony/routing's AttributeClassLoader reads them (with FrameworkBundle's
     * _controller): its methods' #[Route], under the class's own (a prefix, its name, requirements,
     * defaults...); an invokable class's #[Route] its own routes; a path per language one route per
     * language (name.fr, its _locale and _canonical_route); the defaults of the action's parameters
     * set on the variables of their name. Read here rather than through the loader, which stands on
     * symfony/config, a part of Symfony's framework a Laravel application does not install.
     *
     * @param \ReflectionClass<object> $class
     */
    private static function read(RouteCollection $routes, \ReflectionClass $class, ?string $env): void
    {
        $globals = self::globals($class);
        if ($globals['env'] && !\in_array($env, $globals['env'], true)) {
            return;
        }
        $count = \count($routes->all());
        foreach ($class->getMethods() as $method) {
            foreach (self::attributes($method) as $attribute) {
                self::collect($routes, $attribute, $globals, $class, $method, $env);
            }
        }
        if ($count === \count($routes->all()) && $class->hasMethod('__invoke')) {
            foreach (self::attributes($class) as $attribute) {
                self::collect($routes, $attribute, self::globals(null), $class, $class->getMethod('__invoke'), $env);
            }
        }
    }

    /**
     * @param \ReflectionClass<object>|null $class
     *
     * @return array{path: ?string, localized_paths: array<string, string>, name: string, requirements: array<string, string>, defaults: array<string, mixed>, methods: list<string>, host: ?string, priority: int, env: list<string>}
     */
    private static function globals(?\ReflectionClass $class): array
    {
        $globals = ['path' => null, 'localized_paths' => [], 'name' => '', 'requirements' => [], 'defaults' => [], 'methods' => [], 'host' => null, 'priority' => 0, 'env' => []];
        $attribute = null !== $class ? (self::attributes($class)[0] ?? null) : null;
        if (null === $attribute) {
            return $globals;
        }
        $declared = self::declaration($attribute);

        return [
            'path' => \is_string($declared['path']) ? $declared['path'] : null,
            'localized_paths' => \is_array($declared['path']) ? $declared['path'] : [],
            'name' => $declared['name'] ?? '',
            'requirements' => $declared['requirements'],
            'defaults' => $declared['defaults'],
            'methods' => $declared['methods'],
            'host' => $declared['host'],
            'priority' => $declared['priority'] ?? 0,
            'env' => $declared['envs'],
        ];
    }

    /**
     * @param array{path: ?string, localized_paths: array<string, string>, name: string, requirements: array<string, string>, defaults: array<string, mixed>, methods: list<string>, host: ?string, priority: int, env: list<string>} $globals
     * @param \ReflectionClass<object> $class
     */
    private static function collect(RouteCollection $routes, RouteAttribute $attribute, array $globals, \ReflectionClass $class, \ReflectionMethod $method, ?string $env): void
    {
        $declared = self::declaration($attribute);
        if ($declared['envs'] && !\in_array($env, $declared['envs'], true)) {
            return;
        }
        $name = $globals['name'].($declared['name'] ?? strtolower(str_replace('\\', '_', $class->getName()).'_'.$method->getName()));
        $defaults = array_replace($globals['defaults'], $declared['defaults']);
        $requirements = array_replace($globals['requirements'], $declared['requirements']);
        $methods = array_values(array_unique(array_merge($globals['methods'], $declared['methods'])));
        $host = $declared['host'] ?? $globals['host'];
        $priority = $declared['priority'] ?? $globals['priority'];

        $path = $declared['path'] ?? '';
        $prefix = $globals['localized_paths'] ?: $globals['path'];
        $paths = [];
        if (\is_array($path)) {
            foreach ($path as $locale => $localePath) {
                $paths[$locale] = (\is_array($prefix) ? ($prefix[$locale] ?? throw new \LogicException(\sprintf('The route of "%s::%s" has no prefix for "%s".', $class->getName(), $method->getName(), $locale))) : (string) $prefix).$localePath;
            }
        } elseif (\is_array($prefix)) {
            foreach ($prefix as $locale => $localePrefix) {
                $paths[$locale] = $localePrefix.$path;
            }
        } else {
            $paths[] = (string) $prefix.$path;
        }

        // The action's parameters' defaults on the variables of their name.
        foreach ($method->getParameters() as $parameter) {
            if (isset($defaults[$parameter->name]) || !$parameter->isDefaultValueAvailable()) {
                continue;
            }
            foreach ($paths as $each) {
                if (preg_match(\sprintf('/\{(?|([^\}:<]++):%s(?:\.[^\}<]++)?|(%1$s))(?:<.*?>)?\}/', preg_quote($parameter->name)), $each, $match)) {
                    $value = $parameter->getDefaultValue();
                    if (\is_scalar($value) || null === $value) {
                        $defaults[$match[1]] = $value;
                    } elseif ($value instanceof \BackedEnum) {
                        $defaults[$match[1]] = $value->value;
                    }
                    break;
                }
            }
        }

        foreach ($paths as $locale => $each) {
            $route = new Route($each, $defaults, $requirements, [], $host ?? '', [], $methods);
            // As Symfony's FrameworkBundle names an action (AttributeRouteControllerLoader).
            $route->setDefault('_controller', '__invoke' === $method->getName() ? $class->getName() : $class->getName().'::'.$method->getName());
            if (\is_string($locale)) {
                $route->setDefault('_locale', $locale);
                $route->setRequirement('_locale', preg_quote($locale));
                $route->setDefault('_canonical_route', $name);
                $routes->add($name.'.'.$locale, $route, $priority);
            } else {
                $routes->add($name, $route, $priority);
            }
        }
    }

    /**
     * @param \ReflectionClass<object>|\ReflectionMethod $reflection
     *
     * @return list<RouteAttribute>
     */
    private static function attributes(\ReflectionClass|\ReflectionMethod $reflection): array
    {
        return array_map(static fn (\ReflectionAttribute $attribute) => $attribute->newInstance(), $reflection->getAttributes(RouteAttribute::class, \ReflectionAttribute::IS_INSTANCEOF));
    }

    /**
     * What a #[Route] declares, typed: its properties (symfony/routing 8), else its getters (7).
     *
     * @return array{path: string|array<string, string>|null, name: ?string, requirements: array<string, string>, defaults: array<string, mixed>, methods: list<string>, host: ?string, priority: ?int, envs: list<string>}
     */
    private static function declaration(RouteAttribute $attribute): array
    {
        $value = static function (string $property) use ($attribute): mixed {
            if (property_exists($attribute, $property)) {
                return $attribute->{$property};
            }
            $getter = 'get'.ucfirst('envs' === $property ? 'env' : $property);

            return method_exists($attribute, $getter) ? $attribute->{$getter}() : null;
        };
        /** @return array<string> */
        $strings = static fn (mixed $values): array => array_filter(\is_array($values) ? $values : (null === $values ? [] : [$values]), \is_string(...));
        /** @return array<string, string> */
        $keyed = static function (mixed $values) use ($strings): array {
            $keyed = [];
            foreach ($strings($values) as $key => $each) {
                $keyed[(string) $key] = $each;
            }

            return $keyed;
        };
        $path = $value('path');
        $name = $value('name');
        $host = $value('host');
        $priority = $value('priority');
        /** @var array<string, mixed> $defaults */
        $defaults = (array) $value('defaults');

        return [
            'path' => \is_string($path) || null === $path ? $path : $keyed($path),
            'name' => \is_string($name) ? $name : null,
            'requirements' => $keyed($value('requirements')),
            'defaults' => $defaults,
            'methods' => array_values($strings($value('methods'))),
            'host' => \is_string($host) ? $host : null,
            'priority' => \is_int($priority) ? $priority : null,
            'envs' => array_values($strings($value('envs'))),
        ];
    }

    /**
     * The classes of a folder, by its namespace, in the order Symfony's directory loader reads them
     * (by path).
     *
     * @return list<class-string>
     */
    private static function classes(string $namespace, string $folder): array
    {
        if (!is_dir($folder)) {
            return [];
        }
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS)) as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && 'php' === $file->getExtension() && !str_starts_with($file->getBasename(), '.')) {
                $files[] = $file->getPathname();
            }
        }
        usort($files, static fn (string $a, string $b) => $a > $b ? 1 : -1);

        $classes = [];
        foreach ($files as $file) {
            $class = $namespace.str_replace('/', '\\', substr($file, \strlen(rtrim($folder, '/')) + 1, -4));
            if (class_exists($class) && !(new \ReflectionClass($class))->isAbstract()) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
