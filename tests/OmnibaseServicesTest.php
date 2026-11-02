<?php

namespace Omnibridge\Laravel\Tests;

use Base\Database\Entity\EntityHydratorInterface;
use Base\Notifier\Abstract\BaseNotifierInterface;
use Base\Notifier\Notifier;
use Base\Service\BaseService;
use Base\Service\LocalizerInterface;
use Base\Service\SettingBagInterface;
use Base\Service\SpamCheckerInterface;
use Base\Service\TranslatorInterface;
use Omnibridge\Contract\Translations;
use Omnibridge\Laravel\Contract\LaravelValidation;
use Omnibridge\Laravel\Form\FormRenderer;
use Omnibridge\Laravel\Omnibase\FlushEntityEvents;
use Omnibridge\Validation\Constraint\Unique;
use Symfony\Component\Translation\Translator;

/**
 * omnibase's services an account's pages need here (omnisense/omnibase's plan "omnibase agnostic",
 * step F5c: Omnibase\OmnibaseServices), with omnibase's mapping on: the translator in the page's
 * language, the Localizer, the SettingBag, the entity hydrator, the spam checker, BaseTrait's
 * accessors answering them; the Notifier only with a channel policy (omnibridge.notifier); and the
 * markup and messages the account forms print.
 */
final class OmnibaseServicesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('omnibridge.doctrine.entities', [self::FIXTURES.'/Omnibase/Entity']);
        $app['config']->set('omnibridge.doctrine.omnibase', ['project_dir' => self::FIXTURES.'/Omnibase']);
        $app['config']->set('base', [
            'database' => ['use_custom' => true],
            'attributes' => ['use_custom' => true],
            'twig' => ['editor' => ['storage' => 'local.wysiwyg']],
            'uploader' => ['storage' => 'local.uploads'],
            'notifier' => ['technical_recipient' => ['email' => 'noreply@example.org'], 'admin_role' => 'ROLE_ADMIN', 'test_recipients' => [], 'options' => [], 'technical_loopback' => false, 'mailer' => true],
        ]);
        if ('testTheNotifierWithAChannelPolicy' === $this->name()) {
            $app['config']->set('omnibridge.notifier.channel_policy', ['default' => ['email+', 'browser+'], 'success' => ['browser+']]);
        }
    }

    public function testTheServicesAndBaseTraitsAccessors(): void
    {
        self::assertInstanceOf(Translator::class, $this->app->make(Translator::class));
        self::assertSame('fr_FR', $this->app->make(Translator::class)->getLocale(), 'the page\'s language with the country of its first available locale');
        $this->app->setLocale('en');
        self::assertSame('en_GB', $this->app->make(Translator::class)->getLocale(), 'a language switched');

        foreach ([TranslatorInterface::class, LocalizerInterface::class, SettingBagInterface::class, EntityHydratorInterface::class, SpamCheckerInterface::class] as $service) {
            self::assertInstanceOf($service, $this->app->make($service));
        }
        self::assertSame($this->app->make(TranslatorInterface::class), BaseService::getTranslator());
        self::assertSame($this->app->make(LocalizerInterface::class), BaseService::getLocalizer());
        self::assertSame($this->app->make(EntityHydratorInterface::class), BaseService::getEntityHydrator());
        self::assertSame(self::FIXTURES.'/Omnibase', BaseService::getProjectDir());
    }

    public function testNoNotifierWithoutAChannelPolicy(): void
    {
        self::assertFalse($this->app->bound(Notifier::class));
        self::assertNull(BaseService::findNotifier());
    }

    public function testTheNotifierWithAChannelPolicy(): void
    {
        $notifier = $this->app->make(Notifier::class);

        self::assertSame($notifier, $this->app->make(BaseNotifierInterface::class));
        self::assertSame($notifier, BaseService::getNotifier());
        self::assertSame(['email+', 'browser+'], $notifier->getPolicy()->getChannels('default'));
        self::assertTrue($this->app->bound('Base\EntitySubscriber\UserSubscriber'));
    }

    public function testWhatTheEntityEventsLeaveIsFlushedOnceThePageIsAnswered(): void
    {
        self::assertTrue($this->app->make(\Illuminate\Contracts\Http\Kernel::class)->hasMiddleware(FlushEntityEvents::class));
    }

    public function testAValidatorsValueIsTheCataloguesIcuArgumentToo(): void
    {
        $translations = new class implements Translations {
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return ['taken' => '"{value}" est déjà utilisé.', 'long' => 'Au maximum {{ limit }} caractères.'][$id] ?? $id;
            }

            public function exists(string $id, ?string $domain = null, ?string $locale = null): bool
            {
                return true;
            }
        };
        $validation = new LaravelValidation($this->app->make('validator'), $translations, static fn (): bool => true);

        $violations = $validation->validate(['username' => 'Alice'], [new Unique(['username'], 'App\Entity\User', message: 'taken')]);
        self::assertSame('"Alice" est déjà utilisé.', $violations[0]->message, 'unquoted, as omnibase\'s validators give it');
        self::assertSame('Au maximum 500 caractères.', $validation->validate(str_repeat('x', 501), [new \Omnibridge\Validation\Constraint\Length(max: 500, maxMessage: 'long')])[0]->message, '{{ limit }} itself');
    }

    public function testAFieldTypedTwiceIsPrintedOnceItsPartsAreAndAHelpIsATooltip(): void
    {
        $renderer = new FormRenderer($this->app->make(Translations::class));
        $form = new \Omnibridge\Laravel\Form\FormView('sign_up', ['method' => 'POST']);
        $form->add('plainPassword', ['type' => 'password']);
        $form->children['plainPassword']->add('first', ['type' => 'password'])->add('second', ['type' => 'password']);
        $form->add('username', ['type' => 'text', 'help' => 'Your name']);

        $renderer->widget($form['plainPassword']['first']);
        $renderer->widget($form['plainPassword']['second']);
        self::assertStringNotContainsString('sign_up[plainPassword]"', $renderer->rest($form), 'the field of two parts is not printed again whole');
        self::assertStringContainsString('title="Your name"', $renderer->help($form['username']));
        self::assertSame('', $renderer->help($form['plainPassword']));
    }
}
