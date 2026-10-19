<?php

namespace Omnibridge\Laravel\View;

/**
 * encore_entry_link_tags() and encore_entry_script_tags() as omnibase prints
 * them (its WebpackTagRenderer), over a site's builds (its own, omnibase's): an entry
 * is its files and those of its "-async" and "-defer" halves (app is
 * app-async and app-defer; form.editor is form-defer.editor), from the first
 * build that has any; a stylesheet comes with its breakpoint versions beside
 * it (layout2-async-mobile.css, media "all and (max-width: 470px)"); every
 * script is deferred; a file is printed once per page.
 */
final class Encore
{
    /** @var array<string, true> */
    private array $printed = [];

    /** @var array<string, array{entrypoints: array<string, array{js?: list<string>, css?: list<string>}>, manifest: array<string, string>}> */
    private array $read = [];

    /**
     * @param list<array{entrypoints: string, manifest: string}> $builds      the builds, in the order they are looked into
     * @param array<string, string>                              $breakpoints a name (mobile) and its media query
     * @param array<string, list<string>>                        $optional    the entries a page loads with another (form: form.editor)
     */
    public function __construct(
        private readonly array $builds,
        private readonly string $publicDir,
        private readonly array $breakpoints,
        private readonly array $optional = [],
    ) {
    }

    public function linkTags(string $entry): string
    {
        $tags = '';
        foreach ($this->files($entry, 'css', true) as $file) {
            $media = $this->media($file);
            $tags .= '<link rel="stylesheet" href="'.htmlspecialchars($file).'" '.(null !== $media ? 'media="'.htmlspecialchars($media).'"' : '').'>'."\n";
        }

        return $tags;
    }

    public function scriptTags(string $entry): string
    {
        $tags = '';
        foreach ($this->files($entry, 'js', false) as $file) {
            $tags .= '<script src="'.htmlspecialchars($file).'" defer=""></script>'."\n";
        }

        return $tags;
    }

    /** @return list<string> */
    private function files(string $entry, string $type, bool $breakpoints): array
    {
        $files = [];
        foreach ([$entry, ...($this->optional[$entry] ?? [])] as $name) {
            foreach ($this->builds as $index => $build) {
                $found = $this->entryFiles($index, $name, $type);
                if ($breakpoints) {
                    foreach ($found as $file) {
                        foreach ($this->breakpoints as $breakpoint => $_) {
                            $variant = $this->variant($index, $file, $breakpoint);
                            if (null !== $variant) {
                                $found[] = $variant;
                            }
                        }
                    }
                }
                if ($found) {
                    $files = [...$files, ...$found];
                    break;
                }
            }
        }

        $new = [];
        foreach ($files as $file) {
            if (!isset($this->printed[$type.$file])) {
                $this->printed[$type.$file] = true;
                $new[] = $file;
            }
        }

        return $new;
    }

    /** @return list<string> the files of an entry and of its -async and -defer halves */
    private function entryFiles(int $build, string $entry, string $type): array
    {
        $entrypoints = $this->build($build)['entrypoints'];
        [$head, $tail] = array_pad(explode('.', $entry, 2), 2, null);
        $files = [];
        foreach ([$head, $head.'-async', $head.'-defer'] as $name) {
            $prefixes = [$name];
            if (null !== $tail) {
                $prefixes[] = $name.'.'.$tail;
            }
            foreach (array_unique($prefixes) as $prefix) {
                $files = [...$files, ...($entrypoints[$prefix][$type] ?? [])];
            }
        }

        return array_values(array_unique($files));
    }

    /** A stylesheet's version for a breakpoint (base-async.25c495ee.css: base-async-mobile.css, by the manifest), when there is one. */
    private function variant(int $build, string $file, string $breakpoint): ?string
    {
        $stem = explode('.', basename($file))[0].'-'.$breakpoint.'.css';
        $manifest = $this->build($build)['manifest'];
        $url = $manifest['./'.$stem] ?? $manifest[$stem] ?? \dirname($file).'/'.$stem;

        return is_file($this->publicDir.$url) ? $url : null;
    }

    private function media(string $file): ?string
    {
        foreach ($this->breakpoints as $name => $media) {
            if (str_contains($file, '-'.$name)) {
                return $media;
            }
        }

        return null;
    }

    /** @return array{entrypoints: array<string, array{js?: list<string>, css?: list<string>}>, manifest: array<string, string>} */
    private function build(int $index): array
    {
        return $this->read[$index] ??= [
            'entrypoints' => json_decode((string) @file_get_contents($this->builds[$index]['entrypoints']), true)['entrypoints'] ?? [],
            'manifest' => json_decode((string) @file_get_contents($this->builds[$index]['manifest']), true) ?? [],
        ];
    }
}
