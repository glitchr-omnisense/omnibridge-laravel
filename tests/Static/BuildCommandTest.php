<?php

namespace Omnibridge\Laravel\Tests\Static;

use Illuminate\Support\Facades\Artisan;
use Omnibridge\Laravel\Tests\TestCase;
use Omnibridge\Static\Console\Cli;

/**
 * php artisan omnibridge:static:build hands omnibridge/static's command line its export options: --cache keeps the
 * renderer's answers between builds (a page whose declared inputs did not change is not rendered again), --only
 * renders the addresses it names whatever the cache holds, --full renders everything; a relative --cache is under
 * base_path(), as --out is. The site's file is a closure over the host's offer; its pages say which host rendered
 * them, and each declares the content file it shows.
 */
final class BuildCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(Cli::class)) {
            self::markTestSkipped('omnibridge/static is not installed.');
        }
        $this->dir = sys_get_temp_dir().'/omnibridge-laravel-static-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/content', 0777, true);
        file_put_contents($this->dir.'/content/a.txt', 'A');
        file_put_contents($this->dir.'/content/b.txt', 'B');
        file_put_contents($this->dir.'/static.php', <<<'PHP'
            <?php

            use Omnibridge\Static\Console\Cli;
            use Omnibridge\Static\Render\CallableRenderer;
            use Omnibridge\Static\Route\PageList;
            use Omnibridge\Static\Site;
            use Omnibridge\Static\StaticPage;

            return static function (array $host): Site {
                $file = static fn (StaticPage $page): string => __DIR__.'/content/'.('/' === $page->path ? 'a' : ltrim($page->path, '/')).'.txt';

                return (new Site('https://example.org'))
                    ->provide(new PageList(['/', '/b']))
                    ->renderWith(new CallableRenderer(static fn (StaticPage $page): string => sprintf(
                        '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>%s</title></head><body>%s by %s</body></html>',
                        $page->path,
                        file_get_contents($file($page)),
                        $host[Cli::HOST_NAME],
                    )))
                    ->dependsOn([], static fn (StaticPage $page): array => [$file($page)]);
            };
            PHP);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach (isset($this->dir) ? [$this->dir, base_path('omnibridge-static-cache-test')] : [] as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                \assert($file instanceof \SplFileInfo);
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($dir);
        }
    }

    public function testTheExportOptionsReachTheCommandLine(): void
    {
        $build = function (array $options): string {
            $status = Artisan::call('omnibridge:static:build', ['site' => $this->dir.'/static.php', '--out' => $this->dir.'/out', '--no-ansi' => true] + $options);
            $output = Artisan::output();
            $this->assertSame(0, $status, $output);

            return $output;
        };
        $cache = ['--cache' => $this->dir.'/cache'];

        // The first build: no record of an earlier one in the cache, a full export (omnibridge/static 1.1.0).
        $this->assertStringContainsString('full (no record of an earlier export in the cache): 2 rendered, 0 reused;', $build($cache));
        $this->assertStringContainsString('A by laravel', (string) file_get_contents($this->dir.'/out/index.html'), 'the host\'s offer reaches the site\'s file');
        $this->assertDirectoryExists($this->dir.'/cache');

        // --cache: nothing changed, nothing rendered.
        $this->assertStringContainsString('incremental: 0 rendered, 2 reused;', $build($cache));

        // A page's declared input changed: that page alone.
        file_put_contents($this->dir.'/content/b.txt', 'B, changed');
        $this->assertStringContainsString('incremental: 1 rendered, 1 reused;', $build($cache));
        $this->assertStringContainsString('B, changed by laravel', (string) file_get_contents($this->dir.'/out/b/index.html'));

        // --only: the address it names, whatever the cache holds.
        $this->assertStringContainsString('incremental: 1 rendered, 1 reused;', $build($cache + ['--only' => '/']));

        // --full: everything, the cache aside.
        $this->assertStringContainsString('full (asked): 2 rendered, 0 reused;', $build($cache + ['--full' => true]));

        // Without --cache, nothing is reused.
        $this->assertStringContainsString('incremental: 2 rendered, 0 reused;', $build([]));

        // A relative --cache is under base_path(), as --out is.
        $this->assertStringContainsString('full (no record of an earlier export in the cache): 2 rendered, 0 reused;', $build(['--cache' => 'omnibridge-static-cache-test']));
        $this->assertDirectoryExists(base_path('omnibridge-static-cache-test'));
        $this->assertStringContainsString('incremental: 0 rendered, 2 reused;', $build(['--cache' => 'omnibridge-static-cache-test']));
    }

    public function testAnEmptyCacheFolderIsInvalid(): void
    {
        $status = Artisan::call('omnibridge:static:build', ['site' => $this->dir.'/static.php', '--out' => $this->dir.'/out', '--cache' => '', '--no-ansi' => true]);

        $this->assertSame(2, $status);
        $this->assertStringContainsString('--cache names a folder', Artisan::output());
        $this->assertFileDoesNotExist($this->dir.'/out/index.html');
    }
}
