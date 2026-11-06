<?php

namespace Omnibridge\Laravel\Auth\Middleware;

use Base\Auth\Policy\Restriction;
use Base\Auth\Policy\RestrictionDecision;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Translations;
use Omnibridge\Contract\Urls;
use Omnibridge\Laravel\Auth\Policies;
use Omnibridge\Laravel\Http\RequestPage;
use Omnibridge\Laravel\Page\SessionView;
use Symfony\Component\HttpFoundation\Response;

/**
 * omnibase's access restriction on every page of the web group (its Restriction policy, step F5e of its
 * plan; SecuritySubscriber::onAccessRequest() under Symfony): a refused back office answers 404, a denied
 * visitor is sent to the first of `base.settings.access_restriction.redirect_on_deny` (else the sign-in),
 * the notices are flashed.
 */
final class AccessRestriction
{
    public function __construct(private readonly Container $app)
    {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        if (null === $this->app->make(Policies::class)->access()) {
            return $next($request);
        }
        $gate = $this->app->make(Gate::class);
        $decision = (new Restriction(static fn (string $attribute, mixed $subject = null): bool => $gate->allows($attribute, null === $subject ? [] : [$subject])))
            ->decide($this->app->make(Access::class)->getUser(), new RequestPage($request, $this->app->make('Base\Service\ParameterBagInterface')));

        if (null !== $decision->notice) {
            SessionView::add($this->app->make(Session::class), $decision->notice[1], $this->app->make(Translations::class)->trans('@notifications.'.$decision->notice[0]));
        }
        if (RestrictionDecision::NOT_FOUND === $decision->kind) {
            abort(404);
        }
        if (RestrictionDecision::REDIRECT === $decision->kind) {
            return redirect($this->app->make(Urls::class)->path($decision->routes[0] ?? 'security_login'));
        }

        return $next($request);
    }
}
