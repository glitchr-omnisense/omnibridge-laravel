<?php

namespace Omnibridge\Laravel\Container;

use Illuminate\Contracts\Container\Container;

/**
 * The services an application aliases by Symfony's #[AsAlias] (step R27 of
 * the audit "lesringostars face au master plan"), bound in Laravel's
 * container as Symfony's container aliases them: the interface the
 * attribute names - or the class's one interface, when it names none - is
 * the class. One declaration on the class, both hosts: an application no
 * longer keeps a list of its bindings for Laravel.
 *
 *     Aliases::bind(app(), Aliases::read(['App\\Repository\\' => base_path('src/Repository')]));
 *
 * The attribute is read by its name: symfony/dependency-injection need not
 * be installed.
 */
final class Aliases
{
    public const ATTRIBUTE = 'Symfony\Component\DependencyInjection\Attribute\AsAlias';

    /**
     * The aliases the classes of these folders declare, by namespace (PSR-4:
     * a folder's file Foo/Bar.php is its namespace's Foo\Bar), in the order of
     * their files.
     *
     * @param array<string, string> $folders namespace prefix => folder
     *
     * @return array<class-string, class-string> interface => class
     */
    public static function read(array $folders): array
    {
        $aliases = [];
        foreach ($folders as $namespace => $folder) {
            foreach (self::classes(rtrim($namespace, '\\').'\\', $folder) as $class) {
                foreach (self::of($class) as $interface) {
                    $aliases[$interface] = $class;
                }
            }
        }

        return $aliases;
    }

    /**
     * The interfaces a class is the alias of (its #[AsAlias]).
     *
     * @param class-string $class
     *
     * @return list<class-string>
     */
    public static function of(string $class): array
    {
        $reflection = new \ReflectionClass($class);
        $interfaces = [];
        foreach ($reflection->getAttributes(self::ATTRIBUTE) as $attribute) {
            $arguments = $attribute->getArguments();
            $id = $arguments['id'] ?? $arguments[0] ?? null;
            if (null === $id) {
                // As Symfony: the class's only interface, else it cannot tell.
                $implemented = $reflection->getInterfaceNames();
                if (1 !== \count($implemented)) {
                    throw new \LogicException(\sprintf('#[AsAlias] on "%s" names no interface, and the class implements %d.', $class, \count($implemented)));
                }
                $id = $implemented[0];
            }
            if (!\is_string($id) || !(interface_exists($id) || class_exists($id))) {
                throw new \LogicException(\sprintf('#[AsAlias] on "%s" names no interface nor class.', $class));
            }
            $interfaces[] = $id;
        }

        return $interfaces;
    }

    /**
     * Each alias as a shared binding: the interface resolves to the class the
     * container makes (a repository: omnibridge/laravel's, by its class).
     *
     * @param array<class-string, class-string> $aliases
     */
    public static function bind(Container $container, array $aliases): void
    {
        foreach ($aliases as $interface => $class) {
            $container->singleton($interface, static fn (Container $container) => $container->make($class));
        }
    }

    /** @return list<class-string> */
    private static function classes(string $namespace, string $folder): array
    {
        if (!is_dir($folder)) {
            return [];
        }
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && 'php' === $file->getExtension()) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

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
