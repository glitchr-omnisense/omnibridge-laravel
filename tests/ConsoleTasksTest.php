<?php

namespace Omnibridge\Laravel\Tests;

use Base\Console\Task\IconEnums;
use Base\Console\Task\IconEnumsInput;
use Base\Console\Task\TranslationEntities;
use Base\Console\Task\TranslationEntitiesInput;
use Base\Service\LocalizerInterface;
use Base\Service\TranslatorInterface;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Omnibridge\Contract\Locales;
use Omnibridge\Laravel\Console\ArtisanCommands;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * omnibase's console tasks as artisan commands (its plan "omnibase agnostic", step F5e, slice 3): registered where
 * the core has them, each printing the task's Report as omnibridge/symfony's command of the same name prints it -
 * the artisan command's output is the Report's plain text -, and artisan's commands answering the names a task asks.
 */
final class ConsoleTasksTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        // omnibase's services, as OmnibaseServicesTest has them (the translator among them).
        $app['config']->set('omnibridge.doctrine.entities', [self::FIXTURES.'/Omnibase/Entity']);
        $app['config']->set('omnibridge.doctrine.omnibase', ['project_dir' => self::FIXTURES.'/Omnibase']);
        $app['config']->set('base', ['database' => ['use_custom' => true], 'attributes' => ['use_custom' => true], 'uploader' => ['storage' => 'local.uploads']]);
        $app['config']->set('omnibridge.console.source', self::FIXTURES.'/Omnibase');
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(TranslationEntities::class)) {
            self::markTestSkipped('omnibase 4.x has no console tasks.');
        }
    }

    public function testTheTasksAreArtisanCommands(): void
    {
        foreach (['translation:crud', 'translation:entities', 'translation:enums', 'translation:controllers', 'translation:settings', 'cache:clear:sessions', 'omnibase:cache:clear', 'demo:reset', 'uploader:entities', 'uploader:images', 'uploader:images:crop', 'base:mapping', 'icon:enums', 'icon:entities', 'autocomplete:entities', 'entity:discriminator', 'app:notifier', 'notifier:mail:test', 'user:notifications', 'thread:publishable', 'trash:purge', 'user:create'] as $name) {
            $this->assertArrayHasKey($name, Artisan::all(), $name);
        }
    }

    public function testAnArtisanCommandPrintsItsTasksReport(): void
    {
        $this->assertSame(0, Artisan::call('translation:entities', ['--entity' => 'App\Entity\User', '--no-ansi' => true]));

        $report = (new TranslationEntities(app(TranslatorInterface::class), app(LocalizerInterface::class), app(Locales::class)))
            ->run(new TranslationEntitiesInput(self::FIXTURES.'/Omnibase', 'App\Entity\User'));
        $this->assertStringContainsString(' * App\Entity\User : ', $report->text());
        $this->assertStringContainsString('@entities[de-DE].user._singular', $report->text());
        $this->assertSame(self::lines($report->text()), self::lines(Artisan::output()));
    }

    /** Slice 3b: a listing printed as the task says it (Symfony's icon:enums prints the same Report). */
    public function testIconEnumsPrintsItsTasksReport(): void
    {
        $this->assertSame(0, Artisan::call('icon:enums', ['--enum' => 'Base\\Enum\\UserRole', '--no-ansi' => true]));

        $report = (new IconEnums())->run(new IconEnumsInput(self::FIXTURES.'/Omnibase', 'Base\\Enum\\UserRole'));
        $this->assertStringContainsString("\tROLE_ADMIN ", $report->text());
        $this->assertSame(self::lines($report->text()), self::lines(Artisan::output()));
    }

    /** user:create without omnibase's Notifier (no channel policy here) asks for --no-email before it reads or writes anything. */
    public function testUserCreateAsksForNoEmailWithoutANotifier(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('pass --no-email');
        Artisan::call('user:create', ['username' => 'someone', 'email' => 'someone@example.org', '--no-ansi' => true]);
    }

    public function testDemoResetRefusesOutsideTheDemonstration(): void
    {
        $this->assertSame(1, Artisan::call('demo:reset', ['--no-ansi' => true]));
        $this->assertStringContainsString('demo:reset runs in the "demo" environment only', Artisan::output());
    }

    public function testArtisanAnswersTheCommandsATaskAsks(): void
    {
        $commands = new ArtisanCommands(app(Kernel::class), new BufferedOutput());

        $this->assertNull($commands->run('doctrine:no-such-command'));
        $this->assertSame(0, $commands->run('cache:pool:clear', ['--all' => true]), 'Symfony\'s pools are Laravel\'s cache');
    }

    private static function lines(string $text): string
    {
        return implode("\n", array_map('rtrim', explode("\n", rtrim($text))));
    }
}
