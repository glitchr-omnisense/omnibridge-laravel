<?php

namespace Omnibridge\Laravel\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The language menu's links (/locale/en: omnibase's _switch_locale), as
 * omnibase's LocalizerController answers them under Symfony: the visitor's
 * choice kept in the LANG cookie (Localize reads it) - a language of the
 * site's (omnibridge.locales.available), written as Symfony names it (en_GB) -,
 * then back to the page they came from on this site; none given, the choice
 * forgotten; a language the site does not speak, the choice forgotten and
 * the home.
 *
 *     Route::get('/locale/{_locale?}', SwitchLocale::class)->name('_switch_locale');
 */
final class SwitchLocale
{
    public function __invoke(Request $request, ?string $_locale = null): RedirectResponse
    {
        $from = (string) $request->headers->get('referer');
        $host = parse_url($from, \PHP_URL_HOST);
        $back = '' !== $from && (null === $host || $host === $request->getHost()) ? (parse_url($from, \PHP_URL_PATH) ?: '/') : '/';
        $response = redirect($back);

        $locale = null;
        foreach ((array) config('omnibridge.locales.available', []) as $each) {
            if (null !== $_locale && str_starts_with($each, substr($_locale, 0, 2))) {
                $locale = str_replace('-', '_', $each);
            }
        }
        if (null === $locale) {
            $response->headers->clearCookie(Localize::COOKIE, '/');

            return null === $_locale ? $response : redirect('/');
        }
        $response->headers->setCookie(Cookie::create(Localize::COOKIE, $locale, 0, '/', null, $request->isSecure(), false, false, Cookie::SAMESITE_LAX));

        return $response;
    }
}
