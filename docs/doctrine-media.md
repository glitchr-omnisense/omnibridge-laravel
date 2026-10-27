---
title: Doctrine and images
order: 31
---

# Doctrine and images

omnibase stays on Doctrine for storage: this package opens Doctrine on one of Laravel's connections
(omnibridge/doctrine), so Doctrine and Eloquent, if the application uses it, read the same tables.

## The connection and the entity manager

```php
// config/omnibridge.php
'doctrine' => [
    'connection' => 'mysql',              // a connection of config/database.php
    'entities' => [app_path('Entity')],   // the folders the entity manager maps
    'omnibase' => false,
],
```

| Binding | What it is |
|---|---|
| `Doctrine\DBAL\Connection` | `Doctrine\Connections::create()` of `database.connections.<connection>`: the drivers `mysql` and `mariadb` (`pdo_mysql`), `pgsql`, `sqlite` (`:memory:` or a path), `sqlsrv`; host, port, database, username, password, `unix_socket`, the charset (`utf8mb4` for MySQL by default). No connection named: `LogicException`; an unknown one: `InvalidArgumentException`; another driver: `InvalidArgumentException` |
| `Parameters\DatabaseSettings` | omnibase's settings (`layoutSetting`, `layoutSettingIntl`) through that connection |
| `Doctrine\ORM\EntityManagerInterface` | omnibridge/doctrine's `EntityManagers::create()` over `entities`, debug from `app.debug`; or omnibase's ([below](#omnibases-doctrine-mapping)) |
| a repository class | asked of the container (an action's type-hint), the entity manager's repository of the entity that names it as its `repositoryClass` (the one named after it when several do); `LogicException` when none does |

## omnibase's Doctrine mapping

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
omnisense/omnibase records from its Symfony harness, class by class.


The options are read by `OmnibridgeServiceProvider::omnibase()`: `project_dir` (`base_path()`),
`environment` (the given one, else Laravel's by omnibase's name: `production` `prod`, `local` and
`development` `dev`, `testing` `test`), `cache_dir` (`storage/framework/cache/omnibase-doctrine/<env>`),
`parameters` (the given ones over Laravel's whole `base` config flattened by full name), and any other
option handed to `Mapping::register()` as it is. Beside them the provider gives `flysystem` (omnibase's
`Flysystem`, with the media on) and `services` (a closure answering a repository's other constructor
arguments from Laravel's container by type). With the mapping on, the core's repositories are also bound
by their interfaces (`Base\Repository\Aliases::INTERFACES`).

## omnibase's images

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



The routes are declared at boot unless `omnibridge.media.routes` is false, each `$router->get($path,
ImageController::class)->name($name)` with `data` matching `.+`; `ImageController` hands the route's
`data`, `identifier` and `extension` to the `ImageRoute` action the route names
(`ImageRoute::ROUTES`), the cacheless routes to `ImageRoute::cacheless()`. Imagine is Imagick's when the
extension is loaded, GD's otherwise; SVG through omnibase's own Imagine. The obfuscator's compressions:
null, zlib, gzip, deflate, and Hashids keyed by `omnibridge.media.secret` (else `app.key`). The defaults
laid under the config's `base` once (`base.images.*`: `max_resolution` UHD, `debug` false,
`max_quality` 1, `timeout` 60, `enable_webp` true, `fallback` false, `no_image` the placeholders of
`public/bundles/base/images`; `base.obfuscator.*`: `level` -1, `uuid` the URL namespace, `encoding`
null, `max_length` 0, `compression` `null`, `cache_dir` `storage/framework/cache/omnibase-obfuscator`;
`base.uploader.storage` and `base.twig.editor.storage` null). A media parameter asked with the media off
is a `LogicException` ("omnibase's media are off: omnibridge.media.").

First used by lesringostars' Laravel twin, which renders the Symfony site's blog and articles from the
same database and templates.
