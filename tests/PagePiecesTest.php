<?php

namespace Omnibridge\Laravel\Tests;

use Base\Screen\Redirect;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Omnibridge\Laravel\Page\RequestView;
use Omnibridge\Laravel\Page\Theme;
use Omnibridge\Laravel\Screen\Responder;
use Omnibridge\Laravel\View\Companions;
use Omnibridge\Laravel\View\OmnibaseFilters;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * What the pages of an omnibase site ask of Laravel beyond its blog (step R27
 * of the audit "lesringostars face au master plan"): app.theme.layout(1),
 * app.request's attributes as Symfony's (the route's parameters) and its uri,
 * a template's companions (page.css.twig, page.mobile.css.twig,
 * page.js.twig), |trans_entity and |obfuscate, a Redirect answered.
 */
final class PagePiecesTest extends TestCase
{
    private const COMPANIONS = self::FIXTURES.'/companions';

    public function testTheLayoutsAreThoseOfTheFolder(): void
    {
        $theme = Theme::of(['modes' => ['auto' => ['id' => 'auto']], 'layouts' => self::COMPANIONS.'/layout'], null);

        $this->assertNotNull($theme);
        $this->assertSame('layout1.html.twig', $theme->getLayout(1));
        $this->assertSame('layout3.html.twig', $theme->getLayout(3));
        $this->assertSame('layout1.html.twig', $theme->getLayout(2), 'a number without its layout: the first');
    }

    public function testTheRequestsAttributesAreItsRoutes(): void
    {
        $request = Request::create('http://site.test/japon/voyages');
        $route = (new Route(['GET'], '{destination}/voyages', []))->name('app_timeline.fr')->bind($request);
        $request->setRouteResolver(static fn () => $route);
        $view = new RequestView($request, 'fr_FR');

        $this->assertSame('japon', $view->attributes->get('destination'));
        $this->assertSame('app_timeline.fr', $view->attributes->get('_route'));
        $this->assertSame(['destination' => 'japon'], $view->attributes->get('_route_params'));
        $this->assertSame('http://site.test/', (new RequestView(Request::create('http://site.test/'), 'fr_FR'))->getUri(), 'the root with its slash, as Symfony\'s');
    }

    public function testATemplatesCompanionsAreAdded(): void
    {
        $twig = new Environment(new FilesystemLoader(self::COMPANIONS));
        $html = $twig->render('page.html.twig', ['title' => 'Voyages']);

        $this->assertSame(
            '<html><head><title>Voyages</title><style>h1 { color: red; }</style><style media=\'all and (max-width: 470px)\'>p { margin: 0; }</style></head><body>Voyages</body><script>console.log("Voyages");</script></html>'."\n",
            Companions::inject($twig, 'page.html.twig', ['title' => 'Voyages', 'colour' => 'red'], $html, ['mobile' => 'all and (max-width: 470px)', 'tablet' => 'all and (min-width: 471px)']),
        );
        $this->assertSame('<p>x</p>', Companions::inject($twig, 'alone.html.twig', [], '<p>x</p>'), 'none, nothing added');
    }

    public function testTheFiltersAreOmnibases(): void
    {
        $filters = new OmnibaseFilters(app(\Omnibridge\Contract\Translations::class), static fn () => app(\Base\Service\Obfuscator::class));
        $names = array_map(static fn ($filter) => $filter->getName(), $filters->getFilters());

        $this->assertSame(['trans_entity', 'trans_entityExists', 'obfuscate'], $names);
        $this->assertSame(['trans_entity', 'trans_entityExists'], array_map(static fn ($filter) => $filter->getName(), (new OmnibaseFilters(app(\Omnibridge\Contract\Translations::class)))->getFilters()), 'no obfuscator bound, no |obfuscate');
    }

    public function testARedirectIsAnswered(): void
    {
        $response = app(Responder::class)->respond(Request::create('/newsletter/x/y'), new Redirect('/japon/articles/le-fuji'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/japon/articles/le-fuji', $response->headers->get('Location'));
    }
}
