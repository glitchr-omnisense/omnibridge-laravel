<?php

namespace Omnibridge\Laravel\Tests;

use Illuminate\Http\Request;
use Omnibridge\Laravel\Auth\OmnibaseEffects;

/**
 * The domain omnibase's site-wide cookies (the browser's PHPUSERID) are written for under Laravel (R29):
 * omnibase's rule (Base\Routing\CookieDomain, AdvancedRouter::getDomain()'s under Symfony) on the
 * application's base.router configuration; the request's host alone (no Domain) where it configures none.
 */
final class CookieDomainTest extends TestCase
{
    public function testTheRulesDomainOnTheConfiguredDomains(): void
    {
        if (!class_exists('Base\\Routing\\CookieDomain')) {
            self::markTestSkipped('omniscient/omnibase without Base\\Routing\\CookieDomain (before 4.x of R29).');
        }
        $app = $this->app;
        $this->assertNotNull($app);
        $this->assertNull(OmnibaseEffects::cookieDomain($app, Request::create('https://m.example.org/')), 'no router configured: the request\'s host alone');

        config(['base.router' => ['use_fallbacks' => true, 'host_fallbacks' => [['locale' => null, 'domain' => ['example.org', 'localhost']]]]]);
        $this->assertSame('example.org', OmnibaseEffects::cookieDomain($app, Request::create('https://m.example.org/japon')), 'its subdomains share it');
        $this->assertSame('localhost', OmnibaseEffects::cookieDomain($app, Request::create('http://localhost:8000/')));
        $this->assertSame('example.org', OmnibaseEffects::cookieDomain($app, Request::create('https://elsewhere.test/')), 'another host: the first configured');
        $this->assertNull(OmnibaseEffects::cookieDomain($app, null));
    }
}
