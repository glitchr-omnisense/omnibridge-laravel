<?php

namespace Omnibridge\Laravel\Auth\Listener;

use Illuminate\Contracts\Session\Session;
use Omniauth\Event\SignedIn;
use Omnibridge\Contract\Locales;
use Omnibridge\Contract\Translations;
use Omnibridge\Laravel\Page\SessionView;

/**
 * The word of welcome a sign-in leaves for the next page (omnibase's
 * SecuritySubscriber::onLoginSuccess() under Symfony, a "success" flash):
 * for a verified account, "@notifications.login.success.<period>.<active>" -
 * the period of the account's last activity (morning 05-10 h, afternoon
 * 12-15 h, evening 19-05 h, day otherwise), "first" when that activity is
 * not today's, "back" when it is ("Re-bonjour Marki !") -; for an account
 * not yet saved, "@notifications.login.success.social". The account itself
 * is the message's {0}, as omnibase's Notification gives it. Flashed in the
 * session for omnibase's @Base/flashbag.html.twig (app.session.flashbag).
 */
final class WelcomeFlash
{
    public function __construct(private readonly Session $session, private readonly Translations $translations, private readonly ?Locales $locales = null)
    {
    }

    public function onSignedIn(SignedIn $event): void
    {
        $user = $event->account;
        if (method_exists($user, 'isPersistent') && !$user->isPersistent()) {
            $title = '@notifications.login.social';
        } elseif (method_exists($user, 'isVerified') && $user->isVerified()) {
            $activeAt = method_exists($user, 'getActiveAt') ? $user->getActiveAt() : null;
            $activeAt = $activeAt instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($activeAt) : null;
            $active = null === $activeAt || $activeAt->format('Y-m-d') !== (new \DateTimeImmutable('today'))->format('Y-m-d') ? 'first' : 'back';
            $title = '@notifications.login.success.'.self::period($activeAt).'.'.$active;
        } else {
            return;
        }
        $name = $user instanceof \Stringable || method_exists($user, '__toString') ? (string) $user : '';
        // In the visitor's language (the request's, as Localize set it), not the catalogues' default.
        SessionView::add($this->session, 'success', $this->translations->trans($title, [$name], null, $this->locales?->getLocale()));
    }

    /** omnibase's periods of the day (SecuritySubscriber: time_is_between() on the account's last activity). */
    private static function period(?\DateTimeImmutable $at): string
    {
        if (null === $at) {
            return 'day';
        }
        $time = $at->format('H:i:s');

        return match (true) {
            $time >= '05:00:00' && $time < '10:00:00' => 'morning',
            $time >= '12:00:00' && $time < '15:00:00' => 'afternoon',
            $time >= '19:00:00' || $time < '05:00:00' => 'evening',
            default => 'day',
        };
    }
}
