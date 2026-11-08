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
 * media on, as an application drawing an icon picker has it (its remote search's routes), its uploads' storage
 * (a file field shows the record's files), and its entities mapped (a translations field's parts are its
 * translation entity's fields).
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
        // The uploads' storage, as an application whose records hold files has it: a file field shows the record's.
        $app['config']->set('filesystems.disks.omnibase-uploads', ['driver' => 'local', 'root' => $root.'/uploads']);
        $app['config']->set('omnibridge.storages', ['local.cache' => 'omnibase-cache', 'local.uploads' => 'omnibase-uploads']);
        $app['config']->set('base.obfuscator.cache_dir', $root.'/obfuscator');
        // omnibase's entities mapped, as an application has them: a translations field lists its translation
        // entity's fields from its shape (a thread's translations, the sample's record).
        $app['config']->set('omnibridge.doctrine.entities', [self::FIXTURES.'/Omnibase/Entity']);
        $app['config']->set('omnibridge.omnibase', ['project_dir' => self::FIXTURES.'/Omnibase']);
        $app['config']->set('base.database.use_custom', true);
        $app['config']->set('base.attributes.use_custom', true);
        // omnibase's templates, as a site under Twig has them: a wysiwyg's text is printed by its |wysiwyg (quill.html.twig).
        $app['config']->set('omnibridge.twig.paths', [[\dirname((string) (new \ReflectionClass(\Base\Services::class))->getFileName(), 2).'/templates', 'Base']]);
        $app['config']->set('omnibridge.twig.cache', $root.'/twig');
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

    /**
     * A translations field (Base\Field\Behaviour\Translation): a tab per offered language, each its translation
     * entity's fields named and valued as TranslationType names them; what is sent written into the record's
     * translations, a language left empty not kept.
     */
    public function testATranslationsFieldIsDrawnPerLanguageAndBound(): void
    {
        $locales = \Omnibridge\Runtime::locales();
        $default = $locales->getDefaultLocale();
        $thread = new \Base\Entity\Thread();
        $thread->addTranslation((new \Base\Entity\ThreadIntl())->setLocale($default)->setTitle('Abbey Road'));
        $form = \Base\Form\Definition\FormDefinition::new('thread')->setCsrf(false)->setDataClass(\Base\Entity\Thread::class)
            ->add(\Base\Form\Definition\FormDefinition::field('translations', 'translation')->setFormTypeOptions(['fields' => ['title' => ['required' => true], 'excerpt' => []], 'autoload' => false]));
        $processor = new FormProcessor($this->app->make(Validation::class));
        $html = $this->app->make(FormRenderer::class)->form($processor->createView($form, $thread));

        foreach ($locales->getAvailableLocales() as $locale) {
            self::assertStringContainsString('data-bs-target="#thread_translations_'.$locale.'"', $html);
            self::assertStringContainsString('name="thread[translations]['.$locale.'][excerpt]"', $html);
        }
        self::assertStringContainsString('name="thread[translations]['.$default.'][title]" required="required" class="form-control" value="Abbey Road"', $html);

        $other = current(array_diff($locales->getAvailableLocales(), [$default]));
        if (false === $other) {
            return;
        }
        $outcome = $this->host()->submit($form, ['translations' => [$default => ['title' => 'Let It Be', 'excerpt' => ''], $other => ['title' => '', 'excerpt' => '']]], $thread);
        self::assertSame([], $outcome->errors);
        self::assertSame('Let It Be', $thread->translate($default)->getTitle());
        self::assertFalse($thread->getTranslations()->containsKey($other), 'a language left empty is not kept');
    }

    #[DataProvider('kinds')]
    public function testTheKindConforms(string $kind): void
    {
        self::assertSame([], Suite::check($this->host(), Samples::all()[$kind]));
    }
}
