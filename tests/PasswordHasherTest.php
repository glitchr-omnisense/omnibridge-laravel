<?php

namespace Omnibridge\Laravel\Tests;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Omnibridge\Laravel\Auth\Settings;
use Omnibridge\Laravel\Tests\Fixtures\Auth\User;
use Omnibridge\Laravel\Tests\Fixtures\Auth\Users;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * The hasher config/omniauth.php names, as security.yaml's password_hashers
 * names it (bcrypt, auto...): a site's accounts hashed by Symfony sign in
 * here with their stored hash, an unknown identifier costs the same time (the
 * dummy hash), a user name finds the account, and a hash of a lower cost is
 * rehashed at the sign-in. Marco's account found the first two 500s (the
 * name "bcrypt" handed to password_hash() as it was).
 */
final class PasswordHasherTest extends TestCase
{
    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $config = $app->make(Config::class);
        $config->set('session.driver', 'array');
        $config->set('cache.default', 'array');
        $config->set('auth.guards.web', ['driver' => 'omniauth', 'provider' => 'omniauth']);
        $config->set('auth.providers.omniauth', ['driver' => 'omniauth']);
        $config->set('omnibridge.auth', true);
        $config->set('omniauth', [
            'secret' => AuthTest::SECRET,
            'login_route' => 'login',
            'stores' => ['accounts' => Users::class, 'passwords' => Users::class],
            'password' => ['path' => '/login', 'hasher' => ['algorithm' => 'bcrypt', 'cost' => 5]],
            'throttle' => [],
        ]);
        $app->singleton(Users::class);
    }

    /** @param Router $router */
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(static function () use ($router): void {
            $router->get('/', static fn () => 'home')->name('home');
        });
    }

    private function container(): Application
    {
        return $this->app ?? throw new \LogicException('No application.');
    }

    /** @return \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response> */
    private function signIn(string $identifier, string $password = 'secret-password'): \Illuminate\Testing\TestResponse
    {
        $page = json_decode((string) $this->get('/login')->getContent(), true);
        \assert(\is_array($page));

        return $this->post('/login', ['_username' => $identifier, '_password' => $password, '_csrf_token' => Settings::string($page['csrf'] ?? null)]);
    }

    public function testTheNamedAlgorithmIsSymfonysAndASiteHashSignsIn(): void
    {
        $hasher = $this->container()->make(PasswordHasherInterface::class);
        $this->assertStringStartsWith('$2y$05$', $hasher->hash('x'), 'bcrypt at the cost named, through Symfony\'s factory');

        $users = $this->container()->make(Users::class);
        $user = $users->add(new User('u1', 'ada@example.org', password_hash('secret-password', \PASSWORD_BCRYPT, ['cost' => 5]), username: 'ada'));

        $this->signIn('ada@example.org')->assertRedirect('/');
        $this->assertSame($user, $this->container()->make(\Omnibridge\Contract\Access::class)->getUser());
    }

    public function testAnUnknownIdentifierIsRefusedNot500(): void
    {
        $this->signIn('nobody@example.org')->assertRedirect('/login');
        $page = json_decode((string) $this->get('/login')->getContent(), true);
        \assert(\is_array($page));
        $this->assertSame('Invalid credentials.', $page['error'], 'the dummy hash (the same time as for a wrong password) is made with the named algorithm');
    }

    public function testAUserNameFindsTheAccount(): void
    {
        $users = $this->container()->make(Users::class);
        $user = $users->add(new User('u2', 'bob@example.org', password_hash('secret-password', \PASSWORD_BCRYPT, ['cost' => 5]), username: 'bob'));

        $this->signIn('bob')->assertRedirect('/');
        $this->assertSame($user, $this->container()->make(\Omnibridge\Contract\Access::class)->getUser());
    }

    public function testAHashOfALowerCostIsRehashedAtTheSignIn(): void
    {
        $users = $this->container()->make(Users::class);
        $user = $users->add(new User('u3', 'cid@example.org', password_hash('secret-password', \PASSWORD_BCRYPT, ['cost' => 4]), username: 'cid'));

        $this->signIn('cid@example.org')->assertRedirect('/');
        $this->assertStringStartsWith('$2y$05$', (string) $user->password, 'rehashed at the cost named');
        $this->assertTrue(password_verify('secret-password', (string) $user->password));
    }
}
