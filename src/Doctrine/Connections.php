<?php

namespace Omnibridge\Laravel\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/**
 * A Doctrine DBAL connection from one of Laravel's database connections
 * (config/database.php): the same server, database and credentials, so that
 * Doctrine (omnibridge/doctrine's entity manager) and Eloquent, if the
 * application uses it, read the same tables.
 */
final class Connections
{
    private const DRIVERS = ['mysql' => 'pdo_mysql', 'mariadb' => 'pdo_mysql', 'pgsql' => 'pdo_pgsql', 'sqlite' => 'pdo_sqlite', 'sqlsrv' => 'pdo_sqlsrv'];

    /** @param array<string, mixed> $config a connection of Laravel's database configuration */
    public static function create(array $config): Connection
    {
        return DriverManager::getConnection(self::parameters($config));
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed> DriverManager's parameters
     */
    public static function parameters(array $config): array
    {
        $driver = $config['driver'] ?? 'mysql';
        $parameters = ['driver' => self::DRIVERS[$driver] ?? throw new \InvalidArgumentException(\sprintf('No Doctrine driver for Laravel\'s "%s" connections.', $driver))];
        if ('sqlite' === $driver) {
            $database = (string) ($config['database'] ?? ':memory:');

            return $parameters + (':memory:' === $database ? ['memory' => true] : ['path' => $database]);
        }
        $parameters += [
            'host' => $config['host'] ?? null,
            'port' => isset($config['port']) ? (int) $config['port'] : null,
            'dbname' => $config['database'] ?? null,
            'user' => $config['username'] ?? null,
            'password' => $config['password'] ?? null,
        ];
        if (!empty($config['unix_socket'])) {
            $parameters['unix_socket'] = $config['unix_socket'];
        }
        $parameters['charset'] = $config['charset'] ?? ('pdo_mysql' === $parameters['driver'] ? 'utf8mb4' : 'utf8');

        return array_filter($parameters, static fn ($value) => null !== $value);
    }
}
