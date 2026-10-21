<?php

namespace Omnibridge\Laravel\Tests\Fixtures\Auth;

use Omniauth\Contract\TotpSecrets as Secrets;

/** The test accounts' authenticator applications: the secret on the account. */
final class TotpSecrets implements Secrets
{
    public function secret(object $account): ?string
    {
        return $account instanceof User ? $account->totpSecret : null;
    }

    public function enable(object $account, string $secret): void
    {
        \assert($account instanceof User);
        $account->totpSecret = $secret;
    }

    public function disable(object $account): void
    {
        \assert($account instanceof User);
        $account->totpSecret = null;
    }
}
