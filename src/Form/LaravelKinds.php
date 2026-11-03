<?php

namespace Omnibridge\Laravel\Form;

/**
 * What this package's FormRenderer draws of omnisense/omnibase's field kinds
 * (Base\Field\Kind; plan "omnibase agnostic", step F5d), read by name by
 * Base\Field\Kinds for the host "laravel".
 *
 * MAP: kind => the widget FormRenderer draws it with (the block of omnibase's
 * Symfony theme whose markup it prints). COVERAGE: every kind, implemented or
 * unsupported with the reason - FormRenderer refuses an unsupported or
 * undeclared kind aloud (Omnibridge\Form\UnsupportedKind) instead of a plain
 * input. omnibase's field conformance suite runs every implemented one
 * (tests/FieldConformanceTest).
 */
final class LaravelKinds
{
    public const MAP = [
        'text' => 'input', 'textarea' => 'textarea', 'email' => 'input', 'password' => 'password2', 'url' => 'input',
        'tel' => 'input', 'search' => 'input', 'slug' => 'slug', 'hidden' => 'input',
        'integer' => 'input', 'number' => 'number2', 'year' => 'input',
        'boolean' => 'boolean', 'checkbox' => 'checkbox', 'choice' => 'select2', 'country' => 'select2',
        'currency' => 'select2', 'locale' => 'select', 'gender' => 'select2', 'role' => 'select2', 'state' => 'select2',
        'icon' => 'select2',
        'color' => 'jscolor', 'color_picker' => 'colorpickr',
        'date' => 'input', 'datetime' => 'input', 'datetime_picker' => 'datetimepicker', 'time' => 'input',
        'file' => 'fileupload', 'image' => 'imageupload', 'avatar' => 'avatar', 'audio' => 'fileupload', 'video' => 'fileupload',
        'association' => 'association', 'association_file' => 'associationfile', 'attribute' => 'attribute', 'array' => 'array',
        'collection' => 'collection2', 'editor' => 'editor', 'wysiwyg' => 'wysiwyg',
        'repeated' => 'repeated', 'translation' => 'translatable', 'submit' => 'button', 'button' => 'button', 'html' => 'html',
    ];

    /** Why the kinds left unsupported are (plan "omnibase agnostic", step F5d, closed): no form drawn here holds one. */
    private const UNUSED = ' - no field of lesringostars\' back office nor of its forms holds one (plan "omnibase agnostic", step F5d); a bundle whose forms do (omnibase/estate\'s amounts) is drawn here at its own step';

    public const COVERAGE = [
        'text' => 'implemented', 'textarea' => 'implemented', 'email' => 'implemented', 'password' => 'implemented',
        'url' => 'implemented', 'tel' => 'implemented', 'search' => 'implemented', 'slug' => 'implemented',
        'code' => 'unsupported: CodeType\'s one box per character (forms/form-type-code.js) is not drawn here'.self::UNUSED,
        // Editor.js (omnibase's Editor behaviour), its routes omnibase's ux_editorjs_*, which this package declares with
        // media on (EditorController); Quill (Wysiwyg), no route.
        'editor' => 'implemented',
        'wysiwyg' => 'implemented',
        'hidden' => 'implemented',
        'integer' => 'implemented', 'number' => 'implemented',
        'money' => 'unsupported: MoneyType\'s amount (kept divided, its currency\'s symbol beside it) is not drawn here'.self::UNUSED,
        'forex' => 'unsupported: ForexType\'s amount converted at omnibase\'s rates is not drawn here'.self::UNUSED,
        'stock' => 'unsupported: StockType\'s number with its unlimited switch is not drawn here'.self::UNUSED,
        'year' => 'implemented',
        'boolean' => 'implemented', 'checkbox' => 'implemented', 'choice' => 'implemented', 'country' => 'implemented',
        'currency' => 'implemented', 'locale' => 'implemented', 'gender' => 'implemented', 'role' => 'implemented',
        // A choice of records searched remotely (an entity's picker) asks omnibase's ux_autocomplete, which this package declares
        // with media on (SearchController); refused when drawn where the application has no such route.
        'state' => 'implemented',
        'route' => 'unsupported: RouteType lists the Symfony router\'s routes; the back office\'s settings (its one use) are Symfony\'s page',
        // Searched remotely: omnibase's ux_autocomplete_icons, which this package declares with media on (SearchController).
        'icon' => 'implemented',
        'emoji' => 'unsupported: the emoji picker (EmojiPickerType, its script) is not drawn here'.self::UNUSED,
        'color' => 'implemented', 'color_picker' => 'implemented',
        'quadrant' => 'unsupported: QuadrantType\'s nine positions are not drawn here'.self::UNUSED,
        'date' => 'implemented', 'datetime' => 'implemented', 'datetime_picker' => 'implemented', 'time' => 'implemented',
        // A file sent with its form, the stored one shown (omnibase's Upload behaviour); a dropzone's files sent as they are
        // dropped to omnibase's ux_dropzone, which this package declares with media on (UploadController).
        'file' => 'implemented', 'image' => 'implemented',
        'cropper' => 'unsupported: the cropper (CropperType) cuts an image\'s crops in the browser as records of their own (ImageCrop); not drawn here'.self::UNUSED,
        'avatar' => 'implemented', 'audio' => 'implemented', 'video' => 'implemented',
        // A related record's form embedded (omnibase's Association): one record's fields, or several records' forms in a
        // collection with its prototype; bound into the records by omnibase's binder.
        'association' => 'implemented',
        // Records made from uploads (omnibase's AssociationFile): its upload field drawn as above, the files sent made into records.
        'association_file' => 'implemented',
        // The adapters searched remotely (ux_autocomplete), each attribute's value by its adapter's field (omnibase's
        // Attribute); a translated attribute's value (an `intl` part) refused aloud.
        'attribute' => 'implemented',
        // A list of entries with its prototype (omnibase's Collection): ArrayType's texts, CollectionType's entries.
        'array' => 'implemented',
        'collection' => 'implemented',
        'repeated' => 'implemented',
        // One tab per language, the translation entity's fields (omnibase's Translation, RecordFields); a field of a kind
        // not drawn here refuses the whole field.
        'translation' => 'implemented',
        'discriminator' => 'unsupported: the inheritance\'s choice (DiscriminatorType) is not drawn here; the back office shows it on its pages, never on its forms (hideOnForm)',
        'submit' => 'implemented', 'button' => 'implemented', 'html' => 'implemented',
        'custom' => 'unsupported: a Symfony form type named directly (FieldDescriptor::setFormType()) has no Laravel widget; name a kind',
    ];

    private function __construct()
    {
    }
}
