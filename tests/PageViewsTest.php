<?php

namespace Omnibridge\Laravel\Tests;

use Base\Database\Schema\EntitySchema;
use Base\Entity\Analytics\PageView;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Omnibridge\Laravel\Omnibase\PageViews;

/**
 * The pages sent, counted in omnibase's analytics under Laravel as omnibridge/symfony's PageViewSubscriber counts them
 * under Symfony (omnibridge/parity's former `page-views` difference): once the answer is sent (a terminable global
 * middleware, Laravel's testing kernel terminating each request as the server does), a GET answered 2xx in
 * text/html; not a POST, a JSON answer, a redirection, a 404, a prefetch, nor the back office's, the framework's or
 * the API's addresses. The visitor's consented cookie adds the visitor's presence and the page's unique view; a
 * crawler's hit is counted as a bot's and gives no presence. Omnibase on, on Doctrine (the suite's persistence).
 */
final class PageViewsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $config = $app->make(Repository::class);
        $config->set('omnibridge.doctrine.entities', [self::FIXTURES.'/Omnibase/Entity']);
        $config->set('omnibridge.omnibase', ['project_dir' => self::FIXTURES.'/Omnibase']);
        $config->set('base', [
            'database' => ['use_custom' => true],
            'attributes' => ['use_custom' => true],
            'twig' => ['editor' => ['storage' => 'local.wysiwyg']],
            'uploader' => ['storage' => 'local.uploads'],
        ]);
        if ('testNothingIsCountedWhenTurnedOff' === $this->name()) {
            $config->set('omnibridge.page_views.enabled', false);
        }
    }

    protected function defineRoutes($router): void
    {
        $page = static fn () => response('<!doctype html><title>page</title>', 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        foreach (['/page', '/other', '/admin/page', '/_wdt/page', '/api/page'] as $path) {
            Route::get($path, $page);
        }
        Route::post('/page', $page);
        Route::get('/data', static fn () => response()->json(['page' => true]));
        Route::get('/moved', static fn () => redirect('/page'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(EntitySchema::class)) {
            self::markTestSkipped('omnibase 4.x does not describe its entities without a persistence.');
        }
        $this->assertSame(0, Artisan::call('omnibridge:schema:create'));
    }

    protected function tearDown(): void
    {
        EncryptCookies::flushState();
        parent::tearDown();
    }

    private function encrypter(): Encrypter
    {
        $encrypter = $this->app?->make(Encrypter::class);
        \assert($encrypter instanceof Encrypter);

        return $encrypter;
    }

    private function kernel(): Kernel
    {
        $kernel = $this->app?->make(\Illuminate\Contracts\Http\Kernel::class);
        \assert($kernel instanceof Kernel);

        return $kernel;
    }

    /** @return array<string, int> views by "path source" */
    private static function views(): array
    {
        $views = [];
        foreach (DB::select('SELECT path, source, views FROM analytics_page_view') as $row) {
            $row = (array) $row;
            \assert(\is_string($row['path']) && \is_string($row['source']) && is_numeric($row['views']));
            $key = $row['path'].' '.$row['source'];
            $views[$key] = ($views[$key] ?? 0) + (int) $row['views'];
        }
        ksort($views);

        return $views;
    }

    public function testAPageSentIsCountedOnceItIsAnswered(): void
    {
        $this->assertTrue($this->kernel()->hasMiddleware(PageViews::class));

        $this->get('/page')->assertOk();
        $this->get('/page')->assertOk();
        $this->get('/other')->assertOk();

        $this->assertSame(['/other '.PageView::SOURCE_HUMAN => 1, '/page '.PageView::SOURCE_HUMAN => 2], self::views());
        $this->assertSame(0, DB::table('analytics_visit')->count(), 'no visitor cookie, no account: no presence');
    }

    public function testWhatIsNoPageViewIsNotCounted(): void
    {
        $this->post('/page')->assertOk();
        $this->get('/data')->assertOk();
        $this->get('/moved')->assertRedirect();
        $this->get('/no-such-page')->assertNotFound();
        $this->get('/page', ['Sec-Purpose' => 'prefetch'])->assertOk();
        $this->get('/page', ['Purpose' => 'prefetch'])->assertOk();
        foreach (['/admin/page', '/_wdt/page', '/api/page'] as $path) {
            $this->get($path)->assertOk();
        }

        $this->assertSame([], self::views());
    }

    public function testAConsentedVisitorIsPresentAndViewsThePageOnce(): void
    {
        $this->assertTrue((new class($this->encrypter()) extends EncryptCookies {
            public function excepted(string $name): bool
            {
                return $this->isDisabled($name);
            }
        })->excepted(PageViews::VISITOR_COOKIE), 'the script\'s cookie is never decrypted');

        $this->withUnencryptedCookie(PageViews::VISITOR_COOKIE, 'visitor-1')->get('/page')->assertOk();
        $this->withUnencryptedCookie(PageViews::VISITOR_COOKIE, 'visitor-1')->get('/page')->assertOk();

        $this->assertSame(['/page '.PageView::SOURCE_HUMAN => 2], self::views());
        $this->assertEquals([(object) ['subject_type' => 'visitor', 'subject_id' => 'visitor-1']], DB::select('SELECT subject_type, subject_id FROM analytics_visit'));
        $this->assertSame(1, DB::table('analytics_page_visit')->where('path', '/page')->count());
    }

    public function testACrawlersHitIsABotsAndGivesNoPresence(): void
    {
        $this->withUnencryptedCookie(PageViews::VISITOR_COOKIE, 'visitor-2')->get('/page', ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->assertOk();

        $this->assertSame(['/page '.PageView::SOURCE_BOT => 1], self::views());
        $this->assertSame(0, DB::table('analytics_visit')->count());
    }

    public function testNothingIsCountedWhenTurnedOff(): void
    {
        $this->assertFalse($this->kernel()->hasMiddleware(PageViews::class));
        $this->get('/page')->assertOk();

        $this->assertSame([], self::views());
    }
}
