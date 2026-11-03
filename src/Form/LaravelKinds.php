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
        'repeated' => 'repeated', 'translation' => 'translatable', 'submit' => 'button', 'button' => 'button', 'html' => 'html',
    ];

    private const NOT_YET = 'not drawn by omnibridge/laravel yet (plan "omnibase agnostic", step F5d, a later slice): ';

    public const COVERAGE = [
        'text' => 'implemented', 'textarea' => 'implemented', 'email' => 'implemented', 'password' => 'implemented',
        'url' => 'implemented', 'tel' => 'implemented', 'search' => 'implemented', 'slug' => 'implemented',
        'code' => 'unsupported: '.self::NOT_YET.'the one-box-per-character code (CodeType, forms/form-type-code.js)',
        'editor' => 'unsupported: '.self::NOT_YET.'Editor.js and its collaboration relay (EditorType)',
        'wysiwyg' => 'unsupported: '.self::NOT_YET.'Quill (WysiwygType) and its uploads',
        'hidden' => 'implemented',
        'integer' => 'implemented', 'number' => 'implemented',
        'money' => 'unsupported: '.self::NOT_YET.'MoneyType\'s currency and its divisor',
        'forex' => 'unsupported: '.self::NOT_YET.'ForexType\'s rates',
        'stock' => 'unsupported: '.self::NOT_YET.'StockType\'s unlimited switch',
        'year' => 'implemented',
        'boolean' => 'implemented', 'checkbox' => 'implemented', 'choice' => 'implemented', 'country' => 'implemented',
        'currency' => 'implemented', 'locale' => 'implemented', 'gender' => 'implemented', 'role' => 'implemented',
        // A choice of records searched remotely (an entity's picker) asks omnibase's ux_autocomplete, which this package declares
        // with media on (SearchController); refused when drawn where the application has no such route.
        'state' => 'implemented',
        'route' => 'unsupported: '.self::NOT_YET.'RouteType lists the Symfony router\'s routes',
        // Searched remotely: omnibase's ux_autocomplete_icons, which this package declares with media on (SearchController).
        'icon' => 'implemented',
        'emoji' => 'unsupported: '.self::NOT_YET.'the emoji picker (EmojiPickerType)',
        'color' => 'implemented', 'color_picker' => 'implemented',
        'quadrant' => 'unsupported: '.self::NOT_YET.'QuadrantType\'s nine positions',
        'date' => 'implemented', 'datetime' => 'implemented', 'datetime_picker' => 'implemented', 'time' => 'implemented',
        'file' => 'unsupported: '.self::NOT_YET.'the upload pipeline (FileType: dropzone, the stored file\'s preview, deletion)',
        'image' => 'unsupported: '.self::NOT_YET.'the upload pipeline (ImageType)',
        'cropper' => 'unsupported: '.self::NOT_YET.'the cropper (CropperType) on the upload pipeline',
        'avatar' => 'unsupported: '.self::NOT_YET.'the upload pipeline (AvatarType)',
        'audio' => 'unsupported: '.self::NOT_YET.'the upload pipeline (AudioType)',
        'video' => 'unsupported: '.self::NOT_YET.'the upload pipeline (VideoType)',
        'association' => 'unsupported: '.self::NOT_YET.'a related record\'s form embedded (AssociationType)',
        'association_file' => 'unsupported: '.self::NOT_YET.'records made from uploads (AssociationFileType)',
        'attribute' => 'unsupported: '.self::NOT_YET.'the attributes\' adapters (AttributeType)',
        'array' => 'unsupported: '.self::NOT_YET.'a list of entries with its prototype (ArrayType)',
        'collection' => 'unsupported: '.self::NOT_YET.'a collection of sub-forms with its prototype (CollectionType)',
        'repeated' => 'implemented',
        // One tab per language, the translation entity's fields (omnibase's Translation, RecordFields); a field of a kind
        // not drawn here refuses the whole field.
        'translation' => 'implemented',
        'discriminator' => 'unsupported: '.self::NOT_YET.'the inheritance\'s choice (DiscriminatorType)',
        'submit' => 'implemented', 'button' => 'implemented', 'html' => 'implemented',
        'custom' => 'unsupported: a Symfony form type named directly (FieldDescriptor::setFormType()) has no Laravel widget; name a kind',
    ];

    private function __construct()
    {
    }
}
