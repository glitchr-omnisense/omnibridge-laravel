<?php

namespace Omnibridge\Laravel\Form;

use Omnibridge\Contract\Translations;

/**
 * form_start(), form_widget(), form_row(), form_label(), form_errors(),
 * form_rest() and form_end() for a form view - this package's FormView or omnibase's neutral
 * Base\Form\Definition\FormView, the same vars - with the markup omnibase's form theme gives
 * them: inputs, a textarea, a select, buttons, a switch, a list of switches, the errors. Placeholders
 * and labels are translated as Symfony's form theme translates them.
 */
final class FormRenderer
{
    public function __construct(private readonly Translations $translations)
    {
    }

    /** form(): the whole form, its start, a row per field, its end. */
    public function form(object $form): string
    {
        $html = $this->start($form).$this->errors($form);
        foreach ($form->children as $child) {
            if (!$child->rendered && 'hidden' !== $child->vars['type']) {
                $html .= $this->row($child);
            }
        }

        return $html.$this->end($form);
    }

    /** @param array<string, mixed> $options */
    public function start(object $form, array $options = []): string
    {
        $attributes = ['name' => $form->vars['name'], 'method' => strtolower($form->vars['method'] ?? 'post')];
        if (null !== ($form->vars['action'] ?? null) && '' !== $form->vars['action']) {
            $attributes['action'] = $form->vars['action'];
        }

        return '<form '.$this->attributes($attributes + ($options['attr'] ?? [])).'>';
    }

    /** @param array<string, mixed> $options */
    public function widget(object $field, array $options = []): string
    {
        $field->rendered = true;
        $vars = array_replace($field->vars, $options);
        $vars['attr'] = array_replace($field->vars['attr'], $options['attr'] ?? []);

        return match ($vars['type']) {
            // Markup given whole (a captcha's widget: Auth\Controllers\SignIn's FORM_EXTRA), printed as it is.
            'html' => \is_string($vars['html'] ?? null) ? $vars['html'] : '',
            'checkbox' => $this->checkbox($vars),
            'choice' => $this->isExpanded($vars) ? $this->choices($field, $vars) : $this->select($field, $vars),
            'textarea' => $this->textarea($vars),
            'submit', 'button' => ($vars['button'] ?? false) ? $this->action($field, $vars) : $this->button($vars),
            'repeated' => $this->repeated($field),
            default => $this->input($vars),
        };
    }

    /** @param array<string, mixed> $options */
    public function row(object $field, array $options = []): string
    {
        $vars = array_replace($field->vars, $options);
        if ('checkbox' === $vars['type']) {
            $field->rendered = true;

            return '<div  class="form-switch form-switch-lg form-group form-check"><span>'
                .$this->label($vars['id'], $this->labelled($vars))
                .'</span>'.$this->checkbox($vars + ['attr' => []]).$this->errors($field).'</div>';
        }
        if ('repeated' === $vars['type']) {
            return $this->repeated($field);
        }

        if (\in_array($vars['type'], ['hidden', 'submit', 'button', 'html'], true)) {
            return $this->widget($field, $options);
        }
        $label = false === ($vars['label'] ?? null) ? '' : $this->label($vars['id'], $this->labelled($vars));
        if ([] !== (array) ($vars['errors'] ?? [])) {
            $options['attr'] = ['class' => trim('is-invalid '.($options['attr']['class'] ?? ''))] + ($options['attr'] ?? []);
        }
        $rowAttr = (array) ($vars['row_attr'] ?? []);
        $class = trim(($rowAttr['class'] ?? '').' form-group');
        unset($rowAttr['class']);

        return '<div '.$this->attributes(['class' => $class] + $rowAttr).'>'.$label.$this->widget($field, $options).$this->errors($field).'</div>';
    }

    /** A field's label when a template gives none: its own, else its name as Symfony humanizes it (Agree terms). @param array<string, mixed> $vars */
    private function labelled(array $vars): string
    {
        $label = $vars['label'] ?? null;
        if (null !== $label && false !== $label) {
            return $this->translate($label);
        }

        return ucfirst(strtolower(trim((string) preg_replace(['/([A-Z])/', '/[_\s]+/'], ['_$1', ' '], (string) $vars['name']))));
    }

    /** A value typed twice: a row per input, its errors on the first. */
    private function repeated(object $field): string
    {
        $field->rendered = true;
        $html = '';
        foreach ($field->children as $child) {
            $html .= $this->row($child);
        }

        return $html;
    }

    /**
     * A button of omnibase's ButtonType (the theme's button2): the real one kept hidden, the one
     * shown asking first, in a modal when it asks for a confirmation.
     *
     * @param array<string, mixed> $vars
     */
    private function action(object $field, array $vars): string
    {
        $id = (string) $vars['id'];
        $confirmation = (bool) ($vars['confirmation'] ?? false);
        $classes = (string) ($vars['attr']['class'] ?? '');
        $label = (string) ($vars['label'] ?? $this->labelled($vars));
        $label = false === ($vars['label_html'] ?? null) ? htmlspecialchars($this->translate($label)) : $this->translate($label);
        $hidden = ['type' => $vars['type'], 'id' => $id, 'name' => $vars['full_name'], 'data-button-field' => $id, 'data-button-confirmation' => $confirmation ? '1' : '', 'data-button-confirmation-bubbleup' => '1', 'class' => trim($classes.' hidden btn-action').' hidden btn-action'];
        $html = '<button '.$this->attributes($hidden).'>'.$label.'</button>';
        $html .= '<button '.$this->attributes(['type' => 'button', 'class' => $classes, 'id' => $id.'-request']).'>'.$label.'</button>';
        if ($confirmation) {
            $html .= '<div class="modal fade" id="'.htmlspecialchars($id).'-modal" tabindex="-1" role="dialog" aria-labelledby="'.htmlspecialchars($id).'-modal"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content"><div class="modal-body">'
                .htmlspecialchars($this->translate('@fields.boolean.text')).'</div><div class="modal-footer"><button type="button" class="btn btn-default" id="'.htmlspecialchars($id).'-cancel" data-dismiss="modal">'
                .htmlspecialchars($this->translate('@fields.boolean.button.cancel')).'</button><button type="button" class="btn btn-primary" id="'.htmlspecialchars($id).'-confirm">'
                .htmlspecialchars($this->translate('@fields.boolean.button.confirm')).'</button></div></div></div></div>';
        }

        return $html;
    }

    /** form_errors(): the field's errors in the markup of omnibase's form theme; nothing without any. */
    public function errors(object $form): string
    {
        $errors = (array) ($form->vars['errors'] ?? []);
        if ([] === $errors) {
            return '';
        }

        $items = array_map(static fn (mixed $error): string => '<li><span>'.htmlspecialchars((string) $error).'</span></li>', $errors);

        return '<div class="form-errors"><ul>'.implode('', $items).'</ul></div>';
    }

    /**
     * form_label(): the field's label (the one given, else its own), in omnibase's form theme's markup.
     *
     * @param array<string, mixed> $options
     */
    public function fieldLabel(object $field, ?string $label = null, array $options = []): string
    {
        $text = $label ?? $options['label'] ?? $field->vars['label'] ?? $field->vars['name'];
        $id = $field->vars['id'];

        return $this->label(\is_scalar($id) ? (string) $id : '', $this->translate($text));
    }

    /** form_rest(): the fields not printed yet (the hidden ones: the token), without the form's end. */
    public function rest(object $form): string
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
    public function end(object $form): string
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
    private function textarea(array $vars): string
    {
        $attr = $vars['attr'];
        $attributes = ['id' => $vars['id'], 'name' => $vars['full_name'], 'required' => (bool) $vars['required'], 'disabled' => (bool) ($vars['disabled'] ?? false), 'class' => trim('form-control '.($attr['class'] ?? ''))];
        unset($attr['class']);

        return '<textarea '.$this->attributes($attributes + $attr).'>'.htmlspecialchars((string) $vars['value']).'</textarea>';
    }

    /** @param array<string, mixed> $vars */
    private function button(array $vars): string
    {
        $attributes = ['type' => $vars['type'], 'id' => $vars['id'], 'name' => $vars['full_name']] + $vars['attr'];

        return '<button '.$this->attributes($attributes).'>'.htmlspecialchars($this->translate($vars['label'] ?? $vars['name'])).'</button>';
    }

    /**
     * The list of switches of an expanded choice: omnibase's FormView says so with `expanded`; this
     * package's own (the newsletter's destinations) is always one.
     *
     * @param array<string, mixed> $vars
     */
    private function isExpanded(array $vars): bool
    {
        return $vars['expanded'] ?? true;
    }

    /** A neutral view's choices as value => label, groups flattened: this package's own already are. @return array<int|string, string> */
    private function flat(array $choices): array
    {
        $flat = [];
        foreach ($choices as $value => $choice) {
            if (!\is_array($choice)) {
                $flat[$value] = (string) $choice;
            } elseif (isset($choice['choices'])) {
                $flat += $this->flat($choice['choices']);
            } else {
                $flat[$choice['value']] = (string) $choice['label'];
            }
        }

        return $flat;
    }

    /** A single or multiple select. @param array<string, mixed> $vars */
    private function select(object $field, array $vars): string
    {
        $multiple = (bool) ($vars['multiple'] ?? false);
        $attr = $vars['attr'];
        $attributes = ['id' => $vars['id'], 'name' => $vars['full_name'].($multiple ? '[]' : ''), 'multiple' => $multiple, 'required' => (bool) $vars['required'], 'class' => trim('form-control '.($attr['class'] ?? ''))];
        unset($attr['class'], $attr['placeholder']);
        $html = '<select '.$this->attributes($attributes + $attr).'>';
        if (!$multiple && null !== ($vars['placeholder'] ?? null)) {
            $html .= '<option value="">'.htmlspecialchars($this->translate($vars['placeholder'])).'</option>';
        }
        foreach ($this->flat($vars['choices'] ?? []) as $value => $label) {
            $selected = \in_array((string) $value, array_map(strval(...), (array) ($vars['value'] ?? [])), true);
            $html .= '<option '.$this->attributes(['value' => (string) $value, 'selected' => $selected]).'>'.htmlspecialchars($this->translate($label)).'</option>';
        }

        return $html.'</select>';
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
    private function choices(object $field, array $vars): string
    {
        $html = '<div id="'.htmlspecialchars($vars['id']).'" class="form-control">';
        foreach ($this->flat($vars['choices'] ?? []) as $value => $label) {
            $id = $vars['id'].'_'.$value;
            $attributes = ['type' => 'checkbox', 'id' => $id, 'name' => $vars['full_name'].'[]', 'class' => 'form-switch form-switch-lg form-check-input', 'value' => (string) $value];
            if (\in_array((string) $value, array_map(strval(...), (array) ($vars['data'] ?? $vars['value'] ?? [])), true)) {
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
