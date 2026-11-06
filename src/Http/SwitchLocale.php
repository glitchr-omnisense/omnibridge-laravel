<?php

namespace Omnibridge\Laravel\Http;

use Base\Request\LocaleSwitch;
use Base\Service\ParameterBagInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Facades\Route;
use Omnibridge\Contract\Requests;
use Omnibridge\Laravel\Legacy\Http\SwitchLocale as Legacy;
use Omnibridge\Laravel\RouterUrls;
use Omnibridge\Laravel\Routing\TrailingSlash;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

if (!class_exists(LocaleSwitch::class)) {
    // omnisense/omnibase 4.x: the choice is still this bridge's former copy (Legacy\, removed once 4.x is no longer served).
    final class SwitchLocale extends Legacy
    {
    }
} else {
    /**
     * The language menu's links (/locale/en: omnibase's _switch_locale), decided by omnibase's LocaleSwitch (step F5e
     * of its plan, slice 5b), as its LocalizerController asks it under Symfony: a language the site speaks is
     * remembered in the LANG cookie (Localize reads it), written as omnibase's localizer writes it (en-GB), and as a
     * member's own (not while impersonating) - the visitor told so in a flash -; then the page they came from on this
     * site, at its address in that language when its route has one ("name.en"), else its own; a referring path no
     * route of this host matches, another site's, none, the index. A language the site does not speak, the index;
     * none given (/locale), the choice forgotten and the index.
     *
     *     Route::get('/locale/{_locale?}', SwitchLocale::class)->name('_switch_locale');
     */
    final class SwitchLocale
    {
        public function __invoke(Request $request, ?string $_locale = null): RedirectResponse
        {
            $switch = app(LocaleSwitch::class);
            $locale = $switch->choose($_locale);
            if (null === $locale) {
                $response = redirect(self::index());
                if (null === $_locale) {
                    $response->headers->clearCookie(LocaleSwitch::COOKIE, '/');
                }

                return $response;
            }

            $message = $switch->remember($_locale, $locale);
            if ('' !== $message && app()->bound(Requests::class)) {
                app(Requests::class)->flash('info', $message);
            }
            $path = LocaleSwitch::referred($request->headers->get('referer'), $request->getHost());
            $back = null;
            foreach ($switch->back(null !== $path ? self::match($path) : null, $_locale) as [$name, $parameters]) {
                if (null !== $back = self::path($name, $parameters)) {
                    break;
                }
            }
            $response = redirect($back ?? self::index());
            // The choice, for every page that has no language of its own in its address.
            $response->headers->setCookie(Cookie::create(LocaleSwitch::COOKIE, $locale, 0, '/', null, $request->isSecure(), false, false, Cookie::SAMESITE_LAX));

            return $response;
        }

        /**
         * A path's route as this host matches it: its name and its parameters, as Symfony's router gives a match.
         *
         * @return array<string, mixed>|null
         */
        private static function match(string $path): ?array
        {
            try {
                $route = app('router')->getRoutes()->match(Request::create($path));
            } catch (HttpExceptionInterface) {
                return null;
            }
            $name = $route->getName();

            return null !== $name ? ['_route' => $name] + array_filter($route->parameters(), static fn ($value) => \is_scalar($value) || null === $value) : null;
        }

        /**
         * A route's path, its name read as this host's links read it (RouterUrls: in the page's language, then without a
         * destination, then in the routes' fallback language - "app_galleries.default" is /galleries on a German page,
         * as Symfony's router generates it); null for a route this host does not have.
         *
         * @param array<string, mixed> $parameters
         */
        private static function path(string $name, array $parameters): ?string
        {
            $resolved = app()->bound(RouterUrls::class) ? app(RouterUrls::class)->resolve($name) : (Route::has($name) ? $name : null);
            if (null === $resolved || !Route::has($resolved)) {
                return null;
            }
            try {
                return TrailingSlash::apply(route($resolved, $parameters, false), Route::getRoutes()->getByName($resolved));
            } catch (RouteNotFoundException|UrlGenerationException) {
                return null;
            }
        }

        /** The site's index (base.site.index), else the root. */
        private static function index(): string
        {
            $index = app()->bound(ParameterBagInterface::class) ? app(ParameterBagInterface::class)->get('base.site.index') : null;

            return \is_string($index) && '' !== $index && Route::has($index) ? route($index) : '/';
        }
    }
}
