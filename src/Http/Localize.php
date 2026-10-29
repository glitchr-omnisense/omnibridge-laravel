<?php

namespace Omnibridge\Laravel\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * The page's language, chosen as omnibase's LocalizerSubscriber chooses it
 * under Symfony: the visitor's choice (the LANG cookie SwitchLocale sets),
 * else the browser's preferred language among the site's
 * (omnibridge.locales.available), else the site's default
 * (omnibridge.locales.default) - Laravel's locale, its language (fr); a
 * route of one language is that language's. The language shown is
 * remembered in LANG for the next page, as Symfony writes it (en-GB).
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
            : ($request->getPreferredLanguage($languages) ?? self::fallback()));
        app()->setLocale(substr($language, 0, 2));

        $response = $next($request);
        if (!$response instanceof Response) {
            throw new \LogicException('The page gave no response.');
        }
        // The language the page was shown in, remembered for the next one, as LocalizerSubscriber's
        // onKernelResponse() writes it (the site's locale, en-GB) - unless the page set it itself (a
        // change of language): after a page of one language (/japon/travels) the next is in it too.
        $displayed = self::locale(substr($language, 0, 2), (array) config('omnibridge.locales.available', []));
        if ($request->cookies->get(self::COOKIE) !== $displayed && !self::sets($response)) {
            $response->headers->setCookie(Cookie::create(self::COOKIE, $displayed, 0, '/', null, $request->isSecure(), false, false, Cookie::SAMESITE_LAX));
        }

        return $response;
    }

    /** The site's default (omnibridge.locales.default), else Laravel's app.locale: the merged configuration always holds the first key, null when unset. */
    private static function fallback(): string
    {
        $default = config('omnibridge.locales.default');
        $default = \is_string($default) && '' !== $default ? $default : config('app.locale');

        return \is_string($default) ? $default : '';
    }

    /**
     * The site's locale of a language (en-GB for en), as omnibridge.locales.available names it.
     *
     * @param array<array-key, mixed> $available
     */
    private static function locale(string $language, array $available): string
    {
        foreach ($available as $locale) {
            if (\is_string($locale) && str_starts_with($locale, $language)) {
                return $locale;
            }
        }

        return $language;
    }

    private static function sets(Response $response): bool
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if (self::COOKIE === $cookie->getName()) {
                return true;
            }
        }

        return false;
    }
}
