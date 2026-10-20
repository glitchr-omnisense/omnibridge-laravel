<?php

namespace Omnibridge\Laravel\Tests;

use Base\Screen\Cache;
use Base\Screen\File;
use Base\Screen\Fragments;
use Base\Screen\Json;
use Base\Screen\Negotiation;
use Base\Screen\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Omnibridge\Laravel\Screen\Responder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * An action written once, answered under Laravel (step R10 of the audit
 * "lesringostars face au master plan"): omnibase's Negotiation through
 * Screen\Responder - a Template's markup, or its data for a client preferring
 * JSON when it allows it (both varying on Accept); Json with its templates
 * rendered, encoded as Symfony's JsonResponse encodes; a File as it is stored;
 * null a 404; the caching the action asked. A route runs it by
 * Responder::action(): the route's parameters by name, `$query` the query
 * string's, the rest the container's.
 */
final class ResponderTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('omnibridge.twig.paths', [[self::FIXTURES.'/screens', null]]);
    }

    protected function defineRoutes($router): void
    {
        $router->get('/hello/{name}', Responder::action(HelloAction::class));
        $router->get('/feed', Responder::action(HelloAction::class, 'feed'));
        $router->get('/missing/{name}', Responder::action(HelloAction::class, 'missing'));
        $router->get('/file', Responder::action(HelloAction::class, 'file'));
    }

    public function testATemplateIsItsMarkupOrItsDataForAClientPreferringJson(): void
    {
        $html = $this->get('/hello/ada?greeting=Bonjour');
        $html->assertOk();
        $this->assertSame('<p>Bonjour ada</p>', $html->getContent());
        $this->assertContains('Accept', $html->baseResponse->getVary());

        $json = $this->get('/hello/ada?greeting=Bonjour', ['Accept' => 'application/json']);
        $this->assertInstanceOf(JsonResponse::class, $json->baseResponse);
        $this->assertSame('{"greeting":"Bonjour","name":"ada"}', $json->getContent());
        $this->assertContains('Accept', $json->baseResponse->getVary());

        $browser = $this->get('/hello/ada', ['Accept' => 'text/html,application/json;q=0.9']);
        $this->assertSame('<p>Salut ada</p>', $browser->getContent(), 'a browser asking HTML first gets the markup');
        $first = $this->get('/hello/ada', ['Accept' => '*/*, application/json']);
        $this->assertJson($first->getContent(), 'a wildcard names no format: the first type that does decides');
    }

    public function testJsonDataHaveTheirTemplatesRenderedAndAreEncodedAsSymfonysJsonResponse(): void
    {
        $response = $this->get('/feed');
        $response->assertOk();
        $this->assertSame('{"hits":["\u003Cp\u003EBonjour Ada\u003C\/p\u003E"],"html":"\u003Cp\u003ESalut a\u003C\/p\u003E\u003Cp\u003ESalut b\u003C\/p\u003E","n":2}', $response->getContent());
        $this->assertTrue($response->baseResponse->headers->hasCacheControlDirective('public'));
        $this->assertSame('3600', $response->baseResponse->headers->getCacheControlDirective('max-age'));
        $this->assertTrue($response->baseResponse->headers->hasCacheControlDirective('must-revalidate'));
    }

    public function testNoResultIsNoPageAndAFileIsSentAsItIs(): void
    {
        $this->get('/missing/ada')->assertNotFound();

        $file = $this->get('/file');
        $this->assertInstanceOf(BinaryFileResponse::class, $file->baseResponse);
        $this->assertStringStartsWith('text/plain', (string) $file->baseResponse->headers->get('Content-Type'));
        $this->assertTrue($file->baseResponse->headers->hasCacheControlDirective('private'));
    }

    public function testAnApplicationRendersEachKindItsWay(): void
    {
        $this->app->bind(Negotiation::class, fn () => new Negotiation(static fn (string $template, array $context, string $as) => $as.':'.$template));

        $this->assertSame('page:part.html.twig', $this->get('/hello/ada')->getContent());
        $this->assertSame('{"hits":["part:part.html.twig"],"html":"part:part.html.twigpart:part.html.twig","n":2}', $this->get('/feed')->getContent());
    }
}

final class HelloAction
{
    /** @param array<string, mixed> $query */
    public function __invoke(string $name, array $query = []): Template
    {
        return new Template('part.html.twig', ['greeting' => (string) ($query['greeting'] ?? 'Salut'), 'name' => $name], json: true);
    }

    public function feed(): Json
    {
        return new Json([
            'hits' => [new Template('part.html.twig', ['greeting' => 'Bonjour', 'name' => 'Ada'])],
            'html' => new Fragments(new Template('part.html.twig', ['greeting' => 'Salut', 'name' => 'a']), new Template('part.html.twig', ['greeting' => 'Salut', 'name' => 'b'])),
            'n' => 2,
        ], cache: Cache::public(3600, mustRevalidate: true));
    }

    public function missing(string $name): ?Template
    {
        return null;
    }

    public function file(): File
    {
        return new File(__FILE__, 'text/plain', Cache::private());
    }
}
