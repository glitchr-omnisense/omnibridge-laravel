<?php

namespace Omnibridge\Laravel\Legacy\Parameters;

use Illuminate\Contracts\Foundation\Application;

/**
 * omnisense/omnibase 4.x only, which declares no options (its BaseConfiguration is a Symfony tree): the defaults
 * this bridge wrote for the options it reads - the media's (images, obfuscator, uploader, the editor's storage) and
 * the paginator's -, laid under config/base.php where they are read; and the media's Obfuscator, bound by hand. Under 5.0 the defaults are the core's (Base\Options, through
 * Parameters\BaseOptions). Removed once 4.x is no longer served.
 */
final class OptionDefaults
{
    /** The media's defaults under config/base.php, once (where the media are asked, as before). */
    public static function media(Application $app): void
    {
        if (!$app->bound('omnibridge.media.defaults')) {
            $config = $app['config'];
            $config->set('base', array_replace_recursive(self::defaults($app), (array) $config->get('base', [])));
            $app->instance('omnibridge.media.defaults', true);
        }
    }

    /** The paginator's defaults under base.paginator (where the remote search's routes are declared, as before). */
    public static function paginator(Application $app): void
    {
        $config = $app['config'];
        $config->set('base.paginator', array_replace(['page_range' => 1, 'page_size' => 10, 'page_parameter' => 'page', 'default_template' => '@Base/paginator/sliding.html.twig'], (array) $config->get('base.paginator', [])));
    }

    /** @return array<string, mixed> */
    private static function defaults(Application $app): array
    {
        $noImage = static fn (string $file) => $app->publicPath('bundles/base/images/'.$file);

        return [
            'images' => [
                'max_resolution' => 'Base\Imagine\Filter\Basic\Definition\UltraHighDefinitionFilter',
                'debug' => false,
                'max_quality' => 1,
                'timeout' => 60,
                'enable_webp' => true,
                'fallback' => false,
                'no_image' => [
                    ['extension' => 'webp', 'path' => $noImage('image.webp')],
                    ['extension' => 'jpg', 'path' => $noImage('image.jpg')],
                    ['extension' => 'jpeg', 'path' => $noImage('image.jpg')],
                    ['extension' => 'png', 'path' => $noImage('image.png')],
                    ['extension' => 'svg', 'path' => $noImage('image.svg')],
                    ['extension' => 'gif', 'path' => $noImage('image.gif')],
                ],
            ],
            'obfuscator' => [
                'level' => -1,
                'uuid' => \Symfony\Component\Uid\Uuid::NAMESPACE_URL,
                'encoding' => null,
                'max_length' => 0,
                'compression' => 'null',
                'cache_dir' => $app->storagePath('framework/cache/omnibase-obfuscator'),
            ],
            'uploader' => ['storage' => null],
            'twig' => ['editor' => ['storage' => null]],
        ];
    }

    /** The media's Obfuscator, bound by hand: 4.x declares none (Base\Services lists it since 5.0). */
    public static function obfuscator(Application $app, \Closure $parameters): void
    {
        $app->singleton(\Base\Service\Obfuscator::class, static function (Application $app) use ($parameters) {
            $parameters = $parameters($app);
            $obfuscator = new \Base\Service\Obfuscator($parameters, (string) $parameters->get('base.obfuscator.cache_dir'));
            $secret = (string) ($app['config']->get('omnibridge.media.secret') ?? $app['config']->get('app.key'));
            foreach ([new \Base\Service\Model\Obfuscator\Compression\NullCompression(), new \Base\Service\Model\Obfuscator\Compression\ZlibCompression(), new \Base\Service\Model\Obfuscator\Compression\GzipCompression(), new \Base\Service\Model\Obfuscator\Compression\DeflateCompression(), new \Base\Service\Model\Obfuscator\Compression\HashidsCompression($secret)] as $compression) {
                $obfuscator->addCompression($compression);
            }

            return $obfuscator;
        });
    }
}
