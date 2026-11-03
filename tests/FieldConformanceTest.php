<?php

namespace Omnibridge\Laravel\Tests;

use Base\Field\Conformance\Samples;
use Base\Field\Conformance\Suite;
use Base\Form\Definition\FormProcessor;
use Omnibridge\Contract\Validation;
use Omnibridge\Laravel\Form\Conformance\LaravelHost;
use Omnibridge\Laravel\Form\FormRenderer;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * omnisense/omnibase's field conformance suite on Laravel (plan "omnibase agnostic", step F5d):
 * every kind declared (LaravelKinds::COVERAGE), each implemented one drawn, shown, bound and
 * refused as Symfony does it (the samples are Symfony's), each unsupported one refused aloud. With omnibase's
 * media on, as an application drawing an icon picker has it (its remote search's routes).
 */
final class FieldConformanceTest extends TestCase
{
    private ?string $locale = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->locale = \Locale::getDefault();
        \Locale::setDefault('fr_FR');
    }

    protected function tearDown(): void
    {
        \Locale::setDefault((string) $this->locale);
        parent::tearDown();
    }

    /**
     * omnibase's media on, as an application that draws an icon picker has it: the remote search hands its
     * payload through the obfuscator, and its routes (ux_autocomplete, ux_autocomplete_icons) are declared.
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $root = sys_get_temp_dir().'/omnibridge-laravel-conformance-'.getmypid();
        $app['config']->set('omnibridge.media', true);
        $app['config']->set('filesystems.disks.omnibase-cache', ['driver' => 'local', 'root' => $root.'/cache']);
        $app['config']->set('omnibridge.storages', ['local.cache' => 'omnibase-cache']);
        $app['config']->set('base.obfuscator.cache_dir', $root.'/obfuscator');
    }

    private function host(): LaravelHost
    {
        return new LaravelHost($this->app->make(FormRenderer::class), new FormProcessor($this->app->make(Validation::class)));
    }

    public function testEveryKindIsDeclared(): void
    {
        self::assertSame([], Suite::coverage($this->host()));
    }

    /** @return iterable<string, array{string}> */
    public static function kinds(): iterable
    {
        foreach (array_keys(Samples::all()) as $kind) {
            yield $kind => [$kind];
        }
    }

    #[DataProvider('kinds')]
    public function testTheKindConforms(string $kind): void
    {
        self::assertSame([], Suite::check($this->host(), Samples::all()[$kind]));
    }
}
