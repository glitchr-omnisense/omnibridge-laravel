<?php

namespace Omnibridge\Laravel\Page;

use Illuminate\Http\Request;

/**
 * The app variable of omnibase's templates, in Laravel: the request (its
 * language, its address, its headers and attributes, as Symfony's Request
 * names them), the visitor (the account signed in, the application's own
 * object, as Symfony's app.user; none by default), the session, the site,
 * the theme, and the referrer (omnibase's app.referrer, an object printed as
 * the page the visitor came from on this site, or as nothing: Page\Referrer).
 */
final class AppVariable
{
    public function __construct(
        public readonly RequestView $request,
        public readonly Site $site,
        public readonly Theme $theme,
        public readonly mixed $user = null,
        public readonly mixed $session = null,
        public readonly ?Referrer $referrer = null,
    ) {
    }

    public static function of(Request $request, string $locale, Site $site, Theme $theme, ?object $user = null): self
    {
        return new self(new RequestView($request, $locale), $site, $theme, $user, new SessionView(), new Referrer(self::referrer($request)));
    }

    /** The page the visitor came from, when it is this site's and not the page itself. */
    public static function referrer(Request $request): ?string
    {
        $referrer = (string) $request->headers->get('referer');
        if ('' === $referrer) {
            return null;
        }
        $host = parse_url($referrer, \PHP_URL_HOST);
        if (!\is_string($host) || 0 !== strcasecmp($host, $request->getHost())) {
            return null;
        }
        $path = parse_url($referrer, \PHP_URL_PATH);

        return \is_string($path) && $path !== $request->getPathInfo() ? $referrer : null;
    }
}
