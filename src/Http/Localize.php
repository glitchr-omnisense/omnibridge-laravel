<?php

namespace Omnibridge\Laravel\Http;

use Base\Request\Rule\Locale;
use Closure;
use Illuminate\Http\Request;
use Omnibridge\Contract\Access;
use Omnibridge\Laravel\Legacy\Http\Localize as Legacy;
use Symfony\Component\HttpFoundation\Response;

if (!class_exists(Locale::class)) {
    // omnisense/omnibase 4.x: the rule is still this bridge's former copy (Legacy\, removed once 4.x is no longer served).
    final class Localize extends Legacy
    {
    }
} else {
    /**
     * The page's language, decided by omnibase's Locale rule (step F5e of its plan, slice 2), as its
     * LocalizerSubscriber asks it under Symfony: a route of one language ("app_timeline.fr", Routing\
     * ActionRoutes), the member's own, the visitor's choice (the LANG cookie SwitchLocale sets), the browser's
     * among the site's (omnibridge.locales.available), the site's (omnibridge.locales.default, else
     * app.locale) - Laravel's locale, its language (fr) -; then remembered in LANG for the next page (en-GB).
     *
     * In the web group of an omnibase site under Laravel (bootstrap/app.php: $middleware->web(append:
     * [Localize::class])), the LANG cookie left unencrypted, as Symfony writes it.
     */
    final class Localize
    {
        /** omnibase's cookie of the visitor's language. */
        public const COOKIE = Locale::COOKIE;

        public function handle(Request $request, Closure $next): Response
        {
            $available = array_values(array_filter((array) config('omnibridge.locales.available', []), 'is_string'));
            $rule = new Locale(array_values(array_unique(array_map(static fn (string $locale) => substr($locale, 0, 2), $available))), static fn (string $locale): string => self::locale(substr($locale, 0, 2), $available));
            $declared = $request->route('_locale');
            $user = app()->bound(Access::class) ? app(Access::class)->getUser() : null;
            $locale = $rule->decide(
                \is_string($declared) && '' !== $declared ? $declared : null,
                $request->cookies->get(self::COOKIE),
                \is_object($user) && method_exists($user, 'getLocale') ? $user->getLocale() : null,
                false,
                $rule->browser($request->headers->get('Accept-Language'), static fn (array $languages): ?string => $request->getPreferredLanguage($languages)),
                self::fallback(),
            );
            app()->setLocale(substr($locale, 0, 2));

            $response = $next($request);
            if (!$response instanceof Response) {
                throw new \LogicException('The page gave no response.');
            }
            $set = [] !== array_filter($response->headers->getCookies(), static fn ($cookie): bool => self::COOKIE === $cookie->getName());

            return Decisions::cookies(Locale::remember($locale, $request->cookies->get(self::COOKIE), $set), $response, $request->isSecure());
        }

        /** The site's default (omnibridge.locales.default), else Laravel's app.locale. */
        private static function fallback(): string
        {
            $default = config('omnibridge.locales.default');
            $default = \is_string($default) && '' !== $default ? $default : config('app.locale');

            return \is_string($default) ? $default : '';
        }

        /**
         * The site's locale of a language (en-GB for en), as omnibridge.locales.available names it.
         *
         * @param list<string> $available
         */
        private static function locale(string $language, array $available): string
        {
            foreach ($available as $locale) {
                if (str_starts_with($locale, $language)) {
                    return $locale;
                }
            }

            return $language;
        }
    }
}
