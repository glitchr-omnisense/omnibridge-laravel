<?php

namespace Omnibridge\Laravel\Page;

use Base\Service\ThemizerInterface;

/**
 * app.theme, as omnibase's Themizer answers it: the mode and the filter the
 * visitor chose (their cookies THEME/MODE and THEME/FILTER), among those the
 * site offers. omnibase's ThemizerInterface too, which its theme menus read
 * (Base\Twig\Extension\MenuTwigExtension: render_theme_modes()...): no
 * widths, events nor audiences offered here. Its layouts too (app.theme.layout(1),
 * Themizer::getLayout()): the layoutN.html.twig of omnibridge.theme.layouts, a folder
 * (the site's templates/layout).
 */
final class Theme implements ThemizerInterface
{
    /**
     * @param array<string, array<string, string>> $modes
     * @param array<string, array<string, string>> $filters
     * @param array<int, string>                   $layouts  layoutN.html.twig by N
     */
    public function __construct(
        private readonly array $modes,
        private readonly array $filters,
        private readonly ?string $modeId,
        private readonly ?string $filterId,
        private readonly array $noFilter,
        private readonly array $layouts = [],
    ) {
    }

    /**
     * The visitor's theme among those omnibridge.theme offers (its modes, filters, no_filter),
     * their cookies THEME/MODE and THEME/FILTER read from the request; none without modes.
     *
     * @param array{modes?: array<string, array<string, string>>, filters?: array<string, array<string, string>>, no_filter?: array<string, mixed>, layouts?: string} $config
     */
    public static function of(array $config, ?\Illuminate\Http\Request $request): ?self
    {
        if (empty($config['modes'])) {
            return null;
        }
        $mode = $request?->cookie('THEME/MODE');
        $filter = $request?->cookie('THEME/FILTER');

        return new self($config['modes'], $config['filters'] ?? [], \is_string($mode) ? $mode : null, \is_string($filter) ? $filter : null, $config['no_filter'] ?? [], self::layouts($config['layouts'] ?? null));
    }

    /**
     * The layout of that number (layout1.html.twig), else the first there is, as omnibase's
     * Themizer::getLayout() answers it.
     */
    public function getLayout(int|string $id = 0): string
    {
        return $this->layouts[(int) $id] ?? (array_values($this->layouts)[0] ?? throw new \RuntimeException('No layout: omnibridge.theme.layouts names no folder of layoutN.html.twig.'));
    }

    /**
     * A folder's layouts, as omnibase's Themizer finds them: layoutN.html.twig by N.
     *
     * @return array<int, string>
     */
    private static function layouts(?string $folder): array
    {
        $layouts = [];
        foreach (null !== $folder && is_dir($folder) ? (scandir($folder) ?: []) : [] as $file) {
            if (is_file($folder.'/'.$file) && preg_match('/^layout(\d+)\.html\.twig$/', $file, $match)) {
                $layouts[(int) $match[1]] = $file;
            }
        }

        return $layouts;
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
