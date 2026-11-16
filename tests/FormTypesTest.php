<?php

namespace Omnibridge\Laravel\Tests;

use Base\Field\Kind;
use Base\Form\Definition\FormProcessor;
use Base\Form\FormRenderer;
use Omnibridge\Contract\Validation;
use Base\Form\Definition\FormDefinition;
use Base\Form\Definition\TypeReader;
use Omnibridge\Laravel\Tests\Fixtures\Form\ContactMessage;
use Omnibridge\Laravel\Tests\Fixtures\Form\ContactType;
use Omnibridge\Laravel\Tests\Fixtures\Form\VisitorsType;
use Omnibridge\Laravel\Tests\Fixtures\Form\VisitType;

/**
 * "Forms once": the form types an application writes for Symfony (buildForm(), configureOptions(), omnibase's
 * Base\Field\Type\* and Symfony's core types) read as omnibase's FormDefinition, drawn by FormRenderer, bound and
 * validated by omnibase's FormProcessor - the same class on both hosts, nothing rewritten.
 */
final class FormTypesTest extends TestCase
{
    private function processor(): FormProcessor
    {
        return new FormProcessor($this->app->make(Validation::class));
    }

    public function testASitesContactFormIsReadDrawnAndSubmitted(): void
    {
        [$form, $unread] = TypeReader::reader()->read(ContactType::class);

        self::assertSame([], $unread);
        self::assertSame('contact', $form->getName());
        self::assertSame(ContactMessage::class, $form->getDataClass());
        self::assertSame('messages', $form->getTranslationDomain());
        self::assertSame(['action' => 'contact'], $form->getGuard());
        self::assertSame(['name' => Kind::TEXT, 'email' => Kind::EMAIL, 'phone' => Kind::TEL, 'message' => Kind::TEXTAREA, 'website' => Kind::TEXT],
            array_map(static fn ($field): ?string => $field->getKind(), $form->getFields()));
        self::assertFalse($form->get('phone')->isRequired());
        self::assertTrue($form->get('email')->isRequired(), 'required unless said, as Symfony');
        self::assertFalse($form->get('website')->getLabel());

        $html = $this->app->make(FormRenderer::class)->form($this->processor()->createView($form, new ContactMessage()));
        self::assertStringContainsString('name="contact[email]"', $html);
        self::assertStringContainsString('type="email"', $html);
        self::assertStringContainsString('type="tel"', $html);
        self::assertStringContainsString('autocomplete="tel"', $html);
        self::assertMatchesRegularExpression('/<textarea[^>]*name="contact\[message\]"[^>]*rows="6"|<textarea[^>]*rows="6"[^>]*name="contact\[message\]"/', $html);
        self::assertStringContainsString('mh-trap', $html);

        $refused = $this->processor()->handle($form, ['name' => 'Ada', 'email' => 'not an address', 'message' => ''], new ContactMessage());
        self::assertFalse($refused->isValid());
        self::assertArrayHasKey('email', $refused->getErrors());
        self::assertArrayHasKey('message', $refused->getErrors());

        $message = new ContactMessage();
        $accepted = $this->processor()->handle($form, ['name' => 'Ada', 'email' => 'ada@example.org', 'phone' => '', 'message' => 'Hello', 'website' => ''], $message);
        self::assertTrue($accepted->isValid(), json_encode($accepted->getErrors()));
        self::assertSame('Ada', $message->name);
        self::assertSame('ada@example.org', $message->email);
        self::assertSame('Hello', $message->message);
    }

    public function testABundlesFormWithOmnibasesSelectIsReadDrawnAndSubmitted(): void
    {
        [$form, $unread] = TypeReader::reader()->read(VisitType::class);

        self::assertSame(['state' => Kind::CHOICE, 'guests' => Kind::INTEGER, 'note' => Kind::TEXT, 'visitors' => Kind::CHOICE, 'countries' => Kind::COUNTRY, 'on' => Kind::DATE, 'confirmed' => Kind::CHECKBOX],
            array_map(static fn ($field): ?string => $field->getKind(), $form->getFields()));
        self::assertSame(['save', 'reset'], array_keys($form->getActions()));
        self::assertSame('reset', $form->getActions()['reset']['options']['type']);
        self::assertSame(['ada', 'grace'], array_values($form->get('visitors')->getOption('choices')), 'the application\'s select: its own options');
        self::assertTrue($form->get('visitors')->getOption('multiple'));
        self::assertFalse($form->hasCsrf());
        self::assertCount(1, $unread, implode("\n", $unread));
        self::assertStringContainsString('form events', $unread[0]);
        self::assertSame([], array_keys(FormDefinition::of(VisitType::class, ['buttons' => false])->getActions()), 'the form\'s own option');

        $html = $this->app->make(FormRenderer::class)->form($this->processor()->createView($form, []));
        self::assertStringContainsString('name="visit[state]', $html);
        self::assertStringContainsString('VISIT_DONE', $html);
        self::assertStringContainsString('name="visit[visitors]', $html);
        self::assertStringContainsString('value="grace"', $html);
        self::assertStringContainsString('name="visit[guests]"', $html);
        self::assertStringContainsString('name="visit[save]"', $html);

        $refused = $this->processor()->handle($form, ['state' => 'VISIT_DONE', 'guests' => '2', 'note' => 'far too long', 'visitors' => ['ada']], []);
        self::assertFalse($refused->isValid());
        self::assertSame(['Too long.'], $refused->getErrors()['note'] ?? null, json_encode($refused->getErrors()));

        $accepted = $this->processor()->handle($form, ['state' => 'VISIT_DONE', 'guests' => '2', 'note' => 'ok', 'visitors' => ['ada', 'grace'], 'save' => ''], []);
        self::assertTrue($accepted->isValid(), json_encode($accepted->getErrors()));
        self::assertSame('save', $accepted->getClicked());
        self::assertSame(2, $accepted->data['guests']);
        self::assertSame(['ada', 'grace'], $accepted->data['visitors']);
        self::assertSame('VISIT_DONE', $accepted->data['state']);
    }

    /** omnibase's own contact form (Base\Form\Type\ContactType, the core's), the same class as under Symfony. */
    public function testTheCoresContactFormIsReadDrawnAndSubmitted(): void
    {
        [$form, $unread] = TypeReader::reader()->read(\Base\Form\Type\ContactType::class, ['phone' => true, 'attachments' => false, 'trap' => true]);

        self::assertSame([], $unread, implode("\n", $unread));
        self::assertSame(\Base\Form\Model\ContactModel::class, $form->getDataClass());
        self::assertSame(['name', 'email', 'phone', 'subject', 'message', 'website'], array_keys($form->getFields()));
        self::assertSame(['submit', 'reset'], array_keys($form->getActions()));
        self::assertSame(['action' => 'contact'], $form->getGuard());

        $html = $this->app->make(FormRenderer::class)->form($this->processor()->createView($form, new \Base\Form\Model\ContactModel()));
        self::assertStringContainsString('name="'.$form->getName().'[email]"', $html);
        self::assertStringContainsString('base-trap', $html);

        $model = new \Base\Form\Model\ContactModel();
        $accepted = $this->processor()->handle($form, ['name' => 'Ada', 'email' => 'ada@example.org', 'phone' => '', 'subject' => 'Hi', 'message' => 'Hello there', 'website' => '', 'submit' => ''], $model);
        self::assertTrue($accepted->isValid(), json_encode($accepted->getErrors()));
        self::assertSame('ada@example.org', $model->email);
        self::assertSame('submit', $accepted->getClicked());
    }

    /**
     * A field that is a whole form (Kind::FORM): the core's ContactType's `privacy` (PrivacyType, its notice and its
     * consent box) read as a nested form, drawn as omnibase's theme draws it, a missing consent refused, a given one bound.
     */
    public function testANestedFormIsReadDrawnRefusedAndBound(): void
    {
        [$form, $unread] = TypeReader::reader()->read(\Base\Form\Type\ContactType::class, ['attachments' => false, 'buttons' => false, 'privacy' => true, 'privacy_consent' => true]);

        self::assertSame([], $unread, implode("\n", $unread));
        self::assertSame(Kind::FORM, $form->get('privacy')->getKind());
        $nested = $form->get('privacy')->getOption('form');
        self::assertInstanceOf(FormDefinition::class, $nested);
        self::assertSame(['accept'], array_keys($nested->getFields()));
        self::assertSame('@forms.privacy.notice', $form->get('privacy')->getOption('view')['notice']);
        self::assertTrue($form->get('privacy')->isVirtual(), 'PrivacyType is not mapped');

        $name = $form->getName();
        $html = $this->app->make(FormRenderer::class)->form($this->processor()->createView($form, new \Base\Form\Model\ContactModel()));
        self::assertStringContainsString('class="form-privacy"', $html);
        self::assertStringContainsString('form-privacy-notice', $html);
        self::assertStringContainsString('name="'.$name.'[privacy][accept]"', $html);
        self::assertStringContainsString('type="checkbox"', $html);

        $sent = ['name' => 'Ada', 'email' => 'ada@example.org', 'subject' => 'Hi', 'message' => 'Hello there'];
        $refused = $this->processor()->handle($form, $sent, new \Base\Form\Model\ContactModel());
        self::assertFalse($refused->isValid());
        self::assertCount(1, $refused->getErrors()['privacy'] ?? [], json_encode($refused->getErrors()));
        self::assertSame(['privacy.accept'], array_map(static fn ($violation): string => $violation->path, $refused->violations), 'the consent box refused, under the nested form');
        self::assertStringContainsString('form-errors', $this->app->make(FormRenderer::class)->form($refused->createView()));

        $model = new \Base\Form\Model\ContactModel();
        $accepted = $this->processor()->handle($form, $sent + ['privacy' => ['accept' => '1']], $model);
        self::assertTrue($accepted->isValid(), json_encode($accepted->getErrors()));
        self::assertSame(['accept' => true], $form->get('privacy')->getValue());
        self::assertSame(['accept' => true], $accepted->values['privacy']);
        self::assertSame('ada@example.org', $model->email);
    }

    public function testAFieldTypeIsNoForm(): void
    {
        $this->expectExceptionMessage('is a field type');
        TypeReader::reader()->read(VisitorsType::class);
    }

    /** A type's kind by its name where omnibridge/symfony's table is not installed. */
    public function testTheKindsOfTheTypesByTheirNames(): void
    {
        $types = TypeReader::reader();
        foreach ([
            'Base\Field\Type\SelectType' => Kind::CHOICE, 'Base\Field\Type\DateTimePickerType' => Kind::DATETIME_PICKER,
            'Base\Field\Type\EmojiPickerType' => Kind::EMOJI, 'Base\Field\Type\AssociationFileType' => Kind::ASSOCIATION_FILE,
            'Base\Field\Type\ColorPickerType' => Kind::COLOR_PICKER, 'Base\Field\Type\BooleanType' => Kind::BOOLEAN,
            'Base\Field\Type\AudioType' => Kind::AUDIO, 'Base\Field\Type\SubmitType' => Kind::SUBMIT,
            'Symfony\Component\Form\Extension\Core\Type\DateTimeType' => Kind::DATETIME,
            'Symfony\Component\Form\Extension\Core\Type\UrlType' => Kind::URL,
            'Symfony\Component\Form\Extension\Core\Type\HiddenType' => Kind::HIDDEN,
            'Symfony\Component\Form\Extension\Core\Type\ChoiceType' => Kind::CHOICE,
            'Symfony\Component\Form\Extension\Core\Type\RepeatedType' => Kind::REPEATED,
            VisitorsType::class => Kind::CHOICE,
        ] as $type => $kind) {
            self::assertSame($kind, $types->kind($type), $type);
        }
        self::assertNull($types->kind('Symfony\Component\Form\Extension\Core\Type\FormType'));
        self::assertNull($types->kind(ContactType::class), 'a form, no field');
    }
}
