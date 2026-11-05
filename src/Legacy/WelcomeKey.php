<?php

namespace Omnibridge\Laravel\Legacy;

/**
 * The word of welcome's key under omnisense/omnibase 4.x, which has no Welcome policy (Auth\Listener\WelcomeFlash
 * asks Base\Auth\Policy\Welcome under 5.0). Removed once 4.x is no longer served.
 */
final class WelcomeKey
{
    public static function key(object $user): ?string
    {
        if (method_exists($user, 'isPersistent') && !$user->isPersistent()) {
            return 'login.social';
        }
        if (!method_exists($user, 'isVerified') || !$user->isVerified()) {
            return null;
        }
        $activeAt = method_exists($user, 'getActiveAt') ? $user->getActiveAt() : null;
        $activeAt = $activeAt instanceof \DateTimeInterface ? \DateTime::createFromInterface($activeAt) : null;

        return 'login.success.'.self::period($activeAt).'.'.self::active($activeAt);
    }

    /** "first" when the last activity is not today's (omnibase's daydiff()), "back" when it is. */
    private static function active(?\DateTime $activeAt): string
    {
        if (null === $activeAt) {
            return 'first';
        }
        if (\function_exists('daydiff')) {
            return daydiff(clone $activeAt) < 0 ? 'first' : 'back';
        }

        return $activeAt->format('Y-m-d') < (new \DateTime('today'))->format('Y-m-d') ? 'first' : 'back';
    }

    /** omnibase's periods of the day: its time_is_between() on the account's last activity. */
    private static function period(?\DateTime $activeAt): string
    {
        if (null === $activeAt) {
            return 'day';
        }
        if (\function_exists('time_is_between')) {
            return match (true) {
                (bool) time_is_between(clone $activeAt, '05:00:00', '10:00:00') => 'morning',
                (bool) time_is_between(clone $activeAt, '12:00:00', '15:00:00') => 'afternoon',
                (bool) time_is_between(clone $activeAt, '19:00:00', '05:00:00') => 'evening',
                default => 'day',
            };
        }
        $time = $activeAt->format('H:i:s');

        return match (true) {
            $time >= '05:00:00' && $time < '10:00:00' => 'morning',
            $time >= '12:00:00' && $time < '15:00:00' => 'afternoon',
            $time >= '19:00:00' || $time < '05:00:00' => 'evening',
            default => 'day',
        };
    }
}
