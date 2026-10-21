<?php

namespace Omnibridge\Laravel\Page;

use Illuminate\Contracts\Session\Session;

/**
 * app.session as omnibase's templates read it: started, and its flash bag -
 * app.session.flashbag.all, the messages a request left for the next one
 * (omnibase's @Base/flashbag.html.twig draws them by label: success, info,
 * warning, danger). Under Laravel the messages are flashed in the session
 * under FLASHES ([label => [message, ...]]: Flashes::add()), read by the next
 * request's page and gone with it, as Symfony's flash bag.
 */
final class SessionView
{
    /** The session key the flashed messages wait under. */
    public const FLASHES = 'omnibridge.flashes';

    public readonly bool $started;
    public readonly Bag $flashbag;

    public function __construct(?Session $session = null)
    {
        $this->started = true;
        $this->flashbag = new Bag(
            static fn (string $key) => $session?->get(self::FLASHES.'.'.$key),
            static fn (): array => self::labelled($session?->get(self::FLASHES)),
        );
    }

    /** Leaves a message for the next request's page, under its label (omnibase's Notification::send($label)). */
    public static function add(Session $session, string $label, string $message): void
    {
        $flashes = self::labelled($session->get(self::FLASHES));
        $flashes[$label][] = $message;
        $session->flash(self::FLASHES, $flashes);
    }

    /** @return array<string, list<string>> */
    private static function labelled(mixed $flashes): array
    {
        $out = [];
        foreach (\is_array($flashes) ? $flashes : [] as $label => $messages) {
            $out[(string) $label] = array_values(array_map(static fn (mixed $m): string => \is_scalar($m) || $m instanceof \Stringable ? (string) $m : '', \is_array($messages) ? $messages : [$messages]));
        }

        return $out;
    }
}
