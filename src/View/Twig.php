<?php

namespace Omnibridge\Laravel\View;

use Omnibridge\Twig\Contract\Translations;
use Omnibridge\Twig\Contract\Urls;
use Omnibridge\Twig\ContractExtension;
use Omnibridge\Twig\HelperExtension;
use Twig\Environment;
use Twig\Extension\ExtensionInterface;
use Twig\Loader\FilesystemLoader;

/**
 * A Twig environment over an omnibase site's templates, answered by Laravel:
 * its folders and their namespaces (@Base, @Layout...), omnibridge/twig's
 * contract (path(), url(), asset(), |trans) and helpers (|datetime, lang()...)
 * in the page's language, and the application's own extensions.
 */
final class Twig
{
    /**
     * @param list<array{0: string, 1: string|null}> $paths      a folder and its namespace, null for the main one
     * @param list<ExtensionInterface>              $extensions
     * @param array<string, mixed>                  $options    Twig's (cache, auto_reload, strict_variables...)
     */
    public static function create(array $paths, Urls $urls, Translations $translations, string $locale, ?string $timezone = null, array $extensions = [], array $options = []): Environment
    {
        $loader = new FilesystemLoader();
        foreach ($paths as [$path, $namespace]) {
            $loader->addPath($path, $namespace ?? FilesystemLoader::MAIN_NAMESPACE);
        }
        $twig = new Environment($loader, $options + ['auto_reload' => true, 'strict_variables' => false]);
        $twig->addExtension(new ContractExtension($urls, $translations));
        $twig->addExtension(new HelperExtension($locale, $timezone));
        foreach ($extensions as $extension) {
            $twig->addExtension($extension);
        }

        return $twig;
    }
}
