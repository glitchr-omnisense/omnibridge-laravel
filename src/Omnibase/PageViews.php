<?php

namespace Omnibridge\Laravel\Omnibase;

use Base\Service\Analytics;
use Base\Service\SpeculativeRequest;
use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Omnibridge\Contract\Access;
use Symfony\Component\HttpFoundation\Response;

/**
 * The pages sent, counted in omnibase's analytics (Base\Service\Analytics::track(): analytics_page_view, and for a
 * visitor with the analytics cookie or an account, analytics_visit and analytics_page_visit), as omnibridge/symfony's
 * PageViewSubscriber counts them on kernel.terminate: once the answer is sent, so the writes never delay the page.
 * The same pages: a GET, not a speculative one (a prefetch, a prerender: Base\Service\SpeculativeRequest), answered
 * 2xx in text/html, its path under none of the excluded prefixes (omnibridge.page_views.excluded_prefixes; the
 * back office, the framework's own addresses, the API by default, as the Symfony subscriber's). The visitor is the
 * ANALYTICS/VISITOR_ID cookie (written by the page's script with the visitor's consent, only read here: so it is
 * left out of Laravel's cookie encryption, which would drop it as undecryptable), the account
 * the one signed in (Omnibridge\Contract\Access), the source the User-Agent's.
 *
 * Global (OmnibaseServices::boot()), with omnibase's services, whichever persistence: Doctrine's repositories or
 * omnibridge/eloquent's answer Base\Repository\Analytics\*. Off with omnibridge.page_views.enabled false. A failed
 * write is reported (Laravel's exception handler), never shown: the page has already been sent.
 */
final class PageViews
{
    public const EXCLUDED_PREFIXES = ['/admin', '/_', '/api'];

    /** The visitor's cookie, written by the page's script (@glitchr/flowjs/cookie): never encrypted by Laravel, which could not read it back. */
    public const VISITOR_COOKIE = 'ANALYTICS/VISITOR_ID';

    public function __construct(private readonly Container $app)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        \assert($response instanceof Response);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        if (!self::counts($request, $response, $this->excludedPrefixes()) || !$this->app->bound(Analytics::class)) {
            return;
        }

        try {
            $user = $this->app->bound(Access::class) ? $this->app->make(Access::class)->getUser() : null;
            $identifier = null !== $user && method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : null;
            $this->app->make(Analytics::class)->track(
                $request->getPathInfo(),
                $request->cookies->get(self::VISITOR_COOKIE),
                \is_string($identifier) ? $identifier : null,
                // "" (not null) when the header is missing: track() classifies it (a bot's signal), as under Symfony.
                $request->headers->get('User-Agent') ?? '',
            );
        } catch (\Throwable $exception) {
            if (\function_exists('report')) {
                report($exception);
            }
        }
    }

    /**
     * Whether this answer is a page view: the Symfony subscriber's rule.
     *
     * @param list<string> $excludedPrefixes
     */
    public static function counts(Request $request, Response $response, array $excludedPrefixes = self::EXCLUDED_PREFIXES): bool
    {
        if (!$request->isMethod('GET') || (class_exists(SpeculativeRequest::class) && SpeculativeRequest::is($request))) {
            return false;
        }
        $status = $response->getStatusCode();
        if ($status < Response::HTTP_OK || $status >= Response::HTTP_MULTIPLE_CHOICES) {
            return false;
        }
        if (!str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return false;
        }
        $path = $request->getPathInfo();
        foreach ($excludedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function excludedPrefixes(): array
    {
        $prefixes = $this->app->make(Repository::class)->get('omnibridge.page_views.excluded_prefixes');

        return \is_array($prefixes) ? array_values(array_filter($prefixes, 'is_string')) : self::EXCLUDED_PREFIXES;
    }
}
