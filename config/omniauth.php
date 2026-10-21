<?php

// omnibridge/laravel's sign-in through omniauth (Omnibridge\Laravel\Auth\, plan "omniauth" §3.2,
// step AU8), on when config/omnibridge.php says 'auth' => true and omniscient/omniauth is
// installed. The same keys as omnibridge/symfony's config/packages/omniauth.yaml, so one site
// says the same thing under both hosts: each method's key turns it on (null: off); a method
// whose package is missing (omniauth/passkey, omniauth/totp, omniauth/oauth) cannot be.
// php artisan vendor:publish --tag=omniauth-config

return [
    // Keys the e-mailed codes' hashes and the trusted devices' tokens: the Symfony site's
    // kernel.secret, for the devices it trusted and the codes it sent to be good here (app.key
    // when null).
    'secret' => null,

    // The sign-in page's route: where a refused or abandoned attempt goes back to.
    'login_route' => 'security_login',

    // The stores: "omnibase" binds omnibase's Base\Auth\ stores on the entity manager (the
    // user, userToken, userPasskey and userIdentity tables) where the container does not bind
    // the contract already; or the container's bindings by key (accounts, passwords,
    // link_tokens, email_codes, backup_codes, trusted_version, totp_secrets, identities,
    // passkeys, sender), each contract's own name for a key left out.
    'stores' => 'omnibase',

    // The password (null: off): the sign-in page's address (GET shows it, POST signs in), the
    // form's name and fields (the fields at the top of the request when the name is null), its
    // CSRF field and intent (checked by the Tokens contract: Laravel's session token), where a
    // visitor goes once signed in, the page's template (rendered by omnibase's Negotiation, the
    // application's page around it), whether it offers the sign-in link, and the hasher
    // (symfony/password-hasher's native one, bcrypt at cost 13, when null; otherwise what
    // security.yaml's password_hashers says, read by Symfony's PasswordHasherFactory:
    // ['algorithm' => 'auto'|'bcrypt'|'argon2id'|..., 'cost' => 13, 'migrate_from' => [...]]).
    'password' => [
        'path' => '/login',
        'form' => null,
        'identifier' => '_username',
        'password' => '_password',
        'remember' => '_remember_me',
        'target_path_parameter' => '_target_path',
        'default_target_path' => '/',
        'csrf' => ['field' => '_csrf_token', 'intent' => 'authenticate'],
        'template' => 'security/login.html.twig',
        'allow_login_token' => true,
        'hasher' => null,
    ],

    // The sign-in link (null: off; needs a Sender): its page's route and address (a GET with
    // a token signs in; without one the page asks for an address, and POST sends the link), the
    // link's life in seconds (15 minutes, once, kept hashed: decision QA4).
    'link' => null,          // ['route' => 'security_loginWithToken', 'path' => '/login/token/{token?}', 'parameter' => 'token', 'lifetime' => 900, 'template' => 'security/login_token.html.twig', 'form' => 'login_token', 'email' => 'email', 'csrf' => ['field' => '_csrf_token', 'intent' => 'login_token']]

    // The second factors: a code by e-mail (kept hashed; needs a Sender), backup codes, the
    // authenticator application (omniauth/totp: scheb/2fa's parameters), and the trusted
    // devices in scheb/2fa's very cookie (its "fwl" claim: 'firewall', "main" by default).
    'email_code' => null,    // ['digits' => 6, 'lifetime' => 600]
    'backup_codes' => null,  // ['count' => 8]
    'totp' => null,          // ['leeway' => 0, 'period' => 30, 'digits' => 6, 'issuer' => null, 'server_name' => null]
    'trusted_device' => null, // ['lifetime' => 2592000, 'cookie' => 'trusted_device', 'firewall' => 'main']

    // Passkeys (omniauth/passkey): the relying party (its id: the registrable domain both hosts
    // share, decision QA9; the request's host when null), the addresses the site's script posts
    // to (web-auth's JSON: {"status": "ok", "errorMessage": "", ...}), their route names, whether
    // the browser is remembered after one.
    'passkey' => null,       // ['rp_id' => null, 'rp_name' => null, 'origins' => [], 'timeout' => null, 'remember' => true, 'options_path' => '/login/passkey/options', 'options_route' => 'passkey_login_options', 'path' => '/login/passkey', 'route' => 'passkey_login']

    // Identity providers (omniauth/oauth, each provider a package: omniauth/google). Each one's
    // return address is the PATH of its check route unless it sets one (redirect_uri): omniauth
    // puts it on the host of each trip's request, so that under a long-running worker the
    // visitor comes back to the host they left from. link_by_email: true, false, or a binding
    // (omniauth/oauth's EmailLinking; null: never an account with a second factor);
    // sign_up_route: where an identity no account is linked to goes (its PendingIdentity in the
    // session, Omnibridge\Laravel\Auth\Controllers\SignIn::PENDING_IDENTITY).
    'oauth' => null,         // ['link_by_email' => null, 'sign_up_route' => null, 'providers' => ['google' => ['client_id' => ..., 'client_secret' => ..., 'scopes' => ['openid', 'email', 'profile'], 'hosted_domain' => null, 'start_path' => '/connect/google', 'start_route' => 'oauth_google', 'check_path' => '/connect/google/check', 'check_route' => 'oauth_google_check', 'redirect_uri' => null, 'service' => null]]

    // The second factor's page and check (scheb/2fa's addresses and route names, its template's
    // variables), the first factors a second one follows (omniauth's EnrolledSecondFactors::AFTER
    // when null: password, link, oauth - not a passkey, decision QA1), the routes a visitor
    // between two factors may still open (the page, its check and the sign-out always are;
    // every other request is sent to the page).
    'second_factor' => [
        'after' => null,
        'form_path' => '/login/2fa',
        'form_route' => '2fa_login',
        'check_path' => '/login/2fa/check',
        'check_route' => '2fa_login_check',
        'template' => 'security/login_2fa.html.twig',
        'code' => '_auth_code',
        'trusted' => '_trusted',
        'csrf' => ['field' => '_csrf_token', 'intent' => 'two_factor'],
        'open_routes' => [],
    ],

    // The sign-out, CSRF-checked as Security's logout with enable_csrf: the request's address
    // with the session's token (the intent's) signs out and goes to the target (a route name, or
    // a path); without the token, 403. The page asks to confirm, by a link that carries a token.
    'logout' => [
        'path' => '/logout-request',
        'route' => 'security_logoutRequest',
        'page_path' => '/logout',
        'page_route' => 'security_logout',
        'template' => 'security/logout.html.twig',
        'csrf' => ['field' => '_csrf_token', 'intent' => 'logout'],
        'target' => '/',
    ],

    // The remember-me cookie (null: none): signed by the secret - the account's handle, its
    // expiry, its password hash and address (Security's signature_properties) - so that a new
    // password forgets every browser.
    'remember_me' => ['cookie' => 'REMEMBERME', 'lifetime' => 604800],

    // Roles as the gate's abilities: "ROLE_ADMIN" is granted to an account that holds it, or one
    // above it (security.yaml's role_hierarchy); IS_AUTHENTICATED_FULLY, IS_AUTHENTICATED_REMEMBERED,
    // IS_AUTHENTICATED_2FA_IN_PROGRESS and PUBLIC_ACCESS answer as Security's voters do.
    'role_hierarchy' => [],

    // The tries, counted on a Laravel cache store (null: the default one) by omniauth's
    // CacheThrottle, its limits by scope ([tries, seconds]: ['password' => [5, 60]]); false: none.
    'throttle' => ['store' => null, 'limits' => []],

    // The page's words for a refusal, by omniauth's failure or "failure:reason" ("%reason%"
    // replaced), as the page translates them (the "security" domain).
    'messages' => [],

    // false: declare the routes yourself (Omnibridge\Laravel\Auth\Routes::register()).
    'routes' => true,
];
