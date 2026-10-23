<?php

namespace Omnibridge\Laravel\Form;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The form functions of a page's templates under Laravel: form_start(),
 * form_widget(), form_row(), form_label(), form_errors(), form_rest() and
 * form_end() (Symfony's FormExtension) by FormRenderer, and form_proxy() /
 * form_view() (omnibase's FormTwigExtension, its FormProxy: the forms every
 * page carries) answered by the page's FormViews, by name (thread:searchbar).
 * The forms are Symfony's by nature (D3): shown here, posted to the
 * application that answers them.
 */
final class FormExtension extends AbstractExtension
{
    /** @param array<string, FormView> $views the page's forms, by name */
    public function __construct(private readonly FormRenderer $renderer, private readonly array $views = [])
    {
    }

    public function getFunctions(): array
    {
        $safe = ['is_safe' => ['html']];

        return [
            new TwigFunction('form_proxy', $this->view(...)),
            new TwigFunction('form_view', $this->view(...)),
            new TwigFunction('form_start', $this->renderer->start(...), $safe),
            new TwigFunction('form_widget', $this->renderer->widget(...), $safe),
            new TwigFunction('form_row', $this->renderer->row(...), $safe),
            new TwigFunction('form_label', $this->renderer->fieldLabel(...), $safe),
            new TwigFunction('form_errors', $this->renderer->errors(...), $safe),
            new TwigFunction('form_rest', $this->renderer->rest(...), $safe),
            new TwigFunction('form_end', $this->renderer->end(...), $safe),
        ];
    }

    public function view(string $name): ?FormView
    {
        return $this->views[$name] ?? null;
    }
}
