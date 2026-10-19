<?php

namespace Omnibridge\Laravel\View;

/**
 * What omnibase adds to a page once rendered for its scripts to know the
 * screen's size: a style before the first stylesheet whose body:before says
 * the breakpoint's name ("mobile", "tablet"...) in each media query.
 */
final class Breakpoints
{
    /** @param array<string, string> $breakpoints a name (mobile) and its media query */
    public static function style(array $breakpoints): string
    {
        $style = "body:before {\n    display: none;\n    visibility: hidden;\n}";
        foreach ($breakpoints as $name => $media) {
            $style .= '@media '.$media." {\n    body:before {\n        content: \"".$name."\";\n    }\n}";
        }

        return $style;
    }

    /**
     * The page with the style before its first stylesheet in its head; a
     * page without a head, or without a stylesheet there, as it is.
     *
     * @param array<string, string> $breakpoints
     */
    public static function inject(string $html, array $breakpoints): string
    {
        $head = strpos($html, '</head>');
        if (false === $head) {
            return $html;
        }
        $link = strpos($html, '<link');
        if (false !== $link && $link < $head) {
            $html = substr_replace($html, '<style>'.self::style($breakpoints).'</style>'."\n", $link, 0);
        }

        return $html;
    }
}
