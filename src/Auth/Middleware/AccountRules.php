<?php

namespace Omnibridge\Laravel\Auth\Middleware;

use Base\Request\Rule\Accounts;
use Base\Request\Rule\Approval;
use Base\Request\Rule\Kicked;
use Base\Request\Rule\Presence;
use Base\Request\Rule\Verification;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Urls;
use Omnibridge\Laravel\Http\Decisions;
use Omnibridge\Laravel\Http\RequestPage;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Component\HttpFoundation\Response;

/**
 * omnibase's account rules on every page of the web group (Base\Request\Rule\Accounts, step F5e of its plan,
 * slice 2; SecuritySubscriber::onKernelRequest() under Symfony): the kick, the presence (also on the answer,
 * for an account signed in during the request), the verification's notice, the approval. Laravel has no
 * impersonation. Appended to the web group with omnibase's sign-in effects.
 */
final class AccountRules
{
    public function __construct(private readonly Container $app)
    {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $access = $this->app->make(Access::class);
        $parameters = $this->app->bound('Base\Service\ParameterBagInterface') ? $this->app->make('Base\Service\ParameterBagInterface') : null;
        $unitOfWork = $this->app->bound(UnitOfWork::class) ? $this->app->make(UnitOfWork::class) : null;
        $rules = new Accounts(
            new Kicked($this->app->bound(Tokens::class) ? $this->app->make(Tokens::class) : null),
            new Presence($unitOfWork),
            new Verification($this->app->make(Urls::class)),
            new Approval($unitOfWork, (bool) $parameters?->get('base.user.register.autoapprove')),
        );
        $decision = $rules->request($access->getUser(), new RequestPage($request, $parameters), static fn (): bool => $access->isGranted('ROLE_ADMIN'));
        if (null !== $answer = Decisions::answer($this->app, $decision)) {
            return $answer;
        }

        $response = $next($request);
        \assert($response instanceof Response);
        $rules->response($access->getUser());

        return $response;
    }
}
