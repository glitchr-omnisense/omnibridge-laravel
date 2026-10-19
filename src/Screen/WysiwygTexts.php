<?php

namespace Omnibridge\Laravel\Screen;

use Base\Contract\Texts;
use Base\Service\Model\Wysiwyg\EditorJsRenderer;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Twig\Environment;

/**
 * omnibase's Texts contract under Laravel: what its |wysiwyg and |wysiwyg_toc
 * filters answer for an Editor.js document, the same passes in the same order
 * with the same results (omnibase's HtmlTwigExtension, EditorEnhancer and its
 * Heading, Link, Mention, Semantic and Media enhancers), then omnibase's own
 * EditorJsRenderer and @Base/form/wysiwyg/editor_js.html.twig; Quill's HTML
 * (the older texts) takes omnibase's WysiwygEnhancer's way: the links' pass,
 * the headings' anchors, quill.html.twig. The Twig environment must hold
 * omnibase's views under @Base (omnibridge.twig.paths), or be given (on()).
 *
 * Every leaf of the document goes through the enhancers as a string, as PHP
 * hands it to theirs (true is "1", false "", 7 "7"): the page's data-edjs
 * says so too. What needs omnibase's services is left as written: a mention
 * (its identifier is omnibase's obfuscated one), the layout's semantics, a
 * picture's address through omnibase's image route - unless the application
 * says where it serves a picture (images()): Laravel has no image route.
 */
final class WysiwygTexts implements Texts
{
    private AsciiSlugger $slugger;

    /**
     * @param Environment|\Closure(): Environment          $twig
     * @param string|null                                   $host   the host whose links are made relative (the request's when null)
     * @param (\Closure(string $address): ?string)|null    $images a picture's address as the application serves it, null to leave it
     */
    public function __construct(
        private readonly Environment|\Closure $twig,
        private readonly ?string $host = null,
        private readonly ?\Closure $images = null,
    ) {
        $this->slugger = new AsciiSlugger();
    }

    /** The same texts, a picture's stored address (a block's file origin, else url) given to $images. */
    public function images(\Closure $images): self
    {
        return new self($this->twig, $this->host, $images);
    }

    /** The same texts rendered through another environment (a page's own, with its extensions). */
    public function on(Environment $twig): self
    {
        return new self($twig, $this->host, $this->images);
    }

    public function isEditor(?string $text): bool
    {
        return null !== $text && '' !== $text && self::supports($text);
    }

    public static function supports(?string $content): bool
    {
        if (null === $content || !(str_contains($content, '{') && str_contains($content, '}'))) {
            return false;
        }
        json_decode($content);

        return \JSON_ERROR_NONE === json_last_error();
    }

    /** @param array<string, mixed> $options headings, semantics, media, row_attr, hydrate */
    public function render(?string $content, array $options = []): string
    {
        if (null === $content) {
            return '';
        }
        if (!self::supports($content)) {
            // Quill's HTML, as the older articles keep it (omnibase's WysiwygEnhancer): the links' pass, the headings' anchors, quill.html.twig.
            $html = $this->link($content);
            if ($options['headings'] ?? false) {
                $html = $this->anchors($html, true === $options['headings'] ? null : (int) $options['headings']);
            }

            return $this->twig()->render('@Base/form/wysiwyg/quill.html.twig', ['html' => $html, 'options' => ['attr' => $options['row_attr'] ?? []]]);
        }

        $json = $this->strings($content);        // the mentions' pass (left as written) - every leaf a string
        $json = $this->links($json);             // the links' pass
        $json = json_decode($json);              // the semantics' pass (none on this site) and the media's
        foreach ($json->blocks ?? [] as $block) {
            if ('image' === ($block->type ?? null) && isset($block->data->file)) {
                $source = $block->data->file->origin ?? $block->data->file->url ?? null;
                $upload = null !== $this->images && \is_string($source) && '' !== $source ? ($this->images)($source) : null;
                if (null !== $upload) {
                    $block->data->file->url = $upload;
                }
            }
        }
        if ($options['headings'] ?? false) {
            $json = $this->headings($json, true === $options['headings'] ? null : (int) $options['headings']);
        }

        return $this->twig()->render('@Base/form/wysiwyg/editor_js.html.twig', [
            'json' => $json,
            'html' => EditorJsRenderer::render($json),
            'hydrate' => $options['hydrate'] ?? true,
            'options' => ['attr' => $options['row_attr'] ?? []],
        ]);
    }

    /** @return list<array{tag: string, slug: string, title: string}> */
    public function toc(?string $content, ?int $maxLevel = null): array
    {
        if (null === $content || '' === $content) {
            return [];
        }
        if (!self::supports($content)) {
            return $this->headlines($content, $maxLevel);
        }
        $headlines = [];
        foreach (json_decode($content)->blocks ?? [] as $block) {
            if ('header' !== ($block->type ?? null)) {
                continue;
            }
            $level = $block->data->level;
            $headlines = array_merge($headlines, $this->headlines('<h'.$level.'>'.strip_tags((string) $block->data->text).'</h'.$level.'>', $maxLevel));
        }

        return $headlines;
    }

    /** omnibase's HeadingEnhancer::toc(): the headings of an HTML text, level by level. */
    private function headlines(string $html, ?int $maxLevel): array
    {
        $dom = $this->dom($html);
        $headlines = [];
        for ($i = 1; $i <= max(1, $maxLevel ?? 6); ++$i) {
            foreach ($dom->getElementsByTagName('h'.$i) as $tag) {
                $text = str_replace('&nbsp;', ' ', $tag->nodeValue);
                $headlines[] = ['tag' => 'h'.$i, 'slug' => strtolower((string) $this->slugger->slug($text)), 'title' => $text];
            }
        }

        return $headlines;
    }

    /** Every leaf as a string, as an enhancer typed string|array|null receives it. */
    private function strings(string $content): string
    {
        $json = json_decode($content, true);
        array_walk_recursive($json, static function (&$leaf) {
            if (\is_bool($leaf)) {
                $leaf = $leaf ? '1' : '';
            } elseif (\is_int($leaf) || \is_float($leaf)) {
                $leaf = (string) $leaf;
            }
        });

        return json_encode($json);
    }

    /** omnibase's LinkEnhancer on every leaf of a document. */
    private function links(string $content): string
    {
        $json = json_decode($content, true);
        array_walk_recursive($json, function (&$leaf) {
            if ($leaf && \is_string($leaf)) {
                $leaf = $this->link($leaf);
            }
        });

        return json_encode($json);
    }

    /** omnibase's LinkEnhancer: a link to this very host made relative; the text re-written by the DOM wherever there is a link. */
    private function link(string $html): string
    {
        if (!str_contains($html, '<a')) {
            return $html;
        }
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="UTF-8" ?>'.$this->utf8($html), \LIBXML_NOERROR);
        $tags = $dom->getElementsByTagName('a');
        if (\count($tags) < 1) {
            return $html;
        }
        foreach (iterator_to_array($tags) as $tag) {
            $href = $tag->getAttribute('href');
            $parts = $href && str_contains($href, '://') ? parse_url($href) : false;
            if (!$parts || ($parts['host'] ?? null) !== $this->host()) {
                continue;
            }
            $tag->setAttribute('href', ($parts['path'] ?? '/').(!empty($parts['query']) ? '?'.$parts['query'] : '').(!empty($parts['fragment']) ? '#'.$parts['fragment'] : ''));
        }

        return $this->body($dom);
    }

    /** omnibase's HeadingEnhancer on each header block: its text its anchor (<a class="markdown-anchor" id="1-preparer..." href="#1-preparer...">). */
    private function headings(object $json, ?int $maxLevel): object
    {
        foreach ($json->blocks ?? [] as $block) {
            if ('header' !== ($block->type ?? null)) {
                continue;
            }
            $level = $block->data->level;
            $html = $this->anchors('<h'.$level.'>'.strip_tags((string) $block->data->text).'</h'.$level.'>', $maxLevel);
            $open = '<h'.$level.'>';
            $close = '</h'.$level.'>';
            while (str_starts_with($html, $open)) {
                $html = substr($html, \strlen($open));
            }
            while (str_ends_with($html, $close)) {
                $html = substr($html, 0, -\strlen($close));
            }
            $block->data->text = $html;
        }

        return $json;
    }

    /** omnibase's HeadingEnhancer::enhance(): every heading of an HTML text its anchor. */
    private function anchors(string $html, ?int $maxLevel): string
    {
        $dom = $this->dom($html);
        for ($i = 1; $i <= max(1, $maxLevel ?? 6); ++$i) {
            foreach ($dom->getElementsByTagName('h'.$i) as $tag) {
                $text = str_replace('&nbsp;', ' ', $tag->nodeValue);
                $text = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F\xA0]/u', '', strip_tags($text)));
                $tag->nodeValue = null;
                $id = strtolower((string) $this->slugger->slug($text));
                $anchor = $dom->createDocumentFragment();
                $anchor->appendXML('<a class="markdown-anchor" id=\''.$id.'\' href=\'#'.$id.'\'>'.trim($text).'</a>');
                $tag->appendChild($anchor);
            }
        }

        return $this->body($dom);
    }

    private function twig(): Environment
    {
        return $this->twig instanceof Environment ? $this->twig : ($this->twig)();
    }

    private function host(): ?string
    {
        return $this->host ?? (app()->bound('request') ? app('request')->getHost() : null);
    }

    /** omnibase's mb_utf8(): UTF-8, else Windows-1252, else ISO-8859-1, strictly; no byte order mark. */
    private function utf8(string $html): string
    {
        $encoding = mb_detect_encoding($html, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true) ?: 'ISO-8859-1';

        return preg_replace('/^\xEF\xBB\xBF/', '', mb_convert_encoding($html, 'UTF-8', $encoding));
    }

    private function dom(string $html): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$this->utf8($html), \LIBXML_NOERROR | \LIBXML_NOWARNING | \LIBXML_NONET);

        return $dom;
    }

    private function body(\DOMDocument $dom): string
    {
        $body = $dom->getElementsByTagName('body')->item(0);

        return null === $body ? '' : trim(implode(array_map([$dom, 'saveHTML'], iterator_to_array($body->childNodes))));
    }
}
