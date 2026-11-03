# Changelog

## [Unreleased]

### Changed

- **The entity hydrator from omnibase's list** (omnisense/omnibase's plan "omnibase agnostic", step F5c, item 3): where
  `Base\Services` lists `EntityHydrator` (on the unit of work and its shapes), the list binds it and `EntityHydratorInterface`;
  `OmnibaseServices` builds it over the mapping's entity manager and manipulator only for a core that does not.

### Added

- **`Auth\Roles`** implements omnisense/omnibridge's `Contract\Roles` (bound to the contract); omnibase's `SecurityPolicy` is given
  it for its `$roleHierarchy` (step F5c, item 3).
- **`Omnibase\OmnibaseServices`** (omnisense/omnibase's plan "omnibase agnostic", step F5c), with omnibase's mapping on: Symfony's
  `Translator` in the page's language, `Localizer`, `SettingBag`, the entity hydrator, `SpamChecker`, the mentions' enhancer;
  omnibase's entity dispatchers and `ThreadSubscriber`/`UserSubscriber`'s Doctrine hooks (`LazyListener`), their events heard by
  name, what they leave flushed after the page (`FlushEntityEvents`); with `omnibridge.notifier.channel_policy`, omnibase's
  `Notifier` on Symfony's with its channels (`email`, `email+` by Laravel's transport, `browser+`, `inapp`, `push`). Bound at boot,
  once the configuration is final, where nothing is bound yet. See docs/doctrine-media.md.
- **`Auth\Middleware\Presence`**: the account signed in poked (its `activeAt`), before the page and on the answer, as omnibase's
  SecuritySubscriber does; in the web group with omnibase's sign-in effects.
- `form_help()` in Twig (`FormRenderer::help()`): a field's help, the tooltip of omnibase's theme.

### Fixed

- `FormRenderer::rest()` printed a field typed twice again whole once its two parts were printed (a sign-up's password).
- `LaravelValidation` left an ICU catalogue's `{value}` as it was ("\"{value}\" est déjà utilisé."): a validator's `{{ value }}`
  is its `{value}` too, unquoted as omnibase's own validators give it.
- omnibase's `UpdateAccount` listener (a sign-in) could not be built: its constructor takes the unit of work or the entity
  manager, a union Laravel's container cannot choose for; bound.
- The e-mails' `|url` filter took a block's blank lines for a route's name (an e-mail's `action_url`): trimmed.
- A sign-in without a page to go back to redirected to the default target without its last slash (Laravel's generator):
  `"/"` as written, as Symfony's success handler sends it.

- **`Contract\LaravelRequests`**: omnisense/omnibridge's `Contract\Requests` on Laravel's request and session (none in a command): the
  visitor's address, headers, address, route and its parameters, the session's values, and the flashes where omnibase's templates read
  them (`SessionView`). Bound to `Omnibridge\Contract\Requests`.
- `DispatcherEvents` gives an `Omnibridge\Event\NamedEvent` (omnibase's `user.register`...) to the listeners of its name as well,
  `Event::listen('user.register', ...)`, as Symfony's subscribers listen by name.

- **`Form\FormRenderer`** prints what the sites' definitions hold: a value typed twice, omnibase's ButtonType and its confirmation modal, a row's
  attributes (the guard's trap), a label humanized as Symfony's. **`Contract\LaravelValidation`** gets omnibase's `UniqueLookup`.

- **The generic form theme under Laravel** (omnibase's step F5b): `Form\FormRenderer` reads omnibase's neutral `Base\Form\Definition\FormView`
  as well as this package's (any object with the same vars) and prints textareas, selects, buttons and errors, in the markup of omnibase's
  theme; `form()`; Blade directives `@form`, `@formStart`, `@formRow`, `@formWidget`, `@formLabel`, `@formErrors`, `@formRest`, `@formEnd`.
- **`Contract\LaravelValidation`** (omnibase's plan "omnibase agnostic", step F5b): omnibridge's `Validation` under Laravel - the kept
  constraints by omnibridge's `Validator`, so that their verdict and message are every host's, Laravel's own rules (a string, a Rule)
  by its validator; Symfony's messages from the `validators` catalogue, read from symfony/validator where it is installed.

### Fixed

- With media off, omnibase's list of services no longer binds MediaService and the obfuscator, which could not be built and failed every page (OmnibaseExtensions asks whether MediaService is bound).

- omnisense/omnibase's list of services (`Base\Services::definitions()`, its step F5a) bound at boot by
  `Container\Definitions`: what this provider or the application does not bind is a singleton of its class,
  its contracts aliases, its arguments read with `Omnibridge\Container`'s syntax, union types resolved by the
  type the container binds. `Omnibridge\Contract\Views` is bound to omnibridge/twig's `TwigViews` over the
  container's Twig environment; MediaService and omnibase's `EmailComposer` take it in place of the
  environment (which the core deprecates). Docs: contracts.md.

### Changed

- omnibase's Doctrine mapping is `Omnibridge\Doctrine\Omnibase\Mapping`, where omnisense/omnibase's
  plan "omnibase agnostic" (step F3) moved `Base\Bridge\Doctrine\Mapping` with the core's other
  Doctrine classes (omnibridge/doctrine). The provider imports the new name and binds the former one
  as an alias of the same singleton; the core aliases the former class name, so an application's
  code naming it is unchanged.

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
