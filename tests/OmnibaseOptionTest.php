<?php

namespace Omnibridge\Laravel\Tests;

use Base\Bridge\Doctrine\Mapping;
use Illuminate\Config\Repository;
use Omnibridge\Laravel\Omnibase\OmnibaseOption;

/**
 * omnibase's option under its neutral name, omnibridge.omnibase (omnisense/omnibase's plan "omnibase agnostic", step B
 * leftovers): read whichever persistence is chosen; its former name, omnibridge.doctrine.omnibase, still read where the new
 * one is not given, said deprecated once - an application written before keeps its mapping on.
 */
final class OmnibaseOptionTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('omnibridge.doctrine.entities', [self::FIXTURES.'/Omnibase/Entity']);
        // The former name only, as an application configured before.
        $app['config']->set('omnibridge.doctrine.omnibase', ['project_dir' => self::FIXTURES.'/Omnibase']);
        $app['config']->set('base', [
            'database' => ['use_custom' => true],
            'attributes' => ['use_custom' => true],
            'twig' => ['editor' => ['storage' => 'local.wysiwyg']],
            'uploader' => ['storage' => 'local.uploads'],
        ]);
    }

    public function testTheFormerNameStillTurnsOmnibaseOn(): void
    {
        $mapping = $this->app->make(Mapping::class);

        $this->assertSame(self::FIXTURES.'/Omnibase', $mapping->attributeReader->getProjectDir(), 'the former key\'s options, as before');
    }

    public function testTheNeutralNameIsReadAndWins(): void
    {
        $said = $this->deprecations(function (): void {
            $this->assertSame(['project_dir' => '/new'], OmnibaseOption::get(new Repository(['omnibridge' => ['omnibase' => ['project_dir' => '/new'], 'doctrine' => ['omnibase' => ['project_dir' => '/former']]]])));
            $this->assertTrue(OmnibaseOption::get(new Repository(['omnibridge' => ['omnibase' => true]])));
            $this->assertFalse(OmnibaseOption::get(new Repository(['omnibridge' => ['omnibase' => false, 'doctrine' => ['omnibase' => true]]])), 'false is said, not "not given"');
            $this->assertFalse(OmnibaseOption::get(new Repository(['omnibridge' => ['doctrine' => ['connection' => 'testing']]])), 'neither: off');
            $this->assertSame([], OmnibaseOption::options(new Repository(['omnibridge' => ['omnibase' => true]])));
        });

        $this->assertSame([], $said);
    }

    public function testTheFormerNameIsSaidDeprecatedOnce(): void
    {
        $config = new Repository(['omnibridge' => ['doctrine' => ['omnibase' => ['project_dir' => '/former']]]]);
        $said = $this->deprecations(function () use ($config): void {
            $this->assertSame(['project_dir' => '/former'], OmnibaseOption::get($config));
            $this->assertSame(['project_dir' => '/former'], OmnibaseOption::options($config));
        });

        $this->assertCount(1, $said);
        $this->assertStringContainsString('"omnibridge.doctrine.omnibase" is deprecated', $said[0]);
        $this->assertStringContainsString('"omnibridge.omnibase"', $said[0]);
    }

    /** @return list<string> the deprecations said while $run ran, the next former read said again first */
    private function deprecations(\Closure $run): array
    {
        OmnibaseOption::reset();
        $said = [];
        set_error_handler(static function (int $level, string $message) use (&$said): bool {
            $said[] = $message;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            $run();
        } finally {
            restore_error_handler();
        }

        return $said;
    }
}
