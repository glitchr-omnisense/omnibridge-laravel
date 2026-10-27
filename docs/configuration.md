---
title: Configuration
order: 40
---

# Configuration

`config/omnibridge.php` (`php artisan vendor:publish --tag=omnibridge-config`), merged under the key
`omnibridge` by the provider: the published file holds every key below with its default.

| Key | Type | Default | Read by | Effect |
|---|---|---|---|---|
| `locales.available` | `list<string>` | `[]` | `AppLocales`, `Http\Localize`, `Http\SwitchLocale` | the site's languages in omnibase's form (`fr-FR`, `en-GB`) |
| `locales.default` | `?string` | null | `AppLocales`, `Http\Localize` | the default language; null: the first available (`AppLocales`) |
| `translations.paths` | `list<string>` | `[]` | `CatalogueTranslations` | the ICU catalogues' folders, the later over the earlier (the core's, the bundles', the site's last) |
| `translations.fallback` | `?string` | null: `app.fallback_locale`, else `en` | `CatalogueTranslations` | the language of a missing message |
| `assets.manifest` | `?string` | null | `RouterUrls::asset()` | a build's `manifest.json` |
| `twig.paths` | `list<array{string, ?string}>` | `[]` | `View\Twig` | `[folder, namespace]` pairs, null for the main namespace |
| `twig.cache` | `?string` | null: `storage/framework/twig` | `View\Twig` | Twig's cache folder |
| `twig.timezone` | `?string` | null: `app.timezone` | `HelperExtension` | the zone `\|datetime` prints in |
| `doctrine.connection` | `?string` | null | `Doctrine\DBAL\Connection`, the settings, the screens' site | a connection of `config/database.php`; none: Doctrine asked is a `LogicException`, no settings read |
| `doctrine.entities` | `list<string>` | `[]` | the entity manager | the entities' folders |
| `doctrine.omnibase` | `bool\|array` | false | the entity manager | omnibase's own mapping: true, or `Mapping::register()`'s options (`project_dir`, `environment`, `cache_dir`, `parameters`, `types`, `enumerations`, `attribute_paths`, `resolve_target_entities`, `entity_listeners`) ([Doctrine and images](doctrine-media.md)) |
| `parameters.hot` | `?bool` | null: the config's `base.parameter_bag.use_hot_bag` | `HotParameters` | the database settings laid over the config ([Parameters](contracts.md#parameters)) |
| `theme.modes` | `array` | `[]` | `Page\Theme`, `ScreenContext`, `MenuTwigExtension` | id => `{id, class, icon, prefers}`; no colour scheme without modes |
| `theme.filters`, `theme.no_filter` | `array` | `[]` | `Page\Theme` | the filters, the one for no filter |
| `theme.layouts` | `?string` | not in the published file | `Page\Theme::getLayout()` | the folder of the site's `layoutN.html.twig` (`app.theme.layout(1)`) |
| `storages` | `array<string, string>` | `[]` | `DiskFiles`, `Media\DiskStorages`, `Omnidrive\Drives` | omnibase's storage name => Laravel's disk; `default` for a storage not named |
| `media` | `bool\|array` | false | the provider | omnibase's media rule: true, or `{routes: bool (true), secret: ?string (app.key)}` ([Doctrine and images](doctrine-media.md#omnibases-images)) |
| `entity_namespaces` | `array<string, string>` | `[]` | `EntityNouns` | a bundle's entity namespace and its prefix in the "entities" catalogues (`Base\Marketplace\Entity\` => `marketplace\`) |
| `auth` | `bool` | false | `Auth\AuthServiceProvider` | the sign-in through omniauth ([Signing in](omniauth.md)) |
| `encore.builds` | `list<array{entrypoints: string, manifest: string}>` | `[]` | `View\Encore` | the Encore builds, in the order they are looked into; no Encore tags without |
| `encore.breakpoints` | `array<string, string>` | `[]` | `View\Encore` | name => media query, a stylesheet's versions |
| `encore.optional` | `array<string, list<string>>` | `[]` | `View\Encore` | the entries a page loads with another |
| `consent.config` | `string\|array\|null` | null | omnibase/consent's extension | a YAML file with a `consent:` key (the Symfony site's `consent.yaml`), or the array |
| `push.public_key`, `push.private_key`, `push.subject` | `?string` | null | `WebPushService` | the VAPID keys; no push without the public key |
| `deploy.project_dir` | `?string` | null: `base_path()` | `DeployVersionExtension` | the folder whose git heads `deploy_version()` reads |
| `icons.metadata` | `?string` | null: `public/bundles/base/metadata` | `IconTwigExtension` | the icons' metadata (`icons.yml`, `bootstrap-icons.json`) |
| `screens.version` | `?string` | not in the published file | `Base\Screen\Negotiation` | the front end's version omnibridge/react's protocol compares |

Read from Laravel's own configuration: `app.locale`, `app.fallback_locale`, `app.debug`, `app.key`,
`app.timezone`, `app.url` (the static command), `database.connections.*`, `filesystems.*`, the whole
`base` array (the parameters, omnibase's mapping, the media: `base.images.*`, `base.obfuscator.*`,
`base.uploader.storage`, `base.twig.editor.storage`, `base.router.use_fallbacks`,
`base.router.host_fallbacks` for the sign-in cookie's domain).

The other files: `config/omniauth.php` ([Signing in](omniauth.md#configomniauthphp)),
`config/omnimap.php` and `config/omnishield.php` ([Maps, shields, static export](families.md)).
