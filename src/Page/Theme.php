<?php

namespace Omnibridge\Laravel\Page;

/**
 * app.theme, as omnibase's Themizer answers it: the mode and the filter the
 * visitor chose (their cookies THEME/MODE and THEME/FILTER), among those the
 * site offers.
 */
final class Theme
{
    /**
     * @param array<string, array<string, string>> $modes
     * @param array<string, array<string, string>> $filters
     */
    public function __construct(
        private readonly array $modes,
        private readonly array $filters,
        private readonly ?string $modeId,
        private readonly ?string $filterId,
        private readonly array $noFilter,
    ) {
    }

    /** @return array<string, string>|null */
    public function mode(): ?array
    {
        return $this->modes[$this->modeId ?? ''] ?? ($this->modes[array_key_first($this->modes)] ?? null);
    }

    /** @return array<string, string>|null */
    public function filter(): ?array
    {
        return $this->filters[$this->filterId ?? ''] ?? $this->noFilter;
    }

    public function audience(): ?array
    {
        return null;
    }

    public function event(): ?array
    {
        return null;
    }

    public function width(): ?array
    {
        return null;
    }

    public function color_scheme(): string
    {
        return $this->mode()['prefers'] ?? 'light dark';
    }

    /** @return array<string, array<string, string>> */
    public function modes(): array
    {
        return $this->modes;
    }

    /** @return array<string, array<string, string>> */
    public function filters(): array
    {
        return $this->filters;
    }
}
