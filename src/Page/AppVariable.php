<?php

namespace Omnibridge\Laravel\Page;

use Illuminate\Http\Request;

/**
 * The app variable of omnibase's templates, in Laravel: the request (its
 * language, its address, its headers and attributes, as Symfony's Request
 * names them), the visitor (none by default), the session, the site and the
 * theme.
 */
final class AppVariable
{
    public function __construct(
        public readonly RequestView $request,
        public readonly Site $site,
        public readonly Theme $theme,
        public readonly mixed $user = null,
        public readonly mixed $session = null,
    ) {
    }

    public static function of(Request $request, string $locale, Site $site, Theme $theme): self
    {
        return new self(new RequestView($request, $locale), $site, $theme, null, new SessionView());
    }
}
