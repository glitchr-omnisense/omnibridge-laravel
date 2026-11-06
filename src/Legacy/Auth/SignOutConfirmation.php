<?php

namespace Omnibridge\Laravel\Legacy\Auth;

use Omnibridge\Contract\Tokens;

/**
 * The sign-out's confirmation under omnisense/omnibase 4.x, which has no Base\Auth\Account\SignOutPage (plan
 * "omnibase agnostic", step F5e slice 5): the test Auth\Controllers\SignIn::logoutPage() wrote before. Removed once
 * 4.x is no longer served.
 */
final class SignOutConfirmation
{
    /** @param array{intent: string, field?: string}|null $csrf */
    public static function confirms(?Tokens $tokens, ?array $csrf, mixed $token): bool
    {
        return null !== $csrf && null !== $tokens && !$tokens->isValid($csrf['intent'], \is_string($token) ? $token : null);
    }
}
