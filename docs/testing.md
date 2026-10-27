---
title: Testing
order: 60
---

# Testing

## Testing an application

Under Laravel's `TestCase` (or Orchestra Testbench for a package), the provider boots with the
application: `Runtime` is the application's container, and each test's new application replaces the
previous one. `Date::setTestNow()` and `$this->travel()` move `Runtime::clock()`. The sign-in is
tested as a visitor does it (`$this->post('/login', [...])`, then the second factor's page), with the
Tokens contract's token (`app(Tokens::class)->csrf('authenticate')`, the session's token). An
application that runs on both hosts compares their pages (lesringostars' parity suite:
`docker compose run --rm parity` in lesringostars-laravel).

## The package's suite

```sh
docker compose run --rm test
```

The service `test`: the image `omnibridge-laravel:php8.4`, the package at `/package`, a named volume
`composer-cache`; it runs `composer update --ignore-platform-req='ext-*'` (omnisense/omnibase asks for
extensions nothing here loads) then `vendor/bin/phpunit`. PHPUnit on Orchestra Testbench (Laravel 13),
PHP 8.4, omnisense/omnibase 4.x. With the checkout's `vendor/` installed, with no network:

```sh
docker run --rm --network none -v "$PWD":/package omnibridge-laravel:php8.4 vendor/bin/phpunit --do-not-cache-result
```

| Test class | What it checks |
|---|---|
| `ContractsTest` | `Runtime` and the container give the same objects; an extension of a contract is what the registry gives; the clock is Laravel's dates; an event goes to Laravel's listeners and comes back; the environment is Laravel's; Access is the gate and the visitor the guard's user; a session ends for the user signed in only; a token is the session's; routes say their pattern and which an address leads to; nouns read the entities' catalogues; files are the disks |
| `UrlsTranslationsTest` | a route is Laravel's and an unknown one answered elsewhere; a public file read in the manifest; Symfony's generator interface over the contract; a message in the page's language; whether the catalogues hold a key; the later folder wins; Twig renders the contract; the locales in omnibase's form |
| `ParametersTest` | the config read by its dotted path; a false setting wins over the config's true; without the overlay the config alone; the config itself turns the overlay on; a bag laid over all; a database that does not answer leaves the config; set writes the config or the bag |
| `DoctrinePageTest` | Doctrine opens Laravel's connection; the entity manager names tables as omnibase does; the site read in its settings; Encore tags and the breakpoints; a simple form in omnibase's markup |
| `OmnibaseMappingTest` | the entity manager is omnibase's; the application's own class wins; a class the application lacks is the core's by alias; the metadata are Symfony's (recorded from omnibase's Symfony harness); off by default |
| `RepositoriesTest` | a repository is the entity manager's own; one no entity names is refused |
| `EntitiesHostTest` | the attribute reader reads the files through omnibase's Flysystem; the cache pool is one and a repository is given it by type; BaseTrait's MediaService is the bound one; the editor's pictures are omnibase's image routes |
| `MediaTest` | the media off by default; Media bound to MediaService; the image routes declared under their Symfony names; a thumbnail made, read back and served; an image that does not resolve is not found; Pictures make thumbnails |
| `DrivesTest` | the disks are the drives by the storages' names; omnibase's Flysystem reads the same drives |
| `ScreensTest` | Runtime gives the screens' contracts; a picture is its address or the build's asset; a stored picture where the application serves it; an Editor.js text rendered as `\|wysiwyg` renders it; a picture of a text where the application serves it; an older text is Quill's HTML, its headings anchored; the frame is the request's locale, site, links, viewer and theme; a flash read once; a core screen built on Laravel without the application's code |
| `ResponderTest` | a template is its markup, or its data for a client preferring JSON; JSON data have their templates rendered and are encoded as Symfony's JsonResponse; no result is no page, a file sent as it is; an application renders each kind its way |
| `ActionRoutesTest` | the routes are Symfony's in Symfony's order; an address answers with the same route as on Symfony; an environment's routes are there; an action posted to gets its body and checks its own token; a name read as omnibase's router reads it; the aliases are the classes' attributes |
| `LocaleTest` | the page's language is the visitor's choice, else the browser's, else the default; the language shown remembered for the next page; the language menu keeps the choice and goes back |
| `PagePiecesTest` | the layouts are those of the folder; the request's attributes are its route's; a template's companions added; the filters are omnibase's; a redirect answered |
| `OmnibaseTwigTest` | the container's environment has omnibase's extensions; an application's own environment extended alike; the cookie answer on the site's configuration (skipped without omnibase/consent); the forms by their views; a record's back-office page where the routes are answered |
| `TwigAccessTest` | the page's functions are the contracts; the theme is omnibase's Themizer |
| `AuthTest`, `AuthOffTest`, `PasswordHasherTest`, `CookieDomainTest` | every way of signing in, the second factor, refusals, throttle, remember-me, trusted devices, TOTP sealed at rest, a simulated provider; nothing of `Auth\` without `omnibridge.auth`; the hashers; the sign-in cookie's domain |
| `MapTest` | a page has its map and the tiles are served (OpenStreetMap's tiles answered in the test; GD in the image) |
| `ShieldMailTest` | a MIME e-mail sent by Laravel's mailer; the gateways of `config/omnishield.php` and ALTCHA's script; XLIFF catalogues read; markup given whole printed with the rest of the form |
