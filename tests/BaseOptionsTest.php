<?php

namespace Omnibridge\Laravel\Tests;

use Base\Options;
use Omnibridge\Laravel\Parameters\BaseOptions;
use Omnibridge\Options\InvalidOption;

/**
 * config/base.php under omnibase's schema (Base\Options, its plan "omnibase agnostic", step F5e slice 4b): validated
 * when the provider boots - refused with the message Symfony's tree gives the same option (the core's harness compares
 * both on every case: tests/Options/OptionsHostsTest) - and completed with the core's defaults, their placeholders
 * this application's.
 */
final class BaseOptionsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('base', ['images' => ['max_quality' => 0.8], 'user' => ['identifier' => 'firstname']]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(Options::class)) {
            self::markTestSkipped('omnisense/omnibase 4.x declares no options.');
        }
    }

    public function testTheConfigIsCompletedWithTheCoresDefaults(): void
    {
        $this->assertSame(0.8, config('base.images.max_quality'), 'the application\'s');
        $this->assertSame('firstname', config('base.user.identifier'));
        $this->assertSame(60, config('base.user.active_delay'), 'the core\'s default');
        $this->assertSame(3, config('base.guard.min_delay'));
        $this->assertSame(['ROLE_ADMIN'], config('base.demo.admin_roles'));
        $this->assertSame(10, $this->app->make(\Base\Service\ParameterBagInterface::class)->get('base.paginator.page_size'));
    }

    public function testThePlaceholdersAreThisApplications(): void
    {
        $this->assertSame($this->app->basePath().'/public/maintenance.lock', config('base.site.maintenance.lockpath'));
        $this->assertSame($this->app->basePath().'/public/bundles/base/images/image.webp', config('base.images.no_image.0.path'));
        $this->assertSame($this->app->storagePath('framework/cache/omnibase'), config('base.obfuscator.cache_dir'));
    }

    /** What Symfony's tree answers the same configuration (recorded from omnibridge/symfony's BaseConfiguration). */
    public function testARefusedOptionIsRefusedAsUnderSymfony(): void
    {
        foreach ([
            [['autoclea' => 1], 'Unrecognized option "autoclea" under "base". Did you mean "autoclear"?'],
            [['guard' => ['min_delay' => -1]], 'The value -1 is too small for path "base.guard.min_delay". Should be greater than or equal to 0'],
            [['translator' => ['politeness' => 'rude']], 'The value "rude" is not allowed for path "base.translator.politeness". Permissible values: null, "plain", "polite", "formal".'],
            [['user' => ['active_delay' => '60']], "Invalid type for path \"base.user.active_delay\". Expected \"int\", but got \"string\".\nHint: Threshold considering user as inactive (\"active delay\" is expected to be lower than \"online delay\")"],
            [['router' => ['use_custom' => 1]], "Invalid type for path \"base.router.use_custom\". Expected \"bool\" or \"null\", but got \"int\".\nHint: Use custom router"],
            [['twig' => ['themes' => ['modes' => [['class' => 'c']]]]], 'The attribute "name" must be set for path "base.twig.themes.modes".'],
        ] as [$base, $message]) {
            $this->app->forgetInstance(BaseOptions::APPLIED);
            $this->app['config']->set(BaseOptions::PROCESSED, null);
            $this->app['config']->set('base', $base);
            try {
                BaseOptions::apply($this->app, static fn (string $name): mixed => null);
                $this->fail('Refused: '.$message);
            } catch (InvalidOption $e) {
                $this->assertSame($message, $e->getMessage());
            }
        }
    }

    /**
     * The configuration `php artisan config:cache` writes - the options as the booted application holds them, processed,
     * BaseOptions::PROCESSED beside them - boots again as it is (step F7d-2): processing it a second time refused the
     * defaults it holds (base.obfuscator.encoding: null where the schema asks an int).
     */
    public function testACachedConfigurationBootsAsItWasWritten(): void
    {
        $cached = $this->app['config']->all();
        $this->assertTrue($this->app['config']->get(BaseOptions::PROCESSED), 'the processed configuration says so');
        try {
            Options::process($cached['base']);
            $this->fail('the processed options are not processed twice');
        } catch (InvalidOption $e) {
            $this->assertStringContainsString('Expected "int", but got "null"', $e->getMessage());
        }

        $this->app->forgetInstance(BaseOptions::APPLIED);
        $this->app['config']->set($cached);
        BaseOptions::apply($this->app, static fn (string $name): mixed => null);
        $this->assertSame($cached['base'], $this->app['config']->get('base'));
        $this->assertSame(10, $this->app['config']->get('base.paginator.page_size'));
    }

    public function testTheEnvironmentsDefaultsAreTheCores(): void
    {
        if (false !== getenv('BASE_TRADING_LIVE')) {
            $this->markTestSkipped('BASE_TRADING_LIVE is set here.');
        }
        $this->assertTrue(\Omnibridge\Laravel\Container\Definitions::env('bool:BASE_TRADING_LIVE'), 'live, as under Symfony');
        $this->assertSame('', \Omnibridge\Laravel\Container\Definitions::env('COLLAB_RELAY_WS_URL'));
    }
}
