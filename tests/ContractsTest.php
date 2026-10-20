<?php

namespace Omnibridge\Laravel\Tests;

use Base\Contract;
use Base\Contract\Runtime;
use Base\Service\ParameterBagInterface;
use Omnibridge\Contract as Host;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Omnibridge\Laravel\CatalogueTranslations;
use Omnibridge\Laravel\RouterUrls;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\MailerInterface;

/** Every contract of the Foundation the provider binds, reached through omnibase's Runtime as through Laravel's container: one object. */
final class ContractsTest extends TestCase
{
    public function testRuntimeAndTheContainerGiveTheSameObjects(): void
    {
        $contracts = [
            Host\Urls::class, Host\Translations::class, Host\Locales::class, Host\Access::class, Host\Sessions::class,
            Host\Tokens::class, Host\Environment::class, Host\Routes::class, Host\Files::class,
            // Their former names, omnibase's and omnibridge/twig's (aliases until omnibase 4's final pass):
            Contract\Urls::class, \Omnibridge\Twig\Contract\Urls::class, Contract\Translations::class, \Omnibridge\Twig\Contract\Translations::class,
            Contract\Locales::class, Contract\Nouns::class, Contract\Access::class, Contract\Sessions::class, Contract\Tokens::class,
            Contract\Environment::class, Contract\Routes::class, Contract\Files::class, Contract\Pictures::class, Contract\Texts::class, ParameterBagInterface::class,
            ClockInterface::class, EventDispatcherInterface::class, MailerInterface::class,
        ];
        foreach ($contracts as $contract) {
            $this->assertTrue(Runtime::has($contract), $contract);
            $this->assertInstanceOf($contract, Runtime::get($contract));
            $this->assertSame($this->app->make($contract), Runtime::get($contract), $contract);
        }
        $this->assertInstanceOf(RouterUrls::class, Runtime::urls());
        $this->assertSame(Runtime::urls(), $this->app->make(\Omnibridge\Twig\Contract\Urls::class));
        $this->assertInstanceOf(CatalogueTranslations::class, Runtime::translations());
        $this->assertFalse(Runtime::has(Contract\Media::class), 'Media is not bound unless omnibridge.media is on (MediaTest)');
    }

    /** A former name is a Laravel alias of the contract's own: the binding made under the new name, an extension of it seen under the old. */
    public function testTheFormerNamesAreAliasesOfTheContracts(): void
    {
        foreach ([Host\Urls::class => [Contract\Urls::class, \Omnibridge\Twig\Contract\Urls::class], Host\Translations::class => [Contract\Translations::class, \Omnibridge\Twig\Contract\Translations::class], Host\Locales::class => [Contract\Locales::class], Host\Files::class => [Contract\Files::class]] as $contract => $formers) {
            foreach ($formers as $former) {
                $this->assertContains($former, Runtime::formerNames($contract));
                $this->assertTrue($this->app->isAlias($former), $former);
                $this->assertSame($this->app->make($contract), $this->app->make($former), $former);
            }
        }

        $this->app->extend(Host\Locales::class, fn (Host\Locales $locales) => new class($locales) implements Host\Locales {
            public function __construct(private Host\Locales $locales)
            {
            }

            public function getLocale(): string
            {
                return 'de-DE';
            }

            public function getDefaultLocale(): string
            {
                return $this->locales->getDefaultLocale();
            }

            public function getAvailableLocales(): array
            {
                return $this->locales->getAvailableLocales();
            }
        });
        Runtime::setContainer(null);
        Runtime::setContainer($this->app);
        $this->assertSame('de-DE', $this->app->make(Contract\Locales::class)->getLocale());
        $this->assertSame('de-DE', Runtime::locales()->getLocale());
    }

    public function testTheClockIsLaravelsDates(): void
    {
        Date::setTestNow('2026-04-10 18:05:00');
        $this->assertSame('2026-04-10T18:05:00+00:00', Runtime::clock()->now()->format(DATE_ATOM));
        $this->travel(5)->seconds();
        $this->assertSame('2026-04-10T18:05:05+00:00', Runtime::clock()->now()->format(DATE_ATOM));
        Date::setTestNow();
    }

    public function testAnEventGoesToLaravelsListenersAndComesBack(): void
    {
        $heard = [];
        Event::listen(\stdClass::class, function (\stdClass $event) use (&$heard) {
            $heard[] = $event;
        });
        $event = new \stdClass();

        $this->assertSame($event, Runtime::events()->dispatch($event));
        $this->assertSame([$event], $heard);
    }

    public function testTheEnvironmentIsLaravels(): void
    {
        $this->assertSame('testing', Runtime::environment()->name());
        $this->assertSame((bool) config('app.debug'), Runtime::environment()->isDebug());
        $this->assertSame(config('app.key'), Runtime::environment()->secret());
    }

    public function testAccessIsTheGateAndTheVisitorTheGuardsUser(): void
    {
        Gate::define('ROLE_ADMIN', static fn ($user) => 'admin' === $user->name);
        Gate::define('edit', static fn ($user, \stdClass $thread) => $thread->owner === $user->id);

        $this->assertNull(Runtime::access()->getUser());
        $this->assertFalse(Runtime::access()->isGranted('ROLE_ADMIN'), 'nobody signed in');

        $admin = new GenericUser(['id' => 1, 'name' => 'admin', 'remember_token' => null]);
        $this->actingAs($admin);
        $this->assertSame($admin, Runtime::access()->getUser());
        $this->assertTrue(Runtime::access()->isGranted('ROLE_ADMIN'));
        $this->assertTrue(Runtime::access()->isGranted('edit', (object) ['owner' => 1]));
        $this->assertFalse(Runtime::access()->isGranted('edit', (object) ['owner' => 2]));
        $this->assertFalse(Runtime::access()->isGranted('ROLE_UNDEFINED'));
    }

    public function testASessionEndsForTheUserSignedInOnly(): void
    {
        $user = new GenericUser(['id' => 1, 'name' => 'ringo', 'remember_token' => null]);
        $this->assertFalse(Runtime::sessions()->signOut($user), 'nobody signed in');

        $this->actingAs($user);
        $this->assertFalse(Runtime::sessions()->signOut(new GenericUser(['id' => 2, 'name' => 'starr', 'remember_token' => null])));
        $this->assertTrue(Runtime::sessions()->signOut($user));
        $this->assertNull(Runtime::access()->getUser());
    }

    public function testATokenIsTheSessions(): void
    {
        $this->app['session']->start();
        $this->assertSame($this->app['session.store']->token(), Runtime::tokens()->csrf('2fa_disable'));
        $this->assertSame(Runtime::tokens()->csrf('2fa_disable'), Runtime::tokens()->csrf('passkey_delete_12'));
    }

    public function testRoutesSayTheirPatternAndWhichAnAddressLeadsTo(): void
    {
        Route::get('/articles/{slug}', static fn () => '')->name('article');
        $this->app['router']->getRoutes()->refreshNameLookups();

        $this->assertSame('/articles/{slug}', Runtime::routes()->pattern('article'));
        $this->assertNull(Runtime::routes()->pattern('nowhere'));
        $this->assertSame('article', Runtime::routes()->match('/articles/le-fuji'));
        $this->assertNull(Runtime::routes()->match('/nowhere/at/all'));
        $this->assertNull(Runtime::routes()->icons('article'));
    }

    public function testNounsReadTheEntitiesCatalogues(): void
    {
        $this->assertSame('Fil', Runtime::nouns()->entity('App\\Entity\\Thread'));
        $this->assertTrue(Runtime::nouns()->entityExists('Base\\Entity\\Thread'));
        $this->assertFalse(Runtime::nouns()->entityExists('App\\Entity\\Gallery'));
    }

    public function testFilesAreTheDisks(): void
    {
        $root = sys_get_temp_dir().'/omnibridge-files';
        config()->set('filesystems.disks.uploads', ['driver' => 'local', 'root' => $root, 'url' => 'http://localhost/storage', 'visibility' => 'public']);
        config()->set('filesystems.disks.cloud', ['driver' => 's3', 'bucket' => 'b', 'region' => 'eu-west-3']);
        config()->set('omnibridge.storages', ['local.uploads' => 'uploads', 's3.private' => 'cloud']);
        $this->app->forgetInstance(Host\Files::class);
        Runtime::setContainer(null);
        Runtime::setContainer($this->app);
        @mkdir($root.'/logo', 0777, true);
        file_put_contents($root.'/logo/ringo.png', 'png');

        $files = Runtime::files();
        $this->assertFalse($files->isRemote('local.uploads'));
        $this->assertTrue($files->isRemote('s3.private'));
        $this->assertSame($root.'/logo/ringo.png', $files->localPath('logo/ringo.png', 'local.uploads'));
        $this->assertSame('/storage/logo/ringo.png', $files->publicUrl('logo/ringo.png', 'local.uploads'));
        $this->assertSame(public_path('storage/logo/ringo.png'), $files->publicPath('logo/ringo.png', 'local.uploads'));
        $this->assertNull($files->publicUrl('logo/missing.png', 'local.uploads'));
        $this->assertNull($files->localPath('logo/ringo.png', 's3.private'));
    }
}
