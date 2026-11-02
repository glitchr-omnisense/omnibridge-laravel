<?php

namespace Omnibridge\Laravel\Contract;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Omnibridge\Contract\Requests;
use Omnibridge\Laravel\Page\SessionView;

/**
 * The request being answered under Laravel: the container's request (none in
 * a console command), its session, and the flashes omnibase's templates read
 * (SessionView: app.session.flashbag). Asked at each call, never kept: a
 * kept worker or a test's client answers several requests.
 */
final class LaravelRequests implements Requests
{
    public function __construct(private readonly Container $app)
    {
    }

    public function has(): bool
    {
        return null !== $this->current();
    }

    public function ip(): ?string
    {
        return $this->current()?->ip();
    }

    public function header(string $name): ?string
    {
        return $this->current()?->headers->get($name);
    }

    public function uri(): ?string
    {
        return $this->current()?->getUri();
    }

    public function host(): ?string
    {
        return $this->current()?->getSchemeAndHttpHost();
    }

    public function route(): ?string
    {
        return $this->current()?->route()?->getName();
    }

    public function attribute(string $name, mixed $default = null): mixed
    {
        $request = $this->current();
        if (null === $request) {
            return $default;
        }

        return $request->attributes->has($name) ? $request->attributes->get($name) : ($request->route()?->parameter($name) ?? $default);
    }

    public function session(string $key, mixed $default = null): mixed
    {
        $session = $this->sessionStore();

        return null !== $session ? $session->get($key, $default) : $default;
    }

    public function remember(string $key, mixed $value): void
    {
        $session = $this->sessionStore();
        if (null === $session) {
            return;
        }
        null === $value ? $session->forget($key) : $session->put($key, $value);
    }

    public function forget(string $key): void
    {
        $this->sessionStore()?->forget($key);
    }

    public function flash(string $type, string $message): void
    {
        $session = $this->sessionStore();
        if (null !== $session) {
            SessionView::add($session, $type, $message);
        }
    }

    public function flashes(): array
    {
        $session = $this->sessionStore();

        return null !== $session ? (new SessionView($session))->flashbag->all() : [];
    }

    public function request(): ?object
    {
        return $this->current();
    }

    private function current(): ?Request
    {
        if ($this->app instanceof \Illuminate\Contracts\Foundation\Application && $this->app->runningInConsole() && !$this->app->bound('request')) {
            return null;
        }
        $request = $this->app->bound('request') ? $this->app->make('request') : null;

        return $request instanceof Request ? $request : null;
    }

    private function sessionStore(): ?\Illuminate\Contracts\Session\Session
    {
        $request = $this->current();

        return null !== $request && $request->hasSession() ? $request->session() : null;
    }
}
