---
title: Signing in (omniauth)
order: 33
---

# Signing in (omniauth)


```php
// config/omnibridge.php
'auth' => true,
// config/auth.php
'guards' => ['web' => ['driver' => 'omniauth', 'provider' => 'omnibase']],
'providers' => ['omnibase' => ['driver' => 'omniauth']],
```

With `omnibridge.auth` on and [omnisense/omniauth](https://github.com/glitchr-omnisense/omniauth)
installed (it is only suggested), `Auth\AuthServiceProvider` wires omniauth's sign-in
under Laravel as omnibridge/symfony wires it under Symfony (plan "omniauth", §3.2, step
AU8), from `config/omniauth.php` (`php artisan vendor:publish --tag=omniauth-config`) -
the same keys as `config/packages/omniauth.yaml` there, so one site says the same thing
under both hosts: each method's key turns it on (the password, the sign-in link, the
e-mailed code, the backup codes, the trusted devices; passkeys, the authenticator
application and the identity providers with their package, omniauth/webauthn,
omniauth/otp and omniauth/google - the providers' mechanics are omniauth's own `Omniauth\OAuth\`), the stores (`"omnibase"`: the core's
`Base\Auth\` on the entity manager - the user, userToken, userPasskey and userIdentity
tables -, or the container's bindings by key), the throttle on a Laravel cache store, the
messages. `totp.secret_key` (`env('OMNIAUTH_TOTP_KEY')`: base64 of 32 bytes, the Symfony
site's very key, never in the repository) wraps the `TotpSecrets` store in omniauth/otp's
`SealedTotpSecrets` over a `SodiumSecretBox`: the secrets sealed at rest, as omnibridge/symfony
decorates its store.

- `Auth\OmniauthGuard` (`StatefulGuard`, driver `omniauth`) keeps the account's handle
  in the session, reads the account back through omniauth's `Accounts` at each request,
  renews the session's id at a sign-in, sets the signed remember-me cookie
  (`Auth\RememberMe`: the handle, the expiry, the password hash and the address, as
  Security's `signature_properties`) and forgets it at a sign-out; `attempt()` asks
  omniauth the password. `Auth\OmniauthUserProvider` (driver `omniauth`) answers
  Laravel's `UserProvider` over the same stores, the passwords checked by
  symfony/password-hasher as under Symfony (>72-byte passwords included).
- The account is Laravel's `Authenticatable` through `Auth\AccountUser`, a wrapper the
  contracts unwrap: `Omnibridge\Contract\Access::getUser()`, the screens' viewer and
  Twig's `app.user` give the application's own object, as under Symfony.
- `Auth\Controllers\SignIn` is every way in: the password form (and its page, the
  site's `security/login.html.twig` with the form as omnibase's templates read it), the
  sign-in link, a passkey (web-auth's JSON), the second factor's code, an identity
  provider's return, and the sign-out (CSRF-checked as Security's logout with
  `enable_csrf`); `Auth\Controllers\Ceremony` the second factor's page (scheb/2fa's
  variables: the site's template renders unchanged), a passkey's options, the trip out
  to a provider. The forms' CSRF tokens are checked through the Tokens contract
  (Laravel's session token); Laravel's own CSRF middleware is left off these routes.
- `Auth\Routes` declares them at the Symfony site's addresses and under its route names
  (`security_login`, `2fa_login`, `2fa_login_check`, `oauth_google`,
  `oauth_google_check`, `security_logout`, `security_logoutRequest`...), in the web
  group; `omniauth.routes` false to declare them yourself.
- `Auth\Middleware\SecondFactorPending` (appended to the web group, before `auth`)
  keeps a visitor between two factors on the second factor's page; with omnibase's sign-in
  effects, `Auth\Middleware\EnrolmentPrompt` sends a signed-in account to the second factor's
  enrolment page as omnibase's `SecurityEnrolmentSubscriber` does under Symfony (its rule,
  `Base\Auth\EnrolmentPrompt`: the offer after a new device, a required second factor until
  "not now"), and the browser's PHPUSERID is written for the domain omnibase's router gives its
  cookies (`Base\Routing\CookieDomain` on `base.router.use_fallbacks` and `host_fallbacks`); `Auth\Roles`
  answers Security's attributes through `Gate::before()` - `ROLE_*` with
  `omniauth.role_hierarchy`, `IS_AUTHENTICATED_FULLY`, `IS_AUTHENTICATED_REMEMBERED`,
  `IS_AUTHENTICATED_2FA_IN_PROGRESS`, `PUBLIC_ACCESS` - so `is_granted()` and the
  Access contract answer as under Symfony.
- The cookies the Symfony site shares (`trusted_device` in scheb/2fa's format, the
  remember-me) are left out of Laravel's cookie encryption; `omniauth.secret` is the
  Symfony site's `kernel.secret`, so the devices it trusted and the codes it sent are
  good here. An identity provider's return address is the path of its check route,
  completed with the host of each request (a long-running server keeps the providers).

With omnibase's stores, the core's `Base\Auth\Listener\UpdateAccount` brings the
account up to date at its sign-in (a role dropped, its time zone and language kept, its
kick lifted); `Base\Auth\Policy` decorates the second-factor rule where the
application binds omnibase's `SecurityPolicy`; a sign-in link or an e-mailed code needs
a `Omniauth\Contract\Sender` (omnibase's `NotifierSender` needs its Notifier).

What each method does, its stores and events are omniauth's own manual
([omniauth's Laravel page](https://github.com/glitchr-omnisense/omniauth/blob/1.x/docs/laravel.md));
this page is the reference of the bridge.

## Turning it on

`Auth\AuthServiceProvider` is registered by `OmnibridgeServiceProvider` when omnisense/omniauth is
installed; it merges `config/omniauth.php` and binds its services in `register()`, and wires the guard,
the gate, the routes and the middleware in `boot()` only when `omnibridge.auth` is true
(`AuthServiceProvider::on()`). A method whose package is missing stops the boot: `config/omniauth.php
turns "<key>" on, which needs <package>: composer require <package>.`

## config/omniauth.php

The same keys as omnibridge/symfony's `omniauth.yaml`. A method's key turns it on; null (or absent) is
off - but `password`, on unless it is null or false. The defaults below are `Auth\Settings`', laid
under what the file says.

| Key | Default | Effect |
|---|---|---|
| `secret` | null: `app.key` (base64-decoded when written `base64:`) | keys the e-mailed codes' hashes, the trusted devices' tokens and the remember-me signature: the Symfony site's `kernel.secret`, for its devices and codes to be good here |
| `login_route` | `security_login` | where a refused or abandoned attempt goes back to |
| `stores` | `omnibase` | `omnibase`: omnibase's `Base\Auth\` stores on the entity manager (`UserAccounts`, `UserPasswords`, `UserLinkTokens`, `UserEmailCodes`, `UserBackupCodes`, `UserTrustedVersion`, `UserTotpSecrets`, `UserIdentities`, `UserPasskeys`) where the container does not bind the contract already, and omnibase's `NotifierSender` as the Sender; or an array key => binding (`accounts`, `passwords`, `link_tokens`, `email_codes`, `backup_codes`, `trusted_version`, `totp_secrets`, `identities`, `passkeys`, `sender`), each contract's own name for a key left out |
| `password.path` | `/login` | the sign-in page (GET shows it, POST signs in), route `login_route` |
| `password.form` | null | the form's name, its fields read under it; null: at the top of the request |
| `password.identifier`, `.password`, `.remember` | `_username`, `_password`, `_remember_me` | the fields |
| `password.target_path_parameter`, `.default_target_path` | `_target_path`, `/` | where a visitor goes once signed in |
| `password.csrf` | `{field: _csrf_token, intent: authenticate}` | checked by the Tokens contract |
| `password.template` | `security/login.html.twig` | the page, rendered by omnibase's Negotiation |
| `password.allow_login_token` | true | whether the page offers the sign-in link |
| `password.hasher` | null: symfony/password-hasher's native hasher | otherwise `security.yaml`'s `password_hashers` keys (`algorithm`: `auto`, `bcrypt`, `argon2id`...; `cost`, `time_cost`, `memory_cost`, `migrate_from`), read by Symfony's `PasswordHasherFactory` |
| `link` | null | `{route: security_loginWithToken, path: /login/token/{token?}, parameter: token, lifetime: 900, template: security/login_token.html.twig, form: login_token, email: email, csrf: {field: _csrf_token, intent: login_token}}`: a GET with a token signs in, without one the page asks for an address, a POST sends the link (needs a Sender) |
| `email_code` | null | `{digits: 6, lifetime: 600}` (needs a Sender) |
| `backup_codes` | null | `{count: 8}` |
| `totp` | null | `{leeway: 0, period: 30, digits: 6, issuer: null, server_name: null, secret_key: null}` (omniauth/otp); `secret_key` seals the secrets at rest (`omniauth.totp.sealed_secrets` wraps `TotpSecrets`) |
| `trusted_device` | null | `{lifetime: 2592000, cookie: trusted_device, firewall: main}`: scheb/2fa's very cookie, its `fwl` claim |
| `passkey` | null | `{rp_id: null (the request's host), rp_name: null, origins: [], timeout: null, remember: true, options_path: /login/passkey/options, options_route: passkey_login_options, path: /login/passkey, route: passkey_login}` (omniauth/webauthn) |
| `oauth` | null | `{link_by_email: null, sign_up_route: null, providers: {<name>: {client_id, client_secret, scopes, hosted_domain, redirect_uri: null, service: null, start_path: /connect/<name>, start_route: oauth_<name>, check_path: /connect/<name>/check, check_route: oauth_<name>_check}}}`; omniauth/google's `GoogleFactory` when installed, any factory tagged `omniauth.oauth_provider_factory` |
| `second_factor` | `{after: null (password, link, oauth), form_path: /login/2fa, form_route: 2fa_login, check_path: /login/2fa/check, check_route: 2fa_login_check, template: security/login_2fa.html.twig, code: _auth_code, trusted: _trusted, csrf: {field: _csrf_token, intent: two_factor}, open_routes: []}` | the second factor's page and check; `open_routes`: the routes a visitor between two factors may still open (the page, its check and the sign-out always are) |
| `logout` | `{path: /logout-request, route: security_logoutRequest, page_path: /logout, page_route: security_logout, template: security/logout.html.twig, csrf: {field: _csrf_token, intent: logout}, target: /}` | the sign-out, CSRF-checked as Security's logout with `enable_csrf` (403 without the token); its page asks to confirm by a link carrying one |
| `remember_me` | `{cookie: REMEMBERME, lifetime: 604800}` | the signed remember-me cookie; null: none |
| `role_hierarchy` | `[]` | `security.yaml`'s `role_hierarchy` |
| `throttle` | `{store: null, pool: null, limits: []}` | omniauth's `CacheThrottle` on a Laravel cache store (the default one when null), or on `pool`, a PSR-6 pool's binding (the Symfony site's, so both count the same tries; the used TOTP steps kept there too); `limits.<scope>`: `[tries, seconds]` over omniauth's defaults; false: none |
| `captcha` | `{pool: null, firewalls: [main]}` | with omnibase's stores and effects: the captcha after failed sign-ins (`Base\Security\SignInCaptcha`, its gateway `config/omnishield.php`'s), the failures counted in that pool (Laravel's cache store without one), for these Context firewalls |
| `mail` | `{mailer: null, from: null, loopback: false, test_recipients: [], project_dir: null}` | the sign-in's e-mails (`Base\Notifier\Mail\SignInMails`, sent by `config/mail.php`'s mailer): from the settings' address, else `from`; `loopback` sends them all to that address; `test_recipients` (patterns) in debug; `project_dir` where the e-mails' pictures are |
| `messages` | `[]` | a refusal's words by omniauth's failure or `failure:reason`, over `Auth\Refusal::MESSAGES` (omnibridge/symfony's) |
| `routes` | true | false: declare them yourself (`Auth\Routes::register($router, $settings)`) |

## The routes

`Auth\Routes::register()`, in the web group, without Laravel's CSRF middleware (`PreventRequestForgery`,
`ValidateCsrfToken`, `VerifyCsrfToken`, `App\Http\Middleware\VerifyCsrfToken`) - the forms' tokens are
checked through the Tokens contract:

| Name (default) | Path | Methods | Action |
|---|---|---|---|
| `security_login` (`login_route`) | `/login` | GET, POST | `Controllers\SignIn::login` (with the password on) |
| `security_loginWithToken` | `/login/token/{token?}` | GET; POST | `SignIn::link`; `SignIn::requestLink` (with `link`) |
| `2fa_login` | `/login/2fa` | GET | `Controllers\Ceremony::secondFactorPage` |
| `2fa_login_check` | `/login/2fa/check` | GET, POST | `SignIn::secondFactorCheck` |
| `passkey_login_options` | `/login/passkey/options` | POST | `Ceremony::passkeyOptions` (with `passkey`) |
| `passkey_login` | `/login/passkey` | POST | `SignIn::passkey` |
| `oauth_<name>` | `/connect/<name>` | GET | `Ceremony::oauthStart` |
| `oauth_<name>_check` | `/connect/<name>/check` | GET | `SignIn::oauthCheck` |
| `security_logout` | `/logout` | GET | `SignIn::logoutPage` |
| `security_logoutRequest` | `/logout-request` | GET, POST | `SignIn::logout` |

The pages are rendered through the bound `Base\Screen\Negotiation` (`Auth\Pages`), never cached
(`Cache-Control: no-store, private`). The second factor's page has scheb/2fa's variables, as under
Symfony.

## The bindings

| Abstract | What it is |
|---|---|
| `Auth\Settings` | `config/omniauth.php` resolved |
| `Auth\SessionState` (alias `Omniauth\Contract\State`) | the attempt in Laravel's session |
| `Auth\Contexts` | omniauth's `Context` of a request (the trusted-device cookie, the firewall `trusted_device.firewall`, else `main`) |
| `Omniauth\Contract\Throttle` | `CacheThrottle` (`LogicException` when the throttle is off) |
| `omniauth.second_factors`, `Omniauth\Method\Methods` | the second factors; every method |
| `Omniauth\Contract\SecondFactorPolicy` | `EnrolledSecondFactors`, wrapped in omnibase's `Base\Auth\Policy` when the stores are omnibase's and `Base\Service\SecurityPolicy` is bound |
| `Omniauth\Omniauth` | the conductor: accounts, methods, state, policy, throttle, the PSR-14 dispatcher, the clock |
| `Symfony\Component\PasswordHasher\PasswordHasherInterface` | per `password.hasher` |
| `Auth\RememberMe` | the signed cookie (`LogicException` when off) |
| `Auth\Roles`, `Auth\Pages`, `Auth\OmniauthUserProvider` | below |
| `Omniauth\OAuth\Provider\Providers` | the providers through the factories |
| `omniauth.totp.sealed_secrets` (alias `TotpSecrets`) | with `totp.secret_key` |

## The guard and the provider

```php
// config/auth.php
'guards' => ['web' => ['driver' => 'omniauth', 'provider' => 'omnibase']],
'providers' => ['omnibase' => ['driver' => 'omniauth']],
```

`Auth\OmniauthGuard` (driver `omniauth`, a `StatefulGuard`) and `Auth\OmniauthUserProvider` (driver
`omniauth`), as the README above says. The guard follows the request (`setRequest()`, refreshed with
it). Laravel's `EncryptCookies` leaves the `trusted_device` and remember-me cookies out (`except()`), as
the Symfony site writes them plain.

## Roles and the gate

`Auth\Roles::decide()` answers through `Gate::before()`:

| Ability | Granted to |
|---|---|
| `PUBLIC_ACCESS`, `IS_AUTHENTICATED_ANONYMOUSLY` | everybody |
| `IS_AUTHENTICATED`, `IS_AUTHENTICATED_REMEMBERED` | any account signed in |
| `IS_AUTHENTICATED_FULLY` | an account signed in by its proofs, not by its remember-me cookie |
| `IS_AUTHENTICATED_2FA_IN_PROGRESS` | the visitor between two factors |
| `ROLE_*` | an account holding the role, or one above it in `role_hierarchy` |
| anything else | the gate's own definitions (`decide()` answers null) |

## The middleware

- `Auth\Middleware\SecondFactorPending`, appended to the web group and placed before Laravel's
  `AuthenticatesRequests` middleware: a visitor between two factors is redirected to
  `second_factor.form_route` from any route but `Settings::openRoutes()` (the page, its check, the
  sign-out and `open_routes`).
- `Auth\Middleware\EnrolmentPrompt`, appended with omnibase's sign-in effects
  (`Auth\OmnibaseEffects::on()`): a plain page asked by a signed-in account that omnibase's rule
  (`Base\Auth\EnrolmentPrompt`) sends to its second factor's enrolment is redirected to
  `user_settings_enrolment`; nothing without the rule, the policy or the route.
