<?php

namespace Omnibridge\Laravel;

use Illuminate\Support\Facades\Route;
use Omnibridge\Contract\Urls;
use Omnibridge\Laravel\Routing\LocalizedNames;

/**
 * Addresses answered by Laravel: a route by its name through Laravel's
 * router, relative (path()) or whole (url()); a public file by its path,
 * through the asset manifest of a build when one is given (a webpack or Vite
 * manifest: "./fonts/RobotoSlab-Bold.woff2" is
 * "/assets/fonts/RobotoSlab-Bold.9d4f57b2.woff2"), else as it is.
 *
 * A route Laravel does not have may be answered elsewhere (elsewhere()): an
 * application that serves part of a site gives the other part's addresses.
 * Without that, an unknown route is Laravel's error.
 *
 * Names may be read as omnibase's router reads them (localized()): a route
 * declared once per language, or in a version without a destination, by its
 * name alone (Routing\LocalizedNames: "app_timeline" is "app_timeline.fr" on
 * a French page).
 *
 * omniscient/omnibridge's Urls (omnibase's Base\Contract\Urls and omnibridge/twig's
 * by their former names): one object answers the templates' path(), url(), asset()
 * and Runtime::urls().
 */
final class RouterUrls implements Urls
{
    /** @var array<string, string>|null */
    private ?array $manifest = null;

    /**
     * @param (\Closure(string $name, array<string, mixed> $parameters, string $locale): string)|null $elsewhere
     */
    public function __construct(
        private readonly ?\Closure $elsewhere = null,
        private readonly ?string $manifestFile = null,
        private readonly ?string $fallbackLocale = null,
    ) {
    }

    /**
     * The same addresses, a route Laravel does not have answered by $elsewhere.
     *
     * @param \Closure(string $name, array<string, mixed> $parameters, string $locale): string $elsewhere
     */
    public function elsewhere(\Closure $elsewhere): self
    {
        return new self($elsewhere, $this->manifestFile, $this->fallbackLocale);
    }

    /**
     * The same addresses, a name read as omnibase's router reads it (in the
     * page's language, then without a destination, then in $fallbackLocale).
     */
    public function localized(string $fallbackLocale = 'en'): self
    {
        return new self($this->elsewhere, $this->manifestFile, $fallbackLocale);
    }

    public function path(string $name, array $parameters = []): string
    {
        $parameters = array_filter($parameters, static fn ($value) => null !== $value);
        $resolved = $this->resolve($name);
        if (null !== $resolved || null === $this->elsewhere) {
            return route($resolved ?? $name, $parameters, false);
        }

        return ($this->elsewhere)($name, $parameters, app()->getLocale());
    }

    /** The same route, whole. */
    public function url(string $name, array $parameters = []): string
    {
        $path = $this->path($name, $parameters);

        return str_starts_with($path, '/') ? request()->getSchemeAndHttpHost().$path : $path;
    }

    /** The route a name means here, null for none. */
    public function resolve(string $name): ?string
    {
        if (Route::has($name)) {
            return $name;
        }
        if (null === $this->fallbackLocale) {
            return null;
        }

        return LocalizedNames::resolve($name, app()->getLocale(), array_keys(Route::getRoutes()->getRoutesByName()), $this->fallbackLocale);
    }

    public function asset(string $path): string
    {
        return $this->manifest()[$path] ?? '/'.ltrim($path, '/');
    }

    /** @return array<string, string> */
    private function manifest(): array
    {
        return $this->manifest ??= $this->manifestFile && is_file($this->manifestFile)
            ? json_decode((string) file_get_contents($this->manifestFile), true, flags: \JSON_THROW_ON_ERROR)
            : [];
    }
}
