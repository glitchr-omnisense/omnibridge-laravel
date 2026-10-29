---
title: Maps, shields, static export
order: 34
---

# Maps, shields, static export

## Maps: omnimap

Where omnisense/omnimap is installed (suggested), `Map\MapServiceProvider` reads `config/omnimap.php`
(not published: the application writes it) and wires nothing unless it names a `provider`:

```php
// config/omnimap.php
return [
    'provider' => 'openstreetmap',
    'secret' => env('MAP_SECRET'),
    'providers' => ['openstreetmap' => ['user_agent' => 'MySite/1.0 (+https://example.org)']],
    'cache' => ['storage' => storage_path('app/omnimap'), 'directory' => 'omnimap'],
    'routes' => ['tile' => ['path' => '/omnimap/{signature}/{id}.webp', 'name' => 'omnimap_tile']],
];
```

| Key | Default | Effect |
|---|---|---|
| `provider` | `''` (nothing wired) | the page's provider, a key of `providers` |
| `secret` | `''` | signs the pictures' names |
| `providers.<name>` | - | the provider's options, its factory's; `factory` names another factory than the key |
| `geocoder` | the page's provider | the provider `Omnimap\GeocoderInterface` is |
| `cache.drive` | none | an omnidrive drive of the bound `Omnidrive\Drives` (a storage's name) |
| `cache.storage` | `storage/app/omnimap` | a directory (`DirectoryStorage`), or the container's binding of an `Omnimap\Snapshot\Storage` |
| `cache.directory`, `.pattern`, `.format` | `omnimap`, `{signature}/image-{id}`, `png` | omnimap's `SnapshotStore` |
| `cache.make` | `auto` | the server makes a missing picture: `auto` when the provider makes static maps, true, false (a host reading another host's cache read only) |
| `cache.size`, `.scale`, `.tilesize` | `1512x938`, 1, 512 | the picture made, its tiles (0: not cut) |
| `cache.pool` | none: omnimap's files in `storage/framework/cache/omnimap` | a store of Laravel's cache (`config/cache.php`'s `stores`): the providers' cache (tiles fetched, geocoder answers) |
| `cache.max_age` | 86400 | a tile's `max-age`, in seconds |
| `cache.public_directory` | none | a directory (the public folder) where each tile served is written at the route's path, for the web server to serve it next |
| `cache.public_size` | none | the tile served as WebP fitted within this size, in pixels |
| `html` | `[]` | omnimap's `MapHtml` options (`class`, `suffix`, `missing`); `src` is the tile route's path |
| `cache_only` | true | a visitor never gets the live map |
| `control` | `ROLE_ADMIN` | who may change the map (the Access contract) |
| `routes.tile` | `{path: /omnimap/{signature}/{id}.webp, name: omnimap_tile, register: true}` | the tile's route; `register` false when the web server serves the tiles |
| `routes.metadata`, `.export`, `.suppress` | `/omnimap/{signature}`, `.../export`, `.../suppress`, names `omnimap_<name>` | named for the buttons only: not routed here |

| Binding | What it is |
|---|---|
| `Omnimap\Registry` | omnimap/google's, omnimap/openstreetmap's and omnimap/maplibre's factories when installed (`MapServiceProvider::FACTORIES`), and every binding tagged `omnimap.provider_factory` (`MapServiceProvider::FACTORY_TAG`), over the HTTP client bound as `omnimap.http_client` (else Symfony's `HttpClient::create()`), a PSR-18 client when nyholm/psr7 or php-http/discovery is installed, the PSR-16 cache of `cache.pool` |
| `Omnimap\GeocoderInterface` (bind) | the registry's geocoder named by `geocoder`, the page's provider by default |
| `Omnimap\Snapshot\Snapshots` | the page's renderer, the signer, the store, the static map per `cache.make` |
| `Omnimap\MapRendererInterface` (bind) | the snapshots' renderer |
| `Omnimap\MapPrinter` | a picture that could not be made logged as a warning |
| `Omnibridge\Twig\MapExtension` | `omnimap()`, `omnimap_export()`, `omnimap_suppress()`, `omnimap_signature()` in every page's Twig ([Pages](views.md)) |

The tile route (`Map\TileController::tile`, `signature` `[A-Za-z0-9_-]{1,128}`, `id` digits), as
omnibridge/symfony's controller serves it: at a path ending in `.webp`, the tile as WebP (fitted
within `cache.public_size`; GD with WebP, else Imagick, else as kept in the store's format), written
in `cache.public_directory` at the route's path when one is set, public, `max-age` `cache.max_age`,
`must-revalidate`, an ETag; 404 "No such map tile." when none. Capturing and deleting a picture is omnibridge/symfony's.

## Shields: omnishield

Where omnisense/omnishield is installed, `Shield\ShieldServiceProvider` reads `config/omnishield.php`
(`php artisan vendor:publish --tag=omnishield-config`, offered only once a gateway is named and ALTCHA's script is served), the keys of
the Symfony bundle's `omnishield.yaml`, and wires nothing without a gateway:

| Key | Default | Effect |
|---|---|---|
| `gateways.<name>` | `[]` | `{factory, options}`; the factories made here are those needing no HTTP client: `altcha`, the disposable domains, the tests' fixed one |
| `challenge.gateway` | null: the first gateway | the captcha a form asks by default (`ShieldServiceProvider::defaultChallenge()`) |
| `challenge.unreachable` | `reject` | whether a captcha that does not answer lets a form through (`accept`), or refuses it, as the bundle's default |
| `serve_scripts` | true | ALTCHA's widget served by the application (route `omnishield_altcha_script`, cached a year), the gateways' default `script` then |
| `replay.pool` | null: in memory | a PSR-6 pool's binding keeping the tokens already used |

Bindings: `Omnishield\Replay\ReplayStoreInterface`, `Omnishield\Registry`, `Omnishield\WidgetPrinter`
(scoped: one per request, as the bundle resets its own - a long-running worker such as Octane forgets
it between two requests, so each page prints a provider's script).
With omnibase's sign-in effects, the captcha after failed sign-ins uses it ([Signing in](omniauth.md)).

## Static export: omnibridge/static


Where omnibridge/static is installed, `OmnibridgeServiceProvider` registers
the artisan command `omnibridge:static:build`: omnibridge/static's own command
line (`Console\Cli`), handed a renderer through the application's HTTP kernel
(each page a visitor's GET, at `--render-origin`, by default `app.url`), the
container, `public/` and, with `--actions`, the routes the actions of those
folders declare (`Routing\ActionRoutes`).

```sh
php artisan omnibridge:static:build static.php --out=storage/static --actions='App\Action\=src/Action'
```

Run on lesringostars-laravel (2026-10-24, the working copy loaded by hand): the
twin's home, intro, destinations, two articles and the travels pages rendered
by Laravel and written, the routes read from lesringostars' actions.

| Argument or option | Default | Effect |
|---|---|---|
| `site` | `static.php` | relative to `base_path()` unless absolute |
| `--out` | `storage/static` | relative to `base_path()` unless absolute |
| `--render-origin` | `app.url`, else `http://localhost` | the origin the pages are rendered at |
| `--actions=Namespace\=folder` | none, repeatable | the routes the actions of those folders declare (`Routing\ActionRoutes`, Laravel's environment by Symfony's name), handed as the offer's `routes` |
| `--verify`, `--strict`, `--quiet-progress` | off | omnibridge/static's `--verify`, `--strict`, `--quiet` |

Each page goes through the HTTP kernel as `Illuminate\Http\Request::createFromBase()` of the renderer's
request; the offer also holds the container and `public_path()`. An invalid argument prints its message
and exits with `INVALID` (2). omnibridge/static's manual has the site file and the export.
