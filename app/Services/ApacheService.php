<?php

namespace App\Services;

class ApacheService
{
    public function __construct(private SshService $ssh) {}

    //  Générer et activer le VirtualHost 
    public function createVirtualHost(string $name, string $domain, string $deployPath, string $phpVersion): void
    {
        $config = $this->buildVhostConfig($name, $domain, $deployPath);

        // Écrire le fichier de config via SFTP
        $remotePath = "/etc/apache2/sites-available/{$name}.conf";
        $this->ssh->uploadContent($config, $remotePath);

        // Activer le site
        $this->ssh->exec("sudo a2ensite {$name}.conf");
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
    private function buildVhostConfig(string $name, string $domain, string $deployPath): string
    {
        return <<<APACHE
<VirtualHost *:80>
    ServerName {$domain}
    DocumentRoot {$deployPath}/current/public

    <Directory {$deployPath}/current/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    ErrorLog {$deployPath}/logs/apache_error.log
    CustomLog {$deployPath}/logs/apache_access.log combined
</VirtualHost>
APACHE;
    }
}
