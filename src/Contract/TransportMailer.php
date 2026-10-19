<?php

namespace Omnibridge\Laravel\Contract;

use Illuminate\Contracts\Container\Container;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * symfony/mailer's MailerInterface on Laravel's mail: a message omnibase
 * builds (a Symfony Email) sent by the default mailer's transport, which is
 * Symfony's too.
 */
final class TransportMailer implements MailerInterface
{
    public function __construct(private readonly Container $app)
    {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        $this->app->make('mailer')->getSymfonyTransport()->send($message, $envelope);
    }
}
