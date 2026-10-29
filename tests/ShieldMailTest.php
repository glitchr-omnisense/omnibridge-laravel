<?php

namespace Omnibridge\Laravel\Tests;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Foundation\Application;
use Omnibridge\Contract\Translations;
use Omnibridge\Laravel\Form\FormRenderer;
use Omnibridge\Laravel\Form\FormView;
use Omnibridge\Laravel\Mail\LaravelMailer;
use Omnibridge\Laravel\Shield\ShieldServiceProvider;
use Omnishield\Registry;
use Omnishield\Testing\FixedGateway;
use Omnishield\WidgetPrinter;
use Symfony\Component\Mime\Email;

/**
 * What the sign-in's effects need of Laravel beyond Auth\ (plan "omniauth",
 * step AU8b): symfony/mailer's contract over Laravel's mail (LaravelMailer,
 * omnibase's e-mails sent by config/mail.php's mailer), omnishield's
 * Registry from config/omnishield.php and ALTCHA's widget served by the
 * application (Shield\ShieldServiceProvider), and markup given whole to a
 * form (the captcha's widget in the sign-in form).
 */
final class ShieldMailTest extends TestCase
{
    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $config = $app->make(Config::class);
        $config->set('mail.default', 'array');
        $config->set('mail.mailers.array', ['transport' => 'array']);
        $config->set('omnishield.gateways', [
            'forms' => ['factory' => 'fixed'],
            'altcha' => ['factory' => 'altcha', 'options' => ['hmac_key' => 'omnibridge-laravel-test-hmac-key']],
        ]);
        $config->set('omnishield.challenge.gateway', 'forms');
    }

    private function container(): Application
    {
        return $this->app ?? throw new \LogicException('No application.');
    }

    public function testAMimeEmailIsSentByLaravelsMailer(): void
    {
        $email = (new Email())->from('Support <support@example.org>')->to('Ada <ada@example.org>')->subject('Bonjour')->html('<p>Bonjour</p>')->text('Bonjour');

        $this->container()->make(LaravelMailer::class)->send($email);

        $manager = $this->container()->make('mail.manager');
        \assert($manager instanceof \Illuminate\Mail\MailManager);
        $mailer = $manager->mailer('array');
        \assert($mailer instanceof \Illuminate\Mail\Mailer);
        $transport = $mailer->getSymfonyTransport();
        \assert($transport instanceof \Illuminate\Mail\Transport\ArrayTransport);
        $sent = $transport->messages();
        self::assertCount(1, $sent);
        $first = $sent->first();
        \assert($first instanceof \Symfony\Component\Mailer\SentMessage);
        $message = $first->getOriginalMessage();
        self::assertInstanceOf(Email::class, $message);
        self::assertSame('Bonjour', $message->getSubject());
        self::assertSame('<p>Bonjour</p>', $message->getHtmlBody());
    }

    public function testTheGatewaysOfConfigOmnishieldAndAltchasScript(): void
    {
        if (!class_exists(Registry::class)) {
            self::markTestSkipped('omnisense/omnishield is not installed.');
        }
        $registry = $this->container()->make(Registry::class);
        self::assertTrue($registry->has('forms'));
        self::assertSame(FixedGateway::FIELD, $registry->challenge('forms')->widget('login')->field);

        if (!class_exists('Omnishield\Altcha\AltchaGatewayFactory')) {
            self::markTestSkipped('omnishield/altcha is not installed.');
        }
        $widget = $registry->challenge('altcha')->widget('login');
        self::assertSame('/omnishield/altcha/'.\Omnishield\Altcha\AltchaGatewayFactory::SCRIPT_VERSION.'/altcha.min.js', $widget->script, 'served by the application, as the bundle serves it');
        $response = $this->get($widget->script);
        $response->assertOk();
        self::assertStringStartsWith('text/javascript', (string) $response->headers->get('Content-Type'));
    }

    /** The keys of the bundle's omnishield.yaml with its defaults: a provider that does not answer refuses the form. */
    public function testAnUnreachableCaptchaRefusesByDefault(): void
    {
        if (!class_exists(Registry::class)) {
            self::markTestSkipped('omnisense/omnishield is not installed.');
        }
        self::assertFalse(ShieldServiceProvider::acceptsUnreachable($this->container()), 'challenge.unreachable: reject, the bundle\'s default');
        $this->container()->make(Config::class)->set('omnishield.challenge.unreachable', 'accept');
        self::assertTrue(ShieldServiceProvider::acceptsUnreachable($this->container()));
    }

    /**
     * One WidgetPrinter per request, as the bundle resets its own (kernel.reset): a long-running
     * worker (Octane) forgets the scoped instances between two requests, and the next page prints
     * a provider's script again.
     */
    public function testTheWidgetPrinterIsOnePerRequest(): void
    {
        if (!class_exists(Registry::class)) {
            self::markTestSkipped('omnisense/omnishield is not installed.');
        }
        $widget = $this->container()->make(Registry::class)->challenge('altcha')->widget('login');
        $first = $this->container()->make(WidgetPrinter::class);
        self::assertSame($first, $this->container()->make(WidgetPrinter::class), 'one printer for the page');
        self::assertStringContainsString('<script', $first->print($widget));
        self::assertStringNotContainsString('<script', $first->print($widget), 'the script once a page');

        $this->container()->forgetScopedInstances();
        $next = $this->container()->make(WidgetPrinter::class);
        self::assertNotSame($first, $next, 'the next request: a printer of its own');
        self::assertStringContainsString('<script', $next->print($widget), 'the next page has its script');
    }

    /** Symfony's own catalogues are XLIFF (security-core's "security" domain: a refused sign-in's words), read without symfony/config. */
    public function testXliffCataloguesAreRead(): void
    {
        $translations = new \Omnibridge\Laravel\CatalogueTranslations([self::FIXTURES.'/translations'], 'fr');

        self::assertSame('Identifiants invalides.', $translations->trans('Invalid credentials.', [], 'security'));
        self::assertSame('Plusieurs tentatives de connexion ont échoué, veuillez réessayer dans 1 minute.', $translations->trans('Too many failed login attempts, please try again in %minutes% minute.', ['%minutes%' => 1], 'security'));
    }

    public function testMarkupGivenWholeIsPrintedWithTheRestOfTheForm(): void
    {
        $form = new FormView('_base_security_login', ['method' => 'post']);
        $form->add('identifier', ['type' => 'text']);
        $form->add('_extra', ['type' => 'html', 'html' => '<altcha-widget name="altcha"></altcha-widget>']);
        $renderer = new FormRenderer($this->container()->make(Translations::class));

        self::assertStringContainsString('<altcha-widget name="altcha"></altcha-widget>', $renderer->rest($form));
    }
}
