<?php

namespace Omnibridge\Laravel\Tests;

use Base\Contract;
use Base\Contract\Runtime;
use Base\Screen\Author;
use Base\Screen\Builder\Stories;
use Base\Screen\ContextProvider;
use Base\Screen\Story;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Route;
use Omnibridge\Laravel\Screen\AddressPictures;
use Omnibridge\Laravel\Screen\ScreenContext;
use Omnibridge\Laravel\Screen\WysiwygTexts;

/** What omnibase's screens ask of their framework, answered by Laravel: pictures, texts, the context of the page. */
final class ScreensTest extends TestCase
{
    private const DOCUMENT = '{"time":1700000000000,"blocks":['
        .'{"id":"h1","type":"header","data":{"text":"1. Préparer le voyage","level":2}},'
        .'{"id":"p1","type":"paragraph","data":{"text":"Voir <a href=\"http://localhost/japon/articles?page=2#fin\">le blog</a> et <a href=\"https://example.org/x\">ailleurs</a>."}},'
        .'{"id":"i1","type":"image","data":{"file":{"url":"/wysiwyg/0f8e2a54-1b3c-4d5e-8f9a-0b1c2d3e4f50.jpg"},"caption":"Le Fuji","withBorder":false,"stretched":true}},'
        .'{"id":"h2","type":"header","data":{"text":"Au sommet","level":3}}'
        .'],"version":"2.28.2"}';

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('omnibridge.twig.paths', [[self::FIXTURES.'/templates', null], [\dirname(__DIR__).'/vendor/omnisense/omnibase/src/Resources/views', 'Base']]);
    }

    public function testRuntimeGivesTheScreensContracts(): void
    {
        $this->assertInstanceOf(AddressPictures::class, Runtime::pictures());
        $this->assertSame($this->app->make(Contract\Pictures::class), Runtime::pictures());
        $this->assertInstanceOf(WysiwygTexts::class, Runtime::texts());
        $this->assertSame($this->app->make(Contract\Texts::class), Runtime::texts());
        $this->assertInstanceOf(ScreenContext::class, $this->app->make(ContextProvider::class));
    }

    public function testAPictureIsItsAddressOrTheBuildsAsset(): void
    {
        $pictures = Runtime::pictures();
        $this->assertNull($pictures->picture(null));
        $this->assertNull($pictures->picture(''));
        $this->assertSame('/wysiwyg/a.jpg', $pictures->picture('/wysiwyg/a.jpg', 800));
        $this->assertSame('https://example.org/a.jpg', $pictures->picture('https://example.org/a.jpg'));
        $this->assertSame('/images/logo.png', $pictures->picture(public_path('images/logo.png')));
        $this->assertSame('/assets/logo.png', $pictures->picture('logo.png'), 'an asset of the build, by the manifest\'s key when it has one');
    }

    public function testAStoredPictureIsWhereTheApplicationServesIt(): void
    {
        $pictures = Runtime::pictures()->stored(static fn (string $image, ?int $width) => preg_match('~^[0-9a-f-]{36}$~', $image) ? '/uploads/'.$image.($width ? '?w='.$width : '') : null);

        $this->assertSame('/uploads/41e865aa-fd2b-460f-92f5-d4fd713e999a', $pictures->picture('41e865aa-fd2b-460f-92f5-d4fd713e999a'));
        $this->assertSame('/uploads/41e865aa-fd2b-460f-92f5-d4fd713e999a?w=800', $pictures->picture('41e865aa-fd2b-460f-92f5-d4fd713e999a', 800));
        $this->assertSame('/wysiwyg/a.jpg', $pictures->picture('/wysiwyg/a.jpg'), 'anything else by the rule');
    }

    public function testAnEditorJsTextIsRenderedAsWysiwygRendersIt(): void
    {
        $texts = Runtime::texts();
        $this->assertTrue($texts->isEditor(self::DOCUMENT));
        $this->assertFalse($texts->isEditor('<p>Quill</p>'));
        $this->assertFalse($texts->isEditor(null));

        $html = $texts->render(self::DOCUMENT, ['headings' => true]);
        $this->assertStringStartsWith('<div data-edjs="', trim($html));
        $this->assertStringContainsString('class="codex-container wysiwyg"><h2>', $html);
        $this->assertStringContainsString('<a class="markdown-anchor" id="1-preparer-le-voyage" href="#1-preparer-le-voyage">1. Préparer le voyage</a>', $html, 'the heading its anchor');
        $this->assertStringContainsString('href="/japon/articles?page=2#fin"', $html, 'a link to this host made relative');
        $this->assertStringContainsString('href="https://example.org/x"', $html, 'any other left');
        $this->assertStringContainsString('/wysiwyg/0f8e2a54-1b3c-4d5e-8f9a-0b1c2d3e4f50.jpg', $html, 'a picture left as written');
        $this->assertStringContainsString(htmlspecialchars('"withBorder":""', \ENT_QUOTES), $html, 'every leaf a string, as omnibase\'s enhancers hand them on');
        $this->assertSame('', $texts->render(null));

        $this->assertSame([
            ['tag' => 'h2', 'slug' => '1-preparer-le-voyage', 'title' => '1. Préparer le voyage'],
            ['tag' => 'h3', 'slug' => 'au-sommet', 'title' => 'Au sommet'],
        ], $texts->toc(self::DOCUMENT));
        $this->assertSame([], $texts->toc(''));
    }

    public function testAPictureOfATextIsWhereTheApplicationServesIt(): void
    {
        $texts = Runtime::texts()->images(static fn (string $source) => str_starts_with($source, '/wysiwyg/') ? '/files'.$source : null);

        $this->assertStringContainsString('/files/wysiwyg/0f8e2a54-1b3c-4d5e-8f9a-0b1c2d3e4f50.jpg', $texts->render(self::DOCUMENT));
    }

    public function testAnOlderTextIsQuillsHtmlItsHeadingsAnchored(): void
    {
        $html = Runtime::texts()->render('<h2>Le Fuji</h2><p>Voir <a href="http://localhost/japon">ici</a></p>', ['headings' => true]);

        $this->assertStringStartsWith('<div class="ql-editor wysiwyg"><h2>', trim($html));
        $this->assertStringContainsString('<a class="markdown-anchor" id="le-fuji" href="#le-fuji">Le Fuji</a>', $html);
        $this->assertStringContainsString('href="/japon"', $html);
        $this->assertSame([['tag' => 'h2', 'slug' => 'le-fuji', 'title' => 'Le Fuji']], Runtime::texts()->toc('<h2>Le Fuji</h2>'));
    }

    public function testTheFrameIsTheRequestsLocaleSiteLinksViewerAndTheme(): void
    {
        $this->settings([
            'base.settings.title' => [null, ['fr-FR' => 'Les Ringo Stars', 'en-GB' => 'The Ringo Stars']],
            'base.settings.slogan' => [null, ['fr-FR' => 'りんごスター']],
            'base.settings.logo' => [null, ['fr-FR' => './logo.png']],
        ]);
        Route::get('/', static fn () => '')->name('app_index');
        Route::get('/login', static fn () => '')->name('security_login');
        Route::get('/locale/{_locale?}', static fn () => '')->name('_switch_locale');
        $this->app['router']->getRoutes()->refreshNameLookups();
        config()->set('omnibridge.theme.modes', [
            'auto' => ['id' => 'auto', 'prefers' => 'light dark'],
            'dark' => ['id' => 'dark', 'prefers' => 'dark'],
        ]);
        Date::setTestNow('2026-10-19 12:00:00');

        $frame = $this->app->make(ContextProvider::class)->frame(['destination' => ['slug' => 'japon']]);
        $this->assertSame([
            'locale' => 'fr_FR',
            'site' => ['title' => 'Les Ringo Stars', 'slogan' => 'りんごスター', 'logo' => '/assets/logo.1a2b3c.png', 'age' => date('Y'), 'description' => null],
            'destination' => ['slug' => 'japon'],
            'links' => ['home' => '/', 'login' => '/login', 'register' => null, 'logout' => null, 'locales' => ['fr' => '/locale/fr', 'en' => '/locale/en', 'de' => '/locale/de']],
            'viewer' => null,
            'theme' => ['scheme' => 'light dark'],
        ], $frame->toArray());
        $this->assertSame('http://localhost', $frame->host);
        Date::setTestNow();

        $this->actingAs(new GenericUser(['id' => 7, 'name' => 'Apfelstrudel', 'remember_token' => null]));
        $this->assertSame(['id' => 7, 'name' => 'Apfelstrudel', 'avatar' => null], $this->app->make(ContextProvider::class)->viewer()->toArray());
    }

    public function testAFlashIsReadOnce(): void
    {
        $this->app['session']->start();
        $this->app['session']->flash('success', ['Merci !']);
        $context = $this->app->make(ContextProvider::class);

        $this->assertSame(['Merci !'], $context->flashes('success'));
        $this->assertSame([], $context->flashes('success'));
    }

    public function testACoreScreenIsBuiltOnLaravelWithoutTheApplicationsCode(): void
    {
        $stories = new Stories(Runtime::texts(), Runtime::pictures());
        $text = $stories->text(self::DOCUMENT, ['headings' => true]);
        $story = new Story(id: 1, slug: 'le-fuji', title: 'Le Fuji', url: '/japon/articles/le-fuji', state: 'STATE_PUBLISH', text: $text, authors: [new Author('Apfelstrudel')], cover: Runtime::pictures()->picture('/wysiwyg/a.jpg', 1600));

        $props = $story->toArray();
        $this->assertTrue($props['editor']);
        $this->assertSame($text->html, $props['html']);
        $this->assertSame('au-sommet', $props['toc'][1]['slug']);
        $this->assertSame('/wysiwyg/a.jpg', $props['cover']);
    }
}
