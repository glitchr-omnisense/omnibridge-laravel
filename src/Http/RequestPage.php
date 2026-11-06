<?php

namespace Omnibridge\Laravel\Http;

use Base\Request\Page;
use Illuminate\Http\Request;
use Omnibridge\Laravel\Auth\Policies;

/**
 * The page a Laravel request opens, as omnibase's request rules and its restriction ask it (Base\Request\Page,
 * omnisense/omnibase 5.0): the route's name and family (the account's "security_*", the widgets' "ux_*", the
 * back office's and the profiler's, as Auth\Policies names them), the address, the restriction's routes.
 */
final class RequestPage implements Page
{
    public function __construct(private readonly Request $request, private readonly ?object $parameters = null)
    {
    }

    public function isSecurity(): bool
    {
        return $this->request->routeIs('security_*');
    }

    public function isProfiler(): bool
    {
        return $this->request->routeIs('_profiler*', '_wdt*');
    }

    public function isAdmin(): bool
    {
        return $this->request->routeIs(...array_diff(Policies::BACK_OFFICE, ['_profiler*', '_wdt*']));
    }

    public function isSecured(): bool
    {
        return $this->request->routeIs('security_*');
    }

    public function isUx(): bool
    {
        return $this->request->routeIs('ux_*');
    }

    public function name(): ?string
    {
        return $this->request->route()?->getName();
    }

    public function redirectsOnDeny(): array
    {
        return array_values((array) ($this->parameters?->get('base.settings.access_restriction.redirect_on_deny') ?? []));
    }

    public function uri(): string
    {
        return $this->request->getUri();
    }
}
