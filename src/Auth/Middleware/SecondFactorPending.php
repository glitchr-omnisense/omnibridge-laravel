<?php

namespace Omnibridge\Laravel\Auth\Middleware;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request;
use Omnibridge\Laravel\Auth\OmniauthGuard;
use Omnibridge\Laravel\Auth\Settings;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Between two factors, the second factor's page and nothing else (plan
 * "omniauth", §3.2; omnibridge/symfony's SecondFactorGate under Symfony): a
 * visitor whose attempt passed a first factor and waits for a second one is
 * sent to the page from any other request - but the page itself, its check,
 * the sign-out, and the routes config/omniauth.php's second_factor.open_routes
 * names. Appended to the web group by the provider.
 */
final class SecondFactorPending
{
    public function __construct(private readonly Auth $auth, private readonly Settings $settings)
    {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $guard = $this->auth->guard();
        $name = (string) $request->route()->getName();
        if ($guard instanceof OmniauthGuard && null !== $guard->pending() && !\in_array($name, $this->settings->openRoutes(), true)) {
            return new RedirectResponse(route(Settings::string($this->settings->secondFactor['form_route'])));
        }
        $response = $next($request);
        \assert($response instanceof Response);

        return $response;
    }
}
