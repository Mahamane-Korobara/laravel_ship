<?php

namespace App\Services\Database;

use App\Services\RemoteRunner;

class PostgresqlProvisioner implements DatabaseProvisioner
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
        $dbUser = $this->config['DB_USERNAME'] ?? 'postgres';
        $dbPass = $this->config['DB_PASSWORD'] ?? '';
        $dbName = $this->config['DB_DATABASE'] ?? 'laravel_ship';
        $dbPort = $this->config['DB_PORT'] ?? 5432;

        call_user_func($logger, '  Extraction des credentials PostgreSQL: user=' . $dbUser . ', database=' . $dbName);

        // Detect Docker gateway IP dynamically
        $gateway = $this->detectDockerGateway($logger);
        $this->config['DB_GATEWAY'] = $gateway;
        call_user_func($logger, '  Gateway Docker detecté: ' . $gateway);

        // Build PostgreSQL commands to create user and database
        $sqlCommands = [
            "CREATE USER IF NOT EXISTS \"{$dbUser}\" WITH PASSWORD '{$dbPass}';",
            "CREATE DATABASE IF NOT EXISTS \"{$dbName}\" OWNER \"{$dbUser}\";",
            "GRANT ALL PRIVILEGES ON DATABASE \"{$dbName}\" TO \"{$dbUser}\";",
            "ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO \"{$dbUser}\";",
            "ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO \"{$dbUser}\";",
        ];

        $sqlScript = implode("\n", $sqlCommands);

        // Use a temporary SQL file
        $tmpSqlFile = '/tmp/provision_postgres_' . uniqid() . '.sql';

        try {
            call_user_func($logger, '  Provisionnement de l\'utilisateur PostgreSQL...');

            // Upload SQL script to server
            $this->ssh->uploadContent($sqlScript, $tmpSqlFile);

            // Execute the SQL script using psql as postgres user
            $output = trim((string) $this->ssh->exec("sudo -u postgres psql -f {$tmpSqlFile}"));
            call_user_func($logger, '  Utilisateur PostgreSQL provisionné ✓');

            if (!empty($output)) {
                call_user_func($logger, '  Réponse PostgreSQL: ' . substr($output, 0, 100));
            }

            // Cleanup temporary file
            $this->ssh->exec("rm -f {$tmpSqlFile}");
        } catch (\Exception $e) {
            call_user_func($logger, '  ⚠️  Erreur lors du provisionnement PostgreSQL: ' . $e->getMessage());
            $this->ssh->exec("rm -f {$tmpSqlFile}");
        }

    }

    public function getGateway(): string
    {
        return $this->config['DB_GATEWAY'] ?? '172.17.0.1';
    }

    private function detectDockerGateway(callable $logger): string
    {
        $gatewayDetectCmd = "docker network inspect bridge --format '{{(index .IPAM.Config 0).Gateway}}'";
        $gateway = trim((string) $this->ssh->exec($gatewayDetectCmd));

        if (empty($gateway)) {
            $gateway = '172.17.0.1';
            call_user_func($logger, '  ⚠️  Gateway detection failed, using default: ' . $gateway);
        }

        return $gateway;
    }
}
