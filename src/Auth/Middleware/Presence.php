<?php

namespace Omnibridge\Laravel\Auth\Middleware;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Omnibridge\Contract\Access;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Component\HttpFoundation\Response;

/**
 * The presence of the account signed in, as omnibase's SecuritySubscriber records it under Symfony:
 * an omnibase account not active lately (Base\Entity\User::isActive()) is poked - its activeAt now -
 * before the page is drawn, and once more on the answer for an account signed in during the request
 * (a sign-up, a sign-in). Appended to the web group with omnibase's sign-in effects.
 */
final class Presence
{
    public const USER = 'Base\Entity\User';

    public function __construct(private readonly Container $app)
    {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $this->poke();
        $response = $next($request);
        \assert($response instanceof Response);
        $this->poke();

        return $response;
    }

    private function poke(): void
    {
        $user = $this->app->make(Access::class)->getUser();
        $class = self::USER;
        if (!$user instanceof $class || $user->isActive() || !$this->app->bound(UnitOfWork::class)) {
            return;
        }
        $user->poke(new \DateTime('now'));
        $this->app->make(UnitOfWork::class)->flush();
    }
}
