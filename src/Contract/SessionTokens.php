<?php

namespace Omnibridge\Laravel\Contract;

use Base\Contract\Tokens;
use Illuminate\Contracts\Container\Container;

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
