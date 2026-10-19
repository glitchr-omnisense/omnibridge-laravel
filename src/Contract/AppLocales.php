<?php

namespace Omnibridge\Laravel\Contract;

use Base\Contract\Locales;

/**
 * The site's languages in omnibase's form (fr-FR: language and country
 * joined by a dash) over Laravel's locale (fr, or fr_FR): the request's is
 * the site's language that Laravel's current locale names, the default the
 * one configured (or the first), the default first among them all.
 */
final class AppLocales implements Locales
{
    /** @param list<string> $available the site's locales (fr-FR, en-GB...) */
    public function __construct(private readonly array $available, private readonly ?string $default = null)
    {
    }

    public function getLocale(): string
    {
        return $this->resolve(app()->getLocale());
    }

    public function getDefaultLocale(): string
    {
        return $this->resolve($this->default ?? $this->available[0] ?? (string) config('app.locale'));
    }

    public function getAvailableLocales(): array
    {
        $default = $this->getDefaultLocale();
        $others = array_values(array_filter(array_map(self::form(...), $this->available), static fn (string $locale) => $locale !== $default));

        return [$default, ...$others];
    }

    /** The site's locale a Laravel locale names: the same, else the first of the same language, else itself in omnibase's form. */
    private function resolve(string $locale): string
    {
        $locale = self::form($locale);
        foreach ($this->available as $each) {
            if (self::form($each) === $locale) {
                return $locale;
            }
        }
        $language = strtolower(substr($locale, 0, 2));
        foreach ($this->available as $each) {
            if (strtolower(substr($each, 0, 2)) === $language) {
                return self::form($each);
            }
        }

        return $locale;
    }

    private static function form(string $locale): string
    {
        return str_replace('_', '-', $locale);
    }
}
