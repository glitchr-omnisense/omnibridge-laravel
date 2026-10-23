<?php

namespace Omnibridge\Laravel\Page;

use Base\Service\ThemizerInterface;

/**
 * app.theme, as omnibase's Themizer answers it: the mode and the filter the
 * visitor chose (their cookies THEME/MODE and THEME/FILTER), among those the
 * site offers. omnibase's ThemizerInterface too, which its theme menus read
 * (Base\Twig\Extension\MenuTwigExtension: render_theme_modes()...): no
 * widths, events nor audiences offered here.
 */
final class Theme implements ThemizerInterface
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

    /**
     * The visitor's theme among those omnibridge.theme offers (its modes, filters, no_filter),
     * their cookies THEME/MODE and THEME/FILTER read from the request; none without modes.
     *
     * @param array{modes?: array<string, array<string, string>>, filters?: array<string, array<string, string>>, no_filter?: array<string, mixed>} $config
     */
    public static function of(array $config, ?\Illuminate\Http\Request $request): ?self
    {
        if (empty($config['modes'])) {
            return null;
        }
        $mode = $request?->cookie('THEME/MODE');
        $filter = $request?->cookie('THEME/FILTER');

        return new self($config['modes'], $config['filters'] ?? [], \is_string($mode) ? $mode : null, \is_string($filter) ? $filter : null, $config['no_filter'] ?? []);
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

    public function getAvailableModes(): array
    {
        return $this->modes();
    }

    public function getMode(): ?array
    {
        return $this->mode();
    }

    public function getAvailableFilters(): array
    {
        return $this->filters();
    }

    public function getFilter(): ?array
    {
        return $this->filter();
    }

    public function getAvailableWidths(): array
    {
        return [];
    }

    public function getWidth(): ?array
    {
        return $this->width();
    }

    public function getAvailableEvents(): array
    {
        return [];
    }

    public function getEvent(): ?array
    {
        return $this->event();
    }

    public function getAudienceCategories(): array
    {
        return [];
    }

    public function getAudience(): ?array
    {
        return $this->audience();
    }
}
