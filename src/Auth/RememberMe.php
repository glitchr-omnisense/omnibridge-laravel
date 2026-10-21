<?php

namespace Omnibridge\Laravel\Auth;

use Omniauth\Contract\Accounts;
use Omniauth\Contract\Passwords;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The remember-me cookie (plan "omniauth", §1.9): the account's handle and
 * the cookie's expiry, signed (HMAC-SHA256, the host's secret) with the
 * account's password hash and address - Security's signature_properties
 * ['password', 'email'] - so that a new password or a new address forgets
 * every browser, as the Symfony site's remember-me does. No token is stored.
 *
 * Laravel does not encrypt it (left out of EncryptCookies): its value is its
 * own proof.
 */
final class RememberMe
{
    public function __construct(
        private readonly Accounts $accounts,
        private readonly ?Passwords $passwords,
        #[\SensitiveParameter] private readonly string $secret,
        private readonly string $name = 'REMEMBERME',
        private readonly int $lifetime = 604800,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    /** The cookie that remembers this account on this browser. */
    public function cookie(object $account, bool $secure): Cookie
    {
        $handle = $this->accounts->handle($account);
        $expires = time() + $this->lifetime;
        $value = self::base64($handle).':'.$expires.':'.$this->signature($account, $handle, $expires);

        return Cookie::create($this->name, $value, $expires, '/', null, $secure, true, false, Cookie::SAMESITE_LAX);
    }

    /** The cookie that forgets the browser. */
    public function forget(bool $secure): Cookie
    {
        return Cookie::create($this->name, null, 1, '/', null, $secure, true, false, Cookie::SAMESITE_LAX);
    }

    /** The account a cookie's value remembers, when it is signed for it and not yet expired; null otherwise. */
    public function account(?string $value): ?object
    {
        if (null === $value || 2 !== substr_count($value, ':')) {
            return null;
        }
        [$handle, $expires, $signature] = explode(':', $value);
        $handle = base64_decode(strtr($handle, '-_', '+/'), true);
        if (false === $handle || '' === $handle || !ctype_digit($expires) || (int) $expires < time()) {
            return null;
        }
        $account = $this->accounts->byHandle($handle);
        if (null === $account || !hash_equals($this->signature($account, $handle, (int) $expires), $signature)) {
            return null;
        }

        return $account;
    }

    private function signature(object $account, string $handle, int $expires): string
    {
        $properties = [$account::class, $handle, (string) $expires, (string) $this->passwords?->hash($account), (string) $this->accounts->email($account)];

        return self::base64(hash_hmac('sha256', implode("\0", $properties), $this->secret, true));
    }

    private static function base64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
