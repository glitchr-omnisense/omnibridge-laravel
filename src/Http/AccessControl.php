<?php

namespace Omnibridge\Laravel\Http;

use Base\Request\Rule\AccessControl as Rule;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Omnibridge\Contract\Access;
use Omnibridge\Laravel\Auth\OmniauthGuard;
use Symfony\Component\HttpFoundation\Response;

/**
 * The pages an application keeps for some accounts, by their path (omnibase's Base\Request\Rule\AccessControl,
 * step F5e of its plan, slice 2): omnibridge.access_control lists them as Symfony's security configuration
 * does (`access_control`, `^/profile` for ROLE_USER) - an omnibase site served by both hosts reads its
 * security.yaml -, and a visitor refused one meets omnibase's entry point (the sign-in, the address kept for
 * after it; 403 once signed in), as Symfony's firewall answers. ROLE_* and the other attributes are the gate's;
 * IS_AUTHENTICATED_* the guard's (IS_AUTHENTICATED_2FA_IN_PROGRESS: an attempt waiting for its second factor).
 * In the web group with omnibase's sign-in effects; nothing where the list is empty.
 */
final class AccessControl
{
    public function __construct(private readonly Container $app)
    {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $entries = (array) config('omnibridge.access_control', []);
        if ([] !== $entries) {
            $access = $this->app->make(Access::class);
            $signedIn = null !== $access->getUser();
            $guard = $this->app->make(Auth::class)->guard();
            $granted = static fn (string $attribute): bool => match ($attribute) {
                'IS_AUTHENTICATED_2FA_IN_PROGRESS' => $guard instanceof OmniauthGuard && null !== $guard->pending(),
                'IS_AUTHENTICATED', 'IS_AUTHENTICATED_FULLY', 'IS_AUTHENTICATED_REMEMBERED' => $signedIn,
                default => $access->isGranted($attribute),
            };
            if (null !== $answer = Decisions::answer($this->app, (new Rule($entries))->decide($request->getPathInfo(), $signedIn, $granted, $request->getUri()))) {
                return $answer;
            }
        }
        $response = $next($request);
        \assert($response instanceof Response);

        return $response;
    }
}
