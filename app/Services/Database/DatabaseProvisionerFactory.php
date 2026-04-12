<?php

namespace App\Services\Database;

use App\Services\RemoteRunner;
use InvalidArgumentException;

class DatabaseProvisionerFactory
{
    /**
     * Create a database provisioner based on the connection type
     */
    public static function make(string $driver, array $config, RemoteRunner $ssh): DatabaseProvisioner
    {
        return match ($driver) {
            'mysql', 'mariadb' => new MysqlProvisioner($config, $ssh),
            'pgsql', 'postgres', 'postgresql' => new PostgresqlProvisioner($config, $ssh),
            'sqlite' => new SqliteProvisioner($config, $ssh),
            default => throw new InvalidArgumentException("Driver de base de données non supporté: {$driver}"),
        };
    }
}
