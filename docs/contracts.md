---
title: Contracts
order: 20
---

# Contracts

Every binding of `OmnibridgeServiceProvider::register()`, all singletons unless said otherwise. The
registry (`Base\Contract\Runtime`, omnisense/omnibridge's `Omnibridge\Runtime` with omnibase's
accessors) is handed Laravel's application at boot: `Runtime::urls()` is `app(Urls::class)`.

## The bindings

| Abstract | Concrete | Built from |
|---|---|---|
| `Omnibridge\Laravel\RouterUrls` (alias `Omnibridge\Contract\Urls`) | `RouterUrls` | `omnibridge.assets.manifest` |
| `Omnibridge\Laravel\CatalogueTranslations` (alias `Omnibridge\Contract\Translations`) | `CatalogueTranslations` | `omnibridge.translations.paths`, `.fallback` (else `app.fallback_locale`, else `en`) |
| `Omnibridge\Contract\Locales` | `Contract\AppLocales` | `omnibridge.locales.available`, `.default` |
| `Base\Contract\Nouns` | `Base\Service\Model\EntityNouns` | the Translations, `omnibridge.entity_namespaces` |
| `Omnibridge\Contract\Access` | `Contract\GateAccess` | the gate, the auth manager |
| `Omnibridge\Contract\Sessions` | `Contract\GuardSessions` | the auth manager |
| `Omnibridge\Contract\Tokens` | `Contract\SessionTokens` | the session store |
| `Omnibridge\Contract\Environment` | `Contract\AppEnvironment` | `$app->environment()`, `app.debug`, `app.key` |
| `Omnibridge\Contract\Routes` | `Contract\RouterRoutes` | the router |
| `Omnibridge\Contract\Files` | `Contract\DiskFiles` | the filesystem manager, `omnibridge.storages` |
| `Omnidrive\Flysystem\OperatorDrives` (alias `Omnidrive\Drives`) | | each disk's Flysystem operator by its storage name (`Media\DiskStorages`) |
| `Psr\Clock\ClockInterface` | `Contract\DateClock` | Laravel's `Date` |
| `Psr\EventDispatcher\EventDispatcherInterface` | `Contract\DispatcherEvents` | Laravel's dispatcher |
| `Symfony\Component\Mailer\MailerInterface` | `Contract\TransportMailer` | the default mailer's Symfony transport |
| `Base\Contract\Pictures` | `Screen\AddressPictures` | the Urls, the public folder, `MediaService` when `omnibridge.media` is on |
| `Base\Contract\Texts` | `Screen\WysiwygTexts` | the container's Twig (lazily) |
| `Base\Screen\ContextProvider` (bind, not shared) | `Screen\ScreenContext` | the application |
| `Base\Screen\Negotiation` (bind) | omnibase's `Negotiation` | the container's Twig for every template, the context provider, `omnibridge.screens.version` |
| `Omnibridge\Laravel\Screen\Responder` (bind) | | the Negotiation |
| `Symfony\Component\Cache\Adapter\FilesystemAdapter` (aliases `Psr\Cache\CacheItemPoolInterface`, `Symfony\Contracts\Cache\CacheInterface`) | | namespace `omnibase`, in `storage/framework/cache/omnibase-pool` |
| `Base\Service\ParameterBagInterface` | `Base\Service\HotParameters` over `Parameters\ConfigParameters` | Laravel's config, the settings laid over it ([Parameters](#parameters)) |
| `Doctrine\DBAL\Connection`, `Parameters\DatabaseSettings`, `Doctrine\ORM\EntityManagerInterface`, `Base\Bridge\Doctrine\Mapping` | | [Doctrine and images](doctrine-media.md) |
| `Twig\Environment`, `View\OmnibaseExtensions` | | [Pages](views.md) |

With `omnibridge.media` on, at boot: `Media\LaravelMediaRouter`, `Base\Service\Obfuscator` (alias
`ObfuscatorInterface`), `Base\Service\Flysystem`, `Base\Service\MediaService`,
`Base\Service\ImageRoute`, `Base\Contract\Media` (`Media\ServiceMedia`), `MediaEnhancerInterface`,
and the image routes ([Doctrine and images](doctrine-media.md#omnibases-images)).

An application rebinds any of them in its own provider (`$this->app->singleton(Urls::class, ...)`), or
extends one (`$this->app->extend(RouterUrls::class, fn ($urls) => $urls->elsewhere(...))`).

## The adapters

### RouterUrls

- `path($name, $parameters)`: Laravel's `route($name, $parameters, false)` - the parameters that are
  null left out. A name Laravel does not have goes to `elsewhere()`'s closure when one is given
  (`fn (string $name, array $parameters, string $locale): string`), else Laravel's error.
- `url()`: the path at the request's scheme and host (an address the closure gave whole kept as it is).
- `asset($path)`: the manifest's entry (a webpack or Vite manifest: `./fonts/RobotoSlab-Bold.woff2`
  is `/assets/fonts/RobotoSlab-Bold.9d4f57b2.woff2`), else `/<path>`.
- `localized(string $fallbackLocale = 'en')`: a copy that reads a name as omnibase's router does
  (`Routing\LocalizedNames`): `app_timeline` is `app_timeline.fr` on a French page, then the name
  itself, then `app_timeline.default.fr`, `app_timeline.default`, then the fallback language's version.
  Not on by default: the bound object reads names as Laravel does.
- `resolve($name)`: the route a name means here, null for none.

### CatalogueTranslations

The ICU catalogues of the folders given - `<domain>+intl-icu.<locale>.yaml`, `<domain>.<locale>.yaml`,
and XLIFF files (`*.xlf`: each unit's source and target, read without symfony/config) - by Symfony's
Translation component, the later folders over the earlier, in Laravel's current locale unless one is
given.

- A message is named as omnibase's templates name it: `@messages.menu.item2` is `menu.item2` of the
  `messages` domain; the parameters by position (`{0}`) or by name (`{privacy}`), given both as
  `{name}` and `name`.
- A key no catalogue holds (a dotted key) is answered `''`, as omnibase's Translator answers it; a text
  that is no key is answered as it was asked.
- A message that is itself a key is followed, five times at most.
- `exists()`: the catalogue of the locale or a fallback defines the key, in the domain or its
  `+intl-icu` one.
- `translator()` gives Symfony's `Translator`, `locale()` the current locale.

### AppLocales

Laravel's locale (`fr`, `fr_FR`) as the site's (`fr-FR`): the available locale it names, else the first
of the same language, else itself with a dash. The default is `omnibridge.locales.default`, else the
first available, else `app.locale`; `getAvailableLocales()` puts it first.

### GateAccess, GuardSessions

`isGranted($attribute, $subject)` is `Gate::allows()` (an enumeration as it is, anything else as a
string; the subject as the only argument). With the sign-in on, `Auth\Roles` answers Security's
attributes through `Gate::before()` ([Signing in](omniauth.md#roles-and-the-gate)). `getUser()` is the
default guard's user, unwrapped from `Auth\AccountUser` to the application's own object.
`signOut($user)` logs out a `StatefulGuard` whose signed-in user is that very object.

### SessionTokens

Laravel has one session token for every intent: `csrf($intent)` is the session's token whatever the
intent; `isValid()` compares it in constant time, never valid for a missing or empty token or session
token. Both are bound to the session and renewed with it. A page then carries the same value for every
intent (its forms, `data-csrf`, the `X-CSRF-Token` headers, the sign-out link).

### AppEnvironment, DateClock, DispatcherEvents, TransportMailer

`name()` is `$app->environment()` (`production`, `local`, `testing`: Laravel's names, not Symfony's);
`DateClock::now()` follows `Date::setTestNow()` and `travel()`; `DispatcherEvents::dispatch()` hands the
object to Laravel's listeners of its class and returns it; `TransportMailer::send()` uses the default
mailer's Symfony transport (`Mail\LaravelMailer` does the same for a named mailer, for the sign-in's
e-mails).

### RouterRoutes

`pattern($name)`: `/` and the route's URI, else `elsewhere()`'s answer; `match($url)`: the name of the
route a GET of the address matches, null for none; `icons()`: none of Laravel's own - `elsewhere($routes)`
gives another `Routes` for the routes Laravel lacks and the icons.

### DiskFiles

A storage name is the disk `omnibridge.storages` maps it to, else the disk of that name, else
`omnibridge.storages.default`, else `filesystems.default`. A disk whose driver is `local` is local
(`localPath()` its path); any other is remote. `publicUrl()` is the disk's `url()` of an existing file
(its path alone for a local disk), null for a local disk without `url` or a missing file;
`publicPath()` that URL under `public_path()` for a local disk.

## Parameters

`Runtime::parameters()` (`Base\Service\ParameterBagInterface`) reads Laravel's config by the same
dotted path (`app.name`; `base.user.delay` for a site with `config/base.php`), and, when
`omnibridge.parameters.hot` is on (or, null, the config's own `base.parameter_bag.use_hot_bag`) and a
Doctrine connection is configured, the settings the back office links to a parameter, read once in
omnibase's tables (`layoutSetting`, `layoutSettingIntl`, not the vaulted rows) on the first call, in
the default language's value: a setting stored as `false` wins over the config's `true`, as under
Symfony. A database that does not answer leaves the config alone. `ConfigParameters::get($key, $bag)`
lays a bag over the value key by key, as omnibase's bags do; `all()` is every parameter by its full
name. Laravel's `config` binding itself is not decorated.
