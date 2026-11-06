<?php

namespace Omnibridge\Laravel\Omnibase;

use Base\Request\Rule\Redirection;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A page not found, looked up in the site's redirections before the 404 is answered (omnibase's
 * Base\Request\Rule\Redirection, step F5e of its plan, slice 2; its RedirectionSubscriber under Symfony): the
 * old addresses of a site taken over lead to its new pages. With omnisense/omnibase 5.0 and its redirections'
 * repository bound (Base\Repository\Layout\Redirections), asked when the 404 is rendered.
 */
final class Redirections
{
    public const REPOSITORY = 'Base\Repository\Layout\Redirections';

    public static function boot(Application $app): void
    {
        if (!class_exists(Redirection::class)) {
            return;
        }
        $handler = $app->make(ExceptionHandler::class);
        if (!method_exists($handler, 'renderable')) {
            return;
        }
        $handler->renderable(static function (NotFoundHttpException $exception, Request $request) use ($app) {
            if (!$app->bound(self::REPOSITORY)) {
                return null;
            }
            $decision = (new Redirection($app->make(self::REPOSITORY), $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null))
                ->decide($request->isMethodSafe(), $request->getPathInfo(), $request->getQueryString(), $request->getBaseUrl());

            return null === $decision->url ? null : redirect($decision->url, $decision->status);
        });
    }
}
