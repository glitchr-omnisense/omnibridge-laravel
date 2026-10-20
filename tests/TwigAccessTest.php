<?php

namespace Omnibridge\Laravel\Tests;

use Base\Service\ThemizerInterface;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Omnibridge\Laravel\Page\Theme;
use Omnibridge\Twig\AccessExtension;
use Twig\Environment;

/**
 * A page's is_granted() and csrf_token() under Laravel: omnibridge/twig's
 * AccessExtension on the container's Access (the gate) and Tokens (the
 * session's token), the code Symfony's pages ask as well; and app.theme as
 * omnibase's ThemizerInterface, which its theme menus read.
 */
final class TwigAccessTest extends TestCase
{
    public function testThePagesFunctionsAreTheContracts(): void
    {
        Gate::define('ROLE_USER', static fn ($user) => null !== $user);
        $this->app['session']->start();
        $twig = $this->app->make(Environment::class);

        $this->assertTrue($twig->hasExtension(AccessExtension::class));
        $page = $twig->createTemplate('{{ is_granted("ROLE_USER") ? "user" : "visitor" }}|{{ csrf_token("subscriber") }}');
        [$granted, $token] = explode('|', $page->render());
        $this->assertSame('visitor', $granted);
        $this->assertSame($this->app['session']->token(), $token, 'the session\'s token, the one VerifyCsrfToken checks');
        $this->assertNotSame('', $token);
        // The Tokens contract checks it as VerifyCsrfToken does: the session's token, whatever the intent.
        $tokens = $this->app->make(\Omnibridge\Contract\Tokens::class);
        $this->assertTrue($tokens->isValid('ux_typesense', $token));
        $this->assertFalse($tokens->isValid('ux_typesense', 'forged'));
        $this->assertFalse($tokens->isValid('ux_typesense', ''));
        $this->assertFalse($tokens->isValid('ux_typesense', null));

        $this->actingAs(new GenericUser(['id' => 1]));
        $this->assertStringStartsWith('user|', $page->render(), 'the gate, for the user signed in');
    }

    public function testTheThemeIsOmnibasesThemizer(): void
    {
        $theme = new Theme(['auto' => ['id' => 'auto'], 'dark' => ['id' => 'dark']], ['sepia' => ['id' => 'sepia']], 'dark', null, ['id' => null]);

        $this->assertInstanceOf(ThemizerInterface::class, $theme);
        $this->assertSame($theme->modes(), $theme->getAvailableModes());
        $this->assertSame(['id' => 'dark'], $theme->getMode());
        $this->assertSame(['sepia' => ['id' => 'sepia']], $theme->getAvailableFilters());
        $this->assertSame(['id' => null], $theme->getFilter(), 'no filter chosen');
        $this->assertSame([], $theme->getAvailableWidths());
        $this->assertNull($theme->getWidth());
    }
}
