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
    }

    public function testThePagesLanguageIsTheVisitorsChoiceElseTheBrowsersElseTheDefault(): void
    {
        $this->assertSame('fr', $this->get('/page', ['Accept-Language' => 'ja'])->getContent(), 'the default');
        $this->assertSame('de', $this->get('/page', ['Accept-Language' => 'de-DE,de;q=0.9'])->getContent(), 'the browser\'s, among the site\'s');
        $this->assertSame('en', $this->withUnencryptedCookie(Localize::COOKIE, 'en_GB')->get('/page', ['Accept-Language' => 'de'])->getContent(), 'the visitor\'s choice first');
        $this->assertSame('fr', $this->withUnencryptedCookie(Localize::COOKIE, 'ja_JP')->get('/page', ['Accept-Language' => 'ja'])->getContent(), 'a choice the site does not speak: not a choice');
    }

    public function testTheLanguageShownIsRememberedForTheNextPage(): void
    {
        $this->get('/page', ['Accept-Language' => 'de-DE'])->assertPlainCookie(Localize::COOKIE, 'de-DE');
        $kept = $this->withUnencryptedCookie(Localize::COOKIE, 'de-DE')->get('/page', ['Accept-Language' => 'de-DE']);
        $this->assertSame([], $kept->headers->getCookies(), 'already remembered: nothing written');
    }

    public function testTheLanguageMenuKeepsTheChoiceAndGoesBack(): void
    {
        $this->withHeader('Referer', 'http://localhost/japon/articles?page=2')->get('/locale/en')
            ->assertRedirect('/japon/articles')
            ->assertPlainCookie(Localize::COOKIE, 'en_GB');
        $this->withHeader('Referer', 'https://elsewhere.example/x')->get('/locale/de')->assertRedirect('/');

        $forgotten = $this->withHeader('Referer', 'http://localhost/japon/articles')->get('/locale');
        $forgotten->assertRedirect('/japon/articles');
        $this->assertSame('', (string) $forgotten->headers->getCookies()[0]->getValue(), 'none given: the choice forgotten');
        $this->get('/locale/ja')->assertRedirect('/');
    }
}
