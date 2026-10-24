<?php

namespace Omnibridge\Laravel\Tests;

use Base\Contract\Runtime;
use Illuminate\Support\Facades\Route;
use Omnibridge\Laravel\CatalogueTranslations;
use Omnibridge\Laravel\RouterUrlGenerator;
use Omnibridge\Laravel\RouterUrls;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/** Addresses and messages, as a template and omnibase's code ask for them. */
final class UrlsTranslationsTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->get('/japon/articles/{slug}', static fn () => '')->name('article');
    }

    public function testARouteIsLaravelsAndAnUnknownOneIsAnsweredElsewhere(): void
    {
        $urls = Runtime::urls();
        $this->assertSame('/japon/articles/le-fuji', $urls->path('article', ['slug' => 'le-fuji', 'page' => null]));
        $this->assertSame('http://localhost/japon/articles/le-fuji', $urls->url('article', ['slug' => 'le-fuji']));

        $this->app->extend(RouterUrls::class, static fn (RouterUrls $urls) => $urls->elsewhere(static fn (string $name, array $parameters, string $locale) => "https://symfony.test/$locale/$name?".http_build_query($parameters)));
        $urls = $this->app->make(\Omnibridge\Contract\Urls::class);
        $this->assertSame('/japon/articles/le-fuji', $urls->path('article', ['slug' => 'le-fuji']));
        $this->assertSame('https://symfony.test/fr/app_privacy?a=1', $urls->path('app_privacy', ['a' => 1]));
        $this->assertSame('https://symfony.test/fr/app_privacy?', $urls->url('app_privacy'));
    }

    public function testAPublicFileIsReadInTheManifest(): void
    {
        $this->assertSame('/assets/logo.1a2b3c.png', Runtime::urls()->asset('./logo.png'));
        $this->assertSame('/favicon.ico', Runtime::urls()->asset('favicon.ico'));
    }

    public function testSymfonysGeneratorInterfaceOverTheContract(): void
    {
        $generator = new RouterUrlGenerator(Runtime::urls());
        $this->assertSame('/japon/articles/le-fuji', $generator->generate('article', ['slug' => 'le-fuji']));
        $this->assertSame('http://localhost/japon/articles/le-fuji', $generator->generate('article', ['slug' => 'le-fuji'], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function testAMessageInThePagesLanguage(): void
    {
        $translations = Runtime::translations();
        $this->assertSame('Accueil', $translations->trans('menu.home'));
        $this->assertSame('Bonjour Ringo', $translations->trans('@messages.menu.welcome', ['name' => 'Ringo']));
        $this->assertSame('Bonjour Ringo', $translations->trans('menu.welcome', ['{name}' => 'Ringo']));
        $this->assertSame('Paul et John', $translations->trans('menu.position', ['Paul', 'John']));
        $this->assertSame('3 articles', $translations->trans('articles', ['count' => 3]));
        $this->assertSame('Accueil', $translations->trans('menu.nested'), 'a message naming another is translated again');
        $this->assertSame('Bonjour Ringo', $translations->trans('@messages.menu.alias', ['name' => 'Ringo']), 'a message that is a "@domain.key" is followed, with the parameters (the twin of lesringostars printed @notifications.login.success.day.back)');
        $this->assertSame('Bonjour Ringo', $translations->trans('menu.chain', ['name' => 'Ringo']), 'and followed again');
        $this->assertSame('', $translations->trans('menu.nowhere'), 'a key no catalogue holds is nothing, as omnibase\'s Translator answers it (R27)');
        $this->assertSame('Some text of its own', $translations->trans('Some text of its own'), 'a text that is no key is itself');
        $this->assertSame('Fil', ucfirst($translations->trans('thread._singular', [], 'entities')));

        app()->setLocale('en');
        $this->assertSame('Home', $translations->trans('menu.home'));
        $this->assertSame('Paul et John', $translations->trans('menu.position', ['Paul', 'John']), 'the fallback language');
        $this->assertSame('Accueil', $translations->trans('menu.home', locale: 'fr'));
    }

    public function testWhetherTheCataloguesHoldAKey(): void
    {
        $translations = Runtime::translations();
        $this->assertTrue($translations->exists('menu.home'));
        $this->assertTrue($translations->exists('@entities.thread._plural'));
        $this->assertTrue($translations->exists('thread._plural', 'entities'));
        $this->assertFalse($translations->exists('thread._plural'));
        $this->assertFalse($translations->exists('menu.nowhere'));
        $this->assertTrue($translations->exists('menu.position', locale: 'en'), 'through the fallback catalogue');
    }

    public function testTheLaterFolderWins(): void
    {
        $site = sys_get_temp_dir().'/omnibridge-site-translations';
        @mkdir($site);
        file_put_contents($site.'/messages+intl-icu.fr.yaml', "menu:\n    home: Le blog\n");
        $translations = new CatalogueTranslations([self::FIXTURES.'/translations', $site], 'fr');

        $this->assertSame('Le blog', $translations->trans('menu.home'));
        $this->assertSame('Bonjour Ringo', $translations->trans('menu.welcome', ['name' => 'Ringo']));
    }

    public function testTwigRendersTheContract(): void
    {
        $html = $this->app->make(Environment::class)->render('page.html.twig');

        $this->assertSame('<a href="/japon/articles/le-fuji">Accueil</a> <img src="/assets/logo.1a2b3c.png"> Bonjour Ringo'."\n", $html);
    }

    public function testTheLocalesAreOmnibasesForm(): void
    {
        $locales = Runtime::locales();
        $this->assertSame('fr-FR', $locales->getLocale());
        $this->assertSame('fr-FR', $locales->getDefaultLocale());
        $this->assertSame(['fr-FR', 'en-GB', 'de-DE'], $locales->getAvailableLocales());
        app()->setLocale('en');
        $this->assertSame('en-GB', $locales->getLocale());
        app()->setLocale('de_DE');
        $this->assertSame('de-DE', $locales->getLocale());
        config()->set('omnibridge.locales.default', 'en');
        $this->app->forgetInstance(\Omnibridge\Contract\Locales::class);
        Runtime::setContainer(null);
        Runtime::setContainer($this->app);
        $this->assertSame(['en-GB', 'fr-FR', 'de-DE'], Runtime::locales()->getAvailableLocales());
    }
}
