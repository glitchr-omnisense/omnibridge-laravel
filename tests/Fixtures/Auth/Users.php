<?php

namespace Omnibridge\Laravel\Tests\Fixtures\Auth;

use Omniauth\Contract\Accounts;
use Omniauth\Contract\BackupCodes;
use Omniauth\Contract\EmailCodes;
use Omniauth\Contract\Identities;
use Omniauth\Contract\LinkTokens;
use Omniauth\Contract\Passwords;
use Omniauth\Contract\TrustedVersion;
use Omniauth\Exception\AccountRefused;

/** The test application's accounts, in memory, as every store omniauth reads (an application's are its database's). */
final class Users implements Accounts, Passwords, LinkTokens, EmailCodes, BackupCodes, TrustedVersion, Identities
{
    /** @var array<string, User> */
    private array $users = [];

    public function add(User $user): User
    {
        return $this->users[$user->handle] = $user;
    }

    public function byIdentifier(string $identifier): ?object
    {
        foreach ($this->users as $user) {
            if (0 === strcasecmp($user->email, trim($identifier)) || (null !== $user->username && $user->username === trim($identifier))) {
                return $user;
            }
        }

        return null;
    }

    public function byHandle(string $handle): ?object
    {
        return $this->users[$handle] ?? null;
    }

    public function handle(object $account): string
    {
        return $this->of($account)->handle;
    }

    public function identifier(object $account): string
    {
        return $this->of($account)->email;
    }

    public function displayName(object $account): string
    {
        return $this->of($account)->email;
    }

    public function email(object $account): ?string
    {
        $email = $this->of($account)->email;

        return '' !== $email ? $email : null;
    }

    public function check(object $account): void
    {
        if (null !== $this->of($account)->refused) {
            throw new AccountRefused($this->of($account)->refused);
        }
    }

    public function hash(object $account): ?string
    {
        return $this->of($account)->password;
    }

    public function update(object $account, string $hash): void
    {
        $this->of($account)->password = $hash;
    }

    public function issue(object $account, string $hash, \DateTimeImmutable $until): void
    {
        $this->of($account)->links[$hash] = $until->getTimestamp();
    }

    public function consume(string $hash, \DateTimeImmutable $now): ?object
    {
        foreach ($this->users as $user) {
            if (isset($user->links[$hash])) {
                $until = $user->links[$hash];
                unset($user->links[$hash]);

                return $until > $now->getTimestamp() ? $user : null;
            }
        }

        return null;
    }

    public function enabled(object $account): bool
    {
        return $this->of($account)->emailCodeEnabled;
    }

    public function enable(object $account): void
    {
        $this->of($account)->emailCodeEnabled = true;
    }

    public function disable(object $account): void
    {
        $this->of($account)->emailCodeEnabled = false;
    }

    public function remember(object $account, string $hash): void
    {
        $this->of($account)->emailCode = $hash;
    }

    public function remembered(object $account): ?string
    {
        return $this->of($account)->emailCode;
    }

    public function forget(object $account): void
    {
        $this->of($account)->emailCode = null;
    }

    public function hashes(object $account): array
    {
        return $this->of($account)->backupCodes;
    }

    public function replace(object $account, array $hashes): void
    {
        $this->of($account)->backupCodes = $hashes;
    }

    public function version(object $account): int
    {
        return $this->of($account)->trustedVersion;
    }

    public function revokeAll(object $account): void
    {
        ++$this->of($account)->trustedVersion;
    }

    public function owner(string $provider, string $subject): ?object
    {
        foreach ($this->users as $user) {
            if (($user->identities[$provider] ?? null) === $subject) {
                return $user;
            }
        }

        return null;
    }

    public function link(object $account, string $provider, string $subject, ?string $email = null, bool $emailVerified = false): void
    {
        $this->of($account)->identities[$provider] = $subject;
    }

    public function unlink(object $account, string $provider): void
    {
        unset($this->of($account)->identities[$provider]);
    }

    public function used(string $provider, string $subject, \DateTimeImmutable $at): void
    {
    }

    public function providers(object $account): array
    {
        return array_keys($this->of($account)->identities);
    }

    private function of(object $account): User
    {
        return $account instanceof User ? $account : throw new \InvalidArgumentException('Not an account of the test application.');
    }
}
