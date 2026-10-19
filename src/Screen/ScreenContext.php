<?php

namespace Omnibridge\Laravel\Screen;

use Base\Screen\ContextProvider;
use Base\Screen\Frame;
use Base\Screen\Site;
use Base\Screen\Viewer;
use Illuminate\Contracts\Foundation\Application;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Locales;
use Omnibridge\Contract\Urls;
use Omnibridge\Laravel\Page\Theme;
use Omnibridge\Laravel\Parameters\DatabaseSettings;

/**
 * omnibase's screen context under Laravel, as Base\Bridge\Symfony\SymfonyScreenContext
 * gives it under Symfony, asked once for the screen being answered:
 *
 * - the locale: the site's locale of Laravel's (Locales: fr is fr-FR) in the
 *   form a Symfony request carries it (fr_FR);
 * - the host: the scheme, host and base path the page was asked at;
 * - the site: omnibase's settings (base.settings.title, slogan, logo,
 *   meta.description) in the default language, the logo by its asset's address
 *   unless it is one already, the year;
 * - the links: home (app_index, else "/"), login, register, logout, and each
 *   language's address (_switch_locale), those Laravel (or elsewhere()) answers;
 * - the viewer: the Access contract's user (the guard's);
 * - the theme: the colour scheme of the mode the visitor chose (their cookie
 *   THEME/MODE) among omnibridge.theme.modes, none without modes;
 * - the flashes: the session's messages under that key, taken.
 *
 * An application binds its own (Base\Screen\ContextProvider) to add its frame fields.
 */
class ScreenContext implements ContextProvider
{
    public function __construct(private readonly Application $app)
    {
    }

    public function frame(array $extra = []): Frame
    {
        $request = $this->app->bound('request') ? $this->app->make('request') : null;

        return new Frame(
            locale: str_replace('-', '_', $this->app->make(Locales::class)->getLocale()),
            site: $this->site(),
            links: $this->links(),
            viewer: $this->viewer(),
            theme: $this->theme(),
            extra: $extra,
            host: $request ? $request->getSchemeAndHttpHost().$request->getBasePath() : '',
        );
    }

    public function viewer(): ?Viewer
    {
        try {
            $user = $this->app->make(Access::class)->getUser();
        } catch (\Throwable) {
            return null;
        }
        if (!\is_object($user)) {
            return null;
        }
        $id = method_exists($user, 'getId') ? $user->getId() : (method_exists($user, 'getAuthIdentifier') ? $user->getAuthIdentifier() : null);
        $name = method_exists($user, '__toString') ? (string) $user : (string) ($user->name ?? '');

        return new Viewer(\is_int($id) ? $id : null, $name);
    }

    public function flashes(string $type): array
    {
        if (!$this->app->bound('session')) {
            return [];
        }

        return array_values(array_map('strval', (array) $this->app->make('session')->pull($type, [])));
    }

    protected function site(): Site
    {
        $logo = $this->setting('base.settings.logo');

        return new Site(
            title: $this->setting('base.settings.title'),
            slogan: $this->setting('base.settings.slogan'),
            logo: null !== $logo && !preg_match('#^(?:[a-z]+:)?//|^/#i', $logo) ? $this->app->make(Urls::class)->asset($logo) : $logo,
            age: date('Y'),
            description: $this->setting('base.settings.meta.description'),
        );
    }

    /** @return array<string, string|array<string, string>|null> */
    protected function links(): array
    {
        $links = [
            'home' => $this->path('app_index') ?? '/',
            'login' => $this->path('security_login'),
            'register' => $this->path('security_register'),
            'logout' => $this->path('security_logout'),
        ];
        $locales = [];
        foreach ($this->app->make(Locales::class)->getAvailableLocales() as $locale) {
            $lang = strtolower(explode('-', str_replace('_', '-', $locale))[0]);
            if (null !== $address = $this->path('_switch_locale', ['_locale' => $lang])) {
                $locales[$lang] = $address;
            }
        }

        return $links + ['locales' => $locales];
    }

    /** @return array{scheme?: string} */
    protected function theme(): array
    {
        $config = $this->app['config']->get('omnibridge.theme', []);
        if (empty($config['modes'])) {
            return [];
        }
        $request = $this->app->bound('request') ? $this->app->make('request') : null;
        $theme = new Theme($config['modes'], $config['filters'] ?? [], $request?->cookie('THEME/MODE'), $request?->cookie('THEME/FILTER'), $config['no_filter'] ?? []);

        return ['scheme' => $theme->color_scheme()];
    }

    /** A route's address, null when neither Laravel nor elsewhere() answers it. */
    protected function path(string $route, array $parameters = []): ?string
    {
        try {
            return $this->app->make(Urls::class)->path($route, $parameters);
        } catch (\Throwable) {
            return null;
        }
    }

    private function setting(string $path): ?string
    {
        if (null === $this->app['config']->get('omnibridge.doctrine.connection')) {
            return null;
        }
        try {
            $value = $this->app->make(DatabaseSettings::class)->value($path, $this->app->make(Locales::class)->getDefaultLocale());
        } catch (\Throwable) {
            return null;
        }

        return \is_scalar($value) && '' !== (string) $value ? (string) $value : null;
    }
}
