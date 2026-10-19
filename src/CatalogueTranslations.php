<?php

namespace Omnibridge\Laravel;

use Base\Contract\Translations;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\Translator;

/**
 * Messages from omnibase's ICU catalogues (<domain>+intl-icu.<locale>.yaml,
 * or <domain>.<locale>.yaml) in the folders given - the core's, the bundles',
 * the site's last so that its own words win - read by Symfony's Translation
 * component (a library, not the framework), so that Laravel says the same
 * words as Symfony.
 *
 * A message is named as omnibase's templates name it: "@messages.menu.item2"
 * is menu.item2 of the messages domain; its parameters by position ({0},
 * {1}) or by name ({privacy}); a message that names another one is
 * translated again; one that is nowhere is printed as it was asked for.
 *
 * Without a locale given, each message is in Laravel's current one
 * (app()->getLocale()), which a middleware sets per request.
 *
 * omnibase's Base\Contract\Translations (trans() and exists()), hence
 * omnibridge/twig's.
 */
final class CatalogueTranslations implements Translations
{
    private const NESTED_DEPTH = 5;

    private Translator $translator;

    /**
     * @param string|list<string> $directories the catalogues' folders, the later ones over the earlier
     */
    public function __construct(string|array $directories, private readonly ?string $locale = null, private readonly string $fallback = 'fr')
    {
        $this->translator = new Translator($locale ?? $fallback);
        $this->translator->setFallbackLocales([$fallback]);
        $this->translator->addLoader('yaml', new YamlFileLoader());
        foreach ((array) $directories as $directory) {
            foreach (glob(rtrim($directory, '/').'/*.yaml') ?: [] as $file) {
                if (preg_match('~/([^/+.]+)(\+intl-icu)?\.([a-zA-Z_]+)\.yaml$~', $file, $m)) {
                    $this->translator->addResource('yaml', $file, $m[3], $m[1].$m[2]);
                }
            }
        }
    }

    public function translator(): Translator
    {
        return $this->translator;
    }

    public function locale(): string
    {
        return $this->locale ?? app()->getLocale();
    }

    public function trans(mixed $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $id = trim((string) $id);
        if ('' === $id) {
            return $id;
        }
        $locale ??= $this->locale();
        $asked = $id;
        [$id, $domain] = self::named($id, $domain);

        $named = [];
        foreach ($parameters as $key => $value) {
            $key = (string) $key;
            $named[preg_match('/^[{%].*[}%]$/', $key) ? $key : '{'.$key.'}'] = $value;
        }

        $catalogue = $this->translator->getCatalogue($locale);
        if (!$this->defined($catalogue, $id, $domain)) {
            return $asked;
        }
        $text = $this->translator->trans($id, $this->icu($named), $domain, $locale);
        for ($depth = 0; $depth < self::NESTED_DEPTH && $text !== $id && $this->defined($catalogue, $text, $domain); ++$depth) {
            $text = $this->translator->trans($text, $this->icu($named), $domain, $locale);
        }

        return trim($text);
    }

    public function exists(string $id, ?string $domain = null, ?string $locale = null): bool
    {
        [$id, $domain] = self::named(trim($id), $domain);

        return '' !== $id && $this->defined($this->translator->getCatalogue($locale ?? $this->locale()), $id, $domain);
    }

    /** @return array{string, string} the key and its domain: "@domain.key", else the domain given, else "messages" */
    private static function named(string $id, ?string $domain): array
    {
        $domain = null !== $domain ? ltrim($domain, '@') : null;
        if (str_starts_with($id, '@') && str_contains($id, '.')) {
            [$domain, $id] = explode('.', substr($id, 1), 2);
        }

        return [$id, $domain ?? 'messages'];
    }

    private function defined(MessageCatalogueInterface $catalogue, string $id, string $domain): bool
    {
        for ($c = $catalogue; $c; $c = $c->getFallbackCatalogue()) {
            if ($c->defines($id, $domain) || $c->defines($id, $domain.'+intl-icu')) {
                return true;
            }
        }

        return false;
    }

    /**
     * An ICU message takes its parameters by their bare names ({0} is "0"),
     * an older one by the placeholder itself: both are given.
     *
     * @param array<string, mixed> $named
     *
     * @return array<string, mixed>
     */
    private function icu(array $named): array
    {
        $parameters = $named;
        foreach ($named as $key => $value) {
            $parameters[trim($key, '{}%')] = $value;
        }

        return $parameters;
    }
}
