<?php

namespace Omnibridge\Laravel\Container;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;

/**
 * omnisense/omnibase's list of services (Base\Services::definitions(), plan
 * "omnibase agnostic", step F5a) bound in Laravel's container, as Symfony's
 * container and omnisense/omnibridge's Omnibridge\Container read it: each
 * service the container does not bind yet is a singleton of its class, its
 * contracts aliases of it. The arguments the list gives by name are read with
 * Omnibridge\Container's syntax ('@id' a service, '%name%' a parameter, inside
 * a string too); the others are resolved here, where Laravel's own autowiring
 * stops: a union type takes the type the container binds first (omnibase's
 * Views|Environment, Urls|RouterInterface...), then a class it can build, then
 * the default, then null.
 *
 * A class not installed is left out with its contracts; a service or a
 * contract the application (or this bridge) already binds keeps its binding.
 */
final class Definitions
{
    /**
     * @param iterable<array{class: class-string, id?: string, contracts?: list<string>, arguments?: array<string, mixed>}> $definitions
     * @param \Closure(string $name): mixed                                                                                $parameter a parameter by its name
     */
    public static function bind(Container $container, iterable $definitions, \Closure $parameter): void
    {
        foreach ($definitions as $definition) {
            $class = $definition['class'];
            if (!class_exists($class)) {
                continue;
            }
            $id = $definition['id'] ?? $class;
            if (!$container->bound($id)) {
                $arguments = $definition['arguments'] ?? [];
                $container->singleton($id, static fn (Container $container): object => self::build($container, $class, $arguments, $parameter));
            }
            foreach ($definition['contracts'] ?? [] as $contract) {
                if ($contract !== $id && !$container->bound($contract)) {
                    $container->alias($id, $contract);
                }
            }
        }
    }

    /**
     * @param class-string                 $class
     * @param array<string, mixed>         $arguments
     * @param \Closure(string $name): mixed $parameter
     */
    public static function build(Container $container, string $class, array $arguments, \Closure $parameter): object
    {
        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        $values = [];
        foreach ($constructor?->getParameters() ?? [] as $argument) {
            if ($argument->isVariadic()) {
                break;
            }
            $name = $argument->getName();
            if (\array_key_exists('$'.$name, $arguments) || \array_key_exists($name, $arguments)) {
                $values[$name] = self::resolve($container, \array_key_exists('$'.$name, $arguments) ? $arguments['$'.$name] : $arguments[$name], $parameter);
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
                        // its catalogues' translator): left out, as an unbound one; a required one says why.
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
            if (!$argument->isDefaultValueAvailable() && !$argument->allowsNull()) {
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

            throw new \LogicException(sprintf('omnibase\'s service %s: argument $%s (%s) has no binding in Laravel\'s container, no argument in Base\Services\' list and no default.', $class, $name, (string) $type));
        }

        return $reflection->newInstanceArgs($values);
    }

    /** @param \Closure(string $name): mixed $parameter */
    private static function resolve(Container $container, mixed $value, \Closure $parameter): mixed
    {
        if (\is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::resolve($container, $item, $parameter), $value);
        }
        if (!\is_string($value)) {
            return $value;
        }
        if (str_starts_with($value, '@@')) {
            return substr($value, 1);
        }
        if (str_starts_with($value, '@')) {
            return $container->make(substr($value, 1));
        }
        if (preg_match('/^%([^%\s]+)%$/', $value, $match)) {
            return $parameter($match[1]);
        }

        return preg_replace_callback('/%%|%([^%\s]+)%/', static fn (array $match): string => '%%' === $match[0] ? '%' : (string) $parameter($match[1]), $value);
    }
}
