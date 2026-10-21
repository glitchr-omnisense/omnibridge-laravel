<?php

namespace Omnibridge\Laravel\Auth;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Omniauth\Context;

/**
 * omniauth's Context of a Laravel request: its origin and host (WebAuthn's),
 * the visitor's address and browser, the trusted_device cookie (read as the
 * Symfony site wrote it: left out of Laravel's encrypted cookies), and the
 * firewall - the "fwl" claim of the trusted devices' tokens, "main" unless
 * config/omniauth.php's trusted_device.firewall says otherwise: the name
 * scheb/2fa gave the Symfony firewall that asked the second factor, so the
 * devices it trusted are trusted here.
 */
final class Contexts
{
    public function __construct(
        private readonly Container $app,
        private readonly string $cookie = 'trusted_device',
        private readonly string $firewall = 'main',
    ) {
    }

    public function of(?Request $request = null): Context
    {
        $request ??= $this->app->make('request');
        if (!$request instanceof Request) {
            throw new \LogicException('No request to sign in from.');
        }
        $cookie = $request->cookies->get($this->cookie);

        return new Context(
            origin: $request->getSchemeAndHttpHost(),
            host: $request->getHost(),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            trustedDevice: \is_string($cookie) && '' !== $cookie ? $cookie : null,
            firewall: $this->firewall,
        );
    }
}
