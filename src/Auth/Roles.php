<?php

namespace Omnibridge\Laravel\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as Auth;

/**
 * Security's attributes as abilities of Laravel's gate (Gate::before): a
 * role ("ROLE_ADMIN") is granted to an account that holds it or one above
 * it in the hierarchy (security.yaml's role_hierarchy), IS_AUTHENTICATED_FULLY
 * to an account signed in by its proofs (not by its remember-me cookie),
 * IS_AUTHENTICATED_REMEMBERED and IS_AUTHENTICATED to any account signed in,
 * IS_AUTHENTICATED_2FA_IN_PROGRESS to the visitor between two factors
 * (scheb/2fa's name), PUBLIC_ACCESS and IS_AUTHENTICATED_ANONYMOUSLY to
 * everybody. Any other ability is left to the gate's own definitions.
 * So is_granted('ROLE_ADMIN') in a template, and omnibase's Access contract,
 * answer as under Symfony.
 */
final class Roles
{
    /** @param array<string, list<string>> $hierarchy a role and the roles it includes */
    public function __construct(private readonly Auth $auth, private readonly array $hierarchy = [])
    {
    }

    /** The gate's answer for a Security attribute: true, false, or null for an ability that is not one. */
    public function decide(?Authenticatable $user, string $ability): ?bool
    {
        switch ($ability) {
            case 'PUBLIC_ACCESS':
            case 'IS_AUTHENTICATED_ANONYMOUSLY':
                return true;
            case 'IS_AUTHENTICATED':
            case 'IS_AUTHENTICATED_REMEMBERED':
                return null !== $user;
            case 'IS_AUTHENTICATED_FULLY':
                return null !== $user && !$this->viaRemember();
            case 'IS_AUTHENTICATED_2FA_IN_PROGRESS':
                return null === $user && $this->pending();
            case 'IS_IMPERSONATOR':
            case 'IS_REMEMBERED':
                return false;
        }
        if (str_starts_with($ability, 'ROLE_')) {
            return $user instanceof AccountUser && \in_array($ability, $this->reachable($user->roles), true);
        }

        return null;
    }

    /**
     * The roles reachable from these: themselves and every role below them in the hierarchy.
     *
     * @param list<string> $roles
     *
     * @return list<string>
     */
    public function reachable(array $roles): array
    {
        $reachable = [];
        $queue = $roles;
        while (null !== ($role = array_shift($queue))) {
            if (isset($reachable[$role])) {
                continue;
            }
            $reachable[$role] = true;
            foreach ($this->hierarchy[$role] ?? [] as $below) {
                $queue[] = $below;
            }
        }

        return array_keys($reachable);
    }

    private function viaRemember(): bool
    {
        $guard = $this->auth->guard();

        return $guard instanceof OmniauthGuard && $guard->viaRemember();
    }

    private function pending(): bool
    {
        $guard = $this->auth->guard();

        return $guard instanceof OmniauthGuard && null !== $guard->pending();
    }
}
