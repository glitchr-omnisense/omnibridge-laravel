<?php

namespace Omnibridge\Laravel\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The page's language, chosen as omnibase's LocalizerSubscriber chooses it
 * under Symfony: the visitor's choice (the LANG cookie SwitchLocale sets),
 * else the browser's preferred language among the site's
 * (omnibridge.locales.available), else the site's default
 * (omnibridge.locales.default) - Laravel's locale, its language (fr).
 *
 * In the web group of an omnibase site under Laravel (bootstrap/app.php:
 * $middleware->web(append: [Localize::class])), the LANG cookie left
 * unencrypted, as Symfony writes it.
 */
final class Localize
{
    /** omnibase's cookie of the visitor's language. */
    public const COOKIE = 'LANG';

    public function handle(Request $request, Closure $next): Response
    {
        $languages = array_map(static fn (string $locale) => substr($locale, 0, 2), (array) config('omnibridge.locales.available', []));
        // A route of one language (a localized action's "app_timeline.fr", Routing\ActionRoutes) is that
        // language's, whatever the visitor chose, as omnibase's LocalizerSubscriber decides.
        $declared = $request->route('_locale');
        $chosen = substr((string) $request->cookies->get(self::COOKIE), 0, 2);
        $language = \is_string($declared) && '' !== $declared ? $declared : (\in_array($chosen, $languages, true)
            ? $chosen
            : ($request->getPreferredLanguage($languages) ?? (string) config('omnibridge.locales.default', config('app.locale'))));
        app()->setLocale(substr($language, 0, 2));

        return $next($request);
    }
}
