<?php

namespace Omnibridge\Laravel\Page;

/**
 * app.session as omnibase's templates read it: started, its flash messages
 * (none: Laravel's flashes are its own).
 */
final class SessionView
{
    public readonly bool $started;

    public readonly Bag $flashbag;

    public function __construct()
    {
        $this->started = true;
        $this->flashbag = new Bag(static fn () => null);
    }
}
