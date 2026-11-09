<?php

namespace Omnibridge\Laravel\Tests;

use Base\Service\Icons;
use Omnibridge\Laravel\View\OmnibaseExtensions;

/**
 * omnibase's icons on Laravel (View\OmnibaseExtensions::bindIcons()): each metadata folder has its own cache. The
 * adapters key what they read by their class alone, so with one cache for every folder, a folder read first - a
 * missing one, read as no icon at all - answered for the right one read after it in the same cache: `|iconify` gave
 * nothing (OmnibaseTwigTest's Font Awesome icon, red on a fresh install where an earlier test had read the default
 * folder, public/bundles/base/metadata, which a package's test host has not).
 */
final class IconsCacheTest extends TestCase
{
    public function testAFolderReadFirstDoesNotAnswerForAnother(): void
    {
        if (!class_exists(Icons::class)) {
            self::markTestSkipped('omnisense/omnibase before R24: no framework-free Icons.');
        }
        $app = $this->app;
        \assert(null !== $app);
        $empty = sys_get_temp_dir().'/omnibridge-laravel-icons-'.getmypid();
        @mkdir($empty, 0777, true);
        try {
            OmnibaseExtensions::bindIcons($app, $empty);
            self::assertSame([], $app->make(Icons::class)->iconify('fa-solid fa-pencil-alt'), 'a folder without metadata: no icon');

            $app->forgetInstance(Icons::class);
            $app->offsetUnset(Icons::class);
            OmnibaseExtensions::bindIcons($app, \dirname(__DIR__).'/vendor/omnisense/omnibase/public/metadata');
            self::assertSame(["<i  class='fa-fw fa-solid fa-pencil-alt'></i>"], $app->make(Icons::class)->iconify('fa-solid fa-pencil-alt'));
        } finally {
            exec('rm -rf '.escapeshellarg($empty));
        }
    }
}
