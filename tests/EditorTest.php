<?php

namespace Omnibridge\Laravel\Tests;

use Base\Field\Editor\EditorJs;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;

/**
 * omnibase's Editor.js routes under Laravel (plan "omnibase agnostic", step F5d, slice 6): with media on, the
 * routes of Base\Field\Editor\EditorJs::ROUTES are declared under their Symfony names and paths (a mention's page
 * optional), and answered by EditorController - a picture sent with the editor's token is stored in the editor's
 * storage and its address answered; another token is refused; the collaboration's ticket and autosave check the
 * room's own token. Off, nothing is declared.
 */
final class EditorTest extends TestCase
{
    private string $root;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        if ('testNoEditorRoutesWithoutMedia' === $this->name()) {
            return;
        }
        $this->root = sys_get_temp_dir().'/omnibridge-laravel-editor-'.getmypid();
        $app['config']->set('omnibridge.media', true);
        $app['config']->set('filesystems.disks.omnibase-cache', ['driver' => 'local', 'root' => $this->root.'/cache']);
        $app['config']->set('filesystems.disks.omnibase-editor', ['driver' => 'local', 'root' => $this->root.'/editor']);
        $app['config']->set('omnibridge.storages', ['local.cache' => 'omnibase-cache', 'local.editor' => 'omnibase-editor']);
        $app['config']->set('base.twig.editor.storage', 'local.editor');
        $app['config']->set('base.obfuscator.cache_dir', $this->root.'/obfuscator');
        $app['config']->set('omnibridge.doctrine.entities', [self::FIXTURES.'/Omnibase/Entity']);
        $app['config']->set('omnibridge.doctrine.omnibase', ['project_dir' => self::FIXTURES.'/Omnibase']);
    }

    public function testNoEditorRoutesWithoutMedia(): void
    {
        foreach (array_keys(EditorJs::ROUTES) as $name) {
            self::assertFalse(Route::has($name), $name);
        }
    }

    public function testTheRoutesAreSymfonys(): void
    {
        $uris = [];
        foreach (array_keys(EditorJs::ROUTES) as $name) {
            $uris[$name] = Route::getRoutes()->getByName($name)?->uri();
        }
        self::assertSame([
            'ux_editorjs_collabTicket' => 'ux/editorjs/collab-ticket',
            'ux_editorjs_autosave' => 'ux/editorjs/autosave',
            'ux_editorjs_endpointByUser' => 'ux/editorjs/user/{data}/{page?}',
            'ux_editorjs_endpointByKeyword' => 'ux/editorjs/keyword/{data}/{page?}',
            'ux_editorjs_endpointByThread' => 'ux/editorjs/thread/{data}/{page?}',
            'ux_editorjs_uploadByFile' => 'ux/editorjs/{data}',
            'ux_editorjs_uploadByUrl' => 'ux/editorjs/{data}/fetch',
        ], $uris);
    }

    private function data(): string
    {
        $this->withSession(['_token' => 'the-visitors-token']);

        return $this->app->make(EditorJs::class)->data();
    }

    public function testAPictureSentIsStoredInTheEditorsStorage(): void
    {
        $data = $this->data();
        $addresses = $this->app->make(EditorJs::class)->addresses();
        self::assertSame(route('ux_editorjs_uploadByFile', ['data' => $data], false), $addresses['uploadByFile']);

        $answer = $this->post(route('ux_editorjs_uploadByFile', ['data' => $data]), ['image' => UploadedFile::fake()->image('ringo.png', 4, 4)]);

        $answer->assertOk();
        self::assertSame(EditorJs::STATUS_OK, $answer->json('success'));
        $url = (string) $answer->json('file.url');
        self::assertMatchesRegularExpression('#\.png$#', $url, 'its type\'s extension');
        $stored = array_values(array_filter(iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root.'/editor', \FilesystemIterator::SKIP_DOTS))), static fn (\SplFileInfo $file): bool => $file->isFile()));
        self::assertCount(1, $stored, 'the picture in the editor\'s storage');
    }

    public function testAnotherTokenIsRefused(): void
    {
        $this->data();
        $forged = $this->app->make(\Base\Service\ObfuscatorInterface::class)->encode(['token' => 'another-token'], \Base\Service\ObfuscatorInterface::NO_SHORT);

        $answer = $this->post(route('ux_editorjs_uploadByFile', ['data' => $forged]), ['image' => UploadedFile::fake()->image('ringo.png', 4, 4)]);
        $answer->assertStatus(500);
        $this->post(route('ux_editorjs_endpointByUser', ['data' => $forged]), ['query' => 'ringo'])->assertStatus(500);
    }

    public function testTheCollaborationChecksTheRoomsToken(): void
    {
        $this->data();
        $this->postJson(route('ux_editorjs_collabTicket'), [])->assertStatus(400)->assertJson(['success' => EditorJs::STATUS_BAD]);
        $this->postJson(route('ux_editorjs_collabTicket'), ['room' => 'Base\Entity\Thread:1:content:_', 'token' => 'nope'])->assertStatus(403)->assertJson(['success' => EditorJs::STATUS_NOTOKEN]);
        $this->postJson(route('ux_editorjs_autosave'), ['fqcn' => 'Base\Entity\Thread'])->assertStatus(400);
        $this->postJson(route('ux_editorjs_autosave'), ['fqcn' => 'Base\Entity\Thread', 'id' => 1, 'field' => 'content', 'token' => 'nope'])->assertStatus(403);
    }
}
