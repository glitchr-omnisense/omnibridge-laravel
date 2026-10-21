<?php

namespace Omnibridge\Laravel\Auth;

use Omniauth\Factor;
use Omniauth\Failure;

/**
 * A refused sign-in, as the page says it: the message's key and data (the
 * template's `error.messageKey|trans(error.messageData, 'security')`, what
 * Security's AuthenticationException gave omnibase's login page), omniauth's
 * failure and reason. Kept in the session for the page that follows, as an
 * array.
 */
final readonly class Refusal
{
    /** The messages a page translates, by omniauth's failure or "failure:reason" ("%reason%" replaced): omnibridge/symfony's. */
    public const MESSAGES = [
        'bad-credentials' => 'Invalid credentials.',
        'bad-code' => 'code_invalid',
        'invalid-link' => 'Invalid credentials.',
        'expired' => 'Authentication request could not be processed due to a system problem.',
        'throttled' => 'Too many failed login attempts, please try again in %minutes% minute.',
        'account-refused' => 'Account is disabled.',
        'refused' => 'Invalid credentials.',
        'no-attempt' => 'Authentication request could not be processed due to a system problem.',
        'not-offered' => 'code_invalid',
        'not-enrolled' => 'Invalid credentials.',
        'csrf' => 'Invalid CSRF token.',
    ];

    /** @param array<string, mixed> $messageData */
    public function __construct(
        public string $messageKey,
        public array $messageData = [],
        public ?Factor $factor = null,
        public ?Failure $failure = null,
        public ?string $reason = null,
    ) {
    }

    /**
     * @param array<string, string> $messages the application's own, over MESSAGES
     * @param 'csrf'|null           $other    a refusal that is no failure of omniauth's
     */
    public static function of(Factor $factor, ?Failure $failure, ?string $reason, array $messages, ?string $other = null): self
    {
        $key = $other ?? ($failure ?? Failure::BadCredentials)->value;
        $messages += self::MESSAGES;
        $message = (null !== $reason ? $messages[$key.':'.$reason] ?? null : null) ?? $messages[$key] ?? self::MESSAGES['bad-credentials'];
        $data = Failure::Throttled === $failure ? ['%minutes%' => 1] : [];

        return new self(str_replace('%reason%', (string) $reason, $message), $data, $factor, $failure, $reason);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['key' => $this->messageKey, 'data' => $this->messageData, 'factor' => $this->factor?->value, 'failure' => $this->failure?->value, 'reason' => $this->reason];
    }

    public static function fromArray(mixed $data): ?self
    {
        if (!\is_array($data) || !\is_string($data['key'] ?? null)) {
            return null;
        }

        return new self(
            $data['key'],
            \is_array($data['data'] ?? null) ? Settings::stringKeys($data['data']) : [],
            \is_string($data['factor'] ?? null) ? Factor::tryFrom($data['factor']) : null,
            \is_string($data['failure'] ?? null) ? Failure::tryFrom($data['failure']) : null,
            \is_string($data['reason'] ?? null) ? $data['reason'] : null,
        );
    }
}
