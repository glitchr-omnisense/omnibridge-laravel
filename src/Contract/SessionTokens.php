<?php

namespace Omnibridge\Laravel\Contract;

use Illuminate\Contracts\Container\Container;
use Omnibridge\Contract\Tokens;

/**
 * A form's token against cross-site requests: Laravel's session token, the
 * one its CSRF middleware checks, and the same check (the token sent back
 * equal to the session's, compared in constant time). Laravel has one token
 * for every intent, so the intent is not part of it - by design, kept here:
 * Symfony's per-intent tokens narrow what a token leaked from one form can
 * post within the same session; both are bound to the session and renewed
 * with it (the sign-in regenerates it), which is the protection against
 * another site's request. A page then carries the same value for every
 * intent (its forms, data-csrf, the X-CSRF-Token headers, the sign-out link).
 */
final class SessionTokens implements Tokens
{
    public function __construct(private readonly Container $app)
    {
    }

    public function csrf(string $intent): string
    {
        return (string) $this->app->make('session.store')->token();
    }

    public function isValid(string $intent, ?string $token): bool
    {
        $session = (string) $this->app->make('session.store')->token();

        return null !== $token && '' !== $token && '' !== $session && hash_equals($session, $token);
    }
}
