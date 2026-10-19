<?php

namespace Omnibridge\Laravel\Contract;

use Illuminate\Contracts\Container\Container;
use Omnibridge\Contract\Tokens;

/**
 * A form's token against cross-site requests: Laravel's session token, the
 * one its VerifyCsrfToken middleware checks. Laravel has one token for every
 * intent, so the intent is not part of it.
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
}
