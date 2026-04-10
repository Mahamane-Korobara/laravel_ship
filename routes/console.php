<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
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

Artisan::command('ship:e2e {--repo=} {--branch=main} {--port=} {--php=} {--keep} {--no-rollback} {--no-dev} {--unsafe}', function () {
    $repo = (string) ($this->option('repo') ?: config('ship.e2e_repo') ?: base_path());
    $branch = (string) ($this->option('branch') ?: 'main');
    $portOption = $this->option('port');
    $portProvided = $portOption !== null;
    $port = (int) ($portOption ?: config('ship.e2e_port', 18080));
    $phpOption = (string) ($this->option('php') ?: '');
    $keep = (bool) $this->option('keep');
    $noRollback = (bool) $this->option('no-rollback');
    $installDev = (bool) config('ship.e2e_install_dev', true);
    if ($this->option('no-dev')) {
        $installDev = false;
    }
    $safeMode = ! (bool) $this->option('unsafe') && (bool) config('ship.e2e_safe', true);

    $projectKey = 'ship-e2e';
    $baseDir = storage_path("app/ship-e2e/{$projectKey}");
    $release1 = now()->format('Ymd_His') . '_' . Str::lower(Str::random(4));
    $release1Path = "{$baseDir}/releases/{$release1}";
    $release2 = now()->addSecond()->format('Ymd_His') . '_' . Str::lower(Str::random(4));
    $release2Path = "{$baseDir}/releases/{$release2}";
    $sharedPath = "{$baseDir}/shared";
    $envPath = "{$sharedPath}/.env";
    $storagePath = "{$sharedPath}/storage";
    $currentPath = "{$baseDir}/current";
    $image1 = "{$projectKey}:{$release1}";
    $image2 = "{$projectKey}:{$release2}";
    $composeProject1 = "{$projectKey}_{$release1}";
    $composeProject2 = "{$projectKey}_{$release2}";

    $isPortAvailable = function (int $port): bool {
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 0.2);
        if (is_resource($socket)) {
            fclose($socket);
            return false;
        }
        return true;
    };

    if (!$isPortAvailable($port)) {
        if ($portProvided) {
            throw new RuntimeException("Le port {$port} est deja utilise. Arrete le conteneur existant ou relance avec --port=" . ($port + 1) . ".");
        }
        $startPort = $port;
        $tries = 0;
        while ($tries < 20 && !$isPortAvailable($port)) {
            $port++;
            $tries++;
        }
        if (!$isPortAvailable($port)) {
            throw new RuntimeException("Aucun port libre entre {$startPort} et " . ($startPort + 20) . ". Relance avec --port=XXXX.");
        }
        $this->warn("⚠ Port {$startPort} occupe, utilisation de {$port}.");
    }

    $formatE2EError = function (string $output) use ($port) {
        if (preg_match('/Bind for .*:(\\d+) failed: port is already allocated/i', $output, $matches)) {
            $usedPort = (int) ($matches[1] ?? $port);
            $suggested = $usedPort + 1;
            return "Le port {$usedPort} est deja utilise. Arrete le conteneur existant ou relance avec --port={$suggested}.";
        }

        if (preg_match('/Class \"([^\"]+ServiceProvider)\" not found/', $output, $matches)) {
            $provider = $matches[1] ?? 'ServiceProvider';
            return "Provider manquant: {$provider}. Souvent cause par un package en require-dev. En prod, deplace le package en require ou enregistre le provider uniquement en local.";
        }

        return trim($output);
    };

    $run = function (string $command, ?string $cwd = null) use ($formatE2EError) {
        $process = Process::fromShellCommandline($command, $cwd);
        $process->setTimeout(null);
        $process->run(function ($type, $buffer) {
            $line = trim($buffer);
            if ($line !== '') {
                $this->line($line);
            }
        });

        if (!$process->isSuccessful()) {
            $combined = trim($process->getErrorOutput() . "\n" . $process->getOutput());
            $message = $formatE2EError($combined);
            throw new RuntimeException($message !== '' ? $message : 'Commande échouée');
        }

        return trim($process->getOutput());
    };

    $resolveDockerBin = function () use ($run) {
        try {
            $run('docker info >/dev/null 2>&1');
            return 'docker';
        } catch (Throwable $e) {
            // try sudo
        }

        try {
            $run('sudo -n docker info >/dev/null 2>&1');
            return 'sudo -n docker';
        } catch (Throwable $e) {
            throw new RuntimeException('Docker est installe mais inaccessible. Ajoute ton user au groupe docker ou lance la commande avec sudo.');
        }
    };

    $dockerBin = $resolveDockerBin();

    $resolvePhpVersionFromComposer = function (string $path, string $fallback = '8.2') {
        $composerPath = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'composer.json';
        $lockPath = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'composer.lock';
        $allowed = ['8.0', '8.1', '8.2', '8.3', '8.4'];

        $extractCandidates = function (?string $raw): array {
            if (!is_string($raw) || trim($raw) === '') {
                return [];
            }

            preg_match_all('/"php(?:-64bit)?"\\s*:\\s*"[^"]*8\\.(\\d+)/i', $raw, $matches);
            if (empty($matches[1])) {
                return [];
            }

            $candidates = array_unique(array_map(fn ($minor) => '8.' . $minor, $matches[1]));
            usort($candidates, fn ($a, $b) => version_compare($a, $b));
            return $candidates;
        };

        $candidates = [];
        if (is_file($composerPath)) {
            $candidates = array_merge($candidates, $extractCandidates(file_get_contents($composerPath) ?: null));
        }
        if (is_file($lockPath)) {
            $candidates = array_merge($candidates, $extractCandidates(file_get_contents($lockPath) ?: null));
        }

        if (empty($candidates)) {
            return $fallback;
        }

        $candidates = array_values(array_unique($candidates));
        usort($candidates, fn ($a, $b) => version_compare($a, $b));
        $picked = end($candidates) ?: $fallback;
        $allowed = ['8.0', '8.1', '8.2', '8.3', '8.4'];

        if (in_array($picked, $allowed, true)) {
            return $picked;
        }

        $maxAllowed = end($allowed) ?: $fallback;
        if (version_compare($picked, $maxAllowed, '>')) {
            $this->warn("⚠ PHP {$picked} requis, mais image max supportee {$maxAllowed}. Utilisation de {$maxAllowed}.");
            return $maxAllowed;
        }

        $this->warn("⚠ Version PHP non prise en charge dans composer.json. Utilisation de {$fallback}.");
        return $fallback;
    };

    $this->info('→ E2E Docker local : initialisation...');

    $cleanup = function () use ($keep, $dockerBin, $release1Path, $release2Path, $image1, $image2, $baseDir, $composeProject1, $composeProject2) {
        if ($keep) {
            return;
        }

        try {
            $runCmd = function (string $cmd) use ($dockerBin) {
                try {
                    Process::fromShellCommandline($cmd)->setTimeout(null)->run();
                } catch (\Throwable $e) {
                    // ignore cleanup errors
                }
            };

            $runCmd("{$dockerBin} ps -aq --filter \"name=^/ship-e2e\" | xargs -r {$dockerBin} rm -f");
            $runCmd("COMPOSE_PROJECT_NAME={$composeProject1} {$dockerBin} compose -f {$release1Path}/docker-compose.yml down --remove-orphans || true");
            if (is_dir($release2Path)) {
                $runCmd("COMPOSE_PROJECT_NAME={$composeProject2} {$dockerBin} compose -f {$release2Path}/docker-compose.yml down --remove-orphans || true");
            }
            $runCmd("{$dockerBin} image rm -f {$image1} {$image2} || true");
            $runCmd("rm -rf {$baseDir}");
        } catch (\Throwable $e) {
            // ignore cleanup errors
        }
    };

    try {
        $run("mkdir -p {$release1Path} {$sharedPath} {$storagePath}");
        $run("mkdir -p {$storagePath}/framework/cache {$storagePath}/framework/sessions {$storagePath}/framework/views {$storagePath}/framework/tmp {$storagePath}/logs {$storagePath}/app/public");
        $run("chmod -R 777 {$storagePath}");
        if (!$keep) {
            $run("{$dockerBin} ps -aq --filter \"name=^/ship-e2e\" | xargs -r {$dockerBin} rm -f || true");
        }

    $this->info('→ Préparation du code source...');

    $isLocal = is_dir($repo);
    $looksLikeGitUrl = preg_match('/^(https?:\\/\\/|git@|ssh:\\/\\/|git:\\/\\/)/', $repo) === 1;
    if ($isLocal) {
        $repoReal = realpath($repo) ?: $repo;
        $baseReal = realpath($baseDir) ?: $baseDir;

        if (str_starts_with($repoReal, rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("Le repo pointe dans le workspace E2E ({$baseReal}). Choisis un repo en dehors de {$baseReal}.");
        }

        if (str_starts_with($baseReal, rtrim($repoReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            if ($safeMode) {
                throw new RuntimeException("Safe mode actif: le workspace E2E ({$baseReal}) est dans ton repo ({$repoReal}). Choisis un repo en dehors ou relance avec --unsafe.");
            }
            $this->warn('⚠ Le workspace E2E est dans ton repo. Le dossier storage est exclu pour eviter une boucle de copie.');
        }
    }

    if (!$isLocal && !$looksLikeGitUrl) {
        throw new RuntimeException("Chemin repo invalide: {$repo}");
    }

    $syncSource = function (string $dest) use ($repo, $branch, $run, $isLocal) {
        $repoArg = escapeshellarg($repo);
        $destArg = escapeshellarg($dest);

        if ($isLocal) {
            $this->info('→ Copie locale en cours (silencieux, peut prendre quelques minutes)...');
            $excludes = implode(' ', [
                "--exclude='.git'",
                "--exclude='node_modules'",
                "--exclude='vendor'",
                "--exclude='storage'",
            ]);
            $run("rsync -a --delete --info=stats2 --human-readable {$excludes} {$repoArg}/ {$destArg}/");
            $this->info('→ Copie locale terminée.');
            return;
        }

        $branchArg = escapeshellarg($branch);
        $this->info('→ Clonage git en cours...');
        $run("git clone --depth 1 -b {$branchArg} {$repoArg} {$destArg}");
        $this->info('→ Clonage git terminé.');
    };

    $syncSource($release1Path);

    $phpVersion = $phpOption !== '' ? $phpOption : $resolvePhpVersionFromComposer($release1Path, '8.2');
    $this->info("→ PHP Docker detecte: {$phpVersion}");
    $this->info($installDev ? '→ Composer (dev) active' : '→ Composer --no-dev');

    $this->info('→ Préparation .env...');
    $appKey = 'base64:' . base64_encode(random_bytes(32));
    $envContent = implode("\n", [
        "APP_NAME=LaravelShipE2E",
        "APP_ENV=local",
        "APP_KEY={$appKey}",
        "APP_DEBUG=true",
        "APP_URL=http://127.0.0.1:{$port}",
        "DB_CONNECTION=sqlite",
        "DB_DATABASE=/var/www/html/database/database.sqlite",
        "CACHE_STORE=file",
        "SESSION_DRIVER=file",
        "QUEUE_CONNECTION=sync",
    ]) . "\n";
    file_put_contents($envPath, $envContent);

    $run("mkdir -p {$release1Path}/database");
    $run("touch {$release1Path}/database/database.sqlite");

    $dockerfilePath1 = "{$release1Path}/Dockerfile.ship-e2e";
    $writeE2EDockerfile = function (string $path) use ($phpVersion, $installDev) {
        $composerCmd = $installDev
            ? 'composer install --prefer-dist --no-interaction --no-scripts --optimize-autoloader'
            : 'composer install --no-dev --prefer-dist --no-interaction --no-scripts --optimize-autoloader';
        $dockerfile = <<<DOCKER
FROM node:20-alpine AS node_builder
WORKDIR /app
COPY . /app
RUN if [ -f package.json ]; then npm install && npm run build; fi

FROM php:{$phpVersion}-apache
RUN apt-get update \\
    && apt-get install -y git unzip libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libonig-dev libicu-dev libxml2-dev libsqlite3-dev sqlite3 \\
    && docker-php-ext-configure gd --with-freetype --with-jpeg \\
    && docker-php-ext-install pdo_mysql pdo_sqlite mbstring zip exif pcntl intl gd \\
    && a2enmod rewrite headers \\
    && rm -rf /var/lib/apt/lists/*
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!\${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
ENV TMPDIR=/var/www/html/storage/framework/tmp
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock /var/www/html/
RUN {$composerCmd}
COPY . /var/www/html
COPY --from=node_builder /app/public/build /var/www/html/public/build
RUN mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80
CMD ["apache2-foreground"]
DOCKER;
        file_put_contents($path, $dockerfile);
    };

    $this->info('→ Génération Dockerfile E2E...');
    $writeE2EDockerfile($dockerfilePath1);

    $compose1 = <<<YAML
services:
  app:
    image: {$image1}
    restart: unless-stopped
    env_file:
      - {$envPath}
    volumes:
      - {$storagePath}:/var/www/html/storage
    ports:
      - "{$port}:80"
YAML;
    file_put_contents("{$release1Path}/docker-compose.yml", $compose1);

    $this->info('→ Build image + démarrage...');
    $run("{$dockerBin} build -t {$image1} -f {$dockerfilePath1} .", $release1Path);
    $run("COMPOSE_PROJECT_NAME={$composeProject1} {$dockerBin} compose -f {$release1Path}/docker-compose.yml up -d --remove-orphans");
    $run("ln -sfn {$release1Path} {$currentPath}");

    if (file_exists("{$release1Path}/artisan")) {
        $this->info('→ Migrations...');
        $run("COMPOSE_PROJECT_NAME={$composeProject1} {$dockerBin} compose -f {$release1Path}/docker-compose.yml exec -T app php artisan migrate --force");
    }

    $this->info('→ Vérification HTTP...');
    $healthy = false;
    $lastStatus = null;
    $lastError = null;
    $lastBody = null;
    for ($i = 0; $i < 10; $i++) {
        try {
            $response = Http::timeout(2)->get("http://127.0.0.1:{$port}");
            $lastStatus = $response->status();
            $lastBody = substr($response->body(), 0, 300);
            if ($lastStatus >= 200 && $lastStatus < 400) {
                $healthy = true;
                break;
            }
        } catch (Throwable $e) {
            $lastError = $e->getMessage();
        }
        sleep(1);
    }

    if (!$healthy) {
        try {
            $this->info('→ Logs...');
            $run("COMPOSE_PROJECT_NAME={$composeProject1} {$dockerBin} compose -f {$release1Path}/docker-compose.yml logs --tail=50");
        } catch (Throwable $e) {
            // ignore
        }

        $details = [];
        if ($lastStatus !== null) {
            $details[] = "status HTTP={$lastStatus}";
        }
        if ($lastError) {
            $details[] = "erreur={$lastError}";
        }
        if ($lastBody) {
            $details[] = "body=" . $lastBody;
        }
        $suffix = $details ? ' (' . implode(' | ', $details) . ')' : '';
        throw new RuntimeException('Application non accessible sur le port local.' . $suffix);
    }

    $this->info('→ Logs...');
    $run("COMPOSE_PROJECT_NAME={$composeProject1} {$dockerBin} compose -f {$release1Path}/docker-compose.yml logs --tail=50");

    if (!$noRollback) {
        $this->info('→ Release 2 pour test rollback...');
        $run("mkdir -p {$release2Path}");
        $syncSource($release2Path);
        $dockerfilePath2 = "{$release2Path}/Dockerfile.ship-e2e";
        $writeE2EDockerfile($dockerfilePath2);
        $port2 = $port + 1;
        while (!$isPortAvailable($port2)) {
            $port2++;
        }
        $compose2 = <<<YAML
services:
  app:
    image: {$image2}
    restart: unless-stopped
    env_file:
      - {$envPath}
    volumes:
      - {$storagePath}:/var/www/html/storage
    ports:
      - "{$port2}:80"
YAML;
        file_put_contents("{$release2Path}/docker-compose.yml", $compose2);

        $run("{$dockerBin} build -t {$image2} -f {$dockerfilePath2} .", $release2Path);
        $run("COMPOSE_PROJECT_NAME={$composeProject2} {$dockerBin} compose -f {$release2Path}/docker-compose.yml up -d --remove-orphans");
        $run("ln -sfn {$release2Path} {$currentPath}");

        $this->info('→ Rollback vers release 1...');
        $run("ln -sfn {$release1Path} {$currentPath}");
        $run("COMPOSE_PROJECT_NAME={$composeProject2} {$dockerBin} compose -f {$release2Path}/docker-compose.yml down --remove-orphans");
        $run("COMPOSE_PROJECT_NAME={$composeProject1} {$dockerBin} compose -f {$release1Path}/docker-compose.yml up -d --remove-orphans");
    }

        $this->info('✅ E2E local terminé.');
    } finally {
        $cleanup();
    }
})->purpose('Lancer un déploiement Docker local E2E avec rollback');
