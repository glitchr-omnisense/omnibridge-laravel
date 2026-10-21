<?php

namespace Omnibridge\Laravel\Contract;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Auth\StatefulGuard;
use Omnibridge\Contract\Sessions;
use Omnibridge\Laravel\Auth\AccountUser;

/**
 * The visitor's session ended by Laravel's guard: a user signed out when it
 * is the one signed in (logout() forgets it and its remember-me cookie);
 * false otherwise.
 */
final class GuardSessions implements Sessions
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function signOut(object $user): bool
    {
        $guard = $this->auth->guard();
        $signedIn = $guard->user();
        $signedIn = $signedIn instanceof AccountUser ? $signedIn->account : $signedIn;
        if (!$guard instanceof StatefulGuard || !$guard->check() || $signedIn !== $user) {
            return false;
        }
        $guard->logout();

        return true;
    }
}
