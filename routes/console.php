<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Server;
use App\Models\Project;
use App\Services\SshService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('servers:sync-metrics {--only-missing}', function () {
    $onlyMissing = (bool) $this->option('only-missing');

    $query = Server::query();
    if ($onlyMissing) {
        $query->where(function ($q) {
            $q->whereNull('vcpu')
              ->orWhereNull('ram_mb')
              ->orWhereNull('disk_gb');
        });
    }

    $servers = $query->get();
    if ($servers->isEmpty()) {
        $this->info('Aucun serveur à mettre à jour.');
        return;
    }

    foreach ($servers as $server) {
        $this->line("→ {$server->name} ({$server->masked_ip})");

        try {
            $ssh = new SshService(
                ip: $server->ip_address,
                user: $server->ssh_user,
                privateKey: $server->ssh_private_key,
                port: $server->ssh_port,
            );

            $metrics = $ssh->getSystemMetrics();
            $ssh->disconnect();

            $server->update([
                'vcpu' => $metrics['vcpu'],
                'ram_mb' => $metrics['ram_mb'],
                'disk_gb' => $metrics['disk_gb'],
                'status' => 'active',
                'last_error' => null,
                'last_connected_at' => now(),
            ]);

            $this->info("   vCPU={$metrics['vcpu']} | RAM={$metrics['ram_mb']}MB | DISK={$metrics['disk_gb']}GB");
        } catch (\Exception $e) {
            $server->update([
                'status' => 'error',
                'last_error' => $e->getMessage(),
            ]);
            $this->error("   Erreur: {$e->getMessage()}");
        }
    }
})->purpose('Synchroniser vCPU/RAM/stockage des serveurs via SSH');

Artisan::command('projects:backup-db {--days=}', function () {
    $days = (int) ($this->option('days') ?: config('deploy.backup_retention_days', 30));
    $timestamp = now()->format('Ymd_His');

    $projects = Project::query()->with('server')->get();
    if ($projects->isEmpty()) {
        $this->info('Aucun projet à sauvegarder.');
        return;
    }

    foreach ($projects as $project) {
        if (!$project->server) {
            $this->warn("→ {$project->name}: aucun serveur lié.");
            continue;
        }

        $this->line("→ Sauvegarde {$project->name}");

        try {
            $ssh = new SshService(
                ip: $project->server->ip_address,
                user: $project->server->ssh_user,
                privateKey: $project->server->ssh_private_key,
                port: $project->server->ssh_port,
            );

            $deployPath = $project->deploy_path;
            $backupDir = "{$deployPath}/backups";

            $script = <<<BASH
ENV_FILE="{$deployPath}/shared/.env"
if [ ! -f "\$ENV_FILE" ]; then
  echo "NO_ENV"; exit 0; fi
DB_CONN=\$(grep -E '^DB_CONNECTION=' "\$ENV_FILE" | head -n1 | cut -d= -f2- | tr -d '\\r\"')
if [ "\$DB_CONN" != "mysql" ] && [ "\$DB_CONN" != "mariadb" ]; then
  echo "SKIP"; exit 0; fi
DB_NAME=\$(grep -E '^DB_DATABASE=' "\$ENV_FILE" | head -n1 | cut -d= -f2- | tr -d '\\r\"')
DB_USER=\$(grep -E '^DB_USERNAME=' "\$ENV_FILE" | head -n1 | cut -d= -f2- | tr -d '\\r\"')
DB_PASS=\$(grep -E '^DB_PASSWORD=' "\$ENV_FILE" | head -n1 | cut -d= -f2- | tr -d '\\r\"')
DB_HOST=\$(grep -E '^DB_HOST=' "\$ENV_FILE" | head -n1 | cut -d= -f2- | tr -d '\\r\"')
DB_PORT=\$(grep -E '^DB_PORT=' "\$ENV_FILE" | head -n1 | cut -d= -f2- | tr -d '\\r\"')
if [ -z "\$DB_NAME" ] || [ -z "\$DB_USER" ]; then
  echo "MISSING_DB"; exit 0; fi
DB_HOST=\${DB_HOST:-localhost}
DB_PORT=\${DB_PORT:-3306}
mkdir -p "{$backupDir}"
BACKUP_FILE="{$backupDir}/{$timestamp}.sql.gz"
MYSQL_PWD="\$DB_PASS" mysqldump -h "\$DB_HOST" -P "\$DB_PORT" -u "\$DB_USER" --single-transaction --quick --routines --events "\$DB_NAME" | gzip -c > "\$BACKUP_FILE"
find "{$backupDir}" -type f -name "*.sql.gz" -mtime +{$days} -delete

echo "OK:\$BACKUP_FILE"
BASH;

            $result = trim($ssh->exec($script));
            $ssh->disconnect();

            if ($result === 'NO_ENV') {
                $this->warn('   .env introuvable, sauvegarde ignorée.');
            } elseif ($result === 'SKIP') {
                $this->warn('   DB_CONNECTION non MySQL/MariaDB.');
            } elseif ($result === 'MISSING_DB') {
                $this->warn('   Infos DB manquantes dans .env.');
            } else {
                $this->info("   {$result}");
            }
        } catch (\Throwable $e) {
            $this->error("   Erreur: {$e->getMessage()}");
        }
    }
})->purpose('Sauvegarder les bases MySQL des projets sur les VPS');

Artisan::command('projects:setup-logrotate', function () {
    $rotate = (int) config('deploy.logrotate_rotate', 14);

    $projects = Project::query()->with('server')->get();
    if ($projects->isEmpty()) {
        $this->info('Aucun projet à configurer.');
        return;
    }

    foreach ($projects as $project) {
        if (!$project->server) {
            $this->warn("→ {$project->name}: aucun serveur lié.");
            continue;
        }

        $this->line("→ Logrotate {$project->name}");

        try {
            $ssh = new SshService(
                ip: $project->server->ip_address,
                user: $project->server->ssh_user,
                privateKey: $project->server->ssh_private_key,
                port: $project->server->ssh_port,
            );

            $deployPath = $project->deploy_path;
            $projectKey = basename($deployPath);
            $systemUser = $project->server->ssh_user ?: 'www-data';

            $logrotate = <<<CONF
{$deployPath}/logs/*.log /var/log/apache2/{$projectKey}-error.log /var/log/apache2/{$projectKey}-access.log {
    daily
    rotate {$rotate}
    compress
    delaycompress
    missingok
    notifempty
    copytruncate
    create 0640 {$systemUser} www-data
    sharedscripts
    postrotate
        systemctl reload apache2 > /dev/null 2>&1 || true
    endscript
}
CONF;

            $tmp = "/tmp/laravel-ship-{$projectKey}-logrotate.conf";
            $target = "/etc/logrotate.d/laravel-ship-{$projectKey}";

            $ssh->uploadContent($logrotate, $tmp);
            $ssh->exec("sudo mv {$tmp} {$target}");
            $ssh->exec("sudo chown root:root {$target}");
            $ssh->exec("sudo chmod 644 {$target}");
            $ssh->disconnect();

            $this->info('   logrotate configuré.');
        } catch (\Throwable $e) {
            $this->error("   Erreur: {$e->getMessage()}");
        }
    }
})->purpose('Installer logrotate pour les projets sur les VPS');
