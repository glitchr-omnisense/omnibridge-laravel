<?php

namespace Omnibridge\Laravel\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Omniauth\Contract\Accounts;
use Omniauth\Contract\Passwords;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * Laravel's user provider over omniauth's stores (config/auth.php: driver
 * "omniauth"): an account by its handle (retrieveById) or by what the
 * person typed (retrieveByCredentials: identifier, email or username), its
 * password checked by the hasher omniauth's PasswordMethod uses -
 * symfony/password-hasher, so a password longer than 72 bytes verifies as
 * under Symfony - and rehashed when that hasher says so. No remember token:
 * the remember-me cookie is signed (RememberMe).
 */
final class OmniauthUserProvider implements UserProvider
{
    public function __construct(
        private readonly Accounts $accounts,
        private readonly Passwords $passwords,
        private readonly PasswordHasherInterface $hasher,
    ) {
    }

    public function retrieveById($identifier): ?Authenticatable
    {
        $account = $this->accounts->byHandle(Settings::string($identifier));

        return null === $account ? null : $this->wrap($account);
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token): void
    {
    }

    /** @param array<string, mixed> $credentials */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        $identifier = OmniauthGuard::identifier($credentials);
        $account = '' !== $identifier ? $this->accounts->byIdentifier($identifier) : null;

        return null === $account ? null : $this->wrap($account);
    }

    /** @param array<string, mixed> $credentials */
    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        $hash = $user instanceof AccountUser ? $this->passwords->hash($user->account) : null;
        $password = $credentials['password'] ?? null;

        return null !== $hash && \is_string($password) && '' !== $password && $this->hasher->verify($hash, $password);
    }

    /** @param array<string, mixed> $credentials */
    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false): void
    {
        if (!$user instanceof AccountUser) {
            return;
        }
        $hash = $this->passwords->hash($user->account);
        $password = $credentials['password'] ?? null;
        if (null !== $hash && \is_string($password) && ($force || $this->hasher->needsRehash($hash))) {
            $this->passwords->update($user->account, $this->hasher->hash($password));
        }
    }

    private function wrap(object $account): AccountUser
    {
        return AccountUser::of($account, $this->accounts->handle($account), $this->accounts->identifier($account));
    }
}
