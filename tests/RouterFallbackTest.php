<?php

namespace Omnibridge\Laravel\Tests;

use Illuminate\Support\Facades\Route;
use Omnibridge\Contract as Host;
use Omnibridge\Laravel\Tests\Fixtures\Routing\OtherSite;

/**
 * omnibase's options base.router.fallback (a service answering the route names this host lacks: another site's) and
 * base.router.fallback_locale (a name declared per language read in that language last), read by this host's router:
 * an application configures them, it extends nothing.
 */
final class RouterFallbackTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('base.router.fallback', OtherSite::class);
        $app['config']->set('base.router.fallback_locale', 'en');
    }

    protected function defineRoutes($router): void
    {
        $router->get('/here', static fn () => 'here')->name('here');
        $router->get('/timeline', static fn () => 'timeline')->name('app_timeline.en');
    }

    public function testANameThisHostLacksIsAnsweredElsewhere(): void
    {
        $urls = $this->app->make('Base\Contract\Urls');
        self::assertSame('/here', $urls->path('here'));
        self::assertSame('https://other.example/app_preamble', $urls->path('app_preamble'));

        $routes = $this->app->make(Host\Routes::class);
        self::assertSame('/here', $routes->pattern('here'));
        self::assertSame('/elsewhere', $routes->pattern('elsewhere_only'));
        self::assertSame(['fa-solid fa-globe'], $routes->icons('elsewhere_only'));
    }

    public function testANamePerLanguageIsReadInTheFallbackLanguage(): void
    {
        $this->app->setLocale('fr');
        self::assertSame('/timeline', $this->app->make('Base\Contract\Urls')->path('app_timeline'));
    }
}
