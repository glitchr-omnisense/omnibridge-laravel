<?php

namespace Omnibridge\Laravel\Auth;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Cookie\QueueingFactory as Cookies;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Omniauth\Attempt;
use Omniauth\Contract\Accounts;
use Omniauth\Factor;
use Omniauth\Omniauth;
use Omniauth\Result;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Laravel's guard over omniauth (plan "omniauth", §3.2; config/auth.php:
 * driver "omniauth"): the session keeps the handle of the account signed in,
 * user() reads the account back through omniauth's Accounts at each request
 * (as Security's user provider refreshes it), wrapped for Laravel
 * (AccountUser); a sign-in renews the session's id and sets the signed
 * remember-me cookie when asked (RememberMe); a sign-out forgets both and
 * omniauth's attempt.
 *
 * Who signs in is omniauth's decision, taken by Controllers\SignIn: enter()
 * is what it calls with an authenticated Result. attempt() asks omniauth
 * the password (a second factor required leaves the attempt in the session
 * and answers false: the second factor's page follows); between two factors
 * the guard has no user, and pending() has the attempt (what the gate's
 * IS_AUTHENTICATED_2FA_IN_PROGRESS and Middleware\SecondFactorPending read).
 */
final class OmniauthGuard implements StatefulGuard
{
    use GuardHelpers;

    public const HANDLE = 'omnibridge.auth.handle';

    private ?Request $request = null;
    private bool $viaRemember = false;
    private bool $loggedOut = false;

    public function __construct(
        private readonly string $name,
        UserProvider $provider,
        private readonly Omniauth $omniauth,
        private readonly Accounts $accounts,
        private readonly Contexts $contexts,
        private readonly Session $session,
        private readonly Cookies $cookies,
        private readonly ?RememberMe $rememberMe = null,
        private readonly ?Dispatcher $events = null,
    ) {
        $this->provider = $provider;
    }

    public function user(): ?Authenticatable
    {
        if ($this->loggedOut) {
            return null;
        }
        if (null !== $this->user) {
            return $this->user;
        }
        $handle = $this->session->get(self::HANDLE);
        $account = \is_string($handle) && '' !== $handle ? $this->accounts->byHandle($handle) : null;
        if (null === $account && \is_string($handle)) {
            $this->session->forget(self::HANDLE);
        }
        if (null === $account && null !== $this->rememberMe && null !== $this->request) {
            $cookie = $this->request->cookies->get($this->rememberMe->name());
            $account = $this->rememberMe->account(\is_string($cookie) ? $cookie : null);
            if (null !== $account) {
                $this->viaRemember = true;
                $this->session->migrate(true);
                $this->session->put(self::HANDLE, $this->accounts->handle($account));
            } elseif (\is_string($cookie)) {
                $this->cookies->queue($this->rememberMe->forget($this->request->isSecure()));
            }
        }
        if (null === $account) {
            return null;
        }
        $this->user = $this->wrap($account);
        // A request of a visitor signed in (SessionGuard fires it too; Symfony refreshes its user then):
        // omnibase's ConnectionTracker notes the browser's address, agent and host.
        $this->events?->dispatch(new Authenticated($this->name, $this->user));

        return $this->user;
    }

    /** The account signed in, the application's own object (the wrapper's account). */
    public function account(): ?object
    {
        $user = $this->user();

        return $user instanceof AccountUser ? $user->account : null;
    }

    /** The attempt between two factors: a first factor passed, a second one offered; null otherwise. */
    public function pending(): ?Attempt
    {
        if (null !== $this->user()) {
            return null;
        }
        $attempt = $this->omniauth->attempt();

        return null !== $attempt && null !== $attempt->account() && [] !== $attempt->offered() ? $attempt : null;
    }

    /** Signs an account in, as omniauth concluded (Result::$remember says whether the browser is remembered). */
    public function enter(Result $result): AccountUser
    {
        $account = $result->account ?? throw new \LogicException('An authenticated result has its account.');
        if (!$result->isAuthenticated()) {
            throw new \LogicException('Only an authenticated result signs in.');
        }

        return $this->open($account, $result->remember);
    }

    public function login(Authenticatable $user, $remember = false): void
    {
        $this->open($user instanceof AccountUser ? $user->account : $this->accountOf($user), (bool) $remember);
    }

    public function loginUsingId($id, $remember = false): Authenticatable|false
    {
        $account = $this->accounts->byHandle(Settings::string($id));
        if (null === $account) {
            return false;
        }

        return $this->open($account, (bool) $remember);
    }

    /** @param array<string, mixed> $credentials identifier (or email, or username) and password */
    public function attempt(array $credentials = [], $remember = false): bool
    {
        $result = $this->omniauth->verify(Factor::Password, $this->contexts->of($this->request), [
            'identifier' => self::identifier($credentials),
            'password' => Settings::string($credentials['password'] ?? null),
            'remember' => (bool) $remember,
        ]);
        if (!$result->isAuthenticated()) {
            return false;
        }
        $this->enter($result);

        return true;
    }

    /** @param array<string, mixed> $credentials */
    public function once(array $credentials = []): bool
    {
        if (!$this->validate($credentials)) {
            return false;
        }
        $user = $this->provider->retrieveByCredentials($credentials);
        if (null === $user) {
            return false;
        }
        $this->setUser($user);

        return true;
    }

    public function onceUsingId($id): Authenticatable|false
    {
        $user = $this->provider->retrieveById($id);
        if (null === $user) {
            return false;
        }
        $this->setUser($user);

        return $user;
    }

    /** @param array<string, mixed> $credentials */
    public function validate(array $credentials = []): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        return null !== $user && $this->provider->validateCredentials($user, $credentials);
    }

    public function viaRemember(): bool
    {
        return $this->viaRemember;
    }

    public function logout(): void
    {
        $this->session->forget(self::HANDLE);
        $this->session->regenerateToken();
        $this->omniauth->abandon();
        if (null !== $this->rememberMe && null !== $this->request && $this->request->cookies->has($this->rememberMe->name())) {
            $this->cookies->queue($this->rememberMe->forget($this->request->isSecure()));
        }
        $this->user = null;
        $this->viaRemember = false;
        $this->loggedOut = true;
    }

    public function setRequest(Request $request): self
    {
        $this->request = $request;

        return $this;
    }

    /** The guard's name in config/auth.php. */
    public function name(): string
    {
        return $this->name;
    }

    /** The cookie that remembers this browser, queued on the response. */
    public function remember(object $account): ?Cookie
    {
        if (null === $this->rememberMe) {
            return null;
        }
        $cookie = $this->rememberMe->cookie($account, (bool) $this->request?->isSecure());
        $this->cookies->queue($cookie);

        return $cookie;
    }

    private function open(object $account, bool $remember): AccountUser
    {
        // A new session id for a new sign-in (Security's "migrate"), its data kept.
        $this->session->migrate(true);
        $this->session->put(self::HANDLE, $this->accounts->handle($account));
        $this->loggedOut = false;
        $this->viaRemember = false;
        $this->user = $this->wrap($account);
        if ($remember) {
            $this->remember($account);
        }

        return $this->user;
    }

    private function wrap(object $account): AccountUser
    {
        return AccountUser::of($account, $this->accounts->handle($account), $this->accounts->identifier($account));
    }

    private function accountOf(Authenticatable $user): object
    {
        $handle = Settings::string($user->getAuthIdentifier());

        return $this->accounts->byHandle($handle) ?? throw new \InvalidArgumentException(\sprintf('No account of handle "%s".', $handle));
    }

    /** @param array<string, mixed> $credentials */
    public static function identifier(array $credentials): string
    {
        foreach (['identifier', 'email', 'username'] as $key) {
            if (\is_scalar($credentials[$key] ?? null)) {
                return (string) $credentials[$key];
            }
        }

        return '';
    }
}
