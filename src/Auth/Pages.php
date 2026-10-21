<?php

namespace Omnibridge\Laravel\Auth;

use Base\Screen\Negotiation;
use Base\Screen\Template;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Omnibridge\Laravel\Screen\Responder;
use Symfony\Component\HttpFoundation\Response;

/**
 * The sign-in's pages (the form, the second factor's, the sign-out's),
 * rendered as any page of the application is: through the bound
 * Base\Screen\Negotiation - the container's Twig by default, the
 * application's own rendering (its page context, its layout) when it binds
 * one. Never cached: they carry the session's tokens.
 */
final class Pages
{
    public function __construct(private readonly Container $app)
    {
    }

    /** @param array<string, mixed> $data */
    public function render(Request $request, string $template, array $data, int $status = Response::HTTP_OK): Response
    {
        $negotiation = $this->app->make(Negotiation::class);
        $response = Responder::response($negotiation->answer(new Template($template, $data, $status), Responder::visit($request)));
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
