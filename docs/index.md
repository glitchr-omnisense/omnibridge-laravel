# omnibridge/laravel

```sh
composer config allow-plugins.omnibridge/composer true
composer require omnisense/omnibase:4.x-dev omnibridge/laravel:1.x-dev
php artisan vendor:publish --tag=omnibridge-config
```

That hosts [omnisense/omnibase](https://github.com/glitchr-omnisense/omnibase) in a
Laravel application: the provider is discovered by Laravel, binds each contract of
omnibase's Foundation to Laravel's services, and hands Laravel's container to the
Foundation's registry, `Base\Contract\Runtime` (omnisense/omnibridge's
`Omnibridge\Runtime` with omnibase's accessors). The host's contracts are
[omnisense/omnibridge](https://github.com/glitchr-omnisense/omnibridge)'s
(`Omnibridge\Contract\*`), bound under their own names (their former names, `Base\Contract\Urls`... and
omnibridge/twig's, were removed at omnibase 4's final pass). omnibase's framework-free code -
an entity's link, its noun, a translatable's language, a screen's builder - then
runs under Laravel as it runs under Symfony:

```php
use Base\Contract\Runtime;

Runtime::urls()->path('article', ['slug' => 'le-fuji']);   // Laravel's router
Runtime::translations()->trans('@messages.menu.home');      // omnibase's ICU catalogues
Runtime::parameters()->get('base.user.delay');              // Laravel's config, the database settings over it
app(Omnibridge\Contract\Urls::class) === Runtime::urls();   // one object
```

omnibase asks for PHP 8.4 and the extensions its own packages need (bcmath, intl,
gd, exif, ftp, imagick, igbinary). omnibase stays on Doctrine for storage and Twig
for its templates: this package opens Doctrine on one of Laravel's connections
(omnibridge/doctrine) and Twig over the site's templates (omnibridge/twig).

## What is bound

[Contracts](contracts.md) says how each adapter answers, [Configuration](configuration.md) every key that shapes them.

| Contract | Answered by | Class |
|---|---|---|
| `Omnibridge\Contract\Urls` | Laravel's router (`route()`), the build's asset manifest; a route Laravel does not have may be answered elsewhere | `RouterUrls` |
| `Omnibridge\Contract\Translations` | the ICU catalogues of the folders configured, read by symfony/translation, in Laravel's current locale; `exists()` | `CatalogueTranslations` |
| `Omnibridge\Contract\Locales` | Laravel's locale (`fr`) as the site's locale (`fr-FR`), the default first | `Contract\AppLocales` |
| `Base\Contract\Nouns` | omnibase's own rule (`EntityNouns`) on those catalogues | - |
| `Omnibridge\Contract\Access` | the gate (`Gate::allows()`), the default guard's user | `Contract\GateAccess` |
| `Omnibridge\Contract\Sessions` | the guard's `logout()` for the user signed in | `Contract\GuardSessions` |
| `Omnibridge\Contract\Tokens` | the session's CSRF token (one for every intent) | `Contract\SessionTokens` |
| `Omnibridge\Contract\Environment` | `APP_ENV`, `app.debug`, `app.key` | `Contract\AppEnvironment` |
| `Omnibridge\Contract\Routes` | a route's declared path, the route of an address; no icons | `Contract\RouterRoutes` |
| `Omnibridge\Contract\Files` | the disks (omnibase's storage names mapped to disks) | `Contract\DiskFiles` |
| `Base\Service\ParameterBagInterface` | Laravel's config, the settings linked to a parameter laid over it (omnibase's `HotParameters`) | `Parameters\ConfigParameters`, `Parameters\DatabaseSettings` |
| `Psr\Clock\ClockInterface` | Laravel's dates (`Date::setTestNow()`, `travel()` move it) | `Contract\DateClock` |
| `Psr\EventDispatcher\EventDispatcherInterface` | Laravel's dispatcher | `Contract\DispatcherEvents` |
| `Symfony\Component\Mailer\MailerInterface` | the default mailer's Symfony transport | `Contract\TransportMailer` |
| `Doctrine\DBAL\Connection`, `Doctrine\ORM\EntityManagerInterface` | the Laravel connection configured, omnibridge/doctrine's entity manager | `Doctrine\Connections` |
| `Twig\Environment` | the site's templates and namespaces, omnibridge/twig's contract (`path()`, `url()`, `asset()`, `\|trans`), its `AccessExtension` (`is_granted()` on the gate, `csrf_token()` the session's token: the code a Symfony page asks too, omnibridge/symfony registering it there) and helpers | `View\Twig` |
| `Base\Contract\Pictures` | `\|picture`'s rule: a public file by its thumbnail at the size asked (MediaService's, `omnibridge.media` on) or its path, an address as it is, else the build's asset; a stored picture where the application serves it (`stored()`) | `Screen\AddressPictures` |
| `Base\Contract\Texts` | `\|wysiwyg` and `\|wysiwyg_toc`'s passes (headings, links, every leaf a string, Editor.js's renderer or Quill's HTML) through omnibase's own views (`@Base` among the Twig paths, or `on($twig)`); a picture where the application serves it (`images()`) | `Screen\WysiwygTexts` |
| `Base\Screen\ContextProvider` | a screen's frame: the locale as a Symfony request carries it (`fr_FR`), the site's settings, home, login, register, logout and each language's address, the guard's user, the colour scheme of the visitor's mode (`omnibridge.theme`), the session's messages | `Screen\ScreenContext` |

With `omnibridge.media` on (see below): `Base\Contract\Media` (`Media\ServiceMedia`,
MediaService's answers), `Base\Service\MediaService`, `Base\Service\ImageRoute`, and
omnibase's image routes. Off (the default), none of them.

### Screens

With those three, a screen of the core is built on Laravel as under
Symfony, without code of the application's:

```php
use Base\Contract\Runtime;
use Base\Screen\Builder\Stories;

$frame = app(Base\Screen\ContextProvider::class)->frame(['destination' => $destination]);
$text  = (new Stories(Runtime::texts(), Runtime::pictures()))->text($thread->getContent(), ['headings' => true]);
```

An application says where it serves its stored pictures, in a provider:

```php
$this->app->extend(Base\Contract\Pictures::class, fn ($pictures) => $pictures->stored(fn (string $image) => /* its address, or null */));
$this->app->extend(Base\Contract\Texts::class, fn ($texts) => $texts->images(fn (string $source) => /* its address, or null */));
```

### Actions

An action written once for every host (no Request, no Response: its inputs,
and a result of omnibase's - `Base\Screen\Template`, `Json`, `Shell`, `File`, a `View`, or
null for no such page) is a route here, answered by omnibase's `Base\Screen\Negotiation`
(HTML, JSON when the template allows it and the client prefers it, omnibridge/react's
protocol and its 409) through `Screen\Responder`, as `Base\Bridge\Symfony\ScreenResponder`
answers it under Symfony:

```php
use Omnibridge\Laravel\Screen\Responder;

Route::get('/{destination}/articles/{slug}', Responder::action(App\Action\Blog\Article::class))->name('app_article');
```

The action's parameters are the route's by name, `$query` the query string's values, `$body`
what a POST sent (its JSON object, else its form fields), the rest the container's. An action
that changes something checks its form's token itself, through the `Tokens` contract, as under
Symfony: Laravel's CSRF middleware is left off the routes of the actions that are not GET. The templates are rendered by the container's Twig; an application
whose pages carry more (a page context, what it adds once rendered) binds its own
`Base\Screen\Negotiation`, rendering `Negotiation::PAGE` (a page), `PART` (a value of a
JSON answer) and `ALONE` (a page whole, its own markup only) each its way.

### Routes and bindings from the attributes

The routes an application's actions declare -
symfony/routing's `#[Route]` on the action, the declaration Symfony's router reads on the same
class - are registered here by `Routing\ActionRoutes`, by symfony/routing's attribute loader's
rules (not the loader itself, which stands on symfony/config): the same names, localized paths (`app_timeline.fr` and its `_locale`, which
`Http\Localize` takes for the page's language), requirements, optional parameters, priorities
(registered in the order Symfony matches them) and environments (Laravel's `local`, `testing`,
`production` are Symfony's `dev`, `test`, `prod`). A route naming no method answers GET and
HEAD. `RouterUrls::localized()` reads a name as omnibase's router does (`app_timeline` is
`app_timeline.fr` on a French page, a version without a destination `name.default`, a language
the routes lack the fallback's). The services an application aliases with Symfony's
`#[AsAlias]` are bound by `Container\Aliases` (read by the attribute's name: no
symfony/dependency-injection needed), the core's repositories by their interfaces from
omnibase's own list (`Base\Repository\Aliases`):

```php
use Omnibridge\Laravel\Routing\ActionRoutes;

// routes/web.php: the application's actions and omnibase's.
ActionRoutes::register(app('router'), ActionRoutes::load([
    'App\\Action\\' => base_path('src/Action'),
    'Base\\Controller\\Action\\' => base_path('vendor/omnisense/omnibase/src/Controller/Action'),
], ActionRoutes::environment(app()->environment())));

// a service provider: Aliases::bind($this->app, Aliases::read(['App\\Repository\\' => base_path('src/Repository')]));
```

### What omnibase's pages ask more

`app.theme.layout(1)` (`omnibridge.theme.layouts`: the folder
of the site's `layoutN.html.twig`, as omnibase's Themizer finds them); `app.request.attributes` as
Symfony's (the route's name and parameters) and its `uri` as Symfony writes it; a template's
companions (`page.css.twig`, `page.mobile.css.twig`, `page.js.twig`: `View\Companions`, as omnibase's
HtmlTagRenderer adds them); `get_short_class()` (omnibase's ClassTwigExtension), `|trans_entity` and
`|obfuscate` (`View\OmnibaseFilters`); a key no catalogue holds translated as nothing, as omnibase's
Translator answers it; an action's `Base\Screen\Redirect`.

### Language

omnibase's language under Laravel, as its LocalizerSubscriber and
LocalizerController give it under Symfony: `Http\Localize` (the web group's middleware: the
LANG cookie, else the browser's language among `omnibridge.locales.available`, else
`omnibridge.locales.default`; a route of one language is that language's) and `Http\SwitchLocale` (`Route::get('/locale/{_locale?}',
SwitchLocale::class)->name('_switch_locale')`: the choice kept as Symfony writes it, en_GB, and
back to the page it came from on the site). Leave LANG out of the encrypted cookies.

### Parameters

`Runtime::parameters()` reads Laravel's config by the same dotted
path (`app.name`; `base.user.delay` for a site with `config/base.php`), and, when
`omnibridge.parameters.hot` is on (or the config's own
`base.parameter_bag.use_hot_bag`), the settings the back office links to a
parameter, read once in omnibase's tables (`layoutSetting`, `layoutSettingIntl`) on
the first call: a setting stored as `false` wins over the config's `true`, as under
Symfony. Laravel's `config` binding itself is not decorated: Laravel reads and
caches its configuration before any provider, everywhere, as an array; settings
read from a database have no place there.

## Also here

The pieces an omnibase page asks of its framework, for an application that renders
omnibase's templates with Laravel: `View\Encore` (`encore_entry_link_tags()` and
`encore_entry_script_tags()` as omnibase prints them, with the breakpoints'
stylesheets), `View\Breakpoints` (the style omnibase puts before the first
stylesheet), `Page\AppVariable` and its parts (the `app` variable with Symfony's
words: `app.request.getLocale()`, `app.request.attributes.get('_route')`,
`app.site`, `app.theme`, `app.session`; `Page\Theme` is omnibase's `ThemizerInterface` too,
which its theme menus read: `Base\Twig\Extension\MenuTwigExtension`), `Form\FormView` and `Form\FormRenderer`
(a few simple forms in the markup of omnibase's form theme), and
`RouterUrlGenerator` (Symfony's `UrlGeneratorInterface` over the Urls contract, for
a library that asks for one).

omnibase's own Twig extensions in every page (`View\OmnibaseExtensions`, with
omnisense/omnibase 4 from its framework-free extensions): the container's
`Twig\Environment` gets the classes the core registers under Symfony - the menus,
`EnumTwigExtension` (`enum()`, `|colorify`, `|trans_enum`), `PictureTwigExtension`
(`image()`, `|thumbnail`, `|soundify`, `|mimetype`... with `omnibridge.media` on),
`LinkTwigExtension` (`|urlify`), `IconTwigExtension` (`|iconify`, omnibase's
adapters and the Routes contract's icons), `TextsTwigExtension` (`|wysiwyg`),
`NotificationTwigExtension` (the bell; push on `omnibridge.push`),
`SecurityPolicyTwigExtension`, `DeployVersionExtension`, `DemoTwigExtension`, and
omnibase/consent's panel on `omnibridge.consent.config` - plus `View\HostExtension`,
what the templates take from Symfony's bundles (Encore's tags on
`omnibridge.encore`, `stimulus_controller()`, `absolute_url()`, `|url`). An
application rendering a page in an environment of its own calls
`OmnibaseExtensions::extend($twig, $translations, $theme)`; `admin()` is `app.admin`
(`Base\Twig\Variable\AdminLinks`); `Form\FormExtension` gives the `form_*()`
functions over a page's `FormView`s. `Contract\RouterRoutes::elsewhere()` answers
the routes Laravel does not have (their pattern, a route's icons) by another
Routes, as `RouterUrls::elsewhere()` their addresses.

## How this manual is organised

- [Installation](installation.md): requirements, the provider, the configuration published, a run.
- [Contracts](contracts.md): every binding of `OmnibridgeServiceProvider`, each adapter's answers.
- [Routing and actions](routing.md): `Routing\ActionRoutes`, `Screen\Responder`, `Http\Localize`,
  `Http\SwitchLocale`, `Container\Aliases`, `Routing\LocalizedNames`.
- [Doctrine and images](doctrine-media.md): the connection, omnibase's mapping, the repositories,
  omnibase's images and their routes.
- [Pages](views.md): Twig, omnibase's extensions, `HostExtension`, Encore, the companions, the `app`
  variable, the forms, the screens' context.
- [Signing in](omniauth.md): `Auth\` and `config/omniauth.php` key by key.
- [Maps, shields, static export](families.md): `config/omnimap.php`, `config/omnishield.php`,
  `omnibridge:static:build`.
- [Configuration](configuration.md): `config/omnibridge.php` key by key.
- [Errors](errors.md), [Testing](testing.md), [Verified, and not](verified.md).

## Licence

MIT.
