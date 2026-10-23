<?php

namespace Omnibridge\Laravel\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Omnibridge\Twig\MapExtension;
use Omnimap\Model\LatLng;
use Omnimap\Model\MapView;
use Omnimap\Snapshot\DirectoryStorage;
use Omnimap\Snapshot\Snapshots;
use Omnimap\Testing\PlainTiles;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Twig\Environment;

/**
 * omnimap under Laravel through Omnibridge\Laravel\Map\ (config/omnimap.php):
 * a page's Twig has omnimap(); the picture is made by the server from
 * OpenStreetMap's tiles (answered here) and kept on the omnidrive drive
 * local.uploads (a local disk); its tiles are served by the route; an administrator (the gate's ROLE_ADMIN) gets the buttons.
 */
final class MapTest extends TestCase
{
    private const DIR = self::FIXTURES.'/../../.phpunit.cache/omnimap';

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        (new DirectoryStorage(\dirname(self::DIR)))->deleteDirectory(basename(self::DIR));
        // The uploads disk as omnidrive's drive "local.uploads" (omnibridge.storages).
        $config = $app['config'];
        \assert($config instanceof \Illuminate\Contracts\Config\Repository);
        $config->set('filesystems.disks.omnimap-uploads', ['driver' => 'local', 'root' => self::DIR]);
        $config->set('omnibridge.storages', ['local.uploads' => 'omnimap-uploads']);
        $config->set('omnimap', [
            'provider' => 'openstreetmap',
            'secret' => 'c2VjcmV0',
            'providers' => ['openstreetmap' => ['user_agent' => 'omnibridge-tests/1.0 (+https://github.com/glitchr-studio/omnibridge-laravel)']],
            'cache' => ['drive' => 'local.uploads', 'directory' => 'google', 'size' => '600x400'],
            'html' => ['class' => 'google-tilemap', 'suffix' => '_html2canvas'],
            'routes' => ['tile' => ['path' => '/google/{signature}/{id}.webp', 'name' => 'gm_show']],
        ]);
        $app->instance('omnimap.http_client', new MockHttpClient(static function (string $method, string $url): MockResponse {
            preg_match('~/(\d+)/(\d+)/(\d+)\.png$~', $url, $m);

            return new MockResponse(PlainTiles::png((int) $m[1], (int) $m[2], (int) $m[3]), ['response_headers' => ['content-type' => 'image/png']]);
        }));
    }

    protected function defineRoutes($router): void
    {
        $router->post('/google/maps/{signature}/export', static fn () => '')->name('omnimap_export');
        $router->post('/google/maps/{signature}/suppress', static fn () => '')->name('omnimap_suppress');
    }

    public function testAPageHasItsMapAndTheTilesAreServed(): void
    {
        if (!\function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is not installed.');
        }
        $this->app['session']->start();
        $twig = $this->app->make(Environment::class);
        $this->assertTrue($twig->hasExtension(MapExtension::class));
        $view = new MapView(new LatLng(35.0116, 135.7681), 12, controls: false, id: 'myMap');
        $signature = $this->app->make(Snapshots::class)->signature($view);
        $page = $twig->createTemplate('{{ omnimap(view) }}{{ omnimap_export(view) }}');

        $html = $page->render(['view' => $view]);
        $this->assertStringContainsString('<div class="google-tilemap" id="myMap_html2canvas" data-omnimap="snapshot" data-src="/google/%7Bsignature%7D/%7Bid%7D.webp" data-signature="'.$signature.'" data-tilesize="512" data-xtiles="2" data-ytiles="1"', $html);
        $this->assertStringNotContainsString('data-omnimap-export', $html);
        $this->assertNotNull($this->app->make(Snapshots::class)->find($view), 'made by the server and kept');
        $this->assertFileExists(self::DIR."/google/$signature/metadata.txt", 'on the drive, the disk behind it');

        $tile = $this->get("/google/$signature/1.webp");
        $tile->assertOk();
        $this->assertSame([512, 512], \array_slice((array) getimagesizefromstring((string) $tile->getContent()), 0, 2));
        $this->get("/google/$signature/7.webp")->assertNotFound();

        Gate::define('ROLE_ADMIN', static fn ($user) => null !== $user);
        $this->actingAs(new GenericUser(['id' => 1]));
        $this->assertStringContainsString('data-omnimap-export="/google/maps/'.$signature.'/export"', $page->render(['view' => $view]));
    }
}
