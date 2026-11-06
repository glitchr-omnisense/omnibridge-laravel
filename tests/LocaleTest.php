<?php

namespace Omnibridge\Laravel\Tests;

use Omnibridge\Laravel\Http\Localize;
use Omnibridge\Laravel\Http\SwitchLocale;

/**
 * omnibase's language under Laravel, as its LocalizerSubscriber and
 * LocalizerController give it under Symfony (step R10 of the audit
 * "lesringostars face au master plan": lesringostars-laravel's own copies
 * moved here): the LANG cookie, else the browser's language among the
 * site's, else the default; /locale/{lang} keeps the choice as Symfony
 * writes it and goes back to the page it came from on the site.
 */
final class LocaleTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->middleware(Localize::class)->get('/page', static fn () => app()->getLocale());
        $router->get('/locale/{_locale?}', SwitchLocale::class)->name('_switch_locale');
        // A page of one name in every language, a page with an address of its own in English (app_news.en).
        $router->get('/{destination}/articles', static fn () => 'articles')->name('app_articles');
        $router->get('/{destination}/news', static fn () => 'news')->name('app_news');
        $router->get('/en/{destination}/news', static fn () => 'news')->name('app_news.en');
    }

    public function testThePagesLanguageIsTheVisitorsChoiceElseTheBrowsersElseTheDefault(): void
    {
        $this->assertSame('fr', $this->get('/page', ['Accept-Language' => 'ja'])->getContent(), 'the default');
        $this->assertSame('de', $this->get('/page', ['Accept-Language' => 'de-DE,de;q=0.9'])->getContent(), 'the browser\'s, among the site\'s');
        $this->assertSame('en', $this->withUnencryptedCookie(Localize::COOKIE, 'en_GB')->get('/page', ['Accept-Language' => 'de'])->getContent(), 'the visitor\'s choice first');
        $this->assertSame('fr', $this->withUnencryptedCookie(Localize::COOKIE, 'ja_JP')->get('/page', ['Accept-Language' => 'ja'])->getContent(), 'a choice the site does not speak: not a choice');
    }

    /** No language of the site's configured, none from the browser, no default: Laravel's own app.locale. */
    public function testWithoutLocalesConfiguredTheLanguageIsLaravels(): void
    {
        config(['omnibridge.locales.available' => [], 'omnibridge.locales.default' => null, 'app.locale' => 'it']);

        $this->assertSame('it', $this->get('/page', ['Accept-Language' => ''])->getContent());
    }

    public function testTheLanguageShownIsRememberedForTheNextPage(): void
    {
        $this->get('/page', ['Accept-Language' => 'de-DE'])->assertPlainCookie(Localize::COOKIE, 'de-DE');
        $kept = $this->withUnencryptedCookie(Localize::COOKIE, 'de-DE')->get('/page', ['Accept-Language' => 'de-DE']);
        $this->assertSame([], $kept->headers->getCookies(), 'already remembered: nothing written');
    }

    /**
     * The language menu, decided by omnibase's LocaleSwitch (its plan "omnibase agnostic", step F5e slice 5b) as its
     * LocalizerController decides it under Symfony: LANG written as omnibase's localizer writes it (en-GB), back to the
     * referring page's route - in that language where it has one -, the index where this host matches no route, for
     * another site's page, for a language the site does not speak and when none is given (the choice forgotten).
     */
    public function testTheLanguageMenuKeepsTheChoiceAndGoesBack(): void
    {
        if (class_exists(\Base\Request\LocaleSwitch::class)) {
            // omnibase's localizer as it reads this suite's languages (omnibridge.locales.available), without the rest
            // of omnibase's services, which this test does not register (the twin's tests/Feature/LocaleSwitchTest: all).
            $localizer = new class extends \Base\Service\Localizer {
                public function __construct()
                {
                }

                public static function getAvailableLocaleLangs(): array
                {
                    return ['fr', 'en', 'de'];
                }

                public static function getAvailableLocales(): array
                {
                    return ['fr-FR', 'en-GB', 'de-DE'];
                }

                public function getLocale(?string $locale = null): string
                {
                    return ['fr' => 'fr-FR', 'en' => 'en-GB', 'de' => 'de-DE'][substr((string) $locale, 0, 2)] ?? (string) $locale;
                }

                public function getLocaleLang(?string $locale = null): string
                {
                    return substr((string) $locale, 0, 2);
                }

                public function getLocaleLangName(?string $locale = null): ?string
                {
                    return $locale;
                }
            };
            $this->app->instance(\Base\Request\LocaleSwitch::class, new \Base\Request\LocaleSwitch($localizer));
        }

        if (!class_exists(\Base\Request\LocaleSwitch::class)) {
            // omnisense/omnibase 4.x: the former copy (Legacy\Http\SwitchLocale), back to the path as it was.
            $this->withHeader('Referer', 'http://localhost/japon/articles?page=2')->get('/locale/en')
                ->assertRedirect('/japon/articles')
                ->assertPlainCookie(Localize::COOKIE, 'en_GB');
            $this->withHeader('Referer', 'https://elsewhere.example/x')->get('/locale/de')->assertRedirect('/');
            $this->get('/locale/ja')->assertRedirect('/');

            return;
        }

        $this->withHeader('Referer', 'http://localhost/japon/articles?page=2')->get('/locale/en')
            ->assertRedirect('/japon/articles')
            ->assertPlainCookie(Localize::COOKIE, 'en-GB');
        $this->withHeader('Referer', 'http://localhost/japon/news')->get('/locale/en')->assertRedirect('/en/japon/news');
        $this->withHeader('Referer', 'http://localhost/nowhere/at/all')->get('/locale/de')->assertRedirect('/');
        $this->withHeader('Referer', 'https://elsewhere.example/x')->get('/locale/de')->assertRedirect('/');

        $forgotten = $this->withHeader('Referer', 'http://localhost/japon/articles')->get('/locale');
        $forgotten->assertRedirect('/');
        $this->assertSame('', (string) $forgotten->headers->getCookies()[0]->getValue(), 'none given: the choice forgotten');
        $chosen = $this->get('/locale/ja');
        $chosen->assertRedirect('/');
        $this->assertSame([], $chosen->headers->getCookies(), 'a language the site does not speak: nothing remembered');
    }
}
