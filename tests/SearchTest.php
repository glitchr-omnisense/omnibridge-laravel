<?php

namespace Omnibridge\Laravel\Tests;

use Base\Enum\UserGender;
use Base\Field\Search\RemoteSearch;
use Base\Service\Icons;
use Base\Service\Model\IconizeInterface;
use Base\Service\Model\IconProvider\IconAdapterInterface;
use Illuminate\Support\Facades\Route;

/**
 * omnibase's remote search under Laravel (plan "omnibase agnostic", step F5d): with media on, the routes
 * of Base\Field\Search\RemoteSearch::ROUTES are declared under their Symfony names and paths, and
 * answered by SearchController - a select's payload made with the visitor's session token gives an
 * enumeration's values and an icon adapter's icons, as select2 asks them (GET in debug); another
 * token, an unknown payload, a POST outside debug are refused. Off, nothing is declared.
 */
final class SearchTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        if ('testNoSearchWithoutMedia' === $this->name()) {
            return;
        }
        $root = sys_get_temp_dir().'/omnibridge-laravel-search-'.getmypid();
        $app['config']->set('app.debug', true);
        $app['config']->set('omnibridge.media', true);
        $app['config']->set('filesystems.disks.omnibase-cache', ['driver' => 'local', 'root' => $root.'/cache']);
        $app['config']->set('omnibridge.storages', ['local.cache' => 'omnibase-cache']);
        $app['config']->set('base.obfuscator.cache_dir', $root.'/obfuscator');
        // omnibase's services (its translator names the entries), on the mapping omnibase's harness records.
        $app['config']->set('omnibridge.doctrine.entities', [self::FIXTURES.'/Omnibase/Entity']);
        $app['config']->set('omnibridge.doctrine.omnibase', ['project_dir' => self::FIXTURES.'/Omnibase']);
        $app['config']->set('omnibridge.translations.paths', [self::FIXTURES.'/translations', \dirname((new \ReflectionClass(RemoteSearch::class))->getFileName(), 4).'/translations']);
        // One adapter of three icons, in place of the metadata of Font Awesome's.
        $app->singleton(Icons::class, static fn (): Icons => new Icons([new SearchTestIcons()]));
    }

    public function testNoSearchWithoutMedia(): void
    {
        self::assertFalse(Route::has('ux_autocomplete'));
        self::assertFalse(Route::has('ux_autocomplete_icons'));
    }

    public function testTheRoutesAreSymfonys(): void
    {
        self::assertSame('ux/autocomplete/{data}', Route::getRoutes()->getByName('ux_autocomplete')->uri());
        self::assertSame('ux/autocomplete/{provider}/{pageSize}/{data}', Route::getRoutes()->getByName('ux_autocomplete_icons')->uri());
    }

    private function data(?string $class): string
    {
        $this->withSession(['_token' => 'the-visitors-token']);

        return $this->app->make(RemoteSearch::class)->data($class);
    }

    public function testAnEnumerationsValuesAreFound(): void
    {
        $data = $this->data(UserGender::class);

        $answer = $this->get(route('ux_autocomplete', ['data' => $data]).'?term=');

        $answer->assertOk();
        self::assertNotEmpty($answer->json('results'));
        self::assertFalse($answer->json('pagination'));
    }

    public function testAnAdaptersIconsAreFoundByTheTerm(): void
    {
        $data = $this->data(null);

        $answer = $this->get(route('ux_autocomplete_icons', ['provider' => 'test', 'pageSize' => 200, 'data' => $data]).'?term=moon');

        $answer->assertOk();
        $found = json_encode($answer->json('results'));
        self::assertStringContainsString('"id":"test-moon"', $found);
        self::assertStringContainsString('"text":"Moon"', $found);
        self::assertStringNotContainsString('test-anchor', $found);
        self::assertFalse($answer->json('pagination.more'));
    }

    public function testAnotherTokenOrAnUnknownPayloadIsRefused(): void
    {
        $this->data(null);
        $forged = $this->app->make(\Base\Service\ObfuscatorInterface::class)->encode(['class' => UserGender::class, 'fields' => [], 'filters' => [], 'capitalize' => null, 'html' => false, 'avatar' => false, 'token_name' => RemoteSearch::TOKEN, 'token' => 'another-token'], true);

        $this->get(route('ux_autocomplete', ['data' => $forged]))->assertStatus(500);
        self::assertSame('Invalid token. Please refresh the page and try again', $this->get(route('ux_autocomplete', ['data' => $forged]))->json());
        self::assertSame('Unexpected request', $this->get(route('ux_autocomplete', ['data' => 'nothing-known']))->json());
        $this->get(route('ux_autocomplete_icons', ['provider' => 'test', 'pageSize' => 200, 'data' => $forged]))->assertStatus(500);
    }
}

/** Three icons, two of the sky, as an adapter lists them: name => icon, by group. */
final class SearchTestIcons implements IconAdapterInterface
{
    public function supports(IconizeInterface|string|null $icon): bool
    {
        return 'test' === $icon || \is_string($icon) && str_starts_with($icon, 'test-');
    }

    public function iconify(IconizeInterface|string $icon, array $attributes): string
    {
        return '<i class="'.$icon.'"></i>';
    }

    public static function getName(): string
    {
        return 'test';
    }

    public static function getOptions(): array
    {
        return [];
    }

    public function getVersion(): string
    {
        return '1';
    }

    public function getAssets(): array
    {
        return [];
    }

    public function getChoices(string $term = '')
    {
        $choices = ['Sky' => ['Moon' => 'test-moon', 'Star' => 'test-star'], 'Things' => ['Anchor' => 'test-anchor']];

        return '' === $term ? $choices : array_filter(array_map(static fn (array $group): array => array_filter($group, static fn (string $name): bool => str_contains(mb_strtolower($name), $term), \ARRAY_FILTER_USE_KEY), $choices));
    }
}
