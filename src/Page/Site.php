<?php

namespace Omnibridge\Laravel\Page;

use Omnibridge\Laravel\Parameters\DatabaseSettings;

/**
 * app.site, as omnibase's SiteVariable answers it, from the site's settings
 * (DatabaseSettings): its title and slogan, its logo, its meta defaults, the
 * years since it opened, the time the page took.
 */
final class Site
{
    /**
     * @param \Closure(string $uuid): string|null $upload the address of an uploaded file (the logo)
     */
    public function __construct(
        private readonly DatabaseSettings $settings,
        private readonly string $defaultLocale,
        private readonly ?\Closure $upload = null,
    ) {
    }

    public function title(): ?string
    {
        return $this->setting('base.settings.title');
    }

    public function slogan(): ?string
    {
        return $this->setting('base.settings.slogan');
    }

    /** The logo's file (base.settings.logo, an upload), at the address the application serves it at. */
    public function logo(): ?string
    {
        $uuid = $this->setting('base.settings.logo');

        return \is_string($uuid) && null !== $this->upload ? ($this->upload)($uuid) : null;
    }

    /**
     * The page's meta: the site's defaults (meta.description._self...) under
     * what the page says.
     *
     * @param array<string, mixed> $meta
     *
     * @return array<string, mixed>
     */
    public function meta(array $meta = []): array
    {
        $defaults = [];
        foreach (['description', 'keywords', 'author'] as $key) {
            $defaults[$key] = ['_self' => $this->setting('base.settings.meta.'.$key)];
        }

        return array_merge($defaults, $meta);
    }

    public function menu(): ?array
    {
        return null;
    }

    /** The years since it opened (base.settings.launchdate), this year when it does not say. */
    public function age(): string
    {
        $launch = $this->setting('base.settings.launchdate');
        $year = date('Y');
        if (\is_array($launch) && isset($launch['date'])) {
            $launchYear = substr((string) $launch['date'], 0, 4);

            return $launchYear < $year ? $launchYear.'-'.$year : $launchYear;
        }

        return $year;
    }

    public function execution_time(): float
    {
        return round(microtime(true) - (\defined('LARAVEL_START') ? LARAVEL_START : $_SERVER['REQUEST_TIME_FLOAT']), 2);
    }

    private function setting(string $path): mixed
    {
        return $this->settings->value($path, $this->defaultLocale);
    }
}
