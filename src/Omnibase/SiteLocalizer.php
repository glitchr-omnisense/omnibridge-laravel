<?php

namespace Omnibridge\Laravel\Omnibase;

use Base\Service\Localizer;
use Base\Service\ParameterBagInterface;
use Base\Service\TranslatorInterface;

/**
 * omnibase's localizer on the site's languages under Laravel: where Symfony reads them from kernel.default_locale and
 * the translator's fallbacks, this host's are omnibridge.locales.default and omnibridge.locales.available. Without
 * them, the localizer knew the translations' fallback only (fr-BE on the twin), and every rule asking it which
 * languages the site speaks - the language menu's (Base\Request\LocaleSwitch) - refused English and German.
 * Without such a configuration, the localizer reads what it read before.
 */
final class SiteLocalizer extends Localizer
{
    /** @param list<string> $available */
    public function __construct(ParameterBagInterface $parameterBag, TranslatorInterface $translator, string $cacheDir, ?string $default = null, array $available = [])
    {
        $available = array_values(array_filter($available, static fn ($locale): bool => \is_string($locale) && '' !== $locale));
        $default = null !== $default && '' !== $default ? $default : ($available[0] ?? null);
        if (null !== $default) {
            self::$defaultLocale = self::normalizeLocale($default);
            self::$fallbackLocales = array_values(array_diff(self::normalizeLocale($available), [self::$defaultLocale]));
        }

        parent::__construct($parameterBag, $translator, $cacheDir);
    }
}
