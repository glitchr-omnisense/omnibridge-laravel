<?php

namespace Omnibridge\Laravel\Omnibase;

/**
 * The classes of an application's `src/` folders, by the layout every omnibase site shares (src/EntitySubscriber,
 * src/EntityDispatcher, src/Repository under App\; the core's under Base\): what Symfony's autoconfiguration
 * finds by its tags, read here from the folders, so that the same application runs on both hosts without a list of
 * its classes in a configuration file.
 */
final class ProjectClasses
{
    /** The folders of the shared layout that hold classes a host registers by itself. */
    public const ENTITY_SUBSCRIBERS = 'EntitySubscriber';
    public const ENTITY_DISPATCHERS = 'EntityDispatcher';
    public const REPOSITORIES = 'Repository';

    /**
     * The concrete classes of a folder of the application's src/ (App\<Folder>\...) and of the core's (Base\<Folder>\...).
     *
     * @return list<class-string>
     */
    public static function of(string $projectDir, string $folder): array
    {
        $classes = self::in($projectDir.'/src/'.$folder, 'App\\'.$folder.'\\');
        if (class_exists(\Base\Services::class)) {
            $classes = [...self::in(\dirname((string) (new \ReflectionClass(\Base\Services::class))->getFileName()).'/'.$folder, 'Base\\'.$folder.'\\'), ...$classes];
        }

        return array_values(array_unique($classes));
    }

    /**
     * The concrete classes of a folder, PSR-4 (a folder's file Foo/Bar.php is its namespace's Foo\Bar), in the order
     * of their files.
     *
     * @return list<class-string>
     */
    public static function in(string $folder, string $namespace): array
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
            $class = rtrim($namespace, '\\').'\\'.str_replace('/', '\\', substr($file, \strlen(rtrim($folder, '/')) + 1, -4));
            if (class_exists($class) && !(new \ReflectionClass($class))->isAbstract()) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
