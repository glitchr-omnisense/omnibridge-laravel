<?php

namespace Omnibridge\Laravel\Page;

use Illuminate\Http\Request;

/**
 * app.request with the words of Symfony's Request: getLocale(), uri,
 * headers.get(), attributes.get('_route').
 */
final class RequestView
{
    public readonly Bag $headers;

    public readonly Bag $attributes;

    public function __construct(private readonly Request $request, private readonly string $locale)
    {
        $this->headers = new Bag(static fn (string $key) => $request->headers->get($key));
        $this->attributes = new Bag(static fn (string $key) => '_route' === $key ? $request->route()?->getName() : $request->attributes->get($key));
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getUri(): string
    {
        return $this->request->fullUrl();
    }

    public function getPathInfo(): string
    {
        return $this->request->getPathInfo();
    }
}
