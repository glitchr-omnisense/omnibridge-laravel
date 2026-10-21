<?php

namespace Omnibridge\Laravel\Auth;

use Illuminate\Routing\Router;
use Omnibridge\Laravel\Auth\Controllers\Ceremony;
use Omnibridge\Laravel\Auth\Controllers\SignIn;

/**
 * The routes of omniauth's sign-in, at the addresses and under the names
 * config/omniauth.php gives them - by default those the Symfony site has
 * (omnibase's security_login, security_logout, security_logoutRequest,
 * security_loginWithToken; scheb/2fa's 2fa_login and 2fa_login_check; the
 * passkey's; each identity provider's oauth_<name> and oauth_<name>_check),
 * so the pages and scripts that name them do not change. In the web group;
 * the forms' tokens are omniauth's to check (the Tokens contract), not
 * Laravel's CSRF middleware's, which reads another field (PreventRequestForgery
 * in Laravel 13, ValidateCsrfToken in 12, VerifyCsrfToken before).
 *
 * Declared by the provider (omniauth.routes), or by an application that
 * wants them elsewhere: Routes::register($router, $settings).
 */
final class Routes
{
    /** Laravel's CSRF middleware, by its name in each version: the sign-in's routes run without it. */
    public const CSRF_MIDDLEWARE = [
        'Illuminate\Foundation\Http\Middleware\PreventRequestForgery',
        'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken',
        'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken',
        'App\Http\Middleware\VerifyCsrfToken',
    ];

    public static function register(Router $router, Settings $settings): void
    {
        $router->middleware('web')->withoutMiddleware(self::CSRF_MIDDLEWARE)->group(static function (Router $router) use ($settings): void {
            if ($settings->passwordEnabled()) {
                $router->match(['GET', 'POST'], Settings::string($settings->password['path']), [SignIn::class, 'login'])->name($settings->loginRoute);
            }
            if (null !== $settings->link) {
                $router->get(Settings::string($settings->link['path']), [SignIn::class, 'link'])->name(Settings::string($settings->link['route']));
                $router->post(Settings::string($settings->link['path']), [SignIn::class, 'requestLink']);
            }
            $second = $settings->secondFactor;
            $router->get(Settings::string($second['form_path']), [Ceremony::class, 'secondFactorPage'])->name(Settings::string($second['form_route']));
            $router->match(['GET', 'POST'], Settings::string($second['check_path']), [SignIn::class, 'secondFactorCheck'])->name(Settings::string($second['check_route']));
            if (null !== $settings->passkey) {
                $router->post(Settings::string($settings->passkey['options_path']), [Ceremony::class, 'passkeyOptions'])->name(Settings::string($settings->passkey['options_route']));
                $router->post(Settings::string($settings->passkey['path']), [SignIn::class, 'passkey'])->name(Settings::string($settings->passkey['route']));
            }
            foreach ($settings->providers as $name => $provider) {
                $router->get(Settings::string($provider['start_path'] ?? null), [Ceremony::class, 'oauthStart'])->defaults('provider', $name)->name(Settings::string($provider['start_route'] ?? null));
                $router->get(Settings::string($provider['check_path'] ?? null), [SignIn::class, 'oauthCheck'])->defaults('provider', $name)->name(Settings::string($provider['check_route'] ?? null));
            }
            $logout = $settings->logout;
            $router->get(Settings::string($logout['page_path']), [SignIn::class, 'logoutPage'])->name(Settings::string($logout['page_route']));
            $router->match(['GET', 'POST'], Settings::string($logout['path']), [SignIn::class, 'logout'])->name(Settings::string($logout['route']));
        });
    }
}
