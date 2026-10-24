<?php

namespace Omnibridge\Laravel\Tests;

use Illuminate\Routing\Router;
use Omnibridge\Laravel\Container\Aliases;
use Omnibridge\Laravel\Http\Localize;
use Omnibridge\Laravel\RouterUrls;
use Omnibridge\Laravel\Routing\ActionRoutes;
use Omnibridge\Laravel\Routing\LocalizedNames;
use Omnibridge\Laravel\Tests\Fixtures\Action\Home;
use Omnibridge\Laravel\Tests\Fixtures\Action\Pair;
use Omnibridge\Laravel\Tests\Fixtures\Action\Travels;
use Omnibridge\Laravel\Tests\Fixtures\Aliasing\Named;
use Omnibridge\Laravel\Tests\Fixtures\Aliasing\Only;
use Omnibridge\Laravel\Tests\Fixtures\Aliasing\ThingRepository;
use Omnibridge\Laravel\Tests\Fixtures\Aliasing\Things;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The routes an application's actions declare (symfony/routing's #[Route],
 * the attributes Symfony's router reads on the same classes) registered in
 * Laravel, and the services they alias (#[AsAlias]) bound (step R27 of the
 * audit "lesringostars face au master plan"): names, localized paths with
 * their language, requirements, optional parameters, priorities and
 * environments as Symfony gives them; the action run by Screen\Responder;
 * names read as omnibase's router reads them.
 */
final class ActionRoutesTest extends TestCase
{
    private const ACTIONS = self::FIXTURES.'/Action';

    private function routes(string $env = 'prod'): void
    {
        $router = app('router');
        $this->assertInstanceOf(Router::class, $router);
        $router->middleware(Localize::class)->group(static fn () => ActionRoutes::register($router, ActionRoutes::load([self::ACTIONS], $env)));
        $router->getRoutes()->refreshNameLookups();
    }

    private function route(RouteCollection $routes, string $name): Route
    {
        return $routes->get($name) ?? throw new \LogicException(\sprintf('No route "%s".', $name));
    }

    public function testTheRoutesAreSymfonysInSymfonysOrder(): void
    {
        $routes = ActionRoutes::load([self::ACTIONS], 'prod');

        $this->assertSame(['home.default', 'pair_one', 'travels.en', 'travels.fr', 'travels.default.en', 'travels.default.fr', 'home'], array_keys($routes->all()), 'by file, then priority: the catch-all last; the dev route left out');
        $this->assertSame([Home::class, '__invoke'], ActionRoutes::action($this->route($routes, 'home')));
        $this->assertSame([Pair::class, 'one'], ActionRoutes::action($this->route($routes, 'pair_one')));
        $this->assertSame('fr', $this->route($routes, 'travels.fr')->getDefault('_locale'));
        $this->assertArrayHasKey('pair_dev', ActionRoutes::load([self::ACTIONS], 'dev')->all());
        $this->assertSame('dev', ActionRoutes::environment('local'));
        $this->assertSame('test', ActionRoutes::environment('testing'));
    }

    public function testAnAddressAnswersWithTheSameRouteAsOnSymfony(): void
    {
        $this->routes();

        $this->assertSame(['page' => 'home', 'place' => null], $this->getJson('/')->json());
        $this->assertSame(['page' => 'home', 'place' => 'japon'], $this->getJson('/japon')->json());
        $this->assertSame(['page' => 'travels', 'place' => 'japon', 'from' => '2', 'locale' => 'fr'], $this->getJson('/japon/voyages?from=2')->json(), 'the route of a language is that language');
        $this->assertSame('en', $this->withUnencryptedCookie("LANG", "fr")->getJson("/japon/travels")->json('locale'));
        $this->assertSame(['page' => 'travels', 'place' => null, 'from' => null, 'locale' => 'en'], $this->getJson('/travels')->json());
        $this->get('/JAPON/voyages')->assertNotFound();
        $this->get('/nowhere/voyages')->assertNotFound();
        $this->assertSame('one', $this->getJson('/pair/one')->json());
        $this->get('/pair/dev')->assertNotFound();
        $this->post('/japon/voyages')->assertStatus(405);
    }

    public function testAnEnvironmentsRoutesAreThere(): void
    {
        $this->routes('dev');

        $this->assertSame('dev', $this->getJson('/pair/dev')->json());
    }

    public function testANameIsReadAsOmnibasesRouterReadsIt(): void
    {
        $this->routes();
        $urls = (new RouterUrls())->localized('en');

        app()->setLocale('fr');
        $this->assertSame('/japon/voyages', $urls->path('travels', ['place' => 'japon']));
        $this->assertSame('/voyages', $urls->path('travels.default'));
        app()->setLocale('en');
        $this->assertSame('/japon/travels', $urls->path('travels', ['place' => 'japon']));
        app()->setLocale('de');
        $this->assertSame('/japon/travels', $urls->path('travels', ['place' => 'japon']), 'a language the routes lack: the fallback language');
        $this->assertSame('/', $urls->path('home'), 'a name as given first');
        $this->assertNull($urls->resolve('nothing'));
        $this->assertNull((new RouterUrls())->resolve('travels'), 'not localized: the name as given only');

        $this->assertSame('travels.default.fr', LocalizedNames::resolve('travels', 'fr', ['travels.default.en', 'travels.default.fr']));
        $this->assertSame('x.default.en', LocalizedNames::resolve('x', 'de', ['x.default.en', 'x.en', 'x.fr']), 'the fallback language: the first version in the routes\' order');
    }

    public function testTheAliasesAreTheClassesAttributes(): void
    {
        $aliases = Aliases::read(['Omnibridge\\Laravel\\Tests\\Fixtures\\Aliasing\\' => self::FIXTURES.'/Aliasing']);

        $this->assertSame([Named::class => Only::class, Things::class => ThingRepository::class], $aliases);

        $app = app();
        Aliases::bind($app, $aliases);
        $this->assertInstanceOf(ThingRepository::class, $app->make(Things::class));
        $this->assertSame($app->make(Things::class), $app->make(Things::class), 'shared, as Symfony\'s services');
    }
}
