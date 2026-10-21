<?php

namespace Omnibridge\Laravel\Tests;

use Illuminate\Routing\Router;
use Omnibridge\Contract\Access;

/**
 * With omnibridge.auth off (the default), nothing of Auth\ is wired: no route
 * of omniauth's, no guard driver, the gate without Security's attributes -
 * the application signs in as it did.
 */
final class AuthOffTest extends TestCase
{
    public function testNothingOfAuthIsWired(): void
    {
        $this->get('/login')->assertNotFound();
        $this->get('/login/2fa')->assertNotFound();
        $this->get('/logout')->assertNotFound();
        $app = $this->app ?? throw new \LogicException('No application.');
        $this->assertFalse($app->make(Access::class)->isGranted('PUBLIC_ACCESS'), 'no Gate::before of omniauth\'s');
        $this->assertFalse($app->make(Router::class)->getRoutes()->hasNamedRoute('2fa_login'));
    }
}
