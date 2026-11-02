<?php

namespace Omnibridge\Laravel\Tests;

use Illuminate\Support\Facades\Blade;
use Omnibridge\Laravel\Form\FormView;

/** The generic form theme under Laravel: FormRenderer on a form view's vars, from Blade (omnibase's step F5b). */
final class FormThemeTest extends TestCase
{
    private function form(): FormView
    {
        $form = new FormView('comment', ['method' => 'POST', 'action' => '/send', 'errors' => ['Token.']]);
        $form->add('content', ['type' => 'textarea', 'value' => '<b>', 'label' => 'Content', 'required' => true, 'errors' => ['Too short.']]);
        $form->add('email', ['type' => 'email', 'value' => 'a@b.c', 'label' => 'Email']);
        $form->add('room', ['type' => 'choice', 'expanded' => false, 'value' => 'l', 'label' => 'Room', 'placeholder' => 'Pick', 'choices' => [['value' => 's', 'label' => 'Small'], ['label' => 'Big', 'choices' => [['value' => 'l', 'label' => 'Large']]]]]);
        $form->add('submit', ['type' => 'submit', 'label' => 'Send']);
        $form->add('_csrf_token', ['type' => 'hidden', 'value' => 'tok']);

        return $form;
    }

    public function testAWholeFormFromBlade(): void
    {
        $html = Blade::render('@form($form)', ['form' => $this->form()]);

        self::assertStringContainsString('<form name="comment" method="post" action="/send">', $html);
        self::assertStringContainsString('<li><span>Token.</span></li>', $html);
        self::assertStringContainsString('<textarea id="comment_content" name="comment[content]" required="required" class="form-control is-invalid">&lt;b&gt;</textarea><div class="form-errors"><ul><li><span>Too short.</span></li></ul></div>', $html);
        self::assertStringContainsString('<option value="">Pick</option>', $html);
        self::assertStringContainsString('<option value="l" selected="selected">Large</option>', $html);
        self::assertStringContainsString('<button type="submit" id="comment_submit" name="comment[submit]">Send</button>', $html);
        self::assertStringContainsString('name="comment[_csrf_token]"', $html);
        self::assertSame(1, substr_count($html, 'comment[email]'), 'each field once');
        self::assertStringEndsWith('</form>', $html);
    }

    public function testFieldByField(): void
    {
        $form = $this->form();
        $html = Blade::render('@formStart($form)@formRow($form["email"])@formEnd($form)', ['form' => $form]);

        self::assertSame(1, substr_count($html, 'comment[email]'));
        self::assertStringContainsString('comment[_csrf_token]', $html);
    }

    public function testAValueTypedTwiceTheButtonThatAsksAndTheTrapsRow(): void
    {
        $form = new FormView('sign_up', ['method' => 'POST']);
        $password = $form->add('plainPassword', ['type' => 'repeated'])->children['plainPassword'];
        $password->add('first', ['type' => 'password', 'required' => true, 'errors' => ['The values do not match.']]);
        $password->add('second', ['type' => 'password', 'required' => true]);
        $form->add('guard_website', ['type' => 'text', 'label' => false, 'row_attr' => ['class' => 'base-trap', 'aria-hidden' => 'true']]);
        $form->add('submit', ['type' => 'submit', 'button' => true, 'confirmation' => true, 'label' => '<i></i>', 'attr' => ['class' => 'btn']]);

        $html = Blade::render('@form($form)', ['form' => $form]);

        self::assertStringContainsString('name="sign_up[plainPassword][first]"', $html);
        self::assertStringContainsString('name="sign_up[plainPassword][second]"', $html);
        self::assertStringContainsString('<div class="form-errors"><ul><li><span>The values do not match.</span>', $html);
        self::assertStringContainsString('<div class="base-trap form-group" aria-hidden="true"><input type="text" id="sign_up_guard_website"', $html);
        self::assertStringContainsString('data-button-confirmation="1"', $html);
        self::assertStringContainsString('id="sign_up_submit-request"', $html);
        self::assertStringContainsString('id="sign_up_submit-modal"', $html);
        self::assertStringContainsString('<i></i></button>', $html);
    }
}
