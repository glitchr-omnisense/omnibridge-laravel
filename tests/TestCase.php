<?php

namespace Omnibridge\Laravel\Tests;

use Base\Contract\Runtime;
use Illuminate\Support\Facades\DB;
use Omnibridge\Laravel\OmnibridgeServiceProvider;
use Orchestra\Testbench\TestCase as Testbench;

abstract class TestCase extends Testbench
{
    protected const FIXTURES = __DIR__.'/Fixtures';

    protected function getPackageProviders($app): array
    {
        return [OmnibridgeServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('app.locale', 'fr');
        $app['config']->set('app.fallback_locale', 'fr');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => self::FIXTURES.'/../../.phpunit.cache/omnibase.sqlite', 'prefix' => '']);
        $app['config']->set('omnibridge.locales.available', ['fr-FR', 'en-GB', 'de-DE']);
        $app['config']->set('omnibridge.translations.paths', [self::FIXTURES.'/translations']);
        $app['config']->set('omnibridge.assets.manifest', self::FIXTURES.'/build/manifest.json');
        $app['config']->set('omnibridge.twig.paths', [[self::FIXTURES.'/templates', null]]);
        $app['config']->set('omnibridge.twig.cache', false);
        $app['config']->set('omnibridge.doctrine.connection', 'testing');
        $app['config']->set('omnibridge.doctrine.entities', [self::FIXTURES.'/Entity']);
    }

    protected function setUp(): void
    {
        @mkdir(self::FIXTURES.'/../../.phpunit.cache', 0777, true);
        @unlink(self::FIXTURES.'/../../.phpunit.cache/omnibase.sqlite');
        touch(self::FIXTURES.'/../../.phpunit.cache/omnibase.sqlite');
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Runtime::reset();
    }

    /** omnibase's settings tables, as its migrations make them, with these settings: path => [bag, [locale => value]]. */
    protected function settings(array $settings): void
    {
        DB::statement('CREATE TABLE layoutSetting (id INTEGER PRIMARY KEY AUTOINCREMENT, path VARCHAR(255) NOT NULL, locked BOOLEAN NOT NULL DEFAULT 0, bag VARCHAR(255) DEFAULT NULL UNIQUE)');
        DB::statement('CREATE TABLE layoutSettingIntl (id INTEGER PRIMARY KEY AUTOINCREMENT, translatable_id INTEGER NOT NULL, locale VARCHAR(5) NOT NULL, value TEXT, vault VARCHAR(255) DEFAULT NULL)');
        foreach ($settings as $path => [$bag, $values]) {
            $id = DB::table('layoutSetting')->insertGetId(['path' => $path, 'bag' => $bag]);
            foreach ($values as $locale => $value) {
                DB::table('layoutSettingIntl')->insert(['translatable_id' => $id, 'locale' => $locale, 'value' => json_encode($value)]);
            }
        }
    }
}
