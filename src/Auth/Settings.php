<?php

namespace Omnibridge\Laravel\Auth;

use Omniauth\EnrolledSecondFactors;
use Omniauth\Factor;

/**
 * config/omniauth.php, resolved: what the guard, the controllers and the
 * routes read (omnibridge/symfony's OmniauthExtension::runtime() under
 * Symfony). Each method's key turns it on (null: off); the defaults are
 * those of omniauth.yaml, so one site says the same thing under both hosts.
 */
final class Settings
{
    /** @var array<string, mixed> */
    public readonly array $password;
    /** @var array<string, mixed>|null */
    public readonly ?array $link;
    /** @var array<string, mixed>|null */
    public readonly ?array $emailCode;
    /** @var array<string, mixed>|null */
    public readonly ?array $backupCodes;
    /** @var array<string, mixed>|null */
    public readonly ?array $totp;
    /** @var array<string, mixed>|null */
    public readonly ?array $trustedDevice;
    /** @var array<string, mixed>|null */
    public readonly ?array $passkey;
    /** @var array<string, mixed>|null */
    public readonly ?array $oauth;
    /** @var array<string, mixed> */
    public readonly array $secondFactor;
    /** @var array<string, mixed> */
    public readonly array $logout;
    /** @var array<string, mixed>|null */
    public readonly ?array $rememberMe;
    /** @var array<string, list<string>> */
    public readonly array $roleHierarchy;
    /** @var array<string, mixed>|null */
    public readonly ?array $throttle;
    /** @var array<string, string> */
    public readonly array $messages;
    /** @var string|array<string, string> */
    public readonly string|array $stores;
    /** @var array<string, array<string, mixed>> the identity providers, resolved (oauth.providers) */
    public readonly array $providers;
    public readonly string $secret;
    public readonly string $loginRoute;
    public readonly bool $routes;
    /** @var list<Factor> */
    public readonly array $after;

    /**
     * @param array<string, mixed> $config config/omniauth.php's values
     * @param string               $appKey Laravel's app.key, the secret when the config names none
     */
    public function __construct(array $config, string $appKey)
    {
        $secret = self::string($config['secret'] ?? null);
        $this->secret = '' !== $secret ? $secret : self::key($appKey);
        $this->loginRoute = self::string($config['login_route'] ?? null) ?: 'security_login';
        $stores = $config['stores'] ?? 'omnibase';
        if (\is_array($stores)) {
            $bindings = [];
            foreach ($stores as $key => $binding) {
                $bindings[(string) $key] = self::string($binding);
            }
            $this->stores = $bindings;
        } else {
            $this->stores = self::string($stores) ?: 'omnibase';
        }
        $this->routes = false !== ($config['routes'] ?? true);

        $password = self::section($config, 'password') ?? [];
        $this->password = $password + [
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
            'enabled' => \array_key_exists('password', $config) ? null !== $config['password'] && false !== $config['password'] : true,
        ];
        $link = self::section($config, 'link');
        $this->link = null === $link ? null : $link + [
            'route' => 'security_loginWithToken',
            'path' => '/login/token/{token?}',
            'parameter' => 'token',
            'lifetime' => 900,
            'template' => 'security/login_token.html.twig',
            'form' => 'login_token',
            'email' => 'email',
            'csrf' => ['field' => '_csrf_token', 'intent' => 'login_token'],
        ];
        $emailCode = self::section($config, 'email_code');
        $this->emailCode = null === $emailCode ? null : $emailCode + ['digits' => 6, 'lifetime' => 600];
        $backupCodes = self::section($config, 'backup_codes');
        $this->backupCodes = null === $backupCodes ? null : $backupCodes + ['count' => 8];
        $totp = self::section($config, 'totp');
        $this->totp = null === $totp ? null : $totp + ['leeway' => 0, 'period' => 30, 'digits' => 6, 'issuer' => null, 'server_name' => null, 'secret_key' => null];
        $trusted = self::section($config, 'trusted_device');
        $this->trustedDevice = null === $trusted ? null : $trusted + ['lifetime' => 2592000, 'cookie' => 'trusted_device', 'firewall' => 'main'];
        $passkey = self::section($config, 'passkey');
        $this->passkey = null === $passkey ? null : $passkey + [
            'rp_id' => null,
            'rp_name' => null,
            'origins' => [],
            'timeout' => null,
            'remember' => true,
            'options_path' => '/login/passkey/options',
            'options_route' => 'passkey_login_options',
            'path' => '/login/passkey',
            'route' => 'passkey_login',
        ];
        $oauth = self::section($config, 'oauth');
        $providers = [];
        if (null !== $oauth) {
            foreach (\is_array($oauth['providers'] ?? null) ? $oauth['providers'] : [] as $name => $provider) {
                $name = (string) $name;
                $providers[$name] = self::stringKeys(\is_array($provider) ? $provider : []) + [
                    'client_id' => '',
                    'client_secret' => '',
                    'redirect_uri' => null,
                    'service' => null,
                    'start_route' => 'oauth_'.$name,
                    'start_path' => '/connect/'.$name,
                    'check_route' => 'oauth_'.$name.'_check',
                    'check_path' => '/connect/'.$name.'/check',
                ];
            }
            $oauth['providers'] = $providers;
            $oauth += ['link_by_email' => null, 'sign_up_route' => null];
        }
        $this->oauth = $oauth;
        $this->providers = $providers;

        $second = self::section($config, 'second_factor') ?? [];
        $this->secondFactor = $second + [
            'form_path' => '/login/2fa',
            'form_route' => '2fa_login',
            'check_path' => '/login/2fa/check',
            'check_route' => '2fa_login_check',
            'template' => 'security/login_2fa.html.twig',
            'code' => '_auth_code',
            'trusted' => '_trusted',
            'csrf' => ['field' => '_csrf_token', 'intent' => 'two_factor'],
            'open_routes' => [],
        ];
        $after = $second['after'] ?? null;
        $this->after = \is_array($after) ? array_values(array_map(static fn (mixed $f): Factor => Factor::of(self::string($f)), $after)) : EnrolledSecondFactors::AFTER;

        $logout = self::section($config, 'logout') ?? [];
        $this->logout = $logout + [
            'path' => '/logout-request',
            'route' => 'security_logoutRequest',
            'page_path' => '/logout',
            'page_route' => 'security_logout',
            'template' => 'security/logout.html.twig',
            'csrf' => ['field' => '_csrf_token', 'intent' => 'logout'],
            'target' => '/',
        ];
        $remember = self::section($config, 'remember_me');
        $this->rememberMe = null === $remember ? null : $remember + ['cookie' => 'REMEMBERME', 'lifetime' => 604800];
        $hierarchy = [];
        foreach (\is_array($config['role_hierarchy'] ?? null) ? $config['role_hierarchy'] : [] as $role => $below) {
            $hierarchy[(string) $role] = array_values(array_map(self::string(...), (array) $below));
        }
        $this->roleHierarchy = $hierarchy;
        $throttle = self::section($config, 'throttle');
        $this->throttle = null === $throttle ? null : $throttle + ['store' => null, 'limits' => []];
        $messages = [];
        foreach (\is_array($config['messages'] ?? null) ? $config['messages'] : [] as $failure => $message) {
            $messages[(string) $failure] = self::string($message);
        }
        $this->messages = $messages;
    }

    public function passwordEnabled(): bool
    {
        return true === $this->password['enabled'];
    }

    /**
     * @param array<string, mixed> $section
     *
     * @return array{field: string, intent: string}|null a form's CSRF field and intent, null when the check is off
     */
    public function csrf(array $section): ?array
    {
        $csrf = $section['csrf'] ?? null;
        if (!\is_array($csrf) || false === ($csrf['enabled'] ?? true)) {
            return null;
        }

        return ['field' => self::string($csrf['field'] ?? null) ?: '_csrf_token', 'intent' => self::string($csrf['intent'] ?? null) ?: 'authenticate'];
    }

    /** @return array<string, string> the identity providers' check routes and the provider each one is */
    public function oauthRoutes(): array
    {
        $routes = [];
        foreach ($this->providers as $name => $provider) {
            $routes[self::string($provider['check_route'] ?? null)] = $name;
        }

        return $routes;
    }

    /** @return array<string, int> the digits of each second factor's codes, by omniauth's factor name */
    public function digits(): array
    {
        return ['totp' => self::int($this->totp['digits'] ?? null, 6), 'email-code' => self::int($this->emailCode['digits'] ?? null, 6)];
    }

    /** @return list<string> the routes a visitor between two factors may open */
    public function openRoutes(): array
    {
        $routes = [self::string($this->secondFactor['form_route']), self::string($this->secondFactor['check_route']), self::string($this->logout['route']), self::string($this->logout['page_route'])];
        foreach ((array) $this->secondFactor['open_routes'] as $route) {
            $routes[] = self::string($route);
        }

        return array_values(array_unique(array_filter($routes)));
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>|null the section's values, null when it is off (null or false)
     */
    private static function section(array $config, string $key): ?array
    {
        $section = $config[$key] ?? null;
        if (null === $section || false === $section) {
            return null;
        }

        return \is_array($section) ? self::stringKeys($section) : [];
    }

    public static function string(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    public static function int(mixed $value, int $default = 0): int
    {
        return \is_int($value) ? $value : (\is_scalar($value) && is_numeric($value) ? (int) $value : $default);
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<string, mixed>
     */
    public static function stringKeys(array $values): array
    {
        $out = [];
        foreach ($values as $key => $value) {
            $out[(string) $key] = $value;
        }

        return $out;
    }

    /** Laravel's app.key as bytes: "base64:..." decoded. */
    private static function key(string $appKey): string
    {
        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);

            return false === $decoded ? $appKey : $decoded;
        }

        return $appKey;
    }
}
