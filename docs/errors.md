---
title: Errors
order: 50
---

# Errors

The package has no exception class of its own: it throws PHP's and Symfony's, with messages that name
the configuration key to set.

| Exception | Thrown by | When |
|---|---|---|
| `LogicException` | `Doctrine\DBAL\Connection` (resolved) | `No Doctrine connection: omnibridge.doctrine.connection names none of config/database.php.` |
| `InvalidArgumentException` | the same | `No database connection "<name>" (omnibridge.doctrine.connection).`; `Doctrine\Connections`: `No Doctrine driver for Laravel's "<driver>" connections.` |
| `LogicException` | `Base\Bridge\Doctrine\Mapping` (resolved) | `omnibase's Doctrine mapping is off: omnibridge.doctrine.omnibase.` |
| `LogicException` | a repository class resolved | `No mapped entity names <class> as its repository (repositoryClass).` |
| `LogicException` | the media's parameters | `omnibase's media are off: omnibridge.media.` |
| `InvalidArgumentException` (a PSR-11 `NotFoundExceptionInterface`) | `Media\DiskStorages::get()` | `No storage "<id>": omnibridge.storages names no disk for it, and config/filesystems.php has no disk of that name.` |
| `LogicException` | `Media\ImageController` | a route that is no image route |
| `NotFoundHttpException` | `Base\Service\ImageRoute`, `Map\TileController`, `Screen\Responder::respond()` | an image that does not resolve, a missing tile (`No such map tile.`), an action that answered null (404) |
| `RuntimeException` | `Page\Theme::getLayout()` | `No layout: omnibridge.theme.layouts names no folder of layoutN.html.twig.` |
| `LogicException` | `Container\Aliases::of()` | `#[AsAlias] on "<class>" names no interface, and the class implements <n>.`; `... names no interface nor class.` |
| `InvalidArgumentException` | `Routing\ActionRoutes::action()` | `The route "<path>" names no action.`; `... no class "<class>".` |
| `LogicException` | `Routing\ActionRoutes::load()` | `The route of "<class>::<method>" has no prefix for "<locale>".` |
| `LogicException` | `Http\Localize` | the next middleware gave no response |
| `LogicException` | `Mail\LaravelMailer::send()` | `The mailer "<name>" gives no Symfony transport.` |
| `LogicException` | `View\OmnibaseExtensions` | an optional class it names is not installed |
| `BadMethodCallException` | `Form\FormView` | writing into a form view (read only) |
| `LogicException` | `Auth\AuthServiceProvider` (boot) | `config/omniauth.php turns "<key>" on, which needs <package>: composer require <package>.` |
| `LogicException` | `Omniauth\Contract\Throttle`, `Auth\RememberMe` resolved | `The throttle is off (config/omniauth.php: throttle).`, `The remember-me is off (config/omniauth.php: remember_me).` |
| `LogicException` | the Sender with omnibase's stores | omnibase's `NotifierSender` needs `Base\Notifier\Notifier` or omnibase 4.x's `SignInMails`: bind one, or a Sender of your own |
| `Omnibridge\Exception\MissingContractException` | `Runtime` | a contract the container does not bind (`Base\Contract\Media` with the media off) |
| Laravel's `RouteNotFoundException` / `InvalidArgumentException` | `RouterUrls::path()` | a route Laravel does not have and no `elsewhere()` |

A refused sign-in is no exception the application sees: `Auth\Controllers\SignIn` keeps an
`Auth\Refusal` (its message key and data, omniauth's failure and reason) in the session for the page
that follows, or answers a passkey's JSON with `{"status": "failed", ...}`.
