<?php

namespace Omnibridge\Laravel\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * An account as Laravel's guard holds it (plan "omniauth", §3.2): the
 * application's own object (omnibase's user, which implements nothing of
 * Laravel) wrapped for the Authenticatable contract - its identifier the
 * handle omniauth's Accounts give it, its password the hash, no remember
 * token (the remember-me cookie is signed, RememberMe). The contracts an
 * application reads the visitor through (Omnibridge\Contract\Access::getUser(),
 * the screens' viewer, Twig's app.user) unwrap it: the code of the
 * application sees the same object under both hosts.
 *
 * A method the wrapper has not is the account's: auth()->user()->getEmail().
 */
final class AccountUser implements Authenticatable
{
    /** @param list<string> $roles the account's roles, as Security's getRoles() names them */
    public function __construct(
        public readonly object $account,
        public readonly string $handle,
        public readonly string $identifier,
        public readonly array $roles = [],
    ) {
    }

    /** The account's own roles, when it says them (omnibase's User::getRoles()). */
    public static function of(object $account, string $handle, string $identifier): self
    {
        $roles = [];
        if (method_exists($account, 'getRoles')) {
            foreach ((array) $account->getRoles() as $role) {
                $role = $role instanceof \BackedEnum ? $role->value : $role;
                if (\is_scalar($role)) {
                    $roles[] = (string) $role;
                }
            }
        }

        return new self($account, $handle, $identifier, array_values(array_unique(array_filter($roles))));
    }

    public function getAuthIdentifierName(): string
    {
        return 'handle';
    }

    public function getAuthIdentifier(): string
    {
        return $this->handle;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): ?string
    {
        $hash = method_exists($this->account, 'getPassword') ? $this->account->getPassword() : null;

        return \is_string($hash) && '' !== $hash ? $hash : null;
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    /** @param array<int, mixed> $arguments */
    public function __call(string $method, array $arguments): mixed
    {
        if (!method_exists($this->account, $method) && !method_exists($this->account, '__call')) {
            throw new \BadMethodCallException(\sprintf('Neither %s nor its account (%s) has a %s() method.', self::class, $this->account::class, $method));
        }

        return $this->account->$method(...$arguments);
    }

    public function __get(string $name): mixed
    {
        return $this->account->$name;
    }

    public function __isset(string $name): bool
    {
        return isset($this->account->$name);
    }
}
