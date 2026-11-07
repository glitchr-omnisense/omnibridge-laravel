# Changelog

## [Unreleased]

### Added: the packages' listeners of the writes on eloquent (omnisense/omnibase's plan "omnibase agnostic", step B2)

- `Persistence::register()` hands omnibridge/eloquent's persistence what the application tagged
  `omnibridge.lifecycle_listener` (omnisense/omnibridge's `Lifecycle\Listeners::TAG`, `$app->tagged()`, made at the
  first event): a package following the writes (a search index) hears Eloquent's, as under Symfony.

### Fixed: the twin in eloquent mode (omnisense/omnibase's plan "omnibase agnostic", step F7d-2)

- The image route's crops (`Base\Repository\Layout\ImageCrops`) and omnibase's WebPushService (the unit of work,
  `User\PushSubscriptions`) by the core's interfaces, from the persistence chosen, instead of omnibase's entity manager's
  repositories.
- `Persistence::boot()`: on Eloquent with omnibase's mapping on, omnibase's entity manager is made once the application
  has booted, for the AttributeReader it registers (an avatar's public address read before any query refused "Target
  class [config] does not exist") and the column types Twig's enum filters name. Neither unit of work is cleared between
  two requests one application serves (a test's), as Laravel keeps its guards' accounts across them.
- `Parameters\BaseOptions`: a configuration processed already is not processed again (`BaseOptions::PROCESSED`, set
  beside the options): the application boots from `php artisan config:cache`, whose file holds the processed options -
  processing them twice refused the defaults they hold ("Invalid type for path base.obfuscator.encoding. Expected int,
  got null", as Symfony's IntegerNode refuses an explicit null). Found by the F7e bench.

### Added: the persistence chosen, Eloquent's when it is the one (omnisense/omnibase's plan "omnibase agnostic", step F7d)

- `Persistence`: omnibase's option `base.persistence` (config/base.php), chosen by omnisense/omnibridge's `Persistences`
  - the only one installed, else the one named; both installed and none named refused when the application boots.
  Where omnibridge/eloquent is installed, `UnitOfWork`, `Snapshots`, `Backend`, `Repositories` and the core's
  repository interfaces answer from it when it is chosen (`EloquentPersistence::host()` on Laravel's connection,
  `omnibridge.eloquent.connection`; the lifecycle from the container; the trash and vault scopes), from Doctrine as
  before otherwise. `OmnibaseServices::entityDispatchers()`.
- The account's effects on the unit of work chosen (`OmnibaseEffects::unitOfWork()`): the connections' tracker,
  TrackConnections, UpdateAccount, WelcomeBack, omniauth's omnibase stores; the connections' repository by its
  interface. On Doctrine, the same objects as before (the unit of work is the entity manager's).
- PersistenceTest. Docs: docs/configuration.md.

### Added: `omnibridge:schema:create` on artisan (omnisense/omnibase's plan "omnibase agnostic", step F7a)

- `Console\SchemaCreateCommand`, registered with omnibase 5.0 and its mapping on: the installed entities' tables created
  in the empty database of `omnibridge.doctrine.connection` from their description (omnibase's `EntitySchema`,
  omnisense/omnibridge's `SchemaCreator`), no ORM, through Laravel's PDO; `--dump-sql`; a database holding a table of the
  description refused. SchemaCreateTest: the statements printed are SchemaCreator's, the tables read back are the
  description and the ones Doctrine's SchemaTool makes from the entity manager's mapping (SQLite), a second run refused.
  On the twin (lesringostars' entities, MySQL): the same 132 tables as SchemaTool's, column for column.

### Fixed: symfony/http-client required (F6 slice 2's defect)

- `Omnibase\OmnibaseServices` (the SpamChecker's client) and `Map\MapServiceProvider` (omnimap's client) build
  symfony/http-client's `HttpClient`, which was only in require-dev: an application without it (the twin, until its own
  root requirement) failed on 48 tests. It is required now - the bridge is where omnibase's HTTP contract
  (http-client-contracts) gets its implementation.

### Changed: the language menu asks the core's rule (omnisense/omnibase's plan "omnibase agnostic", step F5e, slice 5b)

- `Http\SwitchLocale` asks omnibase's `Base\Request\LocaleSwitch`, the rule LocalizerController asks under Symfony,
  where it was written again here; its answers are Symfony's now: LANG written as omnibase's localizer writes it
  (`en-GB`, it wrote `en_GB`); back to the referring page's route in the chosen language where it has one, else its
  own, its name read as this host's links read it (RouterUrls: `/galleries` is `/albums` in French), the index for a
  referring path no route matches and when no language is given (it went back to the path); a member's language kept
  as their own (not while impersonating) and the visitor told so in a flash. Under omnibase 4.x, which has no such
  rule, the former code (`Legacy\Http\SwitchLocale`).
- `Omnibase\SiteLocalizer`: omnibase's localizer on the site's languages (`omnibridge.locales.default`, `available`),
  as Symfony's is on kernel.default_locale and the translator's fallbacks; it knew the translations' fallback only
  (the twin: fr-FR and fr-BE), so every rule asking it which languages the site speaks refused English and German.
- tests/LocaleTest: the menu's answers under 5.x (and the former ones under 4.x).

### Changed: the sign-out page asks the core's rule (omnisense/omnibase's plan "omnibase agnostic", step F5e, slice 5a)

- `Auth\Controllers\SignIn::logoutPage()`: whether a signed-in visitor is asked to confirm is omnibase's
  `Base\Auth\Account\SignOutPage::confirms()`, the rule SecurityController::Logout() asks under Symfony, where it was
  written again here; under omnibase 4.x, the former test (`Legacy\Auth\SignOutConfirmation`).

### Changed: what the core declares is discovered is applied (omnisense/omnibase's plan "omnibase agnostic", step F5e, slice 4c)

- `Container\Definitions::discover()`, after `bind()`: the core's registries (`Base\Discovery`; omnisense/omnibridge's
  `Container\Discovery`) applied to what the container binds - the tags autoconfiguration gives, each collection handed
  to its service when it is made (the obfuscator's compressions, the sharing adapters...), the fallbacks (the admin's
  links on the null generator). `Definitions::entityListeners()`: the Doctrine listeners the declaration tags and their
  events.
- `Omnibase\OmnibaseServices`: the entity dispatchers and ThreadSubscriber are bound from the core's declaration, and
  every Doctrine listener is registered on the events the registries give it (an application's
  `omnibridge.omnibase.entity_dispatchers` on those of `Base\EntityDispatcher\EventDispatcherInterface`), where the
  bridge wrote the list of dispatchers and the events of each by hand; that code is `Legacy\Omnibase\EntityListeners`,
  for omnibase 4.x. `OmnibaseServices::DISPATCHERS` deprecated. The repository interfaces from the `repositories` registry.
- Hand-written bindings of core services: 30 -> 27 (the two dispatchers, ThreadSubscriber). tests/DiscoveryTest.

### Changed: config/base.php is checked against omnibase's schema (omnisense/omnibase's plan "omnibase agnostic", step F5e, slice 4b)

- `Parameters\BaseOptions::apply()`, first thing at boot: the config's `base` array processed by the core's `Base\Options`
  (omnisense/omnibridge's `Options\Processor`) - refused with Symfony's message, deprecations said - and completed with
  its defaults, their placeholders resolved by the application (`%kernel.project_dir%`, `%kernel.cache_dir%`,
  `%kernel.environment%`, `%env()%`). The parameters' bag and `hasListParameter()` then see every option.
- Gone from the provider, now the core's defaults: `mediaDefaults()` (the images', obfuscator's, uploader's and editor's
  options) and the paginator's, kept for omnibase 4.x in `Legacy\Parameters\OptionDefaults` with the hand-written
  Obfuscator binding: under 5.0 the Obfuscator is the declaration's (its compressions, `%base.obfuscator.cache_dir%`,
  `%kernel.secret%`). Hand-written bindings of core services: 31 -> 30 (OmnibridgeServiceProvider 11 -> 10).
- `Container\Definitions::env()`: `%env()%` as before, the core's default (`Options::environment()`) where the environment
  has none - an unset `BASE_TRADING_LIVE` is live, as under Symfony.
- `OmnibaseEffects::cookieDomain()`: no router configured is now no `base.router.host_fallbacks` (the option always has a
  value); the request's host alone, as before.
- tests/BaseOptionsTest: the defaults, the placeholders, six refusals worded as Symfony's tree words them, the
  environment's defaults.

### Changed: omnibase's whole declaration of services is bound (omnisense/omnibase's plan "omnibase agnostic", step F5e, slice 4a)

- `Container\Definitions::bind()` reads `Base\Services` through omnisense/omnibridge's `Omnibridge\Container\Declarations`
  (requires, environment, parents, positions, bindings, calls, tags, `!tagged_iterator`, `!service_closure`, `%env()%`,
  Symfony's `#[Autowire]` read by name) and binds each declared service that applies and can be built here - what its
  constructor needs bound or declared, its options configured (the config's, checked without building the parameters'
  bag) -, a service the application binds keeping its binding and getting its contracts; a decoration stays the host's
  wiring. A declared service that cannot be built answers Laravel's BindingResolutionException, so an optional parameter
  takes its default as Laravel's autowiring does. The twin binds 142 of the 192 (19 before). No rule of the core is
  written here (rules written twice: 0 before, 0 after); the hand-written bindings of core services are unchanged (31).

### Added: the console's other tasks as artisan commands (omnisense/omnibase's plan "omnibase agnostic", step F5e, slice 3b)

- With omnibase 5.0 (its `Base\Console\Task\BaseMapping`): `base:mapping`, `icon:enums`, `icon:entities`,
  `autocomplete:entities`, `entity:discriminator`, `app:notifier`, `notifier:mail:test`, `user:notifications`,
  `thread:publishable`, `trash:purge` (omnibase's TrashManager on the unit of work and the trash's repository where
  none is bound), `user:create` (the Notifier where bound; `--no-email` asked without it): the core's tasks, the same
  Reports as omnibridge/symfony's commands. base:mailer:trap, timemachine:* and doctrine:* stay Symfony's (README).
  No rule of the core is written here (rules written twice: 0 before, 0 after). tests/ConsoleTasksTest.

### Added: omnibase's console tasks as artisan commands (omnisense/omnibase's plan "omnibase agnostic", step F5e, slice 3a)

- With omnibase 5.0, `Console\*`: `translation:crud`, `translation:entities`, `translation:enums`,
  `translation:controllers` (the named routes), `translation:settings`, `cache:clear:sessions` (config
  `session.files`), `omnibase:cache:clear` (the core's checks, lock and counter around `optimize:clear`; Laravel's
  cache:clear keeps its name), `demo:reset` (artisan's commands through `Console\ArtisanCommands`: the fixtures are
  `db:seed`, the pools `cache:clear`; symfony/lock's FlockStore where installed), `uploader:entities`,
  `uploader:images`, `uploader:images:crop`: the core's `Base\Console\Task\*`, the same Report printed as
  omnibridge/symfony prints it (`Console\Reports`). The application's App\ folder is `omnibridge.console.source`,
  else Composer's App\ folder that has entities, else app/.

### Added: omnibase's request rules (omnisense/omnibase's plan "omnibase agnostic", step F5e, slice 2)

- With omnibase 5.0, in the web group with its sign-in effects, in its Symfony subscribers' order:
  `Http\AccessControl` (the pages kept for some accounts, `omnibridge.access_control` - Symfony's `access_control` as
  data -, a refused visitor sent to the sign-in: the core's `AccessControl` and `EntryPoint`), `AccessRestriction`,
  `Auth\Middleware\SitePages` (the maintenance and launch pages), `Auth\Middleware\AccountRules` (the kick, the
  presence, the verification's notice, the approval: the core's `Accounts`). `Http\Decisions` applies a decision
  (notices flashed, url.intended, the sign-out, abort, the redirection and its cookies), `Http\RequestPage` answers
  the rules' questions about the route.
- `Omnibase\Redirections`: a 404 looked up in the site's redirections (the core's `Redirection`), where their
  repository is bound.
- `Routing\TrailingSlash`: `Route::...->trailingSlash()` keeps a route's last slash in RouterUrls' addresses, as the
  Symfony site declares it (`/profile/{id}/edit/`).

### Changed

- `Http\Localize` asks the core's `Locale` (5.0): a signed-in member's own language now comes before LANG, as on the
  Symfony site. Its former copy of the rule is `Legacy\Http\Localize`, for omnibase 4.x.
- `Auth\Middleware\Presence` is gone: the twin's copy of the presence rule. Under 5.0 the core's `Presence` runs in
  `AccountRules`; under 4.x the former copy, `Legacy\Middleware\Presence`. Both removed with 4.x.
- `AccessRestriction` reads its page through `Http\RequestPage`.

### Added: omnibase's access policies (omnisense/omnibase's plan "omnibase agnostic", step F5e, slice 1)

- `Auth\Policies`, on Laravel's gate (Gate::before, with omnibase 5.0): BACKEND on the back office's and the
  profiler's routes, the permission tags, COMPLAINT_READ / COMPLAINT_HANDLE (on `Auth\Roles`' hierarchy) and the access
  restriction's attributes (where `base.access_restriction` is configured: one firewall, `main`) - the core's
  `Base\Auth\Policy\*`, as its Symfony voters ask them.
- `Auth\Middleware\AccessRestriction` (the web group, with omnibase's sign-in effects): the core's `Restriction`, as
  its SecuritySubscriber applies it (404, the redirection, the notices flashed); nothing where no restriction is set.

### Changed

- `Auth\Listener\WelcomeFlash` asks the core's `Welcome` for its key (5.0); its copy of the rule is
  `Legacy\WelcomeKey`, for omnibase 4.x, removed once 4.x is no longer served. Under 5.0 an account whose last
  activity is unknown is welcomed "back", as the Symfony site welcomes it.

### Changed

- Accepts omnisense/omnibase 5.0 (`^4.0 || ^5.0`, plan "omnibase agnostic", step F6), and requires
  symfony/validator, whose `validators` catalogue LaravelValidation reads and which omnibase 5.0 no longer
  requires.


### Added

- **omnibase's embedded forms, lists and attributes** (omnisense/omnibase's plan "omnibase agnostic", step F5d, slice 7):
  `FormRenderer` draws `association` (an embedded record's form, or several records' forms in the collection `_collection`
  with its prototype, as omnibase's `association_row`, `collection2_row` and `collection2_entry_widget` blocks do), `collection`,
  `array` (`array_row`, a fixed pattern's inputs between its parts) and `attribute` (the select of adapters, each attribute's
  part); `LaravelKinds` declares the four implemented. A translated attribute's value (its adapter's `TranslationType`, the
  `intl` part of every attribute of the core) is refused aloud, naming the reason. Binding is omnibase's (`FormBinder`:
  `Base\Field\Behaviour\{Association, Collection, Attribute}`). Docs: docs/views.md.

- **omnibase's editors** (omnisense/omnibase's plan "omnibase agnostic", step F5d, slice 6): `FormRenderer` draws `editor`
  (Editor.js) and `wysiwyg` (Quill) as omnibase's `editor` and `wysiwyg` blocks do - the text in a hidden input, the script's
  element with what it reads (omnibase's `Editor` and `Wysiwyg` behaviours), Quill's holding the text as `|wysiwyg` prints it,
  the soft line break's hint -; `LaravelKinds` declares both implemented, so the translations holding one (an article's,
  a destination's, a gallery's, a page's) are drawn too. With media on and `omnibridge.forms.editor` (true by default), the
  routes of omnibase's `EditorJs::ROUTES` (`ux_editorjs_*`: the mentions, a text's pictures, the relay's ticket, a field's
  autosave) are declared under their Symfony names and paths and answered by `Form\EditorController`; `Base\Service\Storages`
  is bound to omnibase's Flysystem (a picture's storage). `tests/EditorTest`; `FieldConformanceTest` gives omnibase's templates
  to Twig (a wysiwyg's text printed by `|wysiwyg`).

- **omnibase's records made from uploads** (omnisense/omnibase's plan "omnibase agnostic", step F5d, slice 5): `FormRenderer`
  draws `association_file` (a gallery's photos, a photo's picture) as omnibase's `associationfile` block does - its upload field
  (a file, an image, an avatar) drawn as above, each stored file's name by its record's id in `data-entry-id` -, from omnibase's
  `AssociationFile` behaviour; omnibase's binder makes what is sent into the related records. `LaravelKinds` declares it
  implemented.
- A file sent that its field refuses (a type its `mime_types` do not match, a size over its ceiling) is refused when the form is
  validated, with omnibase's message (omnibase's `Upload::refusal()`, which its neutral binder applies), no longer at the flush.

### Fixed

- The upload's download icon (`data-file-download`) printed with one space between its style and its class, as omnibase's Twig
  macro prints it.

- **omnibase's uploads** (omnisense/omnibase's plan "omnibase agnostic", step F5d, slice 4): `FormRenderer` draws `file`,
  `image`, `avatar`, `audio` and `video` as omnibase's `fileupload`, `imageupload` and `avatar` blocks do, from omnibase's
  `Upload` behaviour - the stored file's name in its hidden input (`[file]`), the file to send (`[raw]`), or a dropzone that
  sends each file as it is dropped; the stored picture's thumbnail, its lightbox, the cropper's modal; the data attributes
  omnibase's scripts read, the size and types accepted, the confirmation's modal. `LaravelKinds` declares the five kinds
  implemented (`cropper` stays unsupported). With `omnibridge.media` on and `omnibridge.forms.uploads` (true by default), this
  package declares omnibase's dropzone routes under their Symfony names and paths (`Base\Field\Upload\Dropzone::ROUTES`:
  `ux_dropzone`, `ux_dropzone_preview`, `ux_dropzone_delete`), in the web group without Laravel's CSRF middleware: the
  dropzone checks the session's token itself. `Form\UploadController` hands omnibase's `Dropzone` the file sent and sends its
  answer. omnibase's `kernel.cache_dir` is `omnibridge.cache_dir`, else `storage/framework/cache/omnibase` (a dropzone's files
  wait in its `dropzone/`). What is sent is bound by omnibase's FormBinder and stored by the record's `#[Uploader]` at the flush.
  tests/FieldConformanceTest gives the uploads a storage (`local.uploads`).
- **omnibase's translations field** (omnisense/omnibase's plan "omnibase agnostic", step F5d, slice 3): `FormRenderer` draws a
  `translation` field as omnibase's `translatable_widget` does - a tab per language, each the rows of the translation entity's
  fields omnibase's `FormView` gives (`Base\Field\Behaviour\Translation`, `RecordFields`), its values the record's; one language
  alone, its rows without tabs. `LaravelKinds` declares `translation` implemented; a translations field holding a field of a kind
  not drawn here (an editor, a wysiwyg) is refused aloud, naming that field. What is sent is written into the record's
  translations by omnibase's `FormBinder`. tests/FieldConformanceTest maps omnibase's entities (the sample's thread) and draws
  and binds a thread's translations. Docs: docs/views.md.
- **omnibase's remote search** (omnisense/omnibase's plan "omnibase agnostic", step F5d, slice 2): with `omnibridge.media` on and
  `omnibridge.forms.search` (true by default), the routes `ux_autocomplete` and `ux_autocomplete_icons` at the Symfony site's
  addresses, answered by `Form\SearchController` through omnibase's `Base\Field\Search\RemoteSearch` (in the web group for the
  session's token, which the search checks itself; `base.paginator` completed with omnibase's defaults). A record's picker and an
  icon picker are drawn: `LaravelKinds` declares `icon` implemented (`select2`); a select searched remotely is still refused where
  the application has no such route. A remote search's chosen records are printed by the words select2 shows them by.
  `View\OmnibaseExtensions::bindIcons()`: omnibase's icons bound where nothing binds them (the search's adapters, the filters').
  `tests/SearchTest`; `tests/FieldConformanceTest` with media on. Docs: docs/views.md.

### Changed

- `Container\Definitions`: an optional argument whose service is bound but cannot be built (omnibase's translator without its
  catalogues' translator) is left to its default or null, as an unbound one; a required one still says why.

- **omnibase's rich kinds drawn** (omnisense/omnibase's plan "omnibase agnostic", step F5d, slice 1): `Form\FormRenderer` draws the
  widget a field's neutral behaviour names - `datetimepicker`, `slug`, `boolean` (with its confirmation modal), `number2`, `select2`
  (the `choice` part, its options and selection printed), `password2` (`plain`, `plain_repeater`, the strength meter) - with the
  behaviour's settings, the ones omnibase's Symfony types read. `Form\LaravelKinds`: `MAP` (kind => widget) and `COVERAGE`, every
  kind implemented or unsupported with the reason; an unsupported or undeclared kind, or a select searched remotely without the
  route, is refused aloud (`Omnibridge\Form\UnsupportedKind`), never a plain input. `Form\Conformance\LaravelHost`, for omnibase's
  field conformance suite; `tests/FieldConformanceTest` (56 kinds). Docs: docs/views.md.

### Changed

- A checkbox is `required` when its view says so, and a single select without a placeholder is not, as Symfony's theme prints them.

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
