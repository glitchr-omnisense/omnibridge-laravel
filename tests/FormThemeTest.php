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
}
