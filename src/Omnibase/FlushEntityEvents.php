<?php

namespace Omnibridge\Laravel\Omnibase;

use Base\EntityDispatcher\AbstractEventDispatcher;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * What omnibase's entity events left to flush (a listener of user.register persisting the
 * verification's token), flushed once the page is answered, before it is sent: omnibase's
 * EntityDispatcherFlushSubscriber on Symfony's kernel.response. Global (OmnibaseServices::boot()).
 */
final class FlushEntityEvents
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        foreach (AbstractEventDispatcher::live() as $dispatcher) {
            $dispatcher->flushPending();
        }

        return $response;
    }
}
