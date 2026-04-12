<?php

namespace App\Services\Database;

use App\Services\RemoteRunner;

class SqliteProvisioner implements DatabaseProvisioner
{
    private RemoteRunner $ssh;
    private array $config;

    public function __construct(array $config, RemoteRunner $ssh)
    {
        $this->config = $config;
        $this->ssh = $ssh;
    }

    public function provision(callable $logger): void
    {
        // Extract database configuration
        $dbPath = $this->config['DB_DATABASE'] ?? 'storage/database.sqlite';

        // Convert relative path to absolute if needed
        if (!str_starts_with($dbPath, '/')) {
            // Assume it's relative to app root
            $deployPath = $this->config['DEPLOY_PATH'] ?? '/app';
            $dbPath = "{$deployPath}/{$dbPath}";
        }

        call_user_func($logger, '  Utilisation de SQLite: ' . $dbPath);

        try {
            call_user_func($logger, '  Provisionnement de SQLite...');

            // Create directory if it doesn't exist
            $dbDir = dirname($dbPath);
            $this->ssh->exec("mkdir -p {$dbDir}");

            // Create or touch the database file
            $this->ssh->exec("touch {$dbPath}");

            // Set proper permissions
            $this->ssh->exec("chmod 666 {$dbPath}");

            call_user_func($logger, '  Base de données SQLite provisionné ✓');
        } catch (\Exception $e) {
            call_user_func($logger, '  ⚠️  Erreur lors du provisionnement SQLite: ' . $e->getMessage());
        }

        // SQLite doesn't need a gateway for Docker
        $this->config['DB_GATEWAY'] = 'localhost';
    }

    public function getGateway(): string
    {
        return 'localhost';
    }
}
