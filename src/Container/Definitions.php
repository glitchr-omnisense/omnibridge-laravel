<?php

namespace Omnibridge\Laravel\Container;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Omnibridge\Container\Declarations;

/**
 * omnisense/omnibase's declaration of its services (Base\Services::definitions(), plan "omnibase agnostic", steps
 * F5a and F5e slice 4) bound in Laravel's container, as Symfony's container and omnisense/omnibridge's
 * Omnibridge\Container read it - through omnibridge's Omnibridge\Container\Declarations: each definition that applies
 * (what it requires installed, its environment this one), its parents inherited, the abstract ones left out. Each
 * service the container does not bind yet is a singleton of its class, its contracts aliases of it, its tags Laravel's
 * tags. The arguments it gives by position or by name are read with Omnibridge\Container's syntax ('@id' a service,
 * '@?id' one or null, '%name%' a parameter, inside a string too, `['!tagged_iterator' => tag]` Laravel's tagged
 * services, `['!service_closure' => '@id']` a closure); its bindings fill what they leave out; its calls are made; the
 * others are resolved here, where Laravel's own autowiring stops: a union type takes the type the container binds first
 * (omnibase's Views|Environment, Urls|RouterInterface...), then a class it can build, then the default, then null.
 *
 * What only Symfony reads (a definition's `symfony` part) is not read; a decoration is the host's own wiring here (this
 * bridge wraps omniauth's second-factor rule itself, under its settings): a decorating definition is not bound. A class
 * not installed is left out with its contracts; a service or a contract the application (or this bridge) already binds
 * keeps its binding.
 */
final class Definitions
{
    /**
     * @param iterable<array<string, mixed>>      $definitions
     * @param \Closure(string $name): mixed       $parameter    a parameter by its name (asked when a service is built)
     * @param (\Closure(string $name): bool)|null $hasParameter whether the application gives a parameter, asked when binding
     *                                                           (without building its parameters' bag): a service whose
     *                                                           #[Autowire] parameter it lacks is not bound
     */
    public static function bind(Container $container, iterable $definitions, \Closure $parameter, ?string $environment = null, ?\Closure $hasParameter = null): void
    {
        $resolved = Declarations::resolve($definitions, $environment);
        $byContract = [];
        foreach ($resolved as $id => $definition) {
            foreach (array_keys($definition['contracts']) as $contract) {
                $byContract[$contract] ??= $id;
            }
        }
        $known = [];
        $tags = [];
        foreach ($resolved as $id => $definition) {
            // A service the application (or this bridge) binds keeps its binding and is given its contracts; one it does
            // not is bound when it can be built here.
            $given = $container->bound($id);
            if (null !== $definition['decorates'] || (!$given && !self::satisfiable($container, $id, $resolved, $byContract, $known, $hasParameter))) {
                continue;
            }
            if (!$given) {
                $container->singleton($id, static fn (Container $container): object => self::guarded($container, $id, $definition, $parameter));
            }
            foreach (array_keys($definition['contracts']) as $contract) {
                if ($contract !== $id && !$container->bound($contract)) {
                    $container->alias($id, $contract);
                }
            }
            foreach ($definition['tags'] as $tag) {
                $tags[\is_string($tag) ? $tag : $tag['name']][] = $id;
            }
        }
        foreach ($tags as $tag => $ids) {
            $container->tag($ids, $tag);
        }
    }

    /**
     * Whether a declared service can be built in this application: its class loads and what its constructor cannot do
     * without is there - a service it names or a type it asks that the application binds, another declared service that
     * can be built, a class that can be built as it is. A service that cannot be built here (a repository this
     * application does not bind...) is not bound: Laravel's bound() stays true to what it can make.
     *
     * @param array<string, array<string, mixed>> $resolved
     * @param array<string, string>               $byContract
     * @param array<string, bool>                 $known      memo (an id being looked at counts as there: a cycle is not a lack)
     */
    private static function satisfiable(Container $container, string $id, array $resolved, array $byContract, array &$known, ?\Closure $hasParameter, int $depth = 0): bool
    {
        if (isset($known[$id])) {
            return $known[$id];
        }
        $definition = $resolved[$id] ?? null;
        if (null === $definition) {
            if ($container->bound($id)) {
                return true;
            }
            if (isset($byContract[$id])) {
                return self::satisfiable($container, $byContract[$id], $resolved, $byContract, $known, $hasParameter, $depth);
            }
            // A class built as it is (Laravel's own autowiring), looked at a few levels down.
            if (!Declarations::loadable($id) || !(new \ReflectionClass($id))->isInstantiable()) {
                return false;
            }
            $definition = ['class' => $id, 'arguments' => [], 'bind' => [], 'factory' => null];
            if ($depth > 3) {
                return true;
            }
        } elseif (!Declarations::loadable($definition['class'])) {
            return $known[$id] = false;
        }
        $known[$id] = true;
        $function = null;
        try {
            $function = \is_array($definition['factory']) && \is_string($definition['factory'][0]) && !str_starts_with($definition['factory'][0], '@')
                ? new \ReflectionMethod($definition['factory'][0], $definition['factory'][1])
                : (null === $definition['factory'] ? (new \ReflectionClass($definition['class']))->getConstructor() : null);
        } catch (\ReflectionException) {
            return $known[$id] = false;
        }
        foreach ($function?->getParameters() ?? [] as $position => $argument) {
            $name = $argument->getName();
            $given = null;
            foreach (['$'.$name, $name, $position] as $key) {
                if (\array_key_exists($key, $definition['arguments'])) {
                    $given = [$definition['arguments'][$key]];
                    break;
                }
            }
            if (null !== $given) {
                $value = $given[0];
                if (\is_string($value) && str_starts_with($value, '@') && !str_starts_with($value, '@?') && !str_starts_with($value, '@!') && !str_starts_with($value, '@@')
                    && !self::satisfiable($container, substr($value, 1), $resolved, $byContract, $known, $hasParameter, $depth + 1)) {
                    return $known[$id] = false;
                }
                continue;
            }
            if ($argument->isDefaultValueAvailable() || $argument->allowsNull() || $argument->isVariadic() || \array_key_exists('$'.$name, $definition['bind'])) {
                continue;
            }
            // Symfony's #[Autowire]: a parameter this application has no value for (an omnibase option its configuration
            // does not give), a service it does not bind.
            foreach ($argument->getAttributes(\Omnibridge\Container::AUTOWIRE_ATTRIBUTE) as $attribute) {
                $options = $attribute->getArguments();
                $value = $options['value'] ?? $options[0] ?? null;
                $param = $options['param'] ?? (\is_string($value) && preg_match('/^%([^%\s]+)%$/', $value, $match) && !str_starts_with($match[1], 'env(') ? $match[1] : null);
                if (null !== $param) {
                    if (null !== $hasParameter && !$hasParameter($param)) {
                        return $known[$id] = false;
                    }
                } elseif (isset($options['service']) && !self::satisfiable($container, $options['service'], $resolved, $byContract, $known, $hasParameter, $depth + 1)) {
                    return $known[$id] = false;
                }
                continue 2;
            }
            $type = $argument->getType();
            $names = [];
            foreach ($type instanceof \ReflectionUnionType ? $type->getTypes() : (null === $type ? [] : [$type]) as $named) {
                if ($named instanceof \ReflectionNamedType && !$named->isBuiltin()) {
                    $names[] = $named->getName();
                }
            }
            if (!$names) {
                continue;
            }
            $found = false;
            foreach ($names as $typeName) {
                if (self::satisfiable($container, $typeName, $resolved, $byContract, $known, $hasParameter, $depth + 1)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return $known[$id] = false;
            }
        }

        return $known[$id] = true;
    }

    /**
     * A declared service, or Laravel's BindingResolutionException when it cannot be built here (a repository, a
     * parameter or a service this application does not have): an optional parameter that asks it then takes its
     * default, as Laravel's own autowiring does; a required one says why.
     *
     * @param array<string, mixed>          $definition
     * @param \Closure(string $name): mixed $parameter
     */
    private static function guarded(Container $container, string $id, array $definition, \Closure $parameter): object
    {
        try {
            return self::build($container, $definition, $parameter);
        } catch (BindingResolutionException $unbuilt) {
            throw $unbuilt;
        } catch (\Throwable $error) {
            throw new BindingResolutionException(sprintf('omnibase\'s service %s cannot be built here: %s', $id, $error->getMessage()), 0, $error);
        }
    }

    /**
     * A service of a definition (Declarations::resolve()'s), or of a class and its arguments.
     *
     * @param array{class: class-string, arguments?: array<int|string, mixed>, bind?: array<string, mixed>, calls?: list<array<mixed>>, factory?: mixed}|class-string $definition
     * @param array<int|string, mixed>                                                                                                                         $arguments
     * @param \Closure(string $name): mixed                                                                                                                   $parameter
     */
    public static function build(Container $container, array|string $definition, array|\Closure $arguments = [], ?\Closure $parameter = null): object
    {
        if (\is_string($definition)) {
            // build($container, $class, $arguments, $parameter), as before slice 4.
            $definition = ['class' => $definition, 'arguments' => $arguments];
        } elseif ($arguments instanceof \Closure) {
            $parameter = $arguments;
        }
        $parameter ??= static fn (string $name): mixed => null;
        $class = $definition['class'];
        $given = $definition['arguments'] ?? [];
        $bind = $definition['bind'] ?? [];
        $factory = isset($definition['factory']) ? self::resolve($container, $definition['factory'], $parameter) : null;
        $function = null !== $factory
            ? (\is_array($factory) ? new \ReflectionMethod($factory[0], $factory[1]) : new \ReflectionFunction(\Closure::fromCallable($factory)))
            : (new \ReflectionClass($class))->getConstructor();
        $values = [];
        foreach ($function?->getParameters() ?? [] as $position => $argument) {
            if ($argument->isVariadic()) {
                break;
            }
            $name = $argument->getName();
            foreach (['$'.$name, $name, $position] as $key) {
                if (\array_key_exists($key, $given)) {
                    $values[$name] = self::resolve($container, $given[$key], $parameter);
                    continue 2;
                }
            }
            // Symfony's #[Autowire], read by its name (never loaded): a parameter, the environment, a value, a service
            // this container binds (one it does not, `advanced_router`..., leaves the parameter to its type).
            foreach ($argument->getAttributes() as $attribute) {
                if (\Omnibridge\Container::AUTOWIRE_ATTRIBUTE !== $attribute->getName()) {
                    continue;
                }
                $options = $attribute->getArguments();
                if (isset($options['service'])) {
                    if ($container->bound($options['service'])) {
                        $values[$name] = $container->make($options['service']);
                        continue 2;
                    }
                } else {
                    $found = match (true) {
                        isset($options['param']) => $parameter($options['param']),
                        isset($options['env']) => self::resolve($container, '%env('.$options['env'].')%', $parameter),
                        \array_key_exists('value', $options) || \array_key_exists(0, $options) => self::resolve($container, $options['value'] ?? $options[0], $parameter),
                        default => null,
                    };
                    // An option this application does not give: the parameter's default, as the class declares it.
                    if (null === $found && $argument->isDefaultValueAvailable()) {
                        continue 2;
                    }
                    $values[$name] = $found;
                    continue 2;
                }
            }
            if (\array_key_exists('$'.$name, $bind)) {
                $values[$name] = self::resolve($container, $bind['$'.$name], $parameter);
                continue;
            }
            $type = $argument->getType();
            $names = [];
            foreach ($type instanceof \ReflectionUnionType ? $type->getTypes() : (null === $type ? [] : [$type]) as $named) {
                if ($named instanceof \ReflectionNamedType && !$named->isBuiltin()) {
                    $names[] = $named->getName();
                }
            }
            $optional = $argument->isDefaultValueAvailable() || $argument->allowsNull();
            foreach ($names as $typeName) {
                if ($container->bound($typeName)) {
                    try {
                        $values[$name] = $container->make($typeName);
                    } catch (BindingResolutionException $unbuilt) {
                        // An optional service this application binds but cannot build (omnibase's translator without
                        // its catalogues' translator, a declared service whose repository is not bound here): left out,
                        // as an unbound one; a required one says why.
                        if (!$optional) {
                            throw $unbuilt;
                        }
                        if (!$argument->isDefaultValueAvailable()) {
                            $values[$name] = null;
                        }
                    }
                    continue 2;
                }
            }
            if (!$optional) {
                foreach ($names as $typeName) {
                    if (class_exists($typeName) && (new \ReflectionClass($typeName))->isInstantiable()) {
                        $values[$name] = $container->make($typeName);
                        continue 2;
                    }
                }
            }
            if ($argument->isDefaultValueAvailable()) {
                continue;
            }
            if ($argument->allowsNull()) {
                $values[$name] = null;
                continue;
            }

            throw new BindingResolutionException(sprintf('omnibase\'s service %s: argument $%s (%s) has no binding in Laravel\'s container, no argument in Base\Services\' list and no default.', $class, $name, (string) $type));
        }

        $service = null !== $factory ? $factory(...$values) : (new \ReflectionClass($class))->newInstanceArgs($values);
        foreach ($definition['calls'] ?? [] as $call) {
            $arguments = [];
            foreach ($call[1] ?? [] as $key => $value) {
                // A service that may be ignored ('@??id', '@!id') and is not bound drops the call, as under Symfony.
                if (\is_string($value) && (str_starts_with($value, '@??') || str_starts_with($value, '@!')) && !$container->bound(ltrim(substr($value, 1), '?!'))) {
                    continue 2;
                }
                $arguments[$key] = self::resolve($container, $value, $parameter);
            }
            $result = $service->{$call[0]}(...$arguments);
            if (!empty($call[2]) && \is_object($result)) {
                $service = $result;
            }
        }

        return $service;
    }

    /** @param \Closure(string $name): mixed $parameter */
    /**
     * An environment variable as Symfony's %env()% reads it ('bool:NAME', 'int:', 'string:', 'default::'), the default
     * the core declares for it where the environment has none (Base\Options::environment(): BASE_TRADING_LIVE,
     * COLLAB_*, as Symfony's env(NAME) parameters give them).
     */
    public static function env(string $expression): mixed
    {
        $processors = explode(':', $expression);
        $variable = array_pop($processors);
        $found = getenv($variable);
        $found = false === $found ? ($_ENV[$variable] ?? $_SERVER[$variable] ?? null) : $found;
        if (null === $found && class_exists(\Base\Options::class)) {
            $found = \Base\Options::environment()[$variable] ?? null;
        }
        foreach (array_reverse($processors) as $processor) {
            $found = match ($processor) {
                'bool' => null === $found ? false : filter_var($found, \FILTER_VALIDATE_BOOL),
                'int' => (int) $found,
                'string' => (string) $found,
                default => $found,
            };
        }

        return $found;
    }

    private static function resolve(Container $container, mixed $value, \Closure $parameter): mixed
    {
        if (\is_array($value)) {
            if (1 === \count($value) && \is_string($key = array_key_first($value)) && str_starts_with($key, '!')) {
                $given = $value[$key];

                return match ($key) {
                    '!tagged_iterator' => iterator_to_array($container->tagged($given), false),
                    '!service_closure' => static fn (): mixed => self::resolve($container, $given, $parameter),
                    '!closure' => \Closure::fromCallable(self::resolve($container, $given, $parameter)),
                    '!new' => self::build($container, ['class' => $given['class'], 'arguments' => $given['arguments'] ?? []], $parameter),
                    '!iterator' => self::resolve($container, $given, $parameter),
                    default => throw new \LogicException(sprintf('Unknown value "%s" in omnibase\'s declaration of services.', $key)),
                };
            }

            return array_map(static fn (mixed $item): mixed => self::resolve($container, $item, $parameter), $value);
        }
        if (!\is_string($value)) {
            return $value;
        }
        if (str_starts_with($value, '@@')) {
            return substr($value, 1);
        }
        if (str_starts_with($value, '@')) {
            $optional = str_starts_with($value, '@?') || str_starts_with($value, '@!');
            $id = ltrim(substr($value, 1), '?!');
            if ($optional && !$container->bound($id) && !(class_exists($id) && (new \ReflectionClass($id))->isInstantiable())) {
                return null;
            }

            return $container->make($id);
        }
        if (preg_match('/^%env\(([^%\s]+)\)%$/', $value, $match)) {
            return self::env($match[1]);
        }
        if (preg_match('/^%([^%\s]+)%$/', $value, $match)) {
            return $parameter($match[1]);
        }

        return preg_replace_callback('/%%|%([^%\s]+)%/', static fn (array $match): string => '%%' === $match[0] ? '%' : (string) $parameter($match[1]), $value);
    }
}
