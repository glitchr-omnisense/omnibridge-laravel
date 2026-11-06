<?php

namespace Omnibridge\Laravel\Routing;

use Illuminate\Routing\Route;

/**
 * A route's address with its last slash, as the Symfony site declares it (`/profile/{id}/edit/`): Laravel trims
 * an address's slashes when a route is declared, so its generator leaves the last one out. A route that keeps
 * it says so -
 *
 *     Route::get('/profile/{id}/edit/', ...)->name('user_profileEdit')->trailingSlash();
 *
 * - and RouterUrls gives its addresses with it, the query after it. The macro is registered by
 * OmnibridgeServiceProvider.
 */
final class TrailingSlash
{
    public const ACTION = 'omnibridge.trailing_slash';

    public static function register(): void
    {
        if (!Route::hasMacro('trailingSlash')) {
            Route::macro('trailingSlash', function (bool $keep = true): Route {
                /* @var Route $this */
                $this->action[TrailingSlash::ACTION] = $keep;

                return $this;
            });
        }
    }

    /** The path with the last slash before its query, where the route keeps one. */
    public static function apply(string $path, ?Route $route): string
    {
        if (true !== ($route?->getAction(self::ACTION) ?? false)) {
            return $path;
        }
        [$address, $query] = array_pad(explode('?', $path, 2), 2, null);

        return rtrim($address, '/').'/'.(null !== $query ? '?'.$query : '');
    }
}
