<?php

namespace Omnibridge\Laravel\Tests;

use Base\Bridge\Doctrine\Mapping;
use Base\Contract\Texts;
use Base\Service\Flysystem;
use Base\Service\MediaService;
use Base\Service\Model\Wysiwyg\MediaEnhancer;
use Base\Service\Model\Wysiwyg\MediaEnhancerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * What an omnibase site's entities and repositories ask of their host, under
 * Laravel (audit "lesringostars face au master plan", R7), with omnibase's
 * mapping and its media on:
 * - the attribute reader reads the stored files through omnibase's Flysystem
 *   on Laravel's disks (#[Uploader]: an avatar, an article's audio);
 * - omnibase's cache pool (PSR-6 and Symfony's cache contracts), which a
 *   repository taking it beside the registry is given (a site's
 *   CommentRepository), from Laravel's container by its type;
 * - BaseTrait's MediaService is the bound one (an avatar's thumbnail);
 * - the editor's pictures by omnibase's MediaEnhancer, as Symfony's
 *   |wysiwyg gives them: the image route's address of each picture of a
 *   text, made from its stored origin in the editor's storage (the picture
 *   served at it: lesringostars-laravel's ArticleTest and parity suite).
 */
final class EntitiesHostTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        if (!\extension_loaded('imagick')) {
            self::markTestSkipped('imagick is not loaded');
        }
        $this->root = sys_get_temp_dir().'/omnibridge-laravel-entities-'.getmypid();
        @mkdir($this->root.'/wysiwyg', 0777, true);
        $picture = new \Imagick();
        $picture->newImage(400, 200, 'steelblue', 'jpeg');
        $picture->writeImage('jpeg:'.$this->root.'/wysiwyg/11111111-2222-3333-4444-555555555555.jpg');

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
        $app['config']->set('omnibridge.doctrine.entities', [self::FIXTURES.'/Omnibase/Entity']);
        $app['config']->set('omnibridge.doctrine.omnibase', ['project_dir' => self::FIXTURES.'/Omnibase']);
        $app['config']->set('omnibridge.media', true);
        $app['config']->set('omnibridge.twig.paths', [[self::FIXTURES.'/templates', null], [\dirname(__DIR__).'/vendor/omnisense/omnibase/src/Resources/views', 'Base']]);
        $app['config']->set('filesystems.disks.omnibase-cache', ['driver' => 'local', 'root' => $this->root.'/cache']);
        $app['config']->set('filesystems.disks.omnibase-wysiwyg', ['driver' => 'local', 'root' => $this->root.'/wysiwyg']);
        $app['config']->set('omnibridge.storages', ['local.cache' => 'omnibase-cache', 'local.wysiwyg' => 'omnibase-wysiwyg']);
        $app['config']->set('base', [
            'database' => ['use_custom' => true],
            'attributes' => ['use_custom' => true],
            'twig' => ['editor' => ['storage' => 'local.wysiwyg']],
            'uploader' => ['storage' => 'local.wysiwyg'],
            'obfuscator' => ['cache_dir' => $this->root.'/obfuscator'],
        ]);
    }

    public function testTheAttributeReaderReadsTheFilesThroughOmnibasesFlysystem(): void
    {
        $this->app->make(EntityManagerInterface::class);

        $this->assertSame($this->app->make(Flysystem::class), $this->app->make(Mapping::class)->attributeReader->getFlysystem());
        $this->assertSame($this->app->make(Flysystem::class), \Base\Attributes\AbstractAttribute::getFlysystem(), 'what #[Uploader] reads');
    }

    public function testTheCachePoolIsOneAndARepositoryIsGivenItByType(): void
    {
        $pool = $this->app->make(CacheItemPoolInterface::class);
        $this->assertSame($pool, $this->app->make(CacheInterface::class), 'PSR-6 and Symfony\'s contracts, one pool');
        $this->assertSame(42, $pool->get('answer', static fn () => 42));
        $this->assertSame(42, $pool->getItem('answer')->get());

        $this->app->make(EntityManagerInterface::class);
        $services = (new \ReflectionProperty(\Base\Bridge\Doctrine\RepositoryFactory::class, 'services'))->getValue($this->app->make(Mapping::class)->repositories);
        $this->assertInstanceOf(\Closure::class, $services, 'Mapping::register() was given the host\'s services');
        $this->assertSame($pool, $services(CacheItemPoolInterface::class));
        $this->assertNull($services('Nowhere\\Service'), 'a service Laravel has not: null');
    }

    public function testBaseTraitsMediaServiceIsTheBoundOne(): void
    {
        $this->assertSame($this->app->make(MediaService::class), \Base\Service\BaseService::getMediaService());
    }

    public function testTheEditorsPicturesAreOmnibasesImageRoutes(): void
    {
        $this->assertInstanceOf(MediaEnhancer::class, $this->app->make(MediaEnhancerInterface::class));

        $text = json_encode(['time' => 1, 'blocks' => [['id' => 'a', 'type' => 'image', 'data' => ['file' => ['url' => '/wysiwyg/x.jpg', 'origin' => '/11111111-2222-3333-4444-555555555555.jpg'], 'caption' => '']]], 'version' => '2.28.2']);
        $html = $this->app->make(Texts::class)->render($text);

        $this->assertSame(1, preg_match('~src=\\\\?"(/images/[0-9a-f/]+/image\.jpg)~', $html, $picture), 'the picture by the image route: '.substr($html, 0, 600));
        $this->assertSame($this->app->make(MediaEnhancerInterface::class)->enhance('/11111111-2222-3333-4444-555555555555.jpg', ['storage' => 'local.wysiwyg'], [], ['class' => 'markdown-media']), $picture[1], 'MediaEnhancer\'s address of the picture\'s origin, in the editor\'s storage, as HtmlTwigExtension asks it');
    }
}
