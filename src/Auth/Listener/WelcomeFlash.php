<?php

namespace Omnibridge\Laravel\Auth\Listener;

use Illuminate\Contracts\Session\Session;
use Omniauth\Event\SignedIn;
use Omnibridge\Contract\Locales;
use Omnibridge\Contract\Translations;
use Omnibridge\Laravel\Page\SessionView;
use Psr\Log\LoggerInterface;

/**
 * The word of welcome a sign-in leaves for the next page (omnibase's
 * SecuritySubscriber::onLoginSuccess() under Symfony, a "success" flash):
 * for a verified account, "@notifications.login.success.<period>.<active>" -
 * the period of the account's last activity (morning 05-10 h, afternoon
 * 12-15 h, evening 19-05 h, day otherwise), "first" when that activity is
 * not today's, "back" when it is ("Re-bonjour Marki !") -; for an account
 * not yet saved, "@notifications.login.success.social". The period and the
 * day are omnibase's own helpers (time_is_between(), daydiff(): the same
 * answers as the Symfony site's, whatever they are); the account itself is
 * the message's {0}, as omnibase's Notification gives it. Translated in the
 * visitor's language (the Locales contract's), else the catalogues' default;
 * a key the catalogues do not have is logged and flashed as nothing - a page
 * never prints a key.
 */
final class WelcomeFlash
{
    public function __construct(
        private readonly Session $session,
        private readonly Translations $translations,
        private readonly ?Locales $locales = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function onSignedIn(SignedIn $event): void
    {
        $user = $event->account;
        if (method_exists($user, 'isPersistent') && !$user->isPersistent()) {
            $title = '@notifications.login.social';
        } elseif (method_exists($user, 'isVerified') && $user->isVerified()) {
            $activeAt = method_exists($user, 'getActiveAt') ? $user->getActiveAt() : null;
            $activeAt = $activeAt instanceof \DateTimeInterface ? \DateTime::createFromInterface($activeAt) : null;
            $title = '@notifications.login.success.'.self::period($activeAt).'.'.self::active($activeAt);
        } else {
            return;
        }
        $name = $user instanceof \Stringable || method_exists($user, '__toString') ? (string) $user : '';
        $locale = $this->locales?->getLocale();
        $message = $this->translations->trans($title, [$name], null, $locale);
        if ($message === $title && null !== $locale) {
            $message = $this->translations->trans($title, [$name]);
        }
        if ($message === $title) {
            $this->logger?->warning('The word of welcome has no translation: {key} in {locale}.', ['key' => $title, 'locale' => $locale ?? 'the default']);

            return;
        }
        SessionView::add($this->session, 'success', $message);
    }

    /** "first" when the last activity is not today's (omnibase's daydiff(): the day of the activity against today), "back" when it is. */
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

    /** omnibase's periods of the day: its time_is_between() on the account's last activity, where its helpers are loaded. */
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
