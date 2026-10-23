<?php

namespace Omnibridge\Laravel\Auth;

use Illuminate\Contracts\Container\Container;
use Omniauth\OAuth\Provider\Provider;
use Omniauth\OAuth\Provider\ProviderFactory;
use Omniauth\OAuth\Provider\Providers;

/**
 * omniauth's identity providers from config/omniauth.php: Providers::configured()
 * through the provider packages' factories (omniauth/google's GoogleFactory;
 * a provider without client id is not served), and a provider given as a
 * binding ("service") as it is.
 *
 * Each one's return address is, unless it sets one (redirect_uri), the PATH
 * of its check route - never an absolute address made here: the providers
 * are built once per process, which a long-running server (Octane, FrankenPHP)
 * keeps between requests, so an absolute address would stay on the host of
 * the first request. omniauth completes the path with the origin of each
 * trip's request (Authorization::returnAddress()), and the exchange repeats
 * that address.
 */
final class OAuthProviders
{
    /**
     * @param array<string, array<string, mixed>> $config    config/omniauth.php's oauth.providers, resolved (Settings)
     * @param iterable<ProviderFactory>           $factories the provider packages'
     */
    public static function create(array $config, Container $app, iterable $factories = []): Providers
    {
        $given = [];
        $settings = [];
        foreach ($config as $name => $entry) {
            $service = $entry['service'] ?? null;
            if (\is_string($service) && '' !== $service) {
                $provider = $app->make($service);
                if (!$provider instanceof Provider) {
                    throw new \InvalidArgumentException(\sprintf('The "%s" provider\'s binding is no %s.', $name, Provider::class));
                }
                $given[] = $provider;
                continue;
            }
            if ('' === Settings::string($entry['client_id'] ?? null)) {
                continue;
            }
            $entry['redirect_uri'] = Settings::string($entry['redirect_uri'] ?? null) ?: Settings::string($entry['check_path'] ?? null);
            $settings[$name] = array_filter($entry, static fn ($v) => null !== $v && [] !== $v);
        }
        $made = [];
        foreach ($factories as $factory) {
            $made[] = $factory;
        }
        foreach (Providers::configured($settings, ...$made) as $provider) {
            if ($provider instanceof Provider) {
                $given[] = $provider;
            }
        }

        return new Providers(...$given);
    }
}
