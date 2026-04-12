<?php

namespace App\Services;

use App\Events\DeploymentLogReceived;
use App\Models\Deployment;
use App\Models\Project;
use App\Services\Database\DatabaseProvisionerFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DeploymentService
{
    private ?RemoteRunner $ssh = null;
    private array $envConfig = [];
    private string $envContent = '';
    private bool $dbCredentialsGenerated = false;
    private ?string $dbAdminUser = null;
    private ?string $dbAdminPass = null;
    private bool $dockerChecked = false;
    private string $dockerBin = 'docker';

    public function __construct(
        private Deployment $deployment,
        private Project    $project,
        ?RemoteRunner $ssh = null,
    ) {
        $this->ssh = $ssh;
    }

    // Point d'entree principal
    public function run(): void
    {
        $server = $this->project->server;
        if (!$server) {
            throw new RuntimeException('Aucun serveur lie a ce projet.');
        }

        if (!$this->ssh) {
            $this->ssh = new SshService(
                ip: $server->ip_address,
                user: $server->ssh_user,
                privateKey: $server->ssh_private_key,
                port: $server->ssh_port,
            );
        }

        $releaseName = now()->format('Ymd_His');
        $deployPath  = $this->project->deploy_path;
        $releasePath = "{$deployPath}/releases/{$releaseName}";
        $projectKey  = $this->normalizeProjectKey($this->project->name ?: basename($deployPath));
        $imageTag    = $this->resolveImageTag($projectKey, $releaseName);

        try {
            $this->deployment->update([
                'status'       => 'running',
                'started_at'   => now(),
                'release_name' => $releaseName,
            ]);

            $this->project->update(['status' => 'deploying']);

            $this->step('📁 Creation de la structure des repertoires...');
            $this->createDirectories($deployPath, $releasePath);

            $this->step('🔗 Clonage du depot GitHub...');
            $this->cloneRepo($releasePath);

            $this->step('⚙️  Ecriture du fichier .env...');
            $this->writeEnv($deployPath);

            $this->step('🗄️  Provisionnement de la base de données...');
            $this->provisionDatabase($deployPath);

            $this->step('�🐳 Build de l\'image Docker...');
            $this->ensureDockerAvailable();
            $this->ensureDockerfile($releasePath);
            $this->buildDockerImage($releasePath, $imageTag);

            $this->step('🧩 Generation du docker-compose...');
            $composePath = $this->createDockerCompose(
                deployPath: $deployPath,
                releasePath: $releasePath,
                imageTag: $imageTag,
                projectKey: $projectKey,
                domain: $this->project->domain
            );

            $this->step('🚀 Demarrage du conteneur...');
            $this->startDockerStack($projectKey, $composePath, $this->project->domain);

            if ($this->project->run_migrations) {
                $this->step('🗄️  Migrations dans le conteneur...');
                $this->runInContainer($projectKey, $composePath, 'php artisan migrate --force');
            }

            if ($this->project->run_seeders) {
                $this->step('🌱 Seeders dans le conteneur...');
                $this->runInContainer($projectKey, $composePath, 'php artisan db:seed --force');
            }

            if ($this->project->has_queue_worker) {
                $this->step('⚙️  Redemarrage du worker queue...');
                $this->runInContainer($projectKey, $composePath, 'php artisan queue:restart');
            }

            $this->step('🔀 Mise a jour du lien symbolique current...');
            $this->ssh->exec("ln -sfn {$releasePath} {$deployPath}/current");
            $this->log("  current → {$releaseName}");

            $this->step('🔐 Permissions stockage...');
            $this->fixFilesystemPermissions($deployPath);

            $this->step('🧹 Nettoyage des anciennes releases...');
            $this->cleanOldReleases($deployPath, $projectKey);

            $duration = abs((int) now()->diffInSeconds($this->deployment->started_at, false));

            $this->deployment->update([
                'status'           => 'success',
                'finished_at'      => now(),
                'duration_seconds' => $duration,
                'release_name'     => $releaseName,
            ]);

            $this->project->update([
                'status'          => 'deployed',
                'current_release' => $releaseName,
            ]);

            $url = $this->resolveProjectUrl($this->project, $projectKey);
            $this->log('');
            $this->log("🎉 Deploiement reussi en {$duration}s — {$url}");
        } catch (\Throwable $e) {
            $this->log('');
            $this->log('❌ ERREUR : ' . $e->getMessage());

            $this->deployment->update([
                'status'      => 'failed',
                'finished_at' => now(),
            ]);

            $this->project->update(['status' => 'failed']);

            throw $e;
        } finally {
            $this->ssh->disconnect();
        }
    }

    // Rollback
    public function rollback(string $targetRelease): void
    {
        $deployPath  = $this->project->deploy_path;
        $releasePath = "{$deployPath}/releases/{$targetRelease}";
        $projectKey  = $this->normalizeProjectKey($this->project->name ?: basename($deployPath));
        $composePath = "{$releasePath}/docker-compose.yml";

        $this->ssh->exec("ln -sfn {$releasePath} {$deployPath}/current");
        $this->startDockerStack($projectKey, $composePath, $this->project->domain);

        $this->deployment->update(['status' => 'rolled_back']);
        $this->project->update(['current_release' => $targetRelease]);

        $this->log("↩️  Retour arriere vers {$targetRelease} effectue");
    }

    private function createDirectories(string $deployPath, string $releasePath): void
    {
        $dirs = [
            $releasePath,
            "{$deployPath}/shared/storage/app/public",
            "{$deployPath}/shared/storage/framework/cache",
            "{$deployPath}/shared/storage/framework/sessions",
            "{$deployPath}/shared/storage/framework/views",
            "{$deployPath}/shared/storage/framework/tmp",
            "{$deployPath}/shared/storage/logs",
            "{$deployPath}/backups",
            "{$deployPath}/logs",
        ];

        foreach ($dirs as $dir) {
            $this->ssh->exec("mkdir -p {$dir}");
        }

        $this->log('  Structure creee ✓');
    }

    private function cloneRepo(string $releasePath): void
    {
        $repo   = $this->project->github_repo;
        $branch = $this->project->github_branch;

        $this->ssh->exec("mkdir -p {$releasePath}");
        $this->ssh->exec("find {$releasePath} -mindepth 1 -maxdepth 1 -exec rm -rf {} +");

        $this->ssh->execStreaming(
            "git clone --depth=1 --branch={$branch} https://github.com/{$repo}.git {$releasePath} 2>&1",
            fn($line) => $this->log($line)
        );

        $this->assertReleaseLooksValid($releasePath, $repo, $branch);

        $commit = trim($this->ssh->exec("cd {$releasePath} && git rev-parse HEAD"));
        $this->deployment->update(['git_commit' => $commit]);
        $this->log("  Commit : {$commit}");

        $this->pruneReleaseStorage($releasePath);
    }

    private function assertReleaseLooksValid(string $releasePath, string $repo, string $branch): void
    {
        $check = trim($this->ssh->exec(
            "[ -f {$releasePath}/artisan ] && [ -f {$releasePath}/composer.json ] && [ -f {$releasePath}/public/index.php ] && echo ok || echo ko"
        ));

        if ($check === 'ok') {
            return;
        }

        $listing = trim($this->ssh->exec("ls -la {$releasePath} | sed -n '1,40p'"));

        throw new RuntimeException(
            "Le clone du depot est incomplet ou invalide (repo {$repo}, branche {$branch}). "
                . "Fichiers Laravel attendus absents: artisan/composer.json/public/index.php.\nContenu release:\n{$listing}"
        );
    }

    private function writeEnv(string $deployPath): void
    {
        $sharedEnvPath = "{$deployPath}/shared/.env";
        $envContent = null;

        if ($this->deployment->env_file_path) {
            if (Storage::disk('local')->exists($this->deployment->env_file_path)) {
                $envContent = Storage::disk('local')->get($this->deployment->env_file_path);
                $this->log('  Utilisation du fichier .env uploade ✓');
            }
        }

        if ($envContent === null && $this->project->env_file_path) {
            if (Storage::disk('local')->exists($this->project->env_file_path)) {
                $envContent = Storage::disk('local')->get($this->project->env_file_path);
                $this->log('  Utilisation du fichier .env du projet ✓');
            }
        }

        if ($envContent === null) {
            $envContent = $this->project->envVariables
                ->map(fn($var) => "{$var->key}={$var->value}")
                ->join("\n");
            $this->log('  .env construit a partir des variables de la base de donnees ✓');
        }

        if (trim((string) $envContent) === '') {
            throw new RuntimeException('Aucune donnee .env disponible pour ce deploiement.');
        }

        $envContent = $this->ensureAppKeyInEnv((string) $envContent);
        $envContent = $this->normalizeDatabaseEnv($envContent);
        $this->envConfig = $this->parseEnv($envContent);
        if ($this->dbAdminUser && empty($this->envConfig['DB_ADMIN_USERNAME'])) {
            $this->envConfig['DB_ADMIN_USERNAME'] = $this->dbAdminUser;
        }
        if ($this->dbAdminPass !== null && empty($this->envConfig['DB_ADMIN_PASSWORD'])) {
            $this->envConfig['DB_ADMIN_PASSWORD'] = $this->dbAdminPass;
        }
        $this->envContent = $envContent; // Store for provisionMysql() access

        $this->ssh->uploadContent($envContent, $sharedEnvPath);
        $this->log('  .env ecrit dans shared/ ✓');
    }

    private function pruneReleaseStorage(string $releasePath): void
    {
        if (!config('ship.docker_exclude_storage', true)) {
            return;
        }

        $this->log('  Nettoyage du dossier storage du release (Docker mode)...');
        $this->ssh->exec("rm -rf {$releasePath}/storage");
        $this->ssh->exec("mkdir -p {$releasePath}/storage/app {$releasePath}/storage/framework/{cache,sessions,views} {$releasePath}/storage/logs");
    }

    private function ensureAppKeyInEnv(string $envContent): string
    {
        $hasKey = preg_match('/^APP_KEY=base64:[A-Za-z0-9+\/=]+/m', $envContent) === 1;

        if ($hasKey) {
            $this->log('  APP_KEY deja definie ✓');
            return $envContent;
        }

        $key = 'base64:' . base64_encode(random_bytes(32));
        $envContent = rtrim($envContent) . "\nAPP_KEY={$key}\n";
        $this->log('  APP_KEY generee ✓');

        return $envContent;
    }

    private function normalizeDatabaseEnv(string $envContent): string
    {
        $config = $this->parseEnv($envContent);
        $driver = $config['DB_CONNECTION'] ?? 'mysql';

        if ($driver === 'sqlite') {
            return $envContent;
        }

        $dbUser = $config['DB_USERNAME'] ?? '';
        $dbPass = $config['DB_PASSWORD'] ?? '';
        $dbName = $config['DB_DATABASE'] ?? '';
        $adminUser = $config['DB_ADMIN_USERNAME'] ?? null;
        $adminPass = $config['DB_ADMIN_PASSWORD'] ?? null;
        $changed = false;

        if ($adminUser !== null || $adminPass !== null) {
            $this->dbAdminUser = $adminUser ?: $this->dbAdminUser;
            $this->dbAdminPass = $adminPass ?? $this->dbAdminPass;
            $envContent = $this->removeEnvKey($envContent, 'DB_ADMIN_USERNAME');
            $envContent = $this->removeEnvKey($envContent, 'DB_ADMIN_PASSWORD');
            $changed = true;
        }

        if ($dbName === '') {
            $dbName = $this->normalizeProjectKey($this->project->name ?: 'laravelship');
            $envContent = $this->setEnvValue($envContent, 'DB_DATABASE', $dbName);
            $this->log('  DB_DATABASE manquant: valeur generee.');
            $changed = true;
        }

        if ($dbUser === '') {
            $dbUser = 'laravelship';
            $envContent = $this->setEnvValue($envContent, 'DB_USERNAME', $dbUser);
            $this->log('  DB_USERNAME manquant: valeur par defaut appliquee.');
            $changed = true;
        }

        if ($dbUser === 'root') {
            if (!$adminUser) {
                $this->dbAdminUser = 'root';
                $this->dbAdminPass = $dbPass !== '' ? $dbPass : null;
            }

            $dbUser = 'laravelship';
            $dbPass = '';
            $envContent = $this->setEnvValue($envContent, 'DB_USERNAME', $dbUser);
            $this->log('  DB_USERNAME=root detecte: remplacement par un utilisateur applicatif.');
            $changed = true;
        }

        if ($dbPass === '') {
            // Generate a password with only safe characters (no special chars that break SQL)
            // Only use: a-zA-Z0-9 + some safe special chars for MySQL passwords
            $dbPass = Str::random(32, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789');
            $envContent = $this->setEnvValue($envContent, 'DB_USERNAME', $dbUser);
            $envContent = $this->setEnvValue($envContent, 'DB_PASSWORD', $dbPass);
            $this->log('  DB_PASSWORD manquant: generation automatique securisee (alphanumerique).');
            $changed = true;
        }

        if ($changed) {
            $this->dbCredentialsGenerated = true;
        }

        return $envContent;
    }

    private function setEnvValue(string $envContent, string $key, string $value): string
    {
        $pattern = '/^' . preg_quote($key, '/') . '=.*/m';
        $line = $key . '=' . $value;

        if (preg_match($pattern, $envContent)) {
            return preg_replace($pattern, $line, $envContent) ?? $envContent;
        }

        return rtrim($envContent) . "\n{$line}\n";
    }

    private function removeEnvKey(string $envContent, string $key): string
    {
        $pattern = '/^' . preg_quote($key, '/') . '=.*\r?$\n?/m';
        return preg_replace($pattern, '', $envContent) ?? $envContent;
    }

    private function provisionDatabase(string $deployPath): void
    {
        // Get the database driver from the parsed config
        $driver = $this->envConfig['DB_CONNECTION'] ?? 'mysql';
        $provisioner = null;

        $this->log('PROVISIONING_START: driver=' . $driver);
        \Log::info('Deployment::provisionDatabase START', ['deployment_id' => $this->deployment->id, 'driver' => $driver]);

        try {
            // Create appropriate provisioner based on driver
            $provisioner = DatabaseProvisionerFactory::make(
                $driver,
                $this->envConfig,
                $this->ssh
            );

            $this->log('PROVISIONING_FACTORY: provisioner created');
            \Log::info('Deployment::provisionDatabase provisioner created', ['driver' => $driver]);

            // Run provisioning with logger callback
            $provisioner->provision(function($msg) {
                $this->log($msg);
                \Log::info('Deployment::provisionDatabase', ['msg' => $msg]);
            });

            $this->log('PROVISIONING_SUCCESS');
            \Log::info('Deployment::provisionDatabase SUCCESS');
        } catch (\Exception $e) {
            $this->log('  ⚠️  Erreur lors du provisionnement: ' . $e->getMessage());
            \Log::error('Deployment::provisionDatabase ERROR', ['error' => $e->getMessage(), 'exception' => $e]);
            
            // Log warning but continue - provisioning error should not block deployment
            // The .env will be updated with gateway and password for the container to use
            if (!empty($this->envContent)) {
                $this->log('  ℹ️  Continuation du déploiement malgré erreur provisioning...');
            }
        } finally {
            if (!empty($this->envContent) && $provisioner) {
                $gateway = $provisioner->getGateway();

                if ($driver !== 'sqlite' && $gateway !== '') {
                    $this->envContent = $this->setEnvValue($this->envContent, 'DB_HOST', $gateway);
                    $this->log('  DB_HOST mis à jour: ' . $gateway);
                    \Log::info('Deployment::provisionDatabase DB_HOST updated', ['gateway' => $gateway]);
                }

                $sharedEnvPath = "{$deployPath}/shared/.env";
                $this->ssh->uploadContent($this->envContent, $sharedEnvPath);
                $this->log('  .env mis à jour et uploade ✓');
                \Log::info('Deployment::provisionDatabase .env uploaded');
            }
        }
    }

    private function ensureDockerAvailable(): void
    {
        if ($this->dockerChecked) {
            return;
        }

        if ($this->ssh instanceof AgentRunner) {
            $dockerOk = trim($this->ssh->exec("docker --version 2>/dev/null || true")) !== '';
            $composeOk = trim($this->ssh->exec("docker compose version 2>/dev/null || true")) !== '';

            if (!$dockerOk) {
                throw new RuntimeException('Docker manquant sur le VPS (agent actif).');
            }

            if (!$composeOk) {
                throw new RuntimeException('Docker Compose (v2) manquant sur le VPS (agent actif).');
            }

            $this->dockerBin = 'docker';
            $this->dockerChecked = true;
            $this->log('  Docker + Compose disponibles ✓');
            return;
        }

        $dockerExists = trim($this->ssh->exec("command -v docker >/dev/null 2>&1 && echo ok || echo missing")) === 'ok';

        if (!$dockerExists) {
            if (!config('ship.allow_docker_setup')) {
                throw new RuntimeException('Docker n\'est pas disponible sur le VPS et l\'installation automatique est desactivee.');
            }

            if (!config('ship.auto_install_docker')) {
                throw new RuntimeException('Docker manquant. Installe Docker sur le VPS et ajoute l\'utilisateur SSH au groupe docker.');
            }

            $this->installDocker();
            $dockerExists = trim($this->ssh->exec("command -v docker >/dev/null 2>&1 && echo ok || echo missing")) === 'ok';
        }

        if (!$dockerExists) {
            throw new RuntimeException('Docker n\'est pas installe sur le VPS.');
        }

        $this->dockerBin = $this->resolveDockerBin();

        $composeExists = trim($this->ssh->exec("{$this->dockerBin} compose version >/dev/null 2>&1 && echo ok || echo missing")) === 'ok';
        if (!$composeExists) {
            if (!config('ship.allow_docker_setup') || !config('ship.auto_install_docker')) {
                throw new RuntimeException('Docker Compose (v2) est requis sur le VPS. Installe-le puis ajoute l\'utilisateur SSH au groupe docker.');
            }

            $this->installDocker();
            $this->dockerBin = $this->resolveDockerBin();
            $composeExists = trim($this->ssh->exec("{$this->dockerBin} compose version >/dev/null 2>&1 && echo ok || echo missing")) === 'ok';
        }

        if (!$composeExists) {
            throw new RuntimeException('Docker Compose (v2) est requis sur le VPS.');
        }

        $this->dockerChecked = true;
        $this->log('  Docker + Compose disponibles ✓');
    }

    private function installDocker(): void
    {
        $this->step('🐳 Installation de Docker sur le VPS...');

        if (!$this->ssh instanceof SshService) {
            throw new RuntimeException('Installation Docker impossible sans SSH.');
        }

        $sudoOk = trim($this->ssh->exec("sudo -n true >/dev/null 2>&1 && echo ok || echo fail")) === 'ok';
        if (!$sudoOk) {
            throw new RuntimeException('Installation Docker impossible : sudo sans mot de passe requis pour l\'utilisateur SSH.');
        }

        $osRelease = $this->ssh->exec('cat /etc/os-release');
        [$osId, $osCodename] = $this->parseOsRelease($osRelease);

        if (!in_array($osId, ['ubuntu', 'debian'], true) || $osCodename === null) {
            throw new RuntimeException("Installation Docker non supportee pour l'OS: {$osId}.");
        }

        $commands = [
            'sudo apt-get update -y',
            'sudo apt-get install -y ca-certificates curl gnupg',
            'sudo install -m 0755 -d /etc/apt/keyrings',
            "curl -fsSL https://download.docker.com/linux/{$osId}/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg",
            'sudo chmod a+r /etc/apt/keyrings/docker.gpg',
            "echo \"deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/{$osId} {$osCodename} stable\" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null",
            'sudo apt-get update -y',
            'sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin',
            'sudo systemctl enable --now docker',
        ];

        foreach ($commands as $command) {
            $this->ssh->exec($command);
        }

        $server = $this->project->server;
        if ($server) {
            $this->ssh->exec("sudo usermod -aG docker {$server->ssh_user} || true");
            $this->reconnectSsh($server);
        }

        $this->log('  Docker installe ✓');
    }

    private function parseOsRelease(string $content): array
    {
        $id = null;
        $codename = null;

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            if (str_starts_with($line, 'ID=')) {
                $id = trim(str_replace('ID=', '', $line), "\"' ");
            }

            if (str_starts_with($line, 'VERSION_CODENAME=')) {
                $codename = trim(str_replace('VERSION_CODENAME=', '', $line), "\"' ");
            }
        }

        return [$id ?? 'unknown', $codename];
    }

    private function reconnectSsh($server): void
    {
        if ($this->ssh) {
            $this->ssh->disconnect();
        }

        $this->ssh = new SshService(
            ip: $server->ip_address,
            user: $server->ssh_user,
            privateKey: $server->ssh_private_key,
            port: $server->ssh_port,
        );
    }

    private function resolveDockerBin(): string
    {
        $dockerOk = trim($this->ssh->exec("docker info >/dev/null 2>&1 && echo ok || echo fail")) === 'ok';
        if ($dockerOk) {
            return 'docker';
        }

        $sudoOk = trim($this->ssh->exec("sudo -n docker info >/dev/null 2>&1 && echo ok || echo fail")) === 'ok';
        if ($sudoOk) {
            return 'sudo -n docker';
        }

        throw new RuntimeException('Docker est installe mais inaccessible (groupe docker ou sudo requis).');
    }

    private function ensureDockerfile(string $releasePath): void
    {
        $exists = trim($this->ssh->exec("[ -f {$releasePath}/Dockerfile ] && echo yes || echo no"));
        if ($exists === 'yes') {
            $this->log('  Dockerfile detecte ✓');
            return;
        }

        $phpVersion = $this->resolveDockerPhpVersion();

        $dockerfile = <<<DOCKER
FROM node:20-alpine AS node_builder
WORKDIR /app
COPY . /app
RUN if [ -f package.json ]; then npm install && npm run build; fi

FROM php:{$phpVersion}-apache
RUN apt-get update \
    && apt-get install -y git unzip libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libonig-dev libicu-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl intl gd \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!\${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
ENV TMPDIR=/var/www/html/storage/framework/tmp
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock /var/www/html/
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --optimize-autoloader
COPY . /var/www/html
COPY --from=node_builder /app/public/build /var/www/html/public/build
RUN mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80
CMD ["apache2-foreground"]
DOCKER;

        $this->ssh->uploadContent($dockerfile, "{$releasePath}/Dockerfile");
        $this->log('  Dockerfile genere ✓');
    }

    private function buildDockerImage(string $releasePath, string $imageTag): void
    {
        // Images de base utilisées dans le Dockerfile pour pre-pull
        $baseImages = [
            'node:20-alpine',
            'php:8.2-apache',
            'composer:2',
        ];

        // Pre-pull toutes les images avec une seule commande (plus robuste)
        $this->log('  Pre-pull des images de base...');
        $hasIpv6Issues = false;

        // Augmenter le timeout pour le pre-pull (peut télécharger plusieurs GB)
        $originalTimeout = 60;
        if ($this->ssh instanceof \App\Services\SshService) {
            $originalTimeout = 60; // sauvegarde pour restaurer après
            $this->ssh->setTimeout(300); // 5 minutes pour le pre-pull
        }

        try {
            // Chaîner les pulls: docker pull A && docker pull B && docker pull C
            $pullCommand = implode(' && ', array_map(fn($img) => "{$this->dockerBin} pull {$img}", $baseImages));

            $pullOutput = '';
            $this->ssh->execStreaming(
                $pullCommand,
                function ($line) use (&$pullOutput) {
                    $pullOutput .= $line . "\n";
                    // Log chaque image
                    if (strpos($line, 'Digest:') !== false || strpos($line, 'Status:') !== false) {
                        $this->log("  " . trim($line));
                    }
                }
            );
            $this->log("  ✓ Toutes les images de base téléchargées");
        } catch (\Exception $e) {
            // Vérifier si c'est une erreur IPv6
            $errorMsg = $e->getMessage() . "\n" . $pullOutput;
            if (strpos($errorMsg, 'network is unreachable') !== false && strpos($errorMsg, '2606:') !== false) {
                $hasIpv6Issues = true;
                $this->log("  ⚠️  Pre-pull échoué en IPv6 → Fallback sans --network=host");
            } else {
                // Si c'est une autre erreur, on continue quand même (les images seront re-pull durant le build)
                $this->log("  ⚠️  Pre-pull incomplet (fallback au build): " . substr($e->getMessage(), 0, 100));
            }
        } finally {
            // Restaurer le timeout normal
            if ($this->ssh instanceof \App\Services\SshService) {
                $this->ssh->setTimeout(300); // Build timeout aussi long
            }
        }

        // Build avec DOCKER_BUILDKIT=1 pour meilleure gestion du réseau
        $this->log('🐳 Build de l\'image Docker avec BuildKit...');

        // Si on a détecté des problèmes IPv6 lors du pre-pull, utiliser directement le fallback
        if ($hasIpv6Issues) {
            $this->log('⚠️  Utilisation du build IPv4-only (sans --network=host)');
            $buildCommand = "cd {$releasePath} && DOCKER_BUILDKIT=1 {$this->dockerBin} build --pull -t {$imageTag} . 2>&1";
        } else {
            $buildCommand = "cd {$releasePath} && DOCKER_BUILDKIT=1 {$this->dockerBin} build --pull --network=host -t {$imageTag} . 2>&1";
        }

        $buildOutput = ''; // Déclarer avant le try pour être accessible dans le catch
        try {
            $this->ssh->execStreaming(
                $buildCommand,
                function ($line) use (&$buildOutput) {
                    $buildOutput .= $line . "\n";
                    $this->log($line);
                }
            );
        } catch (\Exception $buildException) {
            // Détecter si c'est une erreur IPv6 (network unreachable avec IPv6)
            if ((strpos($buildOutput, 'network is unreachable') !== false || strpos($buildException->getMessage(), 'network is unreachable') !== false)
                && (strpos($buildOutput, '2606:') !== false || strpos($buildException->getMessage(), '2606:') !== false)
            ) {

                $this->log('⚠️  Erreur IPv6 détectée, relance du build sans --network=host...');

                // Fallback: build sans --network=host (utilise le DNS du serveur)
                $fallbackCommand = "cd {$releasePath} && DOCKER_BUILDKIT=1 {$this->dockerBin} build --pull -t {$imageTag} . 2>&1";

                try {
                    $this->ssh->execStreaming(
                        $fallbackCommand,
                        fn($line) => $this->log($line)
                    );
                    $this->log('✓ Build réussi en fallback IPv4');
                } catch (\Exception $fallbackException) {
                    // Si même le fallback échoue, on relève l'exception
                    throw new \RuntimeException(
                        "Build Docker échoué même en fallback IPv4: " . $fallbackException->getMessage()
                    );
                }
            } else {
                // Autre erreur non-liée à IPv6
                throw $buildException;
            }
        }
    }

    private function createDockerCompose(
        string $deployPath,
        string $releasePath,
        string $imageTag,
        string $projectKey,
        ?string $domain,
    ): string {
        $sharedEnv = "{$deployPath}/shared/.env";
        $sharedStorage = "{$deployPath}/shared/storage";
        $composePath = "{$releasePath}/docker-compose.yml";

        $service = "app";
        $containerName = "ship-{$projectKey}";
        $labels = '';
        $networks = '';
        $ports = '';

        if ($domain) {
            $safeDomain = str_replace('`', '', $domain);
            $labels = "      - \"traefik.enable=true\"\n" .
                "      - \"traefik.http.routers.{$projectKey}.rule=Host(`{$safeDomain}`)\"\n" .
                "      - \"traefik.http.routers.{$projectKey}.entrypoints=websecure\"\n" .
                "      - \"traefik.http.routers.{$projectKey}.tls=true\"\n" .
                "      - \"traefik.http.routers.{$projectKey}.tls.certresolver=letsencrypt\"\n" .
                "      - \"traefik.http.services.{$projectKey}.loadbalancer.server.port=80\"\n" .
                "      - \"traefik.docker.network=traefik\"\n";
            $networks = "    networks:\n      - traefik\n";
        } else {
            $port = $this->resolveProjectPort();
            $ports = "    ports:\n      - \"{$port}:80\"\n";
        }

        $compose = "services:\n  {$service}:\n" .
            "    image: {$imageTag}\n" .
            "    container_name: {$containerName}\n" .
            "    restart: unless-stopped\n" .
            "    env_file:\n      - {$sharedEnv}\n" .
            "    volumes:\n      - {$sharedStorage}:/var/www/html/storage\n" .
            $ports .
            ($labels ? "    labels:\n{$labels}" : '') .
            $networks;

        if ($domain) {
            $compose .= "networks:\n  traefik:\n    external: true\n";
        }

        $this->ssh->uploadContent($compose, $composePath);
        $this->log('  docker-compose genere ✓');

        return $composePath;
    }

    private function startDockerStack(string $projectKey, string $composePath, ?string $domain): void
    {
        if ($domain) {
            $this->ssh->exec("docker network inspect traefik >/dev/null 2>&1 || docker network create traefik");
        }

        $this->ssh->execStreaming(
            "COMPOSE_PROJECT_NAME={$projectKey} {$this->dockerBin} compose -f {$composePath} up -d --remove-orphans 2>&1",
            fn($line) => $this->log($line)
        );
    }

    private function runInContainer(string $projectKey, string $composePath, string $command): void
    {
        $missingProvider = null;

        try {
            $this->ssh->execStreaming(
                "COMPOSE_PROJECT_NAME={$projectKey} {$this->dockerBin} compose -f {$composePath} exec -T app {$command} 2>&1",
                function ($line) use (&$missingProvider) {
                    if ($missingProvider === null) {
                        $missingProvider = $this->detectDevProviderMissing($line);
                    }
                    $this->log($line);
                }
            );
        } catch (\Throwable $e) {
            if ($missingProvider !== null) {
                throw new RuntimeException($this->formatDevProviderError($missingProvider));
            }
            throw $e;
        }

        if ($missingProvider !== null) {
            throw new RuntimeException($this->formatDevProviderError($missingProvider));
        }
    }

    private function fixFilesystemPermissions(string $deployPath): void
    {
        $commands = [
            "sudo mkdir -p {$deployPath}/shared/storage/framework/{cache,sessions,views,tmp} {$deployPath}/shared/storage/logs",
            "sudo chown -R www-data:www-data {$deployPath}/shared/storage || true",
            "sudo chmod -R 775 {$deployPath}/shared/storage",
        ];

        foreach ($commands as $command) {
            try {
                $this->ssh->exec($command);
            } catch (\Throwable $e) {
                $this->log("  ⚠️ Permission command failed: {$command}");
            }
        }

        $this->log('  Permissions appliquees ✓');
    }

    private function cleanOldReleases(string $deployPath, string $projectKey): void
    {
        $max = config('deploy.max_releases', 5);
        $startAt = ((int) $max) + 1;

        $oldReleases = trim($this->ssh->exec("ls -1dt {$deployPath}/releases/*/ 2>/dev/null | tail -n +{$startAt} || true"));
        if ($oldReleases !== '') {
            foreach (array_filter(explode("\n", $oldReleases)) as $dir) {
                $release = basename(trim($dir, '/'));
                $imageTag = $this->resolveImageTag($projectKey, $release);
                try {
                    $this->ssh->exec("{$this->dockerBin} image rm -f {$imageTag} || true");
                } catch (\Throwable $e) {
                    // non bloquant
                }
            }
        }

        $this->ssh->exec(
            "ls -1dt {$deployPath}/releases/*/ 2>/dev/null | tail -n +{$startAt} | xargs -r rm -rf"
        );

        $this->log("  Retention : {$max} releases maximum ✓");
    }

    private function resolveProjectUrl(Project $project, string $projectKey): string
    {
        if ($project->domain) {
            return 'https://' . $project->domain;
        }

        $port = $this->resolveProjectPort();
        return "http://{$project->server?->ip_address}:{$port}";
    }

    private function resolveProjectPort(): int
    {
        return 8000 + (int) $this->project->id;
    }

    private function resolveImageTag(string $projectKey, string $release): string
    {
        return strtolower($projectKey) . ':' . $release;
    }

    private function resolveDockerPhpVersion(): string
    {
        $candidate = preg_replace('/[^0-9.]/', '', (string) $this->project->php_version);
        return $candidate !== '' ? $candidate : '8.2';
    }

    private function normalizeProjectKey(string $name): string
    {
        $normalized = strtolower(trim($name));
        $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-');

        return $normalized !== '' ? $normalized : 'project';
    }

    private function detectDevProviderMissing(string $line): ?string
    {
        if (preg_match('/Class \"([^\"]+ServiceProvider)\" not found/', $line, $matches)) {
            return $matches[1] ?? 'ServiceProvider';
        }

        return null;
    }

    private function formatDevProviderError(string $provider): string
    {
        return "Provider manquant: {$provider}. Ce provider vient souvent d'un package en require-dev. "
            . "En production, deplace le package dans require ou enregistre ce provider uniquement en local "
            . "(ex: AppServiceProvider::register() avec app()->environment('local')).";
    }

    /**
     * @return array<string, string>
     */
    private function parseEnv(string $content): array
    {
        $parsed = [];

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $parsed[$key] = $value;
        }

        return $parsed;
    }

    private function step(string $message): void
    {
        $this->log('');
        $this->log($message);
    }

    private function log(string $line): void
    {
        $this->deployment->appendLog($line);

        broadcast(new DeploymentLogReceived(
            deploymentId: $this->deployment->id,
            line: $line,
        ));
    }
}
