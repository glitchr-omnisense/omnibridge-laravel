<?php

namespace Omnibridge\Laravel\Form;

use Omnibridge\Contract\Translations;
use Omnibridge\Form\Coverage;
use Omnibridge\Form\UnsupportedKind;

/**
 * form_start(), form_widget(), form_row(), form_label(), form_errors(),
 * form_rest() and form_end() for a form view - this package's FormView or omnibase's neutral
 * Base\Form\Definition\FormView, the same vars - with the markup omnibase's form theme gives
 * them: inputs, a textarea, a select, buttons, a switch, a list of switches, the errors. Placeholders
 * and labels are translated as Symfony's form theme translates them.
 *
 * A rich kind of omnibase (plan "omnibase agnostic", step F5d) is drawn as the block of omnibase's
 * Symfony theme its neutral behaviour names (the view's `widget`): datetimepicker, slug, boolean,
 * number2, select2, password2; its settings are the behaviour's, the same the Symfony type reads.
 * A kind this package declares unsupported, or does not declare (LaravelKinds::COVERAGE), is
 * refused aloud (UnsupportedKind), never drawn as a plain input.
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
        self::refuse($vars);

        switch ($vars['widget'] ?? null) {
            case 'datetimepicker': return $this->dateTimePicker($vars);
            case 'slug': return $this->slug($vars);
            case 'boolean': return $this->toggle($vars);
            case 'number': return $this->number($vars);
            case 'select2': return $this->select2($vars);
            case 'password2': return $this->password($vars);
            // Pickr (colorpickr_widget): the input, told where its field is.
            case 'color': return $this->input(['attr' => $vars['attr'] + ['data-color-field' => $vars['id']]] + $vars);
        }

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
        self::refuse($vars);
        if ('password2' === ($vars['widget'] ?? null)) {
            return $this->widget($field, $options).$this->errors($field);
        }
        if ('boolean' === ($vars['widget'] ?? null)) {
            $rowAttr = (array) ($vars['row_attr'] ?? []);
            $class = trim(($vars['switch'] ?? true ? 'form-check form-switch ' : '').(($vars['inline'] ?? false) ? 'inline ' : '').($rowAttr['class'] ?? '').' form-group');
            unset($rowAttr['class']);

            return '<div '.$this->attributes(['class' => $class] + $rowAttr).'><span>'.$this->label($vars['id'], $this->labelled($vars), (bool) $vars['required']).'</span>'.$this->widget($field, $options).$this->errors($field).'</div>';
        }
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
        // A select2 is its form's `choice` part: its label is for no input, as Symfony's compound row prints it.
        $label = false === ($vars['label'] ?? null) ? '' : $this->label('select2' === ($vars['widget'] ?? null) ? '' : $vars['id'], $this->labelled($vars), 'select2' === ($vars['widget'] ?? null) && (bool) $vars['required']);
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

    /** form_help(): the field's help, the tooltip of omnibase's form theme; nothing without one. */
    public function help(object $field): string
    {
        $help = $field->vars['help'] ?? null;
        if (!\is_string($help) || '' === $help) {
            return '';
        }

        return '<span class="data-help"><i class="fa-regular fa-question-circle" data-toggle="tooltip" data-bs-placement="right" title="'.htmlspecialchars($this->translate($help)).'"></i></span>';
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
            // A field of several (a value typed twice) whose parts were all printed is printed, as Symfony's
            // FormView::isRendered() says it.
            if (!$child->rendered && [] !== $child->children && [] === array_filter($child->children, static fn (object $part): bool => !$part->rendered)) {
                $child->rendered = true;
            }
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
        // Symfony's choice_widget_collapsed: a single select with no placeholder is not required (its first option is a value).
        $required = (bool) $vars['required'] && ($multiple || null !== ($vars['placeholder'] ?? null));
        $attributes = ['id' => $vars['id'], 'name' => $vars['full_name'].($multiple ? '[]' : ''), 'multiple' => $multiple, 'required' => $required, 'class' => trim('form-control '.($attr['class'] ?? ''))];
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
        if ($vars['required'] ?? false) {
            $attributes['required'] = 'required'; // as Symfony's CheckboxType: required unless it says otherwise
        }
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

    private function label(string $for, string $text, bool $required = false): string
    {
        $class = 'form-check-label'.($required ? ' required' : '');

        return '<div class="label-wrapper"><label class="'.$class.'"'.('' !== $for ? ' for="'.htmlspecialchars($for).'"' : '').'>'.htmlspecialchars($text).'</label><span class="badge badge-secondary label"></span></div>';
    }

    /**
     * A kind of omnibase this package does not draw, or does not declare: refused aloud. A view
     * without a kind (this package's own FormView) or the form itself is drawn as it was.
     *
     * @param array<string, mixed> $vars
     */
    private static function refuse(array $vars): void
    {
        $kind = $vars['kind'] ?? null;
        if (!\is_string($kind) || 'form' === $kind || !class_exists(\Base\Field\Kinds::class)) {
            return;
        }
        // A field that names its host type but keeps a kind (the newsletter's records as an association, Symfony's
        // EntityType) is drawn from its generic view - input, textarea, choice - as the definition gives it; one with
        // no kind at all (custom) has nothing to draw here.
        if (($vars['explicit'] ?? false) && 'custom' !== $kind) {
            return;
        }
        $declaration = \Base\Field\Kinds::declaration($kind, 'laravel');
        if (!Coverage::isImplemented($declaration)) {
            throw UnsupportedKind::of($kind, 'omnibridge/laravel', Coverage::reason($declaration));
        }
    }

    /** A date (and time) in flatpickr (datetimepicker_widget). @param array<string, mixed> $vars */
    private function dateTimePicker(array $vars): string
    {
        $vars['type'] = 'text';
        $vars['attr'] = array_replace($vars['attr'], ['data-datetimepicker-field' => $vars['id'], 'data-datetimepicker-options' => $vars['datetimepicker'] ?? '{}']);
        $id = htmlspecialchars((string) $vars['id']);

        return '<div class="input-group form-stock"><div class="input-group-text"><button class="btn btn-outline-secondary" type="button" id="'.$id.'-btn"><i class="fa-solid fa-calendar-day"></i></button></div>'
            .$this->input($vars).'</div>';
    }

    /** A slug made from another field, locked once written (slug_widget). @param array<string, mixed> $vars */
    private function slug(array $vars): string
    {
        $vars['type'] = 'text';
        $vars['attr'] = array_replace($vars['attr'], [
            'data-slug-field' => $vars['id'], 'data-slug-keep' => (string) ($vars['keep'] ?? ''), 'data-slug-strict' => $vars['strict'] ?? 'true',
            'data-slug-upper' => $vars['upper'] ?? 'false', 'data-slug-separator' => (string) ($vars['separator'] ?? ''), 'data-slug-lock' => $vars['lock'] ?? 'null',
            'data-slug-target' => $vars['target_id'] ?? (string) preg_replace('/_[^_]+$/', '', (string) $vars['id']),
        ]);

        return '<div class="input-group">'.$this->input($vars).'<div class="input-group-append"><button class="input-group-text" type="button"><i class="fa-solid '.(empty($vars['target']) ? 'fa-unlock' : 'fa-lock').' fa-fw"></i></button></div></div>';
    }

    /** A switch asking for a confirmation (boolean_widget). @param array<string, mixed> $vars */
    private function toggle(array $vars): string
    {
        $id = (string) $vars['id'];
        $attributes = ['type' => 'checkbox', 'id' => $id, 'name' => $vars['full_name'], 'class' => trim((($vars['switch'] ?? true) ? 'form-check-input ' : '').($vars['attr']['class'] ?? '').' form-control')];
        $attr = $vars['attr'];
        unset($attr['class']);
        $attributes += $attr + [
            'data-boolean-field' => $id,
            'data-boolean-confirmation-check' => (bool) ($vars['confirmation_check'] ?? false),
            'data-boolean-confirmation-uncheck' => (bool) ($vars['confirmation_uncheck'] ?? false),
            'data-toggle-url' => (string) ($vars['toogle_url'] ?? ''),
            'value' => (string) ($vars['value'] ?? '1'),
            'checked' => (bool) ($vars['checked'] ?? false),
            'disabled' => (bool) ($vars['disabled'] ?? false),
        ];
        $html = '<div class="form-checkbox"><input '.$this->attributes($attributes).' /></div>';
        if (($vars['confirmation_check'] ?? false) || ($vars['confirmation_uncheck'] ?? false)) {
            $e = htmlspecialchars($id);
            $html .= '<div class="modal fade" id="'.$e.'-modal" tabindex="-1" role="dialog" aria-labelledby="'.$e.'-modal"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content"><div class="modal-header">'
                .'<button type="button" class="btn-close" data-dismiss="modal" aria-label="Close" id="'.$e.'-close"><span aria-hidden="true">&times;</span></button>'
                .'<h4 class="modal-title" id="'.$e.'-modal-label">'.htmlspecialchars($this->translations->trans('@fields.boolean.title', [false === ($vars['label'] ?? null) ? '' : (string) ($vars['label'] ?? '')])).'</h4></div>'
                .'<div class="modal-body">'.htmlspecialchars($this->translate('@fields.boolean.text')).'</div><div class="modal-footer">'
                .'<button type="button" class="btn btn-default" id="'.$e.'-cancel" data-dismiss="modal">'.htmlspecialchars($this->translate('@fields.boolean.button.cancel')).'</button>'
                .'<button type="button" class="btn btn-primary" id="'.$e.'-confirm">'.htmlspecialchars($this->translate('@fields.boolean.button.confirm')).'</button></div></div></div></div>';
        }

        return $html;
    }

    /** A number with its steppers (number2_widget). @param array<string, mixed> $vars */
    private function number(array $vars): string
    {
        $vars['type'] = 'text';
        $vars['attr'] = array_replace(['inputmode' => 'decimal'], $vars['attr'], [
            'data-number-field' => $vars['id'], 'data-number-keyup' => (bool) ($vars['keyUp'] ?? true), 'data-number-keydown' => (bool) ($vars['keyDown'] ?? true),
            'data-number-up' => (string) ($vars['stepUp'] ?? 1), 'data-number-divisor' => (string) ($vars['divisor'] ?? 1), 'data-number-down' => (string) ($vars['stepDown'] ?? 1),
            'data-number-up-throttle' => (string) ($vars['throttleUp'] ?? 0), 'data-number-down-throttle' => (string) ($vars['throttleDown'] ?? 0),
            'autocomplete' => ($vars['autocomplete'] ?? false) ? 'on' : 'off',
            'data-number-min' => (string) ($vars['min'] ?? ''), 'data-number-max' => (string) ($vars['max'] ?? ''),
            'data-number-suffix' => (string) ($vars['suffix'] ?? ''), 'data-number-prefix' => (string) ($vars['prefix'] ?? ''),
        ]);
        if ($vars['disabled'] ?? false) {
            $vars['attr']['disabled'] = 'disabled';
        }
        $id = htmlspecialchars((string) $vars['id']);
        $disabled = ($vars['disabled'] ?? false) ? ' disabled="disabled"' : '';

        return '<div class="input-group form-number">'.$this->input($vars)
            .'<div class="input-group-text"><button class="btn btn-outline-secondary"'.$disabled.' id="'.$id.'-down" type="button"><i class="fa-solid fa-minus"></i></button></div>'
            .'<div class="input-group-text"><button class="btn btn-outline-secondary"'.$disabled.' id="'.$id.'-up" type="button"><i class="fa-solid fa-plus"></i></button></div></div>';
    }

    /**
     * A select2 (select2_widget): the field's `choice` part, as omnibase's SelectType names it,
     * its options printed, the chosen ones selected, the placeholder first for a single value.
     *
     * @param array<string, mixed> $vars
     */
    private function select2(array $vars): string
    {
        if (\array_key_exists('search', (array) ($vars['endpoints'] ?? [])) && null === $vars['endpoints']['search']) {
            // Its choices are searched on the host (omnibase's ux_autocomplete): without that route, a select of
            // the chosen records alone, which a visitor could not change. Refused aloud.
            throw UnsupportedKind::of((string) $vars['kind'], 'omnibridge/laravel', 'its choices are searched remotely, and this application has no such route (omnibase\'s ux_autocomplete, ux_autocomplete_icons: omnibridge.media and omnibridge.forms.search on)');
        }
        $multiple = (bool) ($vars['multiple'] ?? false);
        $id = $vars['id'].'_choice';
        $attr = $vars['attr'];
        $class = trim('form-select2 form-select2 '.($attr['class'] ?? '').' form-control');
        unset($attr['class'], $attr['placeholder']);
        $attributes = ['id' => $id, 'name' => $vars['full_name'].'[choice]'.($multiple ? '[]' : ''), 'class' => $class] + $attr + [
            'data-select2-field' => $id, 'data-select2-options' => (string) ($vars['select2'] ?? '{}'),
            'data-select2-href' => urldecode((string) ($vars['select2-href'] ?? '')),
            'data-select2-sortable' => (bool) ($vars['select2-sortable'] ?? false),
            'data-select2-tabulation' => (string) ($vars['tabulation'] ?? ''), 'data-select2-highlight' => (bool) ($vars['highlight'] ?? false),
            'multiple' => $multiple, 'disabled' => (bool) ($vars['disabled'] ?? false), 'required' => (bool) $vars['required'],
        ];
        $html = '<select '.$this->attributes($attributes).'>';
        $placeholder = json_decode((string) ($vars['select2'] ?? ''), true)['placeholder'] ?? null;
        if (!$multiple && \is_string($placeholder)) {
            $html .= '<option value="">'.htmlspecialchars($placeholder).'</option>';
        }
        $chosen = array_map('strval', array_filter((array) ($vars['value'] ?? []), 'is_scalar'));
        foreach ($this->flat($vars['choices'] ?? []) as $value => $label) {
            $html .= '<option '.$this->attributes(['value' => (string) $value, 'selected' => \in_array((string) $value, $chosen, true)]).'>'.htmlspecialchars($this->translate($label)).'</option>';
        }
        // A remote search's records already chosen: printed with the words select2 shows them by, select2 asks the rest.
        $texts = [];
        foreach ((array) (json_decode((string) ($vars['select2'] ?? ''), true)['data'] ?? []) as $entry) {
            if (\is_array($entry) && \is_scalar($entry['id'] ?? null) && \is_scalar($entry['text'] ?? null) && '' !== trim((string) $entry['text'])) {
                $texts[(string) $entry['id']] = (string) $entry['text'];
            }
        }
        foreach (array_diff($chosen, array_map('strval', array_keys($this->flat($vars['choices'] ?? [])))) as $value) {
            $html .= '<option value="'.htmlspecialchars($value).'" selected="selected">'.htmlspecialchars($texts[$value] ?? $value).'</option>';
        }
        $html .= '</select>';
        if (isset($vars['tokenSeparators'])) {
            $html .= '<div class="form-filesize form-text text-muted">'.htmlspecialchars($this->translations->trans('@fields.select.token_separator', ["'".implode("' '", (array) $vars['tokenSeparators'])."'"])).'</div>';
        }

        return $html.'<div class="form-choice-thumbnail" id="'.htmlspecialchars($vars['id'].'_label').'"></div>';
    }

    /**
     * omnibase's password (password2_row, inline): typed in `plain`, again in `plain_repeater`, its
     * strength meter; nothing is shown of a password.
     *
     * @param array<string, mixed> $vars
     */
    private function password(array $vars): string
    {
        $id = (string) $vars['id'];
        $t = fn (string $key, array $parameters = []): string => $this->translations->trans($key, $parameters);
        $attributes = [
            'data-password-field' => $id,
            'data-password-strength' => $t('@fields.password.strength.label'),
        ];
        foreach (['bad', 'poor', 'weak', 'good', 'strong'] as $i => $level) {
            $attributes['data-password-strength-name['.$i.']'] = $t('@fields.password.strength.'.$level.'.label');
        }
        foreach (['bad', 'poor', 'weak', 'good', 'strong'] as $i => $level) {
            $attributes['data-password-strength-message['.$i.']'] = $t('@fields.password.strength.'.$level.'.message');
        }
        $attributes += [
            'data-password-autocomplete' => ($vars['autocomplete'] ?? false) ? 'on' : 'new-password',
            'data-password-match' => $t('@validators.password.not_matching'),
            'data-password-allow-empty' => !$vars['required'] ? 'true' : '',
            'data-password-minstrength' => (string) ($vars['min_strength'] ?? 0),
            'data-password-minstrength[feedback]' => $t('@validators.password.min_strength', [$vars['min_strength'] ?? 0]),
            'data-password-minlength' => (string) ($vars['min_length'] ?? 8),
            'data-password-minlength[feedback]' => $t('@validators.password.min_length', [$vars['min_length'] ?? 8]),
            'class' => trim((($vars['repeater'] ?? true) ? 'form-repeater ' : '').'form-password'),
        ];
        $input = fn (string $part): string => '<input '.$this->attributes(['type' => 'password', 'id' => $id.'_'.$part, 'name' => $vars['full_name'].'['.$part.']', 'required' => (bool) $vars['required'], 'class' => 'form-control', 'autocomplete' => ($vars['autocomplete'] ?? false) ? 'autocomplete' : 'new-password']).' />';
        $html = '<div '.$this->attributes($attributes).'><div class="form-group">'.$this->label($id.'_plain', $t('@fields.password.first'))
            .'<div class="input-group"><span class="input-group-text"><i class="fa-fw fa-solid '.(($vars['repeater'] ?? true) ? 'fa-unlock' : 'fa-lock').'"></i></span>'.$input('plain').'</div>'
            .'<div class="form-password-strength" id="'.htmlspecialchars($id).'_strength">';
        for ($i = 1; $i <= (int) ($vars['max_strength'] ?? 5); ++$i) {
            $html .= '<span class="strength" id="'.htmlspecialchars($id).'_strength_'.$i.'"></span>';
        }
        $html .= '</div>';
        if (!$vars['required'] && ($vars['hint'] ?? true)) {
            $html .= '<div class="form-text text-muted">'.htmlspecialchars($t('@fields.password.unchanged')).'</div>';
        }
        if ($vars['min_length'] ?? null) {
            $html .= '<div class="form-text text-muted">'.htmlspecialchars($t('@fields.password.requirements.min_length', [$vars['min_length']])).'</div>';
        }
        if (($vars['min_strength'] ?? 0) > 0) {
            $html .= '<div class="form-text text-muted">'.htmlspecialchars($t('@fields.password.requirements.min_strength', [$vars['min_strength']])).'</div>';
        }
        $html .= '</div>';
        if ($vars['repeater'] ?? true) {
            $html .= '<div class="form-group">'.$this->label($id.'_plain_repeater', $t('@fields.password.second'))
                .'<div class="input-group"><span class="input-group-text"><i class="fa-fw fa-solid fa-lock"></i></span>'.$input('plain_repeater').'</div></div>';
        }

        return $html.'</div>';
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
