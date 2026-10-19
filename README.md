# omnibridge/laravel

```sh
composer config allow-plugins.omnibridge/composer true
composer require omniscient/omnibase:4.x-dev omnibridge/laravel:1.x-dev
php artisan vendor:publish --tag=omnibridge-config
```

That hosts [omniscient/omnibase](https://github.com/glitchr-studio/omnibase) in a
Laravel application: the provider is discovered by Laravel, binds each contract of
omnibase's Fondation to Laravel's services, and hands Laravel's container to the
Fondation's registry, `Base\Contract\Runtime` (omniscient/omnibridge's
`Omnibridge\Runtime` with omnibase's accessors). The host's contracts are
[omniscient/omnibridge](https://github.com/glitchr-studio/omnibridge)'s
(`Omnibridge\Contract\*`), bound under their own names; their former names
(`Base\Contract\Urls`..., omnibridge/twig's) are Laravel aliases of them until
omnibase 4's final pass, the same binding under either. omnibase's framework-free code -
an entity's link, its noun, a translatable's language, a screen's builder - then
runs under Laravel as it runs under Symfony:

```php
use Base\Contract\Runtime;

Runtime::urls()->path('article', ['slug' => 'le-fuji']);   // Laravel's router
Runtime::translations()->trans('@messages.menu.home');      // omnibase's ICU catalogues
Runtime::parameters()->get('base.user.delay');              // Laravel's config, the database settings over it
app(Omnibridge\Contract\Urls::class) === Runtime::urls();   // one object, also app(Base\Contract\Urls::class)
```

omnibase asks for PHP 8.4 and the extensions its own packages need (bcmath, intl,
gd, exif, ftp, imagick, igbinary). omnibase stays on Doctrine for storage and Twig
for its templates: this package opens Doctrine on one of Laravel's connections
(omnibridge/doctrine) and Twig over the site's templates (omnibridge/twig).

## What is bound

| Contract | Answered by | Class |
|---|---|---|
| `Omnibridge\Contract\Urls` (formerly `Base\Contract\Urls` and omnibridge/twig's) | Laravel's router (`route()`), the build's asset manifest; a route Laravel does not have may be answered elsewhere | `RouterUrls` |
| `Omnibridge\Contract\Translations` (formerly `Base\Contract\Translations` and omnibridge/twig's) | the ICU catalogues of the folders configured, read by symfony/translation, in Laravel's current locale; `exists()` | `CatalogueTranslations` |
| `Omnibridge\Contract\Locales` (formerly `Base\Contract\Locales`) | Laravel's locale (`fr`) as the site's locale (`fr-FR`), the default first | `Contract\AppLocales` |
| `Base\Contract\Nouns` | omnibase's own rule (`EntityNouns`) on those catalogues | - |
| `Omnibridge\Contract\Access` (formerly `Base\Contract\Access`) | the gate (`Gate::allows()`), the default guard's user | `Contract\GateAccess` |
| `Omnibridge\Contract\Sessions` (formerly `Base\Contract\Sessions`) | the guard's `logout()` for the user signed in | `Contract\GuardSessions` |
| `Omnibridge\Contract\Tokens` (formerly `Base\Contract\Tokens`) | the session's CSRF token (one for every intent) | `Contract\SessionTokens` |
| `Omnibridge\Contract\Environment` (formerly `Base\Contract\Environment`) | `APP_ENV`, `app.debug`, `app.key` | `Contract\AppEnvironment` |
| `Omnibridge\Contract\Routes` (formerly `Base\Contract\Routes`) | a route's declared path, the route of an address; no icons | `Contract\RouterRoutes` |
| `Omnibridge\Contract\Files` (formerly `Base\Contract\Files`) | the disks (omnibase's storage names mapped to disks) | `Contract\DiskFiles` |
| `Base\Service\ParameterBagInterface` | Laravel's config, the settings linked to a parameter laid over it (omnibase's `HotParameters`) | `Parameters\ConfigParameters`, `Parameters\DatabaseSettings` |
| `Psr\Clock\ClockInterface` | Laravel's dates (`Date::setTestNow()`, `travel()` move it) | `Contract\DateClock` |
| `Psr\EventDispatcher\EventDispatcherInterface` | Laravel's dispatcher | `Contract\DispatcherEvents` |
| `Symfony\Component\Mailer\MailerInterface` | the default mailer's Symfony transport | `Contract\TransportMailer` |
| `Doctrine\DBAL\Connection`, `Doctrine\ORM\EntityManagerInterface` | the Laravel connection configured, omnibridge/doctrine's entity manager | `Doctrine\Connections` |
| `Twig\Environment` | the site's templates and namespaces, omnibridge/twig's contract and helpers | `View\Twig` |
| `Base\Contract\Pictures` | `\|picture`'s rule but the thumbnails: a public file by its path, an address as it is, else the build's asset; a stored picture where the application serves it (`stored()`) | `Screen\AddressPictures` |
| `Base\Contract\Texts` | `\|wysiwyg` and `\|wysiwyg_toc`'s passes (headings, links, every leaf a string, Editor.js's renderer or Quill's HTML) through omnibase's own views (`@Base` among the Twig paths, or `on($twig)`); a picture where the application serves it (`images()`) | `Screen\WysiwygTexts` |
| `Base\Screen\ContextProvider` | a screen's frame: the locale as a Symfony request carries it (`fr_FR`), the site's settings, home, login, register, logout and each language's address, the guard's user, the colour scheme of the visitor's mode (`omnibridge.theme`), the session's messages | `Screen\ScreenContext` |

Not bound: `Media` (omnibase's image route, which Laravel does not serve: no
thumbnail of a stored picture at a size, no obfuscated address).

**Screens.** With those three, a screen of the core is built on Laravel as under
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

**Parameters.** `Runtime::parameters()` reads Laravel's config by the same dotted
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
`app.site`, `app.theme`, `app.session`), `Form\FormView` and `Form\FormRenderer`
(a few simple forms in the markup of omnibase's form theme), and
`RouterUrlGenerator` (Symfony's `UrlGeneratorInterface` over the Urls contract, for
a library that asks for one).

## Configuration

`config/omnibridge.php`: the site's locales, the catalogues' folders, the asset
manifest, Twig's folders and namespaces, the Laravel connection Doctrine opens and
the entities' folders, the parameters' overlay, the themes (the screens' colour scheme), the storages, the bundles' entity
namespaces. A route Laravel does not serve can be answered elsewhere:

```php
$this->app->extend(RouterUrls::class, fn (RouterUrls $urls) => $urls->elsewhere(
    fn (string $name, array $parameters, string $locale) => $otherSite->url($name, $parameters, $locale),
));
```

First used by lesringostars' Laravel twin, which renders the Symfony site's blog
and articles from the same database and templates.

## Tests

```sh
docker compose run --rm test
```

PHPUnit on Orchestra Testbench (Laravel 13), PHP 8.4, omniscient/omnibase 4.x
installed from Packagist: every contract reached through `Runtime` and the
container as one object, each answer (routes, ICU messages and `exists()`, locales,
the gate, sign-out, the CSRF token, the clock under `travel()`, events, disks, the
parameters with a false setting over a true config), Doctrine on Laravel's SQLite
database in UTC, the settings of the site, Encore tags, the breakpoints, a form.

## Licence

MIT.
