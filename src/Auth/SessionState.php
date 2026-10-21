<?php

namespace Omnibridge\Laravel\Auth;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Session\Session;
use Omniauth\Contract\State;

/**
 * omniauth's State under Laravel: the visitor's session (the request's
 * store, started by the web group's StartSession). Without one - a command -
 * nothing is kept.
 */
final class SessionState implements State
{
    public function __construct(private readonly Container $app)
    {
    }

    public function get(string $key): mixed
    {
        return $this->session()?->get($key);
    }

    public function set(string $key, mixed $value): void
    {
        $this->session()?->put($key, $value);
    }

    public function remove(string $key): void
    {
        $this->session()?->forget($key);
    }

    private function session(): ?Session
    {
        if (!$this->app->bound('session.store')) {
            return null;
        }
        $session = $this->app->make('session.store');

        return $session instanceof Session ? $session : null;
    }
}
