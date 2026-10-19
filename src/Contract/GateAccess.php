<?php

namespace Omnibridge\Laravel\Contract;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as Auth;
use Omnibridge\Contract\Access;

/**
 * What the visitor may do, answered by Laravel's gate: an attribute is an
 * ability (a role the application defines as one, "ROLE_ADMIN", or a
 * policy's "edit" on a Thread), allowed or not on the subject; the visitor is
 * the default guard's user.
 */
final class GateAccess implements Access
{
    public function __construct(private readonly Gate $gate, private readonly Auth $auth)
    {
    }

    public function isGranted(mixed $attribute, mixed $subject = null): bool
    {
        return $this->gate->allows($attribute instanceof \UnitEnum ? $attribute : (string) $attribute, null === $subject ? [] : [$subject]);
    }

    public function getUser(): ?object
    {
        return $this->auth->guard()->user();
    }
}
