<?php

namespace Omnibridge\Laravel\Contract;

use Illuminate\Support\Facades\Date;
use Psr\Clock\ClockInterface;

/** PSR-20's clock on Laravel's dates: the time a test sets (Date::setTestNow(), $this->travel()) is this clock's too. */
final class DateClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface(Date::now());
    }
}
