<?php

namespace Omnibridge\Laravel\Auth\Listener;

use Illuminate\Contracts\Session\Session;
use Base\Auth\Policy\Welcome;
use Omniauth\Event\SignedIn;
use Omnibridge\Laravel\Legacy\WelcomeKey;
use Omnibridge\Contract\Locales;
use Omnibridge\Contract\Translations;
use Omnibridge\Laravel\Page\SessionView;
use Psr\Log\LoggerInterface;

/**
 * The word of welcome a sign-in leaves for the next page (omnibase's
 * SecuritySubscriber::onLoginSuccess() under Symfony, a "success" flash), its key
 * omnibase 5.0's Welcome policy (step F5e of its plan; under 4.x Legacy\WelcomeKey):
 * for a verified account, "@notifications.login.success.<period>.<active>" -
 * the period of the account's last activity (morning 05-10 h, afternoon
 * 12-15 h, evening 19-05 h, day otherwise), "first" when that activity is
 * not today's, "back" when it is ("Re-bonjour Marki !") -; for an account
 * not yet saved, "@notifications.login.success.social". The period and the
 * day are omnibase's own helpers (time_is_between(), daydiff(): the same
 * answers as the Symfony site's, whatever they are); the account itself is
 * the message's {0}, as omnibase's Notification gives it. Translated in the
 * visitor's language (the Locales contract's), else the catalogues' default;
 * a key the catalogues do not have - asked, or pointed to by an entry that is
 * itself a key - is logged and flashed as nothing: a page never prints a key.
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
        $key = class_exists(Welcome::class) ? Welcome::key($user) : WelcomeKey::key($user);
        if (null === $key) {
            return;
        }
        $title = '@notifications.'.$key;
        $name = $user instanceof \Stringable || method_exists($user, '__toString') ? (string) $user : '';
        $locale = $this->locales?->getLocale();
        $message = $this->translations->trans($title, [$name], null, $locale);
        if (self::isKey($message) && null !== $locale) {
            $message = $this->translations->trans($title, [$name]);
        }
        if (self::isKey($message)) {
            $this->logger?->warning('The word of welcome has no translation: {key} in {locale}.', ['key' => $title, 'locale' => $locale ?? 'the default']);

            return;
        }
        SessionView::add($this->session, 'success', $message);
    }

    /** A key given back for a message: the one asked, or one it pointed to ("@notifications...": a catalogue entry that is itself a key). */
    private static function isKey(string $message): bool
    {
        return str_starts_with($message, '@') && str_contains($message, '.') && !str_contains($message, ' ');
    }
}
