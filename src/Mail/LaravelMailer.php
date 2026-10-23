<?php

namespace Omnibridge\Laravel\Mail;

use Illuminate\Contracts\Mail\Factory;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * symfony/mailer's MailerInterface over Laravel's mail (plan "omniauth",
 * step AU8b; R26 of the master plan's gaps): an e-mail written as a Symfony
 * Mime message (omnibase's Base\Notifier\Mail\EmailComposer) sent by the
 * transport of one of config/mail.php's mailers - Laravel's mail is
 * Symfony's Mailer, its SMTP, log or array transport the same objects.
 * The contract both hosts answer: Symfony's own mailer under Symfony.
 */
final class LaravelMailer implements MailerInterface
{
    public function __construct(private readonly Factory $mail, private readonly ?string $mailer = null)
    {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        $mailer = $this->mail->mailer($this->mailer);
        $transport = method_exists($mailer, 'getSymfonyTransport') ? $mailer->getSymfonyTransport() : null;
        if (!$transport instanceof TransportInterface) {
            throw new \LogicException(\sprintf('The mailer "%s" gives no Symfony transport.', $this->mailer ?? 'default'));
        }
        $transport->send($message, $envelope);
    }
}
