<?php

namespace Omnibridge\Laravel\Tests;

use Base\Contract\Runtime;
use Base\Entity\User;
use Illuminate\Http\Request;
use Omnibridge\Contract as Host;
use Psr\Clock\ClockInterface;

/**
 * The visitor's time zone (Locales::getTimezone()): the `timezone` cookies omnibase's script writes, the application's
 * zone without them; a date without a time is compared with the visitor's day (a birthdate's age here, as the music
 * releases, the agenda's today and the classroom widget do), instants staying UTC.
 */
final class VisitorTimezoneTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('app.timezone', 'UTC');
    }

    protected function tearDown(): void
    {
        Runtime::set(ClockInterface::class, null);
        parent::tearDown();
    }

    public function testAVisitorInTokyoHasTokyosDayWhileTheApplicationIsInUtc(): void
    {
        // 16:00 UTC on the 1st of March: already the 2nd in Tokyo.
        Runtime::set(ClockInterface::class, new class implements ClockInterface {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-03-01 16:00:00', new \DateTimeZone('UTC'));
            }
        });
        $user = (new User())->setBirthdate(new \DateTimeImmutable('2000-03-02'));

        $this->app->instance('request', Request::create('/', 'GET', [], ['LOCATION/TIMEZONE' => 'Asia/Tokyo']));
        self::assertSame('Asia/Tokyo', Runtime::get(Host\Locales::class)->getTimezone());
        self::assertSame(26, $user->getAge(), 'the 2nd in Tokyo: the birthday');

        $this->app->instance('request', Request::create('/', 'GET', [], ['USER/INFO' => json_encode(['timezone' => 'Asia/Tokyo'])]));
        self::assertSame('Asia/Tokyo', Runtime::get(Host\Locales::class)->getTimezone(), 'the visitor\'s information cookie');

        $this->app->instance('request', Request::create('/'));
        self::assertSame('UTC', Runtime::get(Host\Locales::class)->getTimezone(), 'the application\'s zone without a cookie');
        self::assertSame(25, $user->getAge(), 'still the 1st in UTC');

        $this->app->instance('request', Request::create('/', 'GET', [], ['LOCATION/TIMEZONE' => 'Mars/Olympus']));
        self::assertSame('UTC', Runtime::get(Host\Locales::class)->getTimezone(), 'an unknown zone is UTC, as on Symfony');
    }
}
