<?php

namespace Omnibridge\Laravel\Auth\Middleware;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Omniauth\Contract\State;
use Omnibridge\Contract\Access;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The second factor's enrolment a signed-in account is sent to (step R29 of
 * the audit "lesringostars face au master plan"): omnibase's rule,
 * Base\Auth\EnrolmentPrompt - the offer made once after a sign-in from an
 * unknown browser (the session's flag the sign-in's WarnNewDevice sets), a
 * second factor the administrator or a role requires and the account lacks
 * until the session says "not now" -, as omnibase's SecurityEnrolmentSubscriber
 * asks it under Symfony: a plain page asked is answered by a redirect to the
 * enrolment page (user_settings_enrolment, omnibase's action), everything
 * else goes on. Nothing where omnibase has no such rule, no SecurityPolicy is
 * bound or no enrolment page is routed. Laravel has no impersonation.
 * Appended to the web group by the provider with omnibase's sign-in effects.
 */
final class EnrolmentPrompt
{
    public const RULE = 'Base\Auth\EnrolmentPrompt';
    public const POLICY = 'Base\Service\SecurityPolicy';
    public const ROUTE = 'user_settings_enrolment';
    /** The session's keys: SecurityPolicy::SESSION_NEW_DEVICE_PROMPT, SESSION_ENROLMENT_SKIPPED */
    public const OFFERED = 'security_new_device_prompt';
    public const SKIPPED = 'security_2fa_enrolment_skipped';

    public function __construct(private readonly Container $app)
    {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        if ($this->due($request)) {
            return new RedirectResponse(route(self::ROUTE));
        }
        $response = $next($request);
        \assert($response instanceof Response);

        return $response;
    }

    private function due(Request $request): bool
    {
        if (!class_exists(self::RULE) || !$this->app->bound(self::POLICY) || !$this->app->make(Router::class)->has(self::ROUTE)) {
            return false;
        }
        $rule = self::RULE;
        if (!$rule::interrupts($request->getMethod(), $request->isXmlHttpRequest(), $request->getAcceptableContentTypes(), $request->getPathInfo())) {
            return false;
        }

        $user = $this->app->make(Access::class)->getUser();
        if (null === $user) {
            return false;
        }
        $session = $this->app->make(State::class);
        $offered = (bool) $session->get(self::OFFERED);
        $prompt = new $rule($this->app->make(self::POLICY));
        if ($prompt->forgetsTheOffer($user, $offered)) {
            $session->remove(self::OFFERED);
            $offered = false;
        }

        return $prompt->due($user, $offered, (bool) $session->get(self::SKIPPED), false);
    }
}
