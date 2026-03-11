<?php

namespace App\Services;

class ApacheService
{
    public function __construct(private SshService $ssh) {}

    //  Générer et activer le VirtualHost 
    public function createVirtualHost(
        string $name,
        string $domain,
        string $deployPath,
        string $phpVersion,
        string $systemUser,
    ): void
    {
        $projectKey = $this->normalizeProjectKey($name);
        $phpFpmSocket = $this->ensureProjectPhpFpmPool($projectKey, $phpVersion, $systemUser);
        $config = $this->buildVhostConfig($name, $domain, $deployPath, $phpFpmSocket);

        // Écrire le fichier de config via SFTP
        $remotePath = "/etc/apache2/sites-available/{$name}.conf";
        $this->ssh->uploadContent($config, $remotePath);

        // S'assurer que les modules/configs nécessaires sont actifs
        $this->ssh->exec("sudo a2enmod rewrite proxy proxy_fcgi setenvif");
        $this->ssh->exec("sudo a2enconf php{$phpVersion}-fpm || true");

        // Activer le site
        $this->ssh->exec("sudo a2ensite {$name}.conf");
        $this->ssh->exec("sudo apache2ctl configtest");
        $this->ssh->exec("sudo systemctl reload apache2");
    }

    //  Désactiver un VirtualHost 
    public function removeVirtualHost(string $name): void
    {
        $this->ssh->exec("sudo a2dissite {$name}.conf || true");
        $this->ssh->exec("sudo rm -f /etc/apache2/sites-available/{$name}.conf");
        $this->ssh->exec("sudo systemctl reload apache2");
    }

    //  Template VirtualHost
    private function ensureProjectPhpFpmPool(string $projectKey, string $phpVersion, string $systemUser): string
    {
        $version = $this->resolveAvailablePhpFpmVersion($phpVersion);
        $pool = $projectKey;
        $socketPath = "/run/php/php{$version}-fpm-{$pool}.sock";
        $poolConfPath = "/etc/php/{$version}/fpm/pool.d/{$pool}.conf";
        $runtimeUser = $this->resolvePoolRuntimeUser($systemUser);

        try {
            $poolConfig = <<<CONF
[{$pool}]
user = {$runtimeUser}
group = www-data
listen = {$socketPath}
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = dynamic
pm.max_children = 20
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
chdir = /
CONF;

            $this->ssh->uploadContent($poolConfig, $poolConfPath);
            $this->ssh->exec("sudo chown root:root {$poolConfPath}");
            $this->ssh->exec("sudo chmod 644 {$poolConfPath}");
            $this->ssh->exec("sudo php-fpm{$version} -t");
            $this->ssh->exec("sudo systemctl reload php{$version}-fpm");
        } catch (\Throwable $e) {
            // Rollback du pool invalide pour ne pas bloquer FPM.
            try {
                $this->ssh->exec("sudo rm -f {$poolConfPath}");
                $this->ssh->exec("sudo php-fpm{$version} -t");
                $this->ssh->exec("sudo systemctl reload php{$version}-fpm");
            } catch (\Throwable $ignored) {
                // noop
            }

            throw new \RuntimeException(
                "Impossible de créer le pool PHP-FPM dédié {$pool} (php{$version}-fpm): " . $e->getMessage()
            );
        }

        return $socketPath;
    }

    private function resolvePoolRuntimeUser(string $systemUser): string
    {
        $candidate = trim($systemUser);

        if ($candidate === '' || $candidate === 'root') {
            return 'www-data';
        }

        try {
            $exists = trim($this->ssh->exec("id -u {$candidate} >/dev/null 2>&1 && echo yes || echo no"));
            if ($exists === 'yes') {
                return $candidate;
            }
        } catch (\Throwable $e) {
            // noop
        }

        return 'www-data';
    }

    private function resolveAvailablePhpFpmVersion(string $requestedVersion): string
    {
        $requested = preg_replace('/[^0-9.]/', '', $requestedVersion) ?: '8.4';

        try {
            $exists = trim($this->ssh->exec("[ -d /etc/php/{$requested}/fpm ] && echo yes || echo no"));
            if ($exists === 'yes') {
                return $requested;
            }

            $fallback = trim($this->ssh->exec("ls -1 /etc/php/*/fpm 2>/dev/null | sed -E 's#^/etc/php/([^/]+)/fpm$#\\1#' | sort -Vr | head -n 1"));
            if ($fallback !== '') {
                return $fallback;
            }
        } catch (\Throwable $e) {
            // Handled below with explicit error.
        }

        throw new \RuntimeException("Aucune version PHP-FPM disponible sur le VPS.");
    }

    private function normalizeProjectKey(string $name): string
    {
        $normalized = strtolower(trim($name));
        $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-');

        return $normalized !== '' ? $normalized : 'project';
    }

    private function buildVhostConfig(string $name, string $domain, string $deployPath, string $phpFpmSocket): string
    {
        return <<<APACHE
<VirtualHost *:80>
    ServerName {$domain}
    DocumentRoot {$deployPath}/current/public

    <Directory {$deployPath}/current/public>
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch \\.php$>
        SetHandler "proxy:unix:{$phpFpmSocket}|fcgi://localhost"
    </FilesMatch>

    ErrorLog \${APACHE_LOG_DIR}/{$name}-error.log
    CustomLog \${APACHE_LOG_DIR}/{$name}-access.log combined
</VirtualHost>
APACHE;
    }
}
