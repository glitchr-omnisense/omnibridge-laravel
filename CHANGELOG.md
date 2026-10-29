# Changelog

## [Unreleased]

### Fixed

- omnimap: `MapServiceProvider` makes omnimap/maplibre's factory when it is installed, beside
  Google's and OpenStreetMap's (`FACTORIES`; each factory given as many of the HTTP client, the
  PSR-18 client and the cache as it takes), and adds every binding tagged
  `omnimap.provider_factory` (`FACTORY_TAG`): `provider: maplibre` was "No maplibre factory", and an
  application had to rebind `Omnimap\Registry`.
- omnimap: `config/omnimap.php`'s keys of omnibridge/symfony's `omnimap.yaml` are read: `geocoder`
  (`Omnimap\GeocoderInterface` is now bound, the page's provider by default), `cache.pool` (a store
  of Laravel's cache as the providers' PSR-16 cache), and the tile route serves a tile as Symfony's
  controller does - WebP at a `.webp` path fitted within `cache.public_size`, `max-age`
  `cache.max_age` (86400 by default) with `must-revalidate`, written in `cache.public_directory`
  when set. They were read by nothing, and the tile at its `.webp` address was the store's PNG.
- omnishield: `challenge.unreachable` defaults to `reject`, the bundle's default (a provider that
  does not answer refuses the form); it defaulted to `accept` under Laravel only.
- omnishield: `Omnishield\WidgetPrinter` is a scoped binding, one per request as the bundle resets
  its own: under a long-running worker (Octane), a provider's script printed on one request was
  left out of the next pages' widgets. Unverified under a real worker.
- `Http\Localize` falls back on `app.locale` when `omnibridge.locales.default` is null or empty: the
  merged configuration always holds that key, so `app.locale` was never used, and with no locale
  available and no browser language Laravel's locale was set to `''`.
- README: the former contract names are said removed at omnibase 4's final pass (they were said to
  be Laravel aliases still, and each binding listed its former name).
