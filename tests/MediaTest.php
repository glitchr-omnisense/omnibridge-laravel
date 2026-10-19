<?php

namespace Omnibridge\Laravel\Tests;

use Base\Contract\Media;
use Base\Contract\Pictures;
use Base\Contract\Runtime;
use Base\Imagine\Filter\Basic\ThumbnailFilter;
use Base\Service\ImageRoute;
use Base\Service\MediaService;
use Illuminate\Support\Facades\Route;
use Omnibridge\Laravel\Media\LaravelMediaRouter;
use Omnibridge\Laravel\Media\ServiceMedia;

/**
 * omnibase's media rule under Laravel (audit "lesringostars face au master
 * plan", R6; omnibridge.media on): MediaService on Laravel's router, the
 * Media contract bound to it, Pictures' thumbnails, and the image routes
 * answered by the core's Base\Service\ImageRoute - an address made, read
 * back, redirected to its extension, served at its size, in webp, without
 * the local cache; a missing image a 404. Off, nothing is bound nor
 * declared.
 */
final class MediaTest extends TestCase
{
    private string $root;

    private string $source;

    protected function setUp(): void
    {
        if (!\extension_loaded('imagick')) {
            self::markTestSkipped('imagick is not loaded');
        }
        $this->root = sys_get_temp_dir().'/omnibridge-laravel-media-'.getmypid();
        @mkdir($this->root.'/source', 0777, true);
        $this->source = $this->root.'/source/photo.png';
        $picture = new \Imagick();
        $picture->newImage(400, 200, 'steelblue', 'png');
        $picture->writeImage($this->source);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        exec('rm -rf '.escapeshellarg($this->root));
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        if ('testTheMediaAreOffByDefault' === $this->name()) {
            return;
        }

        $app['config']->set('omnibridge.media', true);
        $app['config']->set('filesystems.disks.omnibase-cache', ['driver' => 'local', 'root' => $this->root.'/cache']);
        $app['config']->set('filesystems.disks.omnibase-uploads', ['driver' => 'local', 'root' => $this->root.'/uploads']);
        $app['config']->set('omnibridge.storages', ['local.cache' => 'omnibase-cache', 'local.uploads' => 'omnibase-uploads']);
        $app['config']->set('base.images.max_quality', 0.8);
        $app['config']->set('base.uploader.storage', 'local.uploads');
        $app['config']->set('base.obfuscator.cache_dir', $this->root.'/obfuscator');
    }

    public function testTheMediaAreOffByDefault(): void
    {
        self::assertFalse(Route::has('ux_image'));
        self::assertFalse($this->app->bound(MediaService::class));
        self::assertFalse(Runtime::has(Media::class));
    }

    public function testMediaIsBoundToMediaService(): void
    {
        $media = $this->app->make(Media::class);
        self::assertInstanceOf(ServiceMedia::class, $media);
        self::assertSame($media, Runtime::media());
        self::assertInstanceOf(LaravelMediaRouter::class, $this->app->make(MediaService::class)->getRouter());
        self::assertSame('png', $media->extension($this->source));
        self::assertMatchesRegularExpression('#^([0-9a-f]{2}/){5}[0-9a-f]{22}$#', (string) $media->obfuscate($this->source));
        self::assertMatchesRegularExpression('#^/images/([0-9a-f]{2}/){5}[0-9a-f]{22}/image\.png$#', (string) $media->link('ux_imageExtension', ['extension' => 'png'], $this->source));
        self::assertNull($media->obfuscate(null));
    }

    public function testTheImageRoutesAreDeclaredUnderTheirSymfonyNames(): void
    {
        $routes = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (isset(ImageRoute::ROUTES[(string) $route->getName()])) {
                $routes[$route->getName()] = '/'.$route->uri();
            }
        }

        self::assertSame(array_map(static fn (array $route) => $route[0], ImageRoute::ROUTES), $routes);
    }

    public function testAThumbnailIsMadeReadBackAndServed(): void
    {
        $media = $this->app->make(MediaService::class);
        $thumbnail = (string) $media->thumbnail($this->source, 100, 100, ['webp' => false]);
        self::assertMatchesRegularExpression('#^/images/([0-9a-f]{2}/){5}[0-9a-f]{22}/image\.png$#', $thumbnail);
        self::assertSame($thumbnail, $media->thumbnail($this->source, 100, 100, ['webp' => false]));

        $match = $media->getRouter()->getRouteMatch($thumbnail);
        self::assertSame('ux_imageExtension', $match['_route']);
        self::assertSame('png', $match['extension']);
        $config = $media->resolve($match['data']);
        self::assertSame($this->source, $config['path']);
        self::assertInstanceOf(ThumbnailFilter::class, $config['filters'][0]);

        // Its address without the extension: sent to the one with it.
        $this->get(substr($thumbnail, 0, -\strlen('/image.png')))->assertStatus(301)->assertHeader('Location', $thumbnail);

        $response = $this->get($thumbnail)->assertOk()->assertHeader('Content-Type', 'image/png');
        $size = getimagesizefromstring((string) $response->getContent());
        self::assertSame([100, 50], [$size[0], $size[1]]);
        self::assertFileExists($this->root.'/cache/'.$match['data'].'/image.png');

        // Asked again: the file kept in the local cache, the same bytes.
        self::assertSame($response->getContent(), $this->get($thumbnail)->assertOk()->getContent());

        // In webp, and without the local cache.
        $webp = $this->get(substr($thumbnail, 0, -\strlen('.png')).'.webp')->assertOk()->assertHeader('Content-Type', 'image/webp');
        self::assertSame([100, 50], \array_slice(getimagesizefromstring((string) $webp->getContent()), 0, 2));
        $cacheless = $this->get('/images/cacheless/'.$match['data'].'/image.png')->assertOk();
        self::assertSame([100, 50], \array_slice(getimagesizefromstring((string) $cacheless->getContent()), 0, 2));
    }

    public function testAnImageThatDoesNotResolveIsNotFound(): void
    {
        $this->get('/images/00/00/00/00/00/0000000000000000000000/image.png')->assertNotFound();
    }

    public function testPicturesMakeThumbnails(): void
    {
        $public = $this->app->publicPath('omnibridge-media-test');
        @mkdir($public, 0777, true);
        copy($this->source, $public.'/photo.png');

        try {
            $pictures = $this->app->make(Pictures::class);
            self::assertMatchesRegularExpression('#^/images/([0-9a-f]{2}/){5}[0-9a-f]{22}/image\.(png|webp)$#', (string) $pictures->picture($public.'/photo.png', 100, 100));
            self::assertSame('/elsewhere/photo.png', $pictures->picture('/elsewhere/photo.png', 100, 100));
        } finally {
            exec('rm -rf '.escapeshellarg($public));
        }
    }
}
