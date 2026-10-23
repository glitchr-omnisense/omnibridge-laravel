<?php

namespace Omnibridge\Laravel\Tests;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Session\Session;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Testing\TestResponse;
use Omniauth\Factor;
use Omniauth\Link\LinkMethod;
use Omniauth\OAuth\Testing\FakeProvider;
use Omniauth\Omniauth;
use Omniauth\Otp\SealedTotpSecrets;
use Omniauth\Otp\SodiumSecretBox;
use Omniauth\Testing\RecordingSender;
use Omniauth\WebAuthn\PasskeyRecord;
use Omniauth\WebAuthn\Testing\InMemoryPasskeys;
use Omniauth\WebAuthn\Testing\SoftwareAuthenticator;
use Omnibridge\Contract\Access;
use Omnibridge\Laravel\Auth\AccountUser;
use Omnibridge\Laravel\Auth\Controllers\SignIn;
use Omnibridge\Laravel\Auth\OmniauthGuard;
use Omnibridge\Laravel\Auth\Settings;
use Omnibridge\Laravel\Tests\Fixtures\Auth\TotpSecrets;
use Omnibridge\Laravel\Tests\Fixtures\Auth\User;
use Omnibridge\Laravel\Tests\Fixtures\Auth\Users;
use OTPHP\TOTP;

/**
 * omniauth under Laravel through Omnibridge\Laravel\Auth\ (plan "omniauth",
 * step AU8), in a Testbench application with omnibridge.auth on and every
 * method of config/omniauth.php: each way of signing in signs in, the second
 * factor is asked when the account has one (and only its page opens
 * meanwhile), refusals are omniauth's, the forms' CSRF token is checked, the
 * remember-me and the trusted device follow, the sign-out asks the
 * session's token, the gate answers Security's attributes.
 */
final class AuthTest extends TestCase
{
    public const SECRET = 'omnibridge-auth-test-secret-0123456789abcdef';
    /** The test's own key for the TOTP secrets at rest (base64 of 32 bytes): a fixed test value, no real one. */
    public const TOTP_KEY = 'b21uaWJyaWRnZS10ZXN0LXRvdHAta2V5LTMyLWJ5dGU=';

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $config = $app->make(Config::class);
        $config->set('cache.default', 'array');
        $config->set('session.driver', 'array');
        $config->set('auth.defaults.guard', 'web');
        $config->set('auth.guards.web', ['driver' => 'omniauth', 'provider' => 'omniauth']);
        $config->set('auth.providers.omniauth', ['driver' => 'omniauth']);
        $config->set('omnibridge.auth', true);
        $config->set('omniauth', [
            'secret' => self::SECRET,
            'login_route' => 'login',
            'stores' => [
                'accounts' => Users::class,
                'passwords' => Users::class,
                'link_tokens' => Users::class,
                'email_codes' => Users::class,
                'backup_codes' => Users::class,
                'trusted_version' => Users::class,
                'identities' => Users::class,
                'totp_secrets' => TotpSecrets::class,
                'passkeys' => InMemoryPasskeys::class,
                'sender' => RecordingSender::class,
            ],
            'password' => ['path' => '/login'],
            'link' => ['route' => 'link', 'path' => '/link/{token?}'],
            'email_code' => [],
            'backup_codes' => [],
            'trusted_device' => [],
            'totp' => ['secret_key' => self::TOTP_KEY],
            'passkey' => [],
            'oauth' => ['providers' => [
                'google' => ['service' => 'test.google'],
                // Google by configuration (omniauth/google's GoogleFactory), its return address the check route's path.
                'workspace' => ['type' => 'google', 'client_id' => 'workspace.apps.googleusercontent.com', 'client_secret' => 'secret'],
            ]],
            'remember_me' => [],
            'role_hierarchy' => ['ROLE_ADMIN' => ['ROLE_USER']],
            'throttle' => [],
        ]);

        $app->singleton(Users::class);
        $app->singleton(TotpSecrets::class);
        $app->singleton(RecordingSender::class);
        $app->singleton(InMemoryPasskeys::class, static fn (Application $app) => new InMemoryPasskeys($app->make(Users::class)));
        // A return path: the host of each request completes it.
        $app->singleton('test.google', fn () => new FakeProvider('google', '/connect/google/check'));
    }

    /** @param Router $router */
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(static function () use ($router): void {
            $router->get('/', static fn () => 'home')->name('home');
            $router->get('/member', static fn () => 'member')->middleware('auth')->name('member');
        });
    }

    private function container(): Application
    {
        return $this->app ?? throw new \LogicException('No application.');
    }

    private function users(): Users
    {
        return $this->container()->make(Users::class);
    }

    private function store(): Session
    {
        return $this->container()->make(Session::class);
    }

    /**
     * @param TestResponse<\Symfony\Component\HttpFoundation\Response> $response
     *
     * @return array<string, mixed> a response's JSON
     */
    private static function decoded(TestResponse $response): array
    {
        $data = json_decode((string) $response->getContent(), true);
        \assert(\is_array($data));

        return Settings::stringKeys($data);
    }

    /**
     * @param list<string> $backupCodes
     * @param list<string> $roles
     */
    private function account(?string $totp = null, bool $emailCode = false, array $backupCodes = [], array $roles = ['ROLE_USER']): User
    {
        $user = new User(bin2hex(random_bytes(4)), 'ada'.bin2hex(random_bytes(3)).'@example.org', password_hash('secret-password', \PASSWORD_BCRYPT, ['cost' => 13]), $totp, roles: $roles);
        $user->emailCodeEnabled = $emailCode;
        $user->backupCodes = array_map(static fn (string $c) => password_hash($c, \PASSWORD_DEFAULT), $backupCodes);

        return $this->users()->add($user);
    }

    /** @return array<string, mixed> the sign-in page, as the test template writes it */
    private function loginPage(): array
    {
        $response = $this->get('/login');
        $response->assertOk();

        return self::decoded($response);
    }

    /** @return TestResponse<\Symfony\Component\HttpFoundation\Response> */
    private function signIn(string $email, string $password = 'secret-password', bool $remember = false, ?string $csrf = null): TestResponse
    {
        $csrf ??= Settings::string($this->loginPage()['csrf']);

        return $this->post('/login', ['_username' => $email, '_password' => $password, '_csrf_token' => $csrf] + ($remember ? ['_remember_me' => '1'] : []));
    }

    /** @return array{0: string, 1: string} the second factor's page, and its CSRF token */
    private function secondFactorPage(?string $prefer = null): array
    {
        $response = $this->get('/login/2fa'.(null !== $prefer ? '?preferProvider='.$prefer : ''));
        $this->assertSame(200, $response->getStatusCode(), 'the second factor\'s page opens');
        $html = (string) $response->getContent();
        preg_match('~name="_csrf_token" value="([^"]+)"~', $html, $token);

        return [$html, $token[1] ?? ''];
    }

    /** @return TestResponse<\Symfony\Component\HttpFoundation\Response> */
    private function secondFactor(string $code, bool $trust = false, ?string $prefer = null): TestResponse
    {
        [, $token] = $this->secondFactorPage($prefer);

        return $this->post('/login/2fa/check', ['_auth_code' => $code, '_csrf_token' => $token] + ($trust ? ['_trusted' => '1'] : []));
    }

    /** @return 'signed-in'|'second-factor'|'signed-out'|string */
    private function state(): string
    {
        $response = $this->get('/member');
        if (200 === $response->getStatusCode()) {
            return 'signed-in';
        }
        $location = (string) $response->headers->get('Location');

        return match (true) {
            str_ends_with($location, '/login/2fa') => 'second-factor',
            str_ends_with($location, '/login') => 'signed-out',
            default => $response->getStatusCode().' '.$location,
        };
    }

    private function guard(): OmniauthGuard
    {
        $guard = $this->container()->make(AuthManager::class)->guard();
        \assert($guard instanceof OmniauthGuard);

        return $guard;
    }

    /** Another browser: no session, no cookies, the guard forgotten (one process serves every request of a test). */
    private function newBrowser(): void
    {
        $this->store()->flush();
        $this->container()->make(AuthManager::class)->forgetGuards();
        $this->unencryptedCookies = [];
        $this->defaultCookies = [];
    }

    public function testAPasswordSignsIn(): void
    {
        $user = $this->account();

        $response = $this->signIn($user->email);

        $response->assertRedirect('/');
        $this->assertSame('signed-in', $this->state());
        $authenticated = $this->guard()->user();
        $this->assertInstanceOf(AccountUser::class, $authenticated);
        $this->assertSame($user, $authenticated->account, 'the account itself, wrapped');
        $this->assertSame($user->email, $authenticated->identifier);
        $this->assertSame($user, $this->container()->make(Access::class)->getUser(), 'the Access contract gives the account, not the wrapper');
        $this->assertNull($response->getCookie('REMEMBERME', false), 'no remember-me unless asked');
        $this->get('/login')->assertRedirect('/');
    }

    public function testAWrongPasswordAnUnknownAddressAndAForgedTokenAreRefused(): void
    {
        $user = $this->account();

        $response = $this->signIn($user->email, 'not-the-password');
        $response->assertRedirect('/login');
        $this->assertSame('Invalid credentials.', $this->loginPage()['error']);
        $this->assertSame($user->email, $this->loginPage()['last_username'], 'the identifier typed is shown again');
        $this->assertSame('signed-out', $this->state());

        $this->signIn('nobody@example.org');
        $this->assertSame('Invalid credentials.', $this->loginPage()['error'], 'the same answer for an address nobody has');

        $this->signIn($user->email, csrf: 'forged');
        $this->assertSame('Invalid CSRF token.', $this->loginPage()['error']);
        $this->assertSame('signed-out', $this->state(), 'the right password without the form\'s token signs nobody in');
    }

    public function testTriesAreThrottled(): void
    {
        $user = $this->account();
        for ($i = 0; $i < 5; ++$i) {
            $this->signIn($user->email, 'wrong');
        }

        $this->signIn($user->email);

        $this->assertSame('Too many failed login attempts, please try again in %minutes% minute.', $this->loginPage()['error']);
        $this->assertSame('signed-out', $this->state(), 'even the right password, once the limit is reached');
    }

    public function testTheRememberMeCookieIsSignedWithThePasswordAndTheAddress(): void
    {
        $user = $this->account();

        $response = $this->signIn($user->email, remember: true);
        $cookie = $response->getCookie('REMEMBERME', false);
        $this->assertNotNull($cookie);
        $access = $this->container()->make(Access::class);
        $this->assertTrue($access->isGranted('IS_AUTHENTICATED_FULLY'));

        $this->newBrowser();
        $this->assertSame('signed-out', $this->state());
        $this->withUnencryptedCookie('REMEMBERME', (string) $cookie->getValue());
        $this->assertSame('signed-in', $this->state(), 'the cookie signs in on a new session');
        $this->assertTrue($this->guard()->viaRemember());
        $this->assertTrue($access->isGranted('IS_AUTHENTICATED_REMEMBERED'));
        $this->assertFalse($access->isGranted('IS_AUTHENTICATED_FULLY'), 'remembered, not fully');

        $user->password = password_hash('another-password', \PASSWORD_BCRYPT, ['cost' => 13]);
        $this->newBrowser();
        $this->withUnencryptedCookie('REMEMBERME', (string) $cookie->getValue());
        $this->assertSame('signed-out', $this->state(), 'a new password forgets every browser');
    }

    /**
     * totp.secret_key: TotpSecrets wrapped in omniauth/otp's SealedTotpSecrets
     * (whoever writes it seals), the account's secret kept sealed under the
     * key, the sign-in verified against the opened secret; a secret kept in
     * clear before (the other tests' accounts) still signs in.
     */
    public function testTotpSecretsAreSealedAtRestWhenAKeyIsSet(): void
    {
        $store = $this->container()->make(\Omniauth\Contract\TotpSecrets::class);
        $this->assertInstanceOf(SealedTotpSecrets::class, $store, 'the store wrapped');
        $user = $this->account();
        $secret = TOTP::generate()->getSecret();
        $store->enable($user, $secret);

        $this->assertTrue(SodiumSecretBox::isSealed((string) $user->totpSecret), 'kept sealed');
        $this->assertSame($secret, SodiumSecretBox::fromBase64(self::TOTP_KEY)->open((string) $user->totpSecret), 'under the configured key');
        $this->assertSame($secret, $store->secret($user));

        $this->signIn($user->email)->assertRedirect('/login/2fa');
        $this->secondFactor(TOTP::createFromSecret($secret)->now());
        $this->assertSame('signed-in', $this->state());
    }

    public function testAnAccountWithTotpIsAskedItsCodeAndOnlyItsPageOpens(): void
    {
        $secret = TOTP::generate()->getSecret();
        $user = $this->account(totp: $secret);

        $response = $this->signIn($user->email, remember: true);

        $response->assertRedirect('/login/2fa');
        $this->assertNull($this->guard()->user(), 'no user between two factors');
        $this->assertNotNull($this->guard()->pending());
        $access = $this->container()->make(Access::class);
        $this->assertTrue($access->isGranted('IS_AUTHENTICATED_2FA_IN_PROGRESS'));
        $this->assertFalse($access->isGranted('ROLE_USER'), 'nothing granted between two factors');
        $this->assertSame('second-factor', $this->state(), 'a member\'s page sends to the second factor');
        $this->get('/')->assertRedirect('/login/2fa');
        $this->get('/login')->assertRedirect('/login/2fa');

        [$page] = $this->secondFactorPage();
        $this->assertStringContainsString('<p id="provider">totp</p>', $page);
        $this->assertStringContainsString('href="http://localhost/logout?_csrf_token=', $page, 'the cancel link is the sign-out, with its token');

        $code = TOTP::createFromSecret($secret)->now();
        $this->secondFactor('000000' === $code ? '111111' : '000000');
        $this->assertSame('second-factor', $this->state(), 'a wrong code keeps the visitor at the second factor');
        [$page] = $this->secondFactorPage();
        $this->assertStringContainsString('<p id="error">code_invalid</p>', $page);

        $response = $this->secondFactor($code);
        $response->assertRedirect('/');
        $this->assertSame('signed-in', $this->state());
        $this->assertNotNull($response->getCookie('REMEMBERME', false), 'remember-me asked at the first factor, set at the last');
        $this->assertNull($response->getCookie('trusted_device', false), 'no trusted device unless asked');
        $this->assertFalse($access->isGranted('IS_AUTHENTICATED_2FA_IN_PROGRESS'), 'nobody\'s once signed in');

        $this->get('/login/2fa')->assertRedirect('/login');
    }

    public function testTheSecondFactorsPageIsNoOnesWithoutAFirstFactor(): void
    {
        $this->get('/login/2fa')->assertRedirect('/login');
        $this->post('/login/2fa/check', ['_auth_code' => '123456'])->assertRedirect('/login');
    }

    public function testAnEmailedCodeIsSentHashedAndServesOnce(): void
    {
        $user = $this->account(emailCode: true);
        $sender = $this->container()->make(RecordingSender::class);

        $this->signIn($user->email);
        $this->assertSame('second-factor', $this->state());
        [$page] = $this->secondFactorPage();
        $this->assertStringContainsString('<p id="provider">email</p>', $page, 'scheb/2fa\'s name');

        $message = $sender->last();
        $this->assertSame(Factor::EmailCode, $message?->factor);
        $this->assertMatchesRegularExpression('~^\d{6}$~', $message->secret);
        $this->assertMatchesRegularExpression('~^[0-9a-f]{64}$~', (string) $user->emailCode, 'kept hashed');
        $this->secondFactorPage();
        $this->assertSame(1, $sender->count(), 'not sent again while it lives');

        $this->secondFactor($message->secret);
        $this->assertSame('signed-in', $this->state());
        $this->assertNull($user->emailCode, 'used up');
    }

    public function testABackupCodeStandsForTheTotpOnce(): void
    {
        $user = $this->account(totp: TOTP::generate()->getSecret(), backupCodes: ['A1B2C3-D4E5F6', '11112222']);

        $this->signIn($user->email);
        [$page] = $this->secondFactorPage();
        $this->assertStringContainsString('<p id="providers">totp</p>', $page, 'backup codes are no provider of their own');
        $this->secondFactor('11112222');
        $this->assertSame('signed-in', $this->state());
        $this->assertCount(1, $user->backupCodes);

        $this->newBrowser();
        $this->signIn($user->email);
        $this->secondFactor('11112222');
        $this->assertSame('second-factor', $this->state(), 'not twice');
    }

    public function testATrustedDeviceSkipsTheSecondFactorUntilRevoked(): void
    {
        $secret = TOTP::generate()->getSecret();
        $user = $this->account(totp: $secret);

        $this->signIn($user->email);
        $response = $this->secondFactor(TOTP::createFromSecret($secret)->now(), trust: true);
        $this->assertSame('signed-in', $this->state());
        $cookie = $response->getCookie('trusted_device', false);
        $this->assertNotNull($cookie);
        $claims = json_decode((string) base64_decode(strtr(explode('.', (string) $cookie->getValue())[1] ?? '', '-_', '+/')), true);
        \assert(\is_array($claims));
        $this->assertSame([$user->email, 'main', 0], [$claims['usr'], $claims['fwl'], $claims['vsn']], 'scheb/2fa\'s claims, unencrypted: the Symfony site reads them too');

        $this->newBrowser();
        $this->withUnencryptedCookie('trusted_device', (string) $cookie->getValue());
        $this->signIn($user->email);
        $this->assertSame('signed-in', $this->state(), 'no second factor on a trusted device');

        ++$user->trustedVersion;
        $this->newBrowser();
        $this->withUnencryptedCookie('trusted_device', (string) $cookie->getValue());
        $this->signIn($user->email);
        $this->assertSame('second-factor', $this->state(), 'its devices revoked: asked again');
    }

    public function testASignInLinkServesOnceAndAFailedOneIsNoLink(): void
    {
        $user = $this->account();
        $sender = $this->container()->make(RecordingSender::class);

        $page = self::decoded($this->get('/link'));
        $this->assertSame(['link', null, 'login_token'], [$page['page'], $page['token'], $page['form']]);
        $this->post('/link', ['login_token' => ['email' => $user->email, '_csrf_token' => $page['csrf']]])->assertRedirect('/link');
        $this->assertTrue(self::decoded($this->get('/link'))['link_sent'], 'the page says a link was sent');
        $token = $sender->last()?->secret;
        $this->assertNotNull($token);
        $this->assertSame([LinkMethod::hash($token)], array_keys($user->links), 'the store holds its hash');

        $this->post('/link', ['login_token' => ['email' => 'nobody@example.org', '_csrf_token' => $page['csrf']]])->assertRedirect('/link');
        $this->assertTrue(self::decoded($this->get('/link'))['link_sent'], 'the same answer for an address nobody has');
        $this->assertSame(1, $sender->count());

        $response = $this->get('/link/'.$token);
        $response->assertRedirect('/');
        $this->assertSame('signed-in', $this->state());
        $this->assertNotNull($response->getCookie('REMEMBERME', false), 'a link from a mailbox remembers the browser');

        $this->newBrowser();
        $page = self::decoded($this->get('/link/'.$token));
        $this->assertSame(['link', null], [$page['page'], $page['token']], 'used once: the page answers as for no link');
        $this->assertSame('signed-out', $this->state());
    }

    public function testAPasskeySignsInWithoutTheSecondFactor(): void
    {
        $user = $this->account(totp: TOTP::generate()->getSecret());
        $authenticator = (new SoftwareAuthenticator('http://localhost'))->belongsTo($user->handle);
        $this->container()->make(InMemoryPasskeys::class)->add($user, new PasskeyRecord(base64_encode($authenticator->credentialId), credentialPublicKey: base64_encode($authenticator->publicKey()), userHandle: $user->handle));

        $options = self::decoded($this->json('POST', '/login/passkey/options', []));
        $this->assertSame(['ok', ''], [$options['status'], $options['errorMessage']], 'the bundle\'s JSON');
        $this->assertSame('localhost', $options['rpId']);

        $response = $this->json('POST', '/login/passkey', $authenticator->get($options));
        $this->assertSame(['status' => 'ok', 'errorMessage' => ''], self::decoded($response));
        $this->assertSame('signed-in', $this->state(), 'QA1: no second factor after a passkey');
        $this->assertNotNull($response->getCookie('REMEMBERME', false), 'remembered');

        $this->newBrowser();
        $options = self::decoded($this->json('POST', '/login/passkey/options', []));
        $impostor = (new SoftwareAuthenticator('http://localhost', credentialId: $authenticator->credentialId))->belongsTo($user->handle);
        $response = $this->json('POST', '/login/passkey', $impostor->get($options));
        $response->assertStatus(401);
        $this->assertSame('failed', self::decoded($response)['status']);
        $this->assertSame('signed-out', $this->state(), 'another key\'s signature signs nobody in');
    }

    /**
     * @param array{sub: string, email?: string, email_verified?: bool|string, name?: string} $person
     *
     * @return array{code: string, state: string}
     */
    private function toGoogle(FakeProvider $google, array $person, string $origin = 'http://localhost'): array
    {
        $response = $this->get($origin.'/connect/google');
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith(FakeProvider::AUTHORIZE, $location);

        return $google->consent($location, $person);
    }

    public function testAnIdentityProviderSignsInByItsSubjectAndAsksTheSecondFactor(): void
    {
        $google = $this->container()->make('test.google');
        \assert($google instanceof FakeProvider);
        $user = $this->account();

        // A verified address links an account without a second factor.
        $this->get('/connect/google/check?'.http_build_query($this->toGoogle($google, ['sub' => 'g-1', 'email' => $user->email, 'email_verified' => true])));
        $this->assertSame('signed-in', $this->state());
        $this->assertSame(['google' => 'g-1'], $user->identities);

        // QA2: a linked account with a TOTP is asked it.
        $secret = TOTP::generate()->getSecret();
        $other = $this->account(totp: $secret);
        $other->identities['google'] = 'g-2';
        $this->newBrowser();
        $this->get('/connect/google/check?'.http_build_query($this->toGoogle($google, ['sub' => 'g-2', 'email' => 'elsewhere@example.org'])));
        $this->assertSame('second-factor', $this->state(), 'by its subject, whatever the address, then the second factor');
        $this->secondFactor(TOTP::createFromSecret($secret)->now());
        $this->assertSame('signed-in', $this->state());

        // Nobody: no account made, the identity kept for a sign-up.
        $this->newBrowser();
        $this->get('/connect/google/check?'.http_build_query($this->toGoogle($google, ['sub' => 'g-3', 'email' => 'new@example.org', 'email_verified' => true])))->assertRedirect('/login');
        $identity = $this->store()->get(SignIn::PENDING_IDENTITY);
        \assert(\is_array($identity));
        $this->assertSame('g-3', $identity['subject']);
        $this->assertNull($this->users()->byIdentifier('new@example.org'));
        $this->assertSame('signed-out', $this->state());

        // A forged state: refused before any exchange.
        $this->newBrowser();
        $return = $this->toGoogle($google, ['sub' => 'g-1']);
        $exchanges = \count($google->exchanges);
        $this->get('/connect/google/check?'.http_build_query(['state' => 'forged'] + $return));
        $this->assertSame('signed-out', $this->state());
        $this->assertCount($exchanges, $google->exchanges, 'no code exchanged');
    }

    /**
     * Under a long-running server the providers are built once: the return
     * address must still be the host of each request, at the trip out and at
     * the exchange (one application serves every request of this test, as a
     * worker keeps it).
     */
    public function testTheReturnAddressFollowsTheHostOfEachRequest(): void
    {
        $google = $this->container()->make('test.google');
        \assert($google instanceof FakeProvider);
        $user = $this->account();
        foreach (['lesringostars.localhost', 'twin.localhost'] as $host) {
            $this->newBrowser();
            $location = (string) $this->get("https://$host/connect/workspace")->headers->get('Location');
            parse_str((string) parse_url($location, \PHP_URL_QUERY), $query);
            $this->assertSame("https://$host/connect/workspace/check", $query['redirect_uri'] ?? $location, 'Google by configuration');

            $location = (string) $this->get("https://$host/connect/google")->headers->get('Location');
            parse_str((string) parse_url($location, \PHP_URL_QUERY), $query);
            $this->assertSame("https://$host/connect/google/check", $query['redirect_uri'] ?? $location);
            $this->get("https://$host/connect/google/check?".http_build_query($google->consent($location, ['sub' => 'g-host', 'email' => $user->email, 'email_verified' => true])));
            $this->assertSame('signed-in', $this->state(), $host.': the exchange repeated the trip\'s address');
        }
    }

    public function testAnUnservedProviderIsNoPage(): void
    {
        $this->get('/connect/nobody')->assertNotFound();
    }

    public function testTheSignOutAsksTheSessionsToken(): void
    {
        $user = $this->account();
        $this->signIn($user->email);

        $page = $this->get('/logout');
        $page->assertOk();
        $this->assertStringContainsString('<p id="return">/</p>', (string) $page->getContent());
        preg_match('~href="([^"]+)"~', (string) $page->getContent(), $link);
        $link = html_entity_decode($link[1] ?? '');
        $this->assertStringStartsWith('http://localhost/logout-request?_csrf_token=', $link, 'the confirmation link carries the token');
        $this->assertSame('signed-in', $this->state(), 'a page shown signs nobody out');

        $this->get('/logout-request')->assertForbidden();
        $this->assertSame('signed-in', $this->state(), 'without the token, nothing');

        $this->get($link)->assertRedirect('/');
        $this->assertSame('signed-out', $this->state());
        $this->assertNull($this->store()->get(OmniauthGuard::HANDLE));
        $this->get('/logout')->assertRedirect('/');
    }

    public function testTheGateAnswersSecuritysAttributes(): void
    {
        $access = $this->container()->make(Access::class);
        $this->assertTrue($access->isGranted('PUBLIC_ACCESS'));
        $this->assertFalse($access->isGranted('ROLE_USER'));
        $this->assertFalse($access->isGranted('IS_AUTHENTICATED_REMEMBERED'));

        $admin = $this->account(roles: ['ROLE_ADMIN']);
        $this->signIn($admin->email);
        $this->assertTrue($access->isGranted('ROLE_ADMIN'));
        $this->assertTrue($access->isGranted('ROLE_USER'), 'ROLE_ADMIN includes ROLE_USER (role_hierarchy)');
        $this->assertFalse($access->isGranted('ROLE_SUPERADMIN'));
        $this->assertTrue($access->isGranted('IS_AUTHENTICATED_FULLY'));
        $this->assertFalse($access->isGranted('edit-settings'), 'an ability that is no attribute of Security\'s stays the gate\'s own (none defined: refused)');
    }

    public function testTheGuardIsLaravelsToo(): void
    {
        $user = $this->account();
        $this->get('/');
        $guard = $this->guard();

        $this->assertFalse($guard->attempt(['email' => $user->email, 'password' => 'wrong']));
        $this->assertTrue($guard->attempt(['email' => $user->email, 'password' => 'secret-password']));
        $this->assertSame($user->handle, $guard->id());
        $this->assertTrue($guard->validate(['username' => $user->email, 'password' => 'secret-password']));
        $provided = $this->container()->make(AuthManager::class)->createUserProvider('omniauth')?->retrieveById($user->handle);
        $this->assertInstanceOf(AccountUser::class, $provided);
        $this->assertSame($user->email, $provided->identifier);

        $guard->logout();
        $this->assertNull($guard->user());
        $this->assertNull($this->container()->make(Omniauth::class)->attempt(), 'the attempt is forgotten too');
    }
}
