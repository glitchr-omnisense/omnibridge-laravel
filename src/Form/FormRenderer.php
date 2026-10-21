<?php

namespace Omnibridge\Laravel\Form;

use Omnibridge\Contract\Translations;

/**
 * form_start(), form_widget(), form_row(), form_label(), form_errors(),
 * form_rest() and form_end() for the forms of FormView, with the markup omnibase's form theme gives
 * them: inputs, a switch, a list of switches. Placeholders
 * and labels are translated as Symfony's form theme translates them.
 */
final class FormRenderer
{
    public function __construct(private readonly Translations $translations)
    {
    }

    /** @param array<string, mixed> $options */
    public function start(FormView $form, array $options = []): string
    {
        $attributes = ['name' => $form->vars['name'], 'method' => strtolower($form->vars['method'] ?? 'post')];
        if (null !== ($form->vars['action'] ?? null) && '' !== $form->vars['action']) {
            $attributes['action'] = $form->vars['action'];
        }

        return '<form '.$this->attributes($attributes + ($options['attr'] ?? [])).'>';
    }

    /** @param array<string, mixed> $options */
    public function widget(FormView $field, array $options = []): string
    {
        $field->rendered = true;
        $vars = array_replace($field->vars, $options);
        $vars['attr'] = array_replace($field->vars['attr'], $options['attr'] ?? []);

        return match ($vars['type']) {
            'checkbox' => $this->checkbox($vars),
            'choice' => $this->choices($field, $vars),
            default => $this->input($vars),
        };
    }

    /** @param array<string, mixed> $options */
    public function row(FormView $field, array $options = []): string
    {
        $vars = array_replace($field->vars, $options);
        if ('checkbox' === $vars['type']) {
            $field->rendered = true;

            return '<div  class="form-switch form-switch-lg form-group form-check"><span>'
                .$this->label($vars['id'], $this->translate($vars['label'] ?? $vars['name']))
                .'</span>'.$this->checkbox($vars + ['attr' => []]).'</div>';
        }

        return '<div class="form-group">'.$this->label($vars['id'], $this->translate($vars['label'] ?? $vars['name'])).$this->widget($field, $options).'</div>';
    }

    public function errors(FormView $form): string
    {
        return '';
    }

    /**
     * form_label(): the field's label (the one given, else its own), in omnibase's form theme's markup.
     *
     * @param array<string, mixed> $options
     */
    public function fieldLabel(FormView $field, ?string $label = null, array $options = []): string
    {
        $text = $label ?? $options['label'] ?? $field->vars['label'] ?? $field->vars['name'];
        $id = $field->vars['id'];

        return $this->label(\is_scalar($id) ? (string) $id : '', $this->translate($text));
    }

    /** form_rest(): the fields not printed yet (the hidden ones: the token), without the form's end. */
    public function rest(FormView $form): string
    {
        $rest = '';
        foreach ($form->children as $child) {
            if (!$child->rendered) {
                $rest .= $this->widget($child);
            }
        }

        return $rest;
    }

    /** The fields not printed yet (the hidden ones: a parent's id, the token), then the end of the form. */
    public function end(FormView $form): string
    {
        return $this->rest($form).'</form>';
    }

    /** @param array<string, mixed> $vars */
    private function input(array $vars): string
    {
        $attr = $vars['attr'];
        foreach (['placeholder', 'title'] as $translated) {
            if (isset($attr[$translated])) {
                $attr[$translated] = $this->translate($attr[$translated]);
            }
        }
        $attributes = ['type' => $vars['type'], 'id' => $vars['id'], 'name' => $vars['full_name']];
        if ($vars['required']) {
            $attributes['required'] = 'required';
        }
        if ('hidden' !== $vars['type']) {
            $attributes['class'] = trim('form-control '.($attr['class'] ?? ''));
        }
        unset($attr['class']);
        $attributes += $attr;
        $value = $vars['value'];
        if (null !== $value && '' !== $value && !\is_array($value)) {
            $attributes['value'] = (string) $value;
        }

        return '<input '.$this->attributes($attributes).' />';
    }

    /** @param array<string, mixed> $vars */
    private function checkbox(array $vars): string
    {
        $attributes = ['type' => 'checkbox', 'id' => $vars['id'], 'name' => $vars['full_name'], 'class' => 'form-switch form-switch-lg form-check-input', 'value' => (string) ($vars['value'] ?? '1')];
        if ($vars['checked'] ?? false) {
            $attributes['checked'] = 'checked';
        }

        return '<input '.$this->attributes($attributes).' />';
    }

    /** A list of switches, one per choice (expanded, multiple). @param array<string, mixed> $vars */
    private function choices(FormView $field, array $vars): string
    {
        $html = '<div id="'.htmlspecialchars($vars['id']).'" class="form-control">';
        foreach ($vars['choices'] ?? [] as $value => $label) {
            $id = $vars['id'].'_'.$value;
            $attributes = ['type' => 'checkbox', 'id' => $id, 'name' => $vars['full_name'].'[]', 'class' => 'form-switch form-switch-lg form-check-input', 'value' => (string) $value];
            if (\in_array($value, (array) ($vars['data'] ?? []), false)) {
                $attributes['checked'] = 'checked';
            }
            $html .= '<input '.$this->attributes($attributes).' />'.$this->label($id, (string) $label);
        }

        return $html.'</div>';
    }

    private function label(string $for, string $text): string
    {
        return '<div class="label-wrapper"><label class="form-check-label" for="'.htmlspecialchars($for).'">'.htmlspecialchars($text).'</label><span class="badge badge-secondary label"></span></div>';
    }

    private function translate(mixed $text): string
    {
        return $this->translations->trans((string) $text);
    }

    /** @param array<string, mixed> $attributes */
    private function attributes(array $attributes): string
    {
        $out = [];
        foreach ($attributes as $name => $value) {
            if (false === $value || null === $value) {
                continue;
            }
            $out[] = true === $value ? $name.'="'.$name.'"' : $name.'="'.htmlspecialchars((string) $value, \ENT_QUOTES).'"';
        }

        return implode(' ', $out);
    }
}
