<?php

namespace Omnibridge\Laravel\Tests;

/**
 * The application's own repository interfaces, named by its repositories' #[AsAlias] (the attribute the Symfony site
 * writes), bound by this package from the project's src/Repository: the application binds none itself.
 */
final class ProjectAliasesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('omnibridge.doctrine.entities', [self::FIXTURES.'/Omnibase/Entity']);
        $app['config']->set('omnibridge.omnibase', ['project_dir' => self::FIXTURES.'/Aliased']);
        $app['config']->set('base', ['database' => ['use_custom' => true], 'attributes' => ['use_custom' => true]]);
    }

    public function testAnInterfaceARepositoryAliasesIsBound(): void
    {
        $notes = $this->app->make(\App\Repository\Notes::class);

        self::assertInstanceOf(\App\Repository\NoteRepository::class, $notes);
        self::assertSame(['first-snow'], $notes->latest());
    }
}
