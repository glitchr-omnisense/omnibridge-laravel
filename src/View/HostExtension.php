<?php

namespace Omnibridge\Laravel\View;

use Illuminate\Http\Request;
use Omnibridge\Contract\Urls;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Laravel's answers to what a Symfony site's templates take from Symfony's
 * own bundles rather than from omnibase - the host's part, which Symfony
 * answers with its own (audit "lesringostars face au master plan", R24):
 *
 * - encore_entry_link_tags(), encore_entry_script_tags(),
 *   encore_entry_css_source() (WebpackEncoreBundle, through omnibase's
 *   WebpackTagRenderer): Encore, over the builds omnibridge.encore names;
 * - html_entry_noscript_tags() (omnibase's HtmlTagRenderer, which collects
 *   what the services of a Symfony request add): nothing adds any here;
 * - stimulus_controller() (StimulusBundle): data-controller="name", the
 *   name as StimulusBundle normalises it;
 * - absolute_url() (Twig bridge's HttpFoundationExtension) and the `|url`
 *   filter (omnibase's router, AdvancedRouter::getUrl()): an address whole,
 *   at the request's origin; a route's name by the Urls contract.
 */
final class HostExtension extends AbstractExtension
{
    /**
     * @param \Closure(): ?Request $request the request being answered
     */
    public function __construct(
        private readonly Urls $urls,
        private readonly \Closure $request,
        private readonly ?Encore $encore = null,
    ) {
    }

    public function getFunctions(): array
    {
        $safe = ['is_safe' => ['html']];
        $functions = [
            new TwigFunction('html_entry_noscript_tags', static fn (): string => '', $safe),
            new TwigFunction('stimulus_controller', self::stimulusController(...)),
            new TwigFunction('absolute_url', $this->absoluteUrl(...)),
        ];
        if (null !== $this->encore) {
            $encore = $this->encore;
            $functions[] = new TwigFunction('encore_entry_link_tags', static fn (string|array $entry): string => $encore->linkTags(self::entry($entry)), $safe);
            $functions[] = new TwigFunction('encore_entry_script_tags', static fn (string|array $entry): string => $encore->scriptTags(self::entry($entry)), $safe);
            $functions[] = new TwigFunction('encore_entry_css_source', static fn (string|array $entry): string => $encore->cssSource(self::entry($entry)), ['is_safe' => ['all']]);
        }

        return $functions;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('url', $this->url(...)),
        ];
    }

    /**
     * An entry's name, or omnibase's {value: name} form.
     *
     * @param string|array<mixed> $entry
     */
    private static function entry(string|array $entry): string
    {
        $value = \is_array($entry) ? ($entry['value'] ?? '') : $entry;

        return \is_string($value) ? $value : '';
    }

    /** data-controller="name", the name as StimulusBundle normalises it (a/b: a--b, a_b: a-b, no leading @). */
    public static function stimulusController(string $name): Markup
    {
        $name = (string) preg_replace('/^@/', '', str_replace('_', '-', str_replace('/', '--', $name)));

        return new Markup('data-controller="'.htmlspecialchars($name, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8').'"', 'UTF-8');
    }

    /** Symfony's absolute_url(): a path whole at the request's origin, a relative one under the page's folder. */
    public function absoluteUrl(?string $path): string
    {
        $path = (string) $path;
        if (str_contains($path, '://') || str_starts_with($path, '//')) {
            return $path;
        }
        $request = ($this->request)();
        if (null === $request) {
            return $path;
        }
        if ('' !== $path && '#' === $path[0]) {
            $path = $request->getRequestUri().$path;
        } elseif ('' !== $path && '?' === $path[0]) {
            $path = $request->getPathInfo().$path;
        }
        if ('' === $path || '/' !== $path[0]) {
            $prefix = $request->getPathInfo();
            $last = \strlen($prefix) - 1;
            if ($last !== $pos = strrpos($prefix, '/')) {
                $prefix = substr($prefix, 0, (int) $pos).'/';
            }

            return $request->getUriForPath($prefix.$path);
        }

        return $request->getSchemeAndHttpHost().$path;
    }

    /**
     * omnibase's |url: none as it is; an address whole as it is; a path at
     * the request's origin; a route's name its address whole (Urls::url()).
     */
    public function url(?string $nameOrUrl): ?string
    {
        if (null === $nameOrUrl || '' === $nameOrUrl) {
            return $nameOrUrl;
        }
        $nameOrUrl = trim($nameOrUrl);
        if (false !== filter_var($nameOrUrl, \FILTER_VALIDATE_URL)) {
            return $nameOrUrl;
        }
        if (str_contains($nameOrUrl, '/')) {
            $request = ($this->request)();

            return ($request?->getSchemeAndHttpHost() ?? '').'/'.ltrim($nameOrUrl, '/');
        }

        return $this->urls->url($nameOrUrl);
    }
}
