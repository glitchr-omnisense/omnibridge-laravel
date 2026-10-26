<?php

namespace Omnibridge\Laravel\Tests;

use Base\Twig\Extension\DemoTwigExtension;
use Base\Twig\Extension\DeployVersionExtension;
use Base\Twig\Extension\EnumTwigExtension;
use Base\Twig\Extension\IconTwigExtension;
use Base\Twig\Extension\LinkTwigExtension;
use Base\Twig\Extension\MenuTwigExtension;
use Base\Twig\Variable\AdminLinks;
use Illuminate\Support\Facades\Route;
use Omnibridge\Contract\Routes;
use Omnibridge\Laravel\Contract\RouterRoutes;
use Omnibridge\Laravel\Form\FormExtension;
use Omnibridge\Laravel\Form\FormRenderer;
use Omnibridge\Laravel\Form\FormView;
use Omnibridge\Laravel\View\HostExtension;
use Omnibridge\Laravel\View\OmnibaseExtensions;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * omnibase's own Twig extensions in Laravel's pages (audit "lesringostars
 * face au master plan", R24): the container's Environment has the classes the
 * core registers under Symfony, built on Laravel's contracts, and an
 * application's own environment gets the same by extend(); what the templates
 * take from Symfony's bundles (Encore, Stimulus, absolute_url(), |url) is
 * HostExtension's; the forms FormExtension's.
 */
final class OmnibaseTwigTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $config = $app['config'];
        \assert($config instanceof \Illuminate\Contracts\Config\Repository);
        $config->set('omnibridge.theme', ['modes' => ['auto' => ['id' => 'auto'], 'dark' => ['id' => 'dark']], 'filters' => [], 'no_filter' => ['id' => null]]);
        $config->set('omnibridge.icons.metadata', \dirname(__DIR__).'/vendor/omnisense/omnibase/public/metadata');
    }

    private function application(): \Illuminate\Foundation\Application
    {
        \assert(null !== $this->app);

        return $this->app;
    }

    public function testTheContainersEnvironmentHasOmnibasesExtensions(): void
    {
        if (!OmnibaseExtensions::available()) {
            self::markTestSkipped('omnisense/omnibase before R24: no framework-free Twig extensions.');
        }
        $twig = $this->application()->make(Environment::class);

        foreach ([HostExtension::class, MenuTwigExtension::class, EnumTwigExtension::class, LinkTwigExtension::class, IconTwigExtension::class, DeployVersionExtension::class, DemoTwigExtension::class] as $extension) {
            $this->assertTrue($twig->hasExtension($extension), $extension);
        }
        $page = $twig->createTemplate('{{ "fa-solid fa-pencil-alt"|iconify|first|raw }}|{{ demo_mode() ? "demo" : "no demo" }}|{{ demo_accounts()|length }}|{{ deploy_version() is not empty ? "version" : "" }}|{{ "/here"|urlify("here") }}');

        $this->assertSame("<i  class='fa-fw fa-solid fa-pencil-alt'></i>|no demo|0|version|<a href='/here' >here</a>", $page->render());
    }

    public function testAnApplicationsOwnEnvironmentIsExtendedAlike(): void
    {
        $twig = new Environment(new ArrayLoader(['page' => '{{ stimulus_controller("blog_article/list") }}|{{ absolute_url("/assets/a.png") }}|{{ "/images/x"|url }}|{{ none|url }}|{{ html_entry_noscript_tags() }}']));
        $this->application()->make(OmnibaseExtensions::class)->extend($twig);
        $this->application()->instance('request', \Illuminate\Http\Request::create('https://site.test/japon/articles'));

        $this->assertSame('data-controller="blog-article--list"|https://site.test/assets/a.png|https://site.test/images/x||', $twig->render('page', ['none' => null]));
    }

    public function testTheCookieAnswerOnTheSitesConfiguration(): void
    {
        if (!OmnibaseExtensions::available() || !class_exists(\Base\Consent\ConsentConfig::class)) {
            self::markTestSkipped('omnisense/omnibase before R24, or omnibase/consent not installed.');
        }
        $config = $this->application()->make('config');
        \assert($config instanceof \Illuminate\Contracts\Config\Repository);
        $config->set('omnibridge.consent.config', ['countdown' => 7, 'privacy_route' => null, 'features' => ['CHAT' => null]]);
        $twig = $this->application()->make(OmnibaseExtensions::class)->extend(new Environment(new ArrayLoader(['page' => '{{ consent_settings() }}'])));

        $settings = json_decode($twig->render('page'), true);
        $this->assertIsArray($settings);
        $this->assertStringContainsString('7', json_encode($settings) ?: '', 'the site\'s countdown');
        $this->assertStringContainsString('CHAT', json_encode($settings) ?: '', 'its feature');
    }

    public function testTheFormsByTheirViews(): void
    {
        $search = (new FormView('thread_searchbar', ['action' => '/japon/articles']))->add('generic', ['type' => 'search']);
        $twig = new Environment(new ArrayLoader(['page' => '{% set form = form_view("thread:searchbar") %}{{ form_start(form) }}{{ form_widget(form.generic) }}{{ form_end(form) }}|{{ form_proxy("none") is null ? "none" : "" }}']));
        $twig->addExtension(new FormExtension(new FormRenderer($this->application()->make(\Omnibridge\Contract\Translations::class)), ['thread:searchbar' => $search]));

        $html = $twig->render('page');
        $this->assertStringStartsWith('<form name="thread_searchbar" method="post" action="/japon/articles">', $html);
        $this->assertStringContainsString('name="thread_searchbar[generic]"', $html);
        $this->assertStringEndsWith('</form>|none', $html);
    }

    public function testARecordsBackOfficePageWhereTheRoutesAreAnswered(): void
    {
        if (!OmnibaseExtensions::available()) {
            self::markTestSkipped('omnisense/omnibase before R24: no AdminLinks.');
        }
        $elsewhere = new class implements Routes {
            public function pattern(string $name): ?string { return 'admin_crud_articles_edit' === $name ? '/admin/articles/{entityId}/edit' : null; }
            public function match(string $url): ?string { return null; }
            public function icons(string $name): ?array { return 'app_articles' === $name ? ['fa-solid fa-book'] : null; }
        };
        Route::get('/japon/articles', static fn () => '')->name('app_articles_here');
        Route::getRoutes()->refreshNameLookups();
        $this->application()->extend(Routes::class, static fn (RouterRoutes $routes) => $routes->elsewhere($elsewhere));
        $this->application()->extend(\Omnibridge\Laravel\RouterUrls::class, static fn (\Omnibridge\Laravel\RouterUrls $urls) => $urls->elsewhere(static fn (string $name, array $parameters): string => 'https://symfony.test/'.$name.'/'.(\is_scalar($parameters['entityId'] ?? null) ? (string) $parameters['entityId'] : '')));

        $routes = $this->application()->make(Routes::class);
        $this->assertSame('/japon/articles', $routes->pattern('app_articles_here'), 'Laravel\'s own');
        $this->assertSame('/admin/articles/{entityId}/edit', $routes->pattern('admin_crud_articles_edit'), 'answered elsewhere');
        $this->assertSame(['fa-solid fa-book'], $routes->icons('app_articles'));

        $admin = $this->application()->make(OmnibaseExtensions::class)->admin();
        $this->assertInstanceOf(AdminLinks::class, $admin);
        $article = self::article();
        $this->assertSame('https://symfony.test/admin_crud_articles_edit/3', $admin->crudify($article));
    }

    /** A record of App\Entity\Article\Article (declared here when the fixtures have none), id 3. */
    private static function article(): object
    {
        if (!class_exists('App\Entity\Article\Article')) {
            eval('namespace App\Entity\Article; final class Article { public function getId(): int { return 3; } }');
        }
        /** @var class-string $class */
        $class = implode('\\', ['App', 'Entity', 'Article', 'Article']);

        return new $class();
    }
}
