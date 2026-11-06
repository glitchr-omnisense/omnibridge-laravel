<?php

namespace Omnibridge\Laravel\Http;

use Base\Request\Decision;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Session\Session;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Translations;
use Omnibridge\Contract\Urls;
use Omnibridge\Laravel\Page\SessionView;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * A decision of omnibase's request rules (Base\Request\Decision, omnisense/omnibase 5.0) applied to a Laravel
 * request, as omnibridge/symfony's subscribers apply it to Symfony's events: the notices flashed (translated
 * in @notifications unless they name their domain), the address kept for after the sign-in (url.intended),
 * the account signed out, then a refusal (abort) or a redirection - its cookies on it -, or nothing: the
 * request goes on.
 */
final class Decisions
{
    public static function answer(Container $app, Decision $decision): ?Response
    {
        self::notices($app, $decision);
        if (null !== $decision->remember && $app->bound(Session::class)) {
            $app->make(Session::class)->put('url.intended', $decision->remember);
        }
        $user = $decision->signOut ? $app->make(Access::class)->getUser() : null;
        if (\is_object($user) && method_exists($user, 'logout')) {
            $user->logout();
        }
        if (Decision::DENY === $decision->kind) {
            abort($decision->status);
        }
        if (Decision::REDIRECT !== $decision->kind) {
            return null;
        }

        return self::cookies($decision, redirect($decision->url ?? $app->make(Urls::class)->path((string) $decision->route, $decision->parameters), $decision->status));
    }

    public static function notices(Container $app, Decision $decision): void
    {
        if ([] === $decision->notices || !$app->bound(Session::class)) {
            return;
        }
        foreach ($decision->notices as [$key, $parameters, $importance]) {
            $parameters = array_map(static fn ($p) => \is_object($p) ? ($p instanceof \Stringable || method_exists($p, '__toString') ? (string) $p : '') : $p, $parameters);
            $message = $app->make(Translations::class)->trans(str_starts_with($key, '@') ? $key : '@notifications.'.$key, $parameters);
            SessionView::add($app->make(Session::class), $importance, $message);
        }
    }

    /** The decision's cookies on the answer (a null value clears one), written as Symfony writes them: plain, the whole site. */
    public static function cookies(Decision $decision, Response $response, bool $secure = false): Response
    {
        foreach ($decision->cookies as [$name, $value]) {
            null === $value
                ? $response->headers->clearCookie($name, '/')
                : $response->headers->setCookie(Cookie::create($name, $value, 0, '/', null, $secure, false, false, Cookie::SAMESITE_LAX));
        }

        return $response;
    }
}
