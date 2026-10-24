<?php

namespace Omnibridge\Laravel\View;

use Twig\Environment;

/**
 * What omnibase adds to a page for its template's companions, as its
 * HtmlTagRenderer does under Symfony (step R27 of the audit "lesringostars
 * face au master plan"): beside client/timeline.html.twig, a
 * client/timeline.css.twig is the page's own style (a <style> before the
 * end of its head), a client/timeline.mobile.css.twig its style for a
 * breakpoint (<style media='...'>), a client/timeline.js.twig its script
 * (a <script> after its body) - each rendered in the page's context.
 */
final class Companions
{
    /**
     * @param array<string, mixed>  $context
     * @param array<string, string> $breakpoints the breakpoints' media by name (mobile => all and (max-width: 470px))
     */
    public static function inject(Environment $twig, string $template, array $context, string $html, array $breakpoints = []): string
    {
        if (!str_ends_with($template, '.html.twig')) {
            return $html;
        }
        $base = substr($template, 0, -\strlen('.html.twig'));

        $styles = '';
        if ('' !== ($css = self::render($twig, $base.'.css.twig', $context))) {
            $styles .= '<style>'.$css.'</style>';
        }
        foreach ($breakpoints as $format => $media) {
            if ('' !== ($css = self::render($twig, $base.'.'.$format.'.css.twig', $context))) {
                $styles .= "<style media='".$media."'>".$css.'</style>';
            }
        }
        $script = self::render($twig, $base.'.js.twig', $context);

        if ('' !== $styles && false !== ($head = stripos($html, '</head'))) {
            $html = substr_replace($html, $styles, $head, 0);
        }
        if ('' !== $script && preg_match('~</body\b[^>]*>~i', $html, $body, \PREG_OFFSET_CAPTURE)) {
            $html = substr_replace($html, '<script>'.$script.'</script>', $body[0][1] + \strlen($body[0][0]), 0);
        }

        return $html;
    }

    /** @param array<string, mixed> $context */
    private static function render(Environment $twig, string $template, array $context): string
    {
        return $twig->getLoader()->exists($template) ? $twig->render($template, $context) : '';
    }
}
