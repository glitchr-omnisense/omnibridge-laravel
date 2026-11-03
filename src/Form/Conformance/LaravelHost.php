<?php

namespace Omnibridge\Laravel\Form\Conformance;

use Base\Field\Conformance\Host;
use Base\Field\Conformance\Outcome;
use Base\Form\Definition\FormDefinition;
use Base\Form\Definition\FormProcessor;
use Base\Form\Definition\FormView;
use Omnibridge\Laravel\Form\FormRenderer;

/**
 * This package as omnisense/omnibase's field conformance suite drives it
 * (Base\Field\Conformance\Suite): a definition drawn by FormRenderer from
 * omnibase's neutral FormView, a submission bound and validated by omnibase's
 * FormProcessor (its FormBinder, the host's Validation).
 */
final class LaravelHost implements Host
{
    public function __construct(private readonly FormRenderer $renderer, private readonly FormProcessor $processor)
    {
    }

    public function name(): string
    {
        return 'laravel';
    }

    public function render(FormDefinition $form, mixed $data): string
    {
        return $this->renderer->form($this->processor->createView($form, $data));
    }

    public function submit(FormDefinition $form, array $submitted, mixed $data): Outcome
    {
        $submission = $this->processor->handle($form, $submitted, $data);

        return new Outcome($submission->data, $submission->getErrors());
    }

    /** The view drawn, for a test that reads its vars. */
    public function view(FormDefinition $form, mixed $data): FormView
    {
        return $this->processor->createView($form, $data);
    }
}
