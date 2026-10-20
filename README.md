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
| `Twig\Environment` | the site's templates and namespaces, omnibridge/twig's contract (`path()`, `url()`, `asset()`, `\|trans`), its `AccessExtension` (`is_granted()` on the gate, `csrf_token()` the session's token: the code a Symfony page asks too, omnibridge/symfony registering it there) and helpers | `View\Twig` |
| `Base\Contract\Pictures` | `\|picture`'s rule: a public file by its thumbnail at the size asked (MediaService's, `omnibridge.media` on) or its path, an address as it is, else the build's asset; a stored picture where the application serves it (`stored()`) | `Screen\AddressPictures` |
| `Base\Contract\Texts` | `\|wysiwyg` and `\|wysiwyg_toc`'s passes (headings, links, every leaf a string, Editor.js's renderer or Quill's HTML) through omnibase's own views (`@Base` among the Twig paths, or `on($twig)`); a picture where the application serves it (`images()`) | `Screen\WysiwygTexts` |
| `Base\Screen\ContextProvider` | a screen's frame: the locale as a Symfony request carries it (`fr_FR`), the site's settings, home, login, register, logout and each language's address, the guard's user, the colour scheme of the visitor's mode (`omnibridge.theme`), the session's messages | `Screen\ScreenContext` |

With `omnibridge.media` on (see below): `Base\Contract\Media` (`Media\ServiceMedia`,
MediaService's answers), `Base\Service\MediaService`, `Base\Service\ImageRoute`, and
omnibase's image routes. Off (the default), none of them.

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
`app.site`, `app.theme`, `app.session`; `Page\Theme` is omnibase's `ThemizerInterface` too,
which its theme menus read: `Base\Twig\Extension\MenuTwigExtension`), `Form\FormView` and `Form\FormRenderer`
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

### omnibase's Doctrine mapping

```php
// config/omnibridge.php
'doctrine' => [
    'connection' => 'mysql',
    'entities' => [app_path('Entity')],
    'omnibase' => ['project_dir' => base_path()],   // or true; false (the default) leaves it off
],
```

With `omnibridge.doctrine.omnibase` on, the bound entity manager is omnibase's, as
under Symfony: built by omnibridge/doctrine with `Base\Bridge\Doctrine\Mapping::configure()`
(the repositories from omnibase's registry), over the core's entities beside the
application's, then `Mapping::register()` - the class convention (`App\Entity\X` is
`Base\Entity\X` where the application has none, an alias and not a subclass; an
application's own class always wins), the column types and enumerations (the
project's `src/Enum`), the naming strategy, the discriminator maps, `#[Alias]`, the
translations, the listeners and the trash and vault filters. Its options are
`Mapping::register()`'s: `project_dir` (`base_path()`), `environment` (omnibase's
name, the vault's: `prod`, `dev`, `test` for Laravel's `production`, `local`,
`testing`), `cache_dir`, `parameters` (over Laravel's `base` config by full name -
the entities read `base.uploader.storage`...), `types`, `enumerations`,
`attribute_paths`, `resolve_target_entities`, `entity_listeners`. `app(Mapping::class)`
gives what it registered. `OmnibaseMappingTest` checks the metadata against those
omniscient/omnibase records from its Symfony harness, class by class.

### omnibase's images

```php
// config/omnibridge.php
'storages' => ['local.cache' => 'omnibase-images', 'local.uploads' => 'uploads'],   // Laravel's disks
'media' => true,   // or ['routes' => false, 'secret' => ...]; false (the default) leaves it off
```

With `omnibridge.media` on, omnibase's media rule runs under Laravel as under Symfony:
`MediaService` (thumbnails, formats, the image route's obfuscated addresses) built on
Laravel's router (`Media\LaravelMediaRouter`, the core's `Base\Contract\MediaRouter`),
omnibase's `Obfuscator` and `Flysystem` (its storages are Laravel's disks,
`Media\DiskStorages`: `omnibridge.storages`, else the disk of the storage's name;
`local.cache`, where the thumbnails are kept, is required) and Imagine (Imagick, else GD).
The image routes (`/images/...`, `/contents/...`) are declared under their Symfony names
in Symfony's order (`Base\Service\ImageRoute::ROUTES`) and answered by the core's
`Base\Service\ImageRoute` (`Media\ImageController`, a line per request): the same
addresses, thumbnails, formats, redirections and placeholder. Its parameters are the
config's `base`: `base.images.*` (`max_quality`, `enable_webp`, `fallback`, `no_image`...),
`base.obfuscator.*` (`uuid`, `compression`... and `cache_dir`, the map of the
identifiers: share the Symfony site's to read the addresses it wrote),
`base.uploader.storage`, `base.twig.editor.storage`; omnibase's defaults where it says
nothing. A file has the address Symfony gives it when it has the same path on both hosts
(the identifiers are made of it: the same project folder, the uploads where the Symfony
site's `public/` links them).

With omnibase's mapping on as well, what a site's entities and repositories ask of
their host is answered here too: the attribute reader reads the stored files through
that `Flysystem` (`#[Uploader]`: an avatar, an article's audio, whose `/contents/...`
addresses `ImageRoute` serves), `BaseTrait::getMediaService()` is the bound
`MediaService`, the editor's pictures go through omnibase's `MediaEnhancer`
(`MediaEnhancerInterface`, the image route's address of each picture of a text, as
Symfony's `|wysiwyg` gives it), and a repository taking services beside the registry
(a site's `CommentRepository`, its cache pool first) is given them from Laravel's
container by type (`Mapping::register()`'s `services`). omnibase's cache pool
(`Psr\Cache\CacheItemPoolInterface`, Symfony's `CacheInterface`) is Symfony's cache
component in `storage/framework/cache/omnibase-pool` - not Laravel's store, which
unserializes no object (`cache.serializable_classes`).

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
database in UTC, omnibase's mapping (an `App\` class over a `Base\` one, another only by
alias, the metadata of 94 classes equal to Symfony's), omnibase's images (a thumbnail's
address made and read back, redirected to its extension, served at its size, in webp and
without the local cache, a missing one a 404, Pictures' thumbnails; Imagick in the image),
the settings of the site, Encore tags, the breakpoints, a form.

## Licence

MIT.
