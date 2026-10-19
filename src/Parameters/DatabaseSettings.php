<?php

namespace Omnibridge\Laravel\Parameters;

use Doctrine\DBAL\Connection;

/**
 * The settings an omnibase site keeps in its database (layoutSetting and its
 * translations, layoutSettingIntl), read through Doctrine DBAL in one query:
 * each setting's value in each language (a JSON value, as omnibase stores it,
 * none of a vault), and the parameter a setting stands for when the back
 * office links it to one (its "bag").
 *
 * A setting is read in the default language's value whatever the page's
 * language, else in any language it has: as omnibase's settings answer on a
 * page (an English page shows the French title though an en-GB one exists).
 */
final class DatabaseSettings
{
    /** @var array<string, array<string, mixed>>|null path => locale => value */
    private ?array $values = null;

    /** @var array<string, string> parameter => path */
    private array $bags = [];

    public function __construct(private readonly Connection $db)
    {
    }

    /** @return array<string, array<string, mixed>> every setting's values, by path then locale */
    public function values(): array
    {
        if (null === $this->values) {
            $this->values = [];
            $rows = $this->db->fetchAllAssociative(
                'SELECT s.path, s.bag, i.locale, i.value FROM layoutSetting s JOIN layoutSettingIntl i ON i.translatable_id = s.id WHERE i.vault IS NULL',
            );
            foreach ($rows as $row) {
                $this->values[$row['path']][(string) $row['locale']] = json_decode((string) $row['value'], true);
                if (null !== $row['bag'] && '' !== $row['bag']) {
                    $this->bags[$row['bag']] = $row['path'];
                }
            }
        }

        return $this->values;
    }

    /** A setting in the default language's value, else in any; null for none. */
    public function value(string $path, string $defaultLocale): mixed
    {
        $values = $this->values()[$path] ?? [];
        foreach ($values as $locale => $value) {
            if (str_starts_with($locale, substr($defaultLocale, 0, 2))) {
                return $value;
            }
        }

        return $values ? reset($values) : null;
    }

    /**
     * The settings linked to a parameter, by the parameter's full name: what
     * omnibase's HotParameters lays over the configuration.
     *
     * @return array<string, mixed>
     */
    public function parameters(string $defaultLocale): array
    {
        $this->values();
        $parameters = [];
        foreach ($this->bags as $parameter => $path) {
            $parameters[$parameter] = $this->value($path, $defaultLocale);
        }

        return $parameters;
    }
}
