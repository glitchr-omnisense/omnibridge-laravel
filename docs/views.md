---
title: Pages
order: 32
---

# Pages

omnibase stays on Twig for its templates: this package opens Twig over the site's templates
(omnibridge/twig) and gives a page what omnibase's pages ask of Symfony.

## The Twig environment

`Twig\Environment` is bound when it is first asked, by `View\Twig::create()`:

```php
// config/omnibridge.php
'twig' => [
    'paths' => [
        [resource_path('views'), null],                                   // the main namespace
        [base_path('vendor/omnisense/omnibase/templates'), 'Base'],       // @Base
    ],
    'cache' => null,        // storage/framework/twig when null
    'timezone' => null,     // app.timezone when null
],
```

| Added | What it gives |
|---|---|
| a `FilesystemLoader` | each `[folder, namespace]` pair, null for the main namespace |
| options | `auto_reload` true, `strict_variables` false, `cache` as configured |
| omnibridge/twig's `ContractExtension` | `path()`, `url()`, `asset()`, `\|trans` on the bound `RouterUrls` and `CatalogueTranslations` |
| omnibridge/twig's `AccessExtension` | `is_granted()` and `csrf_token()` on the container's `Access` and `Tokens`, each when bound |
| omnibridge/twig's `HelperExtension` | the helpers, in the application's locale and `omnibridge.twig.timezone` (else `app.timezone`) |
| omnibridge/twig's `MapExtension` | when `config/omnimap.php` names a provider ([Maps](families.md#maps-omnimap)) |
| `View\OmnibaseExtensions::extend()` | after the environment is resolved: omnibase's own extensions, below |

`View\Twig::create(array $paths, Urls $urls, Translations $translations, string $locale, ?string $timezone = null, array $extensions = [], array $options = [])`
builds one for an application that wants an environment of its own.

## omnibase's own extensions: View\OmnibaseExtensions

The classes the core registers under Symfony, built on what Laravel binds - the same functions and
filters from the same code - each added once, when the core has its framework-free extensions
(omnisense/omnibase 4 from audit R24) and what it stands on is bound:

| Extension | What it gives | Stands on |
|---|---|---|
| `View\HostExtension` | always: `encore_entry_link_tags()`, `encore_entry_script_tags()`, `encore_entry_css_source()` (with `omnibridge.encore.builds`), `html_entry_noscript_tags()` (nothing), `stimulus_controller()`, `absolute_url()`, `\|url` | Urls, the request |
| `MenuTwigExtension` | `render_locale()`, `render_theme_*()` | Locales, the visitor's theme (`omnibridge.theme.modes`) |
| `EnumTwigExtension` | `enum()`, `\|colorify`, `\|trans_enum` | the page's Translations (the entity manager built first, for the DBAL types) |
| `ClassTwigExtension` | `get_class()`, `class_exists()`, `get_short_class()` | - |
| `View\OmnibaseFilters` | `\|trans_entity`, `\|trans_entityExists`, `\|obfuscate` (with the media) | Translations (`EntityNouns`), the Obfuscator |
| `LinkTwigExtension` | `\|urlify`, `\|linkify` | - |
| `IconTwigExtension` | `\|iconify` | omnibase's Font Awesome and Bootstrap adapters over `omnibridge.icons.metadata` (else `public/bundles/base/metadata`), cached in `storage/framework/cache/omnibase-icons`; a route's icons by the Routes contract |
| `PictureTwigExtension` | `image()`, `\|thumbnail`, `\|soundify`, `\|mimetype`... | `MediaService` (`omnibridge.media` on) |
| `TextsTwigExtension` | `\|wysiwyg`, `\|wysiwyg_toc` | the Texts contract, on this environment |
| `NotificationTwigExtension` | the bell | Access, Tokens, a bound Notifications repository; push with `omnibridge.push`'s keys |
| `SecurityPolicyTwigExtension` | `security_policy` | a bound `Base\Service\SecurityPolicy` |
| `DeployVersionExtension` | `deploy_version()` | `omnibridge.deploy.project_dir`, else `base_path()` |
| `DemoTwigExtension` | `demo_mode()`, `demo_accounts()` | the application's environment |
| omnibase/consent's `ConsentExtension` | `consent_options()`, `consent_settings()` | omnibase/consent installed, `omnibridge.consent.config` (a YAML file with a `consent:` key, or the array) |
| `AccountTwigExtension` | omnibase's account screens | the policy when bound, Urls, Tokens, Access |

`extend(Environment $twig, ?Translations $translations = null, ?ThemizerInterface $theme = null)` adds
them to another environment (the page's own language, its theme); `admin()` is omnibase's `AdminLinks`
on the Routes and Urls contracts (`app.admin`).

## What the templates take from Symfony's bundles: View\HostExtension

- `encore_entry_link_tags(entry)`, `encore_entry_script_tags(entry)` (`View\Encore`, as omnibase's
  WebpackTagRenderer prints them): an entry is its files and those of its `-async` and `-defer` halves,
  from the first build of `omnibridge.encore.builds` that has any; a stylesheet comes with its
  breakpoint versions beside it (`layout2-async-mobile.css`, media from `omnibridge.encore.breakpoints`);
  every script deferred; a file printed once per page; `omnibridge.encore.optional` names the entries a
  page loads with another (`form` => `form.editor`). `encore_entry_css_source(entry)` the stylesheets'
  content (an e-mail's inlined style). An entry may be given as omnibase's `{value: name}`.
- `stimulus_controller(name)`: `data-controller="name"` as StimulusBundle normalises it (`a/b` `a--b`,
  `a_b` `a-b`, no leading `@`).
- `absolute_url(path)`: a path whole at the request's origin; `|url`: an address whole, a route's name
  by the Urls contract.

## The page around the template

- `View\Breakpoints::style($breakpoints)` and `inject($html, $breakpoints)`: the style omnibase puts
  before the first stylesheet of the head, whose `body:before` names the breakpoint in each media query.
- `View\Companions::inject(Environment $twig, string $template, array $context, string $html, array $breakpoints = [])`:
  beside `client/timeline.html.twig`, `client/timeline.css.twig` is the page's style (before `</head>`),
  `client/timeline.mobile.css.twig` its style for a breakpoint (`<style media=...>`),
  `client/timeline.js.twig` its script (after `</body>`), each rendered in the page's context, as
  omnibase's HtmlTagRenderer adds them.

## The app variable: Page\*

`Page\AppVariable::of(Request $request, string $locale, Site $site, Theme $theme, ?object $user = null, ?object $admin = null)`:
`app` with Symfony's words - `app.request` (`Page\RequestView`: `getLocale()`, `getUri()` as Symfony
writes it, `getPathInfo()`, `headers.get()`, `attributes.get('_route')`, `_route_params` and the route's
parameters), `app.user`, `app.session` (`Page\SessionView`: `started`, `flashbag.all()`, the messages
flashed under `omnibridge.flashes` by `SessionView::add($session, $label, $message)`), `app.site`
(`Page\Site`: `title()`, `slogan()`, `logo()`, `meta()`, `age()`, `execution_time()` from the settings),
`app.theme` (`Page\Theme`: the mode and filter of the `THEME/MODE` and `THEME/FILTER` cookies among
`omnibridge.theme`, omnibase's `ThemizerInterface` too, `getLayout(N)` from `omnibridge.theme.layouts`'
`layoutN.html.twig`), `app.referrer` (`Page\Referrer`, this site's page the visitor came from) and
`app.admin`. The application builds it and hands it to its templates.

## Forms: Form\*

A few simple forms in the markup of omnibase's form theme, for the forms every page carries
(omnibase's FormProxy: the search bar, the newsletter): `Form\FormView` (Symfony's `FormView` words:
`vars`, `children`, `add()`, read-only array access), `Form\FormRenderer` (`start()`, `widget()` -
`html`, `checkbox`, `choice`, inputs -, `row()`, `fieldLabel()`, `errors()` (nothing), `rest()`,
`end()`, labels and placeholders translated), `Form\FormExtension` (`form_start()`, `form_widget()`,
`form_row()`, `form_label()`, `form_errors()`, `form_rest()`, `form_end()`, `form_proxy()` /
`form_view()` by name). What answers a submission is the application's.

## The screens' context and texts

- `Screen\ScreenContext` (`Base\Screen\ContextProvider`, not final): the locale as a Symfony request
  carries it (`fr_FR`); the host (scheme, host, base path); the site from the settings (title, slogan,
  logo by its asset's address, the year, the description) when a connection is configured; the links -
  home (`app_index`, else `/`), `security_login`, `security_register`, `security_logout`, each
  language's `_switch_locale` - those that resolve; the viewer (the Access contract's user: its id and
  name); the theme's colour scheme; the session's flashes, taken. An application binds its own to add its
  frame fields.
- `Screen\AddressPictures` (`Base\Contract\Pictures`): `|picture`'s rule - the application's `stored()`
  closure first; a file under the public folder by its thumbnail (MediaService) or its public path; an
  address or a root path as it is; anything else `asset('assets/'.$image)`.
- `Screen\WysiwygTexts` (`Base\Contract\Texts`): `|wysiwyg` and `|wysiwyg_toc` for an Editor.js
  document (the mentions left as written, every leaf a string, the links' pass, the pictures by
  `images()`, the headings' anchors, omnibase's `EditorJsRenderer` and `@Base/form/wysiwyg/editor_js.html.twig`)
  or Quill's HTML (`@Base/form/wysiwyg/quill.html.twig`); `on($twig)` renders through another
  environment. The Twig environment must hold omnibase's views under `@Base`.

```php
$this->app->extend(Base\Contract\Pictures::class, fn ($pictures) => $pictures->stored(fn (string $image) => /* its address, or null */));
$this->app->extend(Base\Contract\Texts::class, fn ($texts) => $texts->images(fn (string $source) => /* its address, or null */));
```
