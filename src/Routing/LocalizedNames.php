<?php

namespace Omnibridge\Laravel\Routing;

/**
 * The route a name means in a language, as omnibase's router reads a name
 * (step R27 of the audit "lesringostars face au master plan"; formerly the
 * rule of lesringostars-laravel's SymfonyRoutes alone): a localized route is
 * declared once per language ("app_timeline.fr", "app_timeline.en", as
 * Symfony names the paths of a #[Route(["fr" => ..., "en" => ...])]) and an
 * omnibase route may have a version without a destination
 * ("app_timeline.default.fr"). A name is looked for in the page's language,
 * as given, then as its version without a destination; a language the
 * routes do not have takes the first of the fallback language's versions,
 * in the routes' order (a German page links to /japon/travels).
 */
final class LocalizedNames
{
    /**
     * @param iterable<string> $names the routes' names, in their order
     */
    public static function resolve(string $name, string $locale, iterable $names, string $fallbackLocale = 'en'): ?string
    {
        $known = [];
        foreach ($names as $each) {
            $known[$each] = true;
        }

        $language = substr($locale, 0, 2);
        foreach ([$name.'.'.$language, $name, $name.'.default.'.$language, $name.'.default'] as $candidate) {
            if (isset($known[$candidate])) {
                return $candidate;
            }
        }
        $fallback = substr($fallbackLocale, 0, 2);
        foreach (array_keys($known) as $candidate) {
            if ($candidate === $name.'.'.$fallback || $candidate === $name.'.default.'.$fallback) {
                return $candidate;
            }
        }

        return null;
    }
}
