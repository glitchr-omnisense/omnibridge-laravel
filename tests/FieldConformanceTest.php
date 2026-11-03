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
 * refused as Symfony does it (the samples are Symfony's), each unsupported one refused aloud.
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
