<?php

namespace App\Services;

use App\Events\DeploymentLogReceived;
use App\Models\Deployment;
use App\Models\Project;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeploymentService
{
    private SshService    $ssh;
    private ApacheService $apache;
    private SslService    $ssl;
    private array         $envConfig = [];
    private bool          $aptUpdated = false;
    private bool          $phpFpmRestarted = false;

    public function __construct(
        private Deployment $deployment,
        private Project    $project,
    ) {}

    //  Point d'entrée principal 
    public function run(): void
    {
        $server = $this->project->server;

        $this->ssh    = new SshService(
            ip: $server->ip_address,
            user: $server->ssh_user,
            privateKey: $server->ssh_private_key,
            port: $server->ssh_port,
        );
        $this->apache = new ApacheService($this->ssh);
        $this->ssl    = new SslService($this->ssh);

        $releaseName = now()->format('Ymd_His');
        $deployPath  = $this->project->deploy_path;
        $releasePath = "{$deployPath}/releases/{$releaseName}";

        try {
            // Mise à jour statut
            $this->deployment->update([
                'status'       => 'running',
                'started_at'   => now(),
                'release_name' => $releaseName,
            ]);

            $this->project->update(['status' => 'deploying']);

            //  Étapes 
            $this->step("📁 Création de la structure des répertoires...");
            $this->createDirectories($deployPath, $releasePath);

            $this->step("🔗 Clonage du dépôt GitHub...");
            $this->cloneRepo($releasePath);

            $this->step("⚙️  Écriture du fichier .env...");
            $this->writeEnv($deployPath, $releasePath);

            $this->step("🔧 Vérification des dépendances projet...");
            $this->ensureProjectDependencies();

            $this->step("🔗 Création des liens symboliques...");
            $this->createSymlinks($deployPath, $releasePath);

            $this->step("📦 Installation des dépendances Composer...");
            $this->ssh->execStreaming(
                "cd {$releasePath} && composer install --no-dev --optimize-autoloader --no-interaction 2>&1",
                fn($line) => $this->log($line)
            );

            $this->step("🔑 Vérification APP_KEY...");
            $this->ensureAppKey($releasePath);

            if ($this->project->run_migrations) {
                $this->step("🗄️  Exécution des migrations...");
                $this->runMigrationsWithRecovery($releasePath);
            }

            if ($this->project->run_seeders) {
                $this->step("🌱 Exécution des seeders...");
                $this->log($this->ssh->exec("cd {$releasePath} && php artisan db:seed --force 2>&1"));
            }

            if ($this->project->run_npm_build) {
                $this->step("🎨 Build des assets NPM...");
                $this->ssh->execStreaming(
                    "cd {$releasePath} && npm install && npm run build 2>&1",
                    fn($line) => $this->log($line)
                );
            }

            $this->step("⚡ Optimisation Laravel...");
            $this->ssh->exec("cd {$releasePath} && php artisan config:cache && php artisan route:cache && php artisan view:cache 2>&1");
            $this->log("  config:cache ✓  route:cache ✓  view:cache ✓");

            $this->step("🔀 Mise à jour du lien symbolique current...");
            $this->ssh->exec("ln -sfn {$releasePath} {$deployPath}/current");
            $this->log("  current → {$releaseName}");

            $this->step("🔐 Ajustement des permissions Linux/Apache...");
            $this->fixFilesystemPermissions($deployPath, $releasePath, $server->ssh_user);

            $this->step("🧾 Configuration logrotate...");
            try {
                $this->ensureLogrotate($deployPath, basename($deployPath), $server->ssh_user);
                $this->log("  logrotate configuré ✓");
            } catch (\Throwable $e) {
                $this->log("  ⚠️ logrotate non configuré : " . substr($e->getMessage(), 0, 120));
            }

            $this->step("🧹 Nettoyage des anciennes releases...");
            $this->cleanOldReleases($deployPath);
            $this->assertCurrentReleaseIntegrity($releasePath);

            if ($this->project->domain) {
                $this->step("🌐 Configuration Apache VirtualHost...");
                $this->apache->createVirtualHost(
                    name: basename($deployPath),
                    domain: $this->project->domain,
                    deployPath: $deployPath,
                    phpVersion: $this->project->php_version,
                    systemUser: $server->ssh_user,
                );
                $this->log("  VirtualHost {$this->project->domain} activé ✓");
                $this->verifyHttpAccess($this->project->domain, $deployPath, $releasePath, $server->ssh_user);

                $this->step("🔒 Obtention du certificat SSL...");
                try {
                    $this->ssl->obtain($this->project->domain, config('deploy.admin_email'));
                    $this->log("  SSL Let's Encrypt activé ✓");
                } catch (\Throwable $e) {
                    $message = $e->getMessage();

                    if ($this->isCertbotDnsNotReadyError($message)) {
                        $this->log("  ⚠️ DNS non prêt pour {$this->project->domain} (NXDOMAIN).");
                        $this->log("  Action client requise: créer un enregistrement A {$this->project->domain} → {$server->ip_address}.");
                        $this->log("  Le déploiement continue en HTTP. Le SSL sera installé automatiquement au prochain déploiement.");
                    } else {
                        throw $e;
                    }
                }
            }

            if ($this->project->has_queue_worker) {
                $this->step("⚙️  Redémarrage du worker queue...");
                $this->ssh->exec("cd {$releasePath} && php artisan queue:restart 2>&1");
                $this->log("  Worker redémarré ✓");
            }

            //  Succès 
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

            $url = $this->project->url ?? "Déployé dans {$deployPath}/current";
            $this->log("");
            $this->log("🎉 Déploiement réussi en {$duration}s — {$url}");
        } catch (\Throwable $e) {
            $this->log("");
            $this->log("❌ ERREUR : " . $e->getMessage());

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

    //  Rollback 
    public function rollback(string $targetRelease): void
    {
        $deployPath  = $this->project->deploy_path;
        $releasePath = "{$deployPath}/releases/{$targetRelease}";

        $this->ssh->exec("ln -sfn {$releasePath} {$deployPath}/current");

        $this->deployment->update(['status' => 'rolled_back']);
        $this->project->update(['current_release' => $targetRelease]);

        $this->log("↩️  Retour arrière vers {$targetRelease} effectué");
    }

    //  Helpers privés 
    private function createDirectories(string $deployPath, string $releasePath): void
    {
        $dirs = [
            $releasePath,
            "{$deployPath}/shared/storage/app/public",
            "{$deployPath}/shared/storage/framework/cache",
            "{$deployPath}/shared/storage/framework/sessions",
            "{$deployPath}/shared/storage/framework/views",
            "{$deployPath}/shared/storage/logs",
            "{$deployPath}/backups",
            "{$deployPath}/logs",
        ];

        foreach ($dirs as $dir) {
            $this->ssh->exec("mkdir -p {$dir}");
        }

        $this->log("  Structure créée ✓");
    }

    private function cloneRepo(string $releasePath): void
    {
        $repo   = $this->project->github_repo;
        $branch = $this->project->github_branch;

        // Garantir un répertoire de release vide avant clone.
        $this->ssh->exec("mkdir -p {$releasePath}");
        $this->ssh->exec("find {$releasePath} -mindepth 1 -maxdepth 1 -exec rm -rf {} +");

        $this->ssh->execStreaming(
            "git clone --depth=1 --branch={$branch} https://github.com/{$repo}.git {$releasePath} 2>&1",
            fn($line) => $this->log($line)
        );

        $this->assertReleaseLooksValid($releasePath, $repo, $branch);

        // Récupérer le hash du commit
        $commit = trim($this->ssh->exec("cd {$releasePath} && git rev-parse HEAD"));
        $this->deployment->update(['git_commit' => $commit]);
        $this->log("  Commit : {$commit}");
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
            "Le clone du dépôt est incomplet ou invalide (repo {$repo}, branche {$branch}). "
            . "Fichiers Laravel attendus absents: artisan/composer.json/public/index.php.\nContenu release:\n{$listing}"
        );
    }

    private function writeEnv(string $deployPath, string $releasePath): void
    {
        $sharedEnvPath = "{$deployPath}/shared/.env";
        $envContent = null;

        if ($this->deployment->env_file_path) {
            if (Storage::disk('local')->exists($this->deployment->env_file_path)) {
                $envContent = Storage::disk('local')->get($this->deployment->env_file_path);
                $this->log("  Utilisation du fichier .env uploadé ✓");
            } else {
                $this->log("  ⚠️ Fichier .env de déploiement introuvable, fallback automatique...");
            }
        }

        if ($envContent === null && $this->project->env_file_path) {
            if (Storage::disk('local')->exists($this->project->env_file_path)) {
                $envContent = Storage::disk('local')->get($this->project->env_file_path);
                $this->log("  Utilisation du fichier .env du projet ✓");
            } else {
                $this->log("  ⚠️ Fichier .env du projet introuvable, fallback vers variables DB...");
            }
        }

        if ($envContent === null) {
            $envContent = $this->project->envVariables
                ->map(fn($var) => "{$var->key}={$var->value}")
                ->join("\n");
            $this->log("  .env construit à partir des variables de la base de données ✓");
        }

        if (trim((string) $envContent) === '') {
            throw new RuntimeException("Aucune donnée .env disponible pour ce déploiement.");
        }

        $this->envConfig = $this->parseEnv((string) $envContent);
        $this->ssh->uploadContent($envContent, $sharedEnvPath);
        $this->log("  .env écrit dans shared/ ✓");
    }

    private function runMigrationsWithRecovery(string $releasePath): void
    {
        try {
            $this->log($this->ssh->exec("cd {$releasePath} && php artisan migrate --force 2>&1"));
            return;
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            if (!$this->isMysqlAccessDenied($message)) {
                throw $e;
            }

            $this->log("  ⚠️ Accès MySQL refusé, tentative de provision automatique...");

            if (!$this->provisionMysqlFromEnv()) {
                throw new RuntimeException(
                    "Accès MySQL refusé et auto-provision impossible. Vérifie DB_USERNAME/DB_PASSWORD et droits MySQL."
                );
            }

            $this->log("  Droits MySQL provisionnés, relance des migrations...");
            $this->log($this->ssh->exec("cd {$releasePath} && php artisan migrate --force 2>&1"));
        }
    }

    private function isMysqlAccessDenied(string $message): bool
    {
        return str_contains($message, 'SQLSTATE[HY000] [1045]')
            || str_contains($message, 'Access denied for user');
    }

    private function provisionMysqlFromEnv(): bool
    {
        if (($this->envConfig['DB_CONNECTION'] ?? null) !== 'mysql') {
            $this->log("  Auto-provision DB ignorée (DB_CONNECTION != mysql).");
            return false;
        }

        $dbName = $this->envConfig['DB_DATABASE'] ?? '';
        $dbUser = $this->envConfig['DB_USERNAME'] ?? '';
        $dbPass = $this->envConfig['DB_PASSWORD'] ?? '';

        if ($dbName === '' || $dbUser === '' || $dbPass === '') {
            $this->log("  Auto-provision DB impossible: DB_DATABASE/DB_USERNAME/DB_PASSWORD incomplets.");
            return false;
        }

        $dbNameEsc = str_replace('`', '``', $dbName);
        $dbUserEsc = str_replace("'", "''", $dbUser);
        $dbPassEsc = str_replace("'", "''", $dbPass);

        $sql = "CREATE DATABASE IF NOT EXISTS `{$dbNameEsc}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n"
            . "CREATE USER IF NOT EXISTS '{$dbUserEsc}'@'localhost' IDENTIFIED BY '{$dbPassEsc}';"
            . "\nCREATE USER IF NOT EXISTS '{$dbUserEsc}'@'%' IDENTIFIED BY '{$dbPassEsc}';"
            . "GRANT ALL PRIVILEGES ON `{$dbNameEsc}`.* TO '{$dbUserEsc}'@'localhost';"
            . "\nGRANT ALL PRIVILEGES ON `{$dbNameEsc}`.* TO '{$dbUserEsc}'@'%';"
            . "FLUSH PRIVILEGES;";

        $sqlRemotePath = "/tmp/ship_db_provision_{$this->deployment->id}.sql";
        $this->ssh->uploadContent($sql, $sqlRemotePath);

        $dbRootUser = $this->envConfig['DB_ROOT_USERNAME'] ?? 'root';
        $dbRootPass = $this->envConfig['DB_ROOT_PASSWORD'] ?? null;

        $commands = [
            "mysql < {$sqlRemotePath}",
            "mysql -u {$dbRootUser} < {$sqlRemotePath}",
            "sudo -n mysql < {$sqlRemotePath}",
        ];

        if ($dbRootPass !== null && $dbRootPass !== '') {
            $safeRootPass = str_replace("'", "'\"'\"'", $dbRootPass);
            $commands[] = "mysql -u {$dbRootUser} -p'{$safeRootPass}' < {$sqlRemotePath}";
        }

        $lastError = null;
        foreach ($commands as $command) {
            try {
                $this->ssh->exec($command);
                $this->ssh->exec("rm -f {$sqlRemotePath}");
                return true;
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        try {
            $this->ssh->exec("rm -f {$sqlRemotePath}");
        } catch (\Throwable $e) {
            // noop
        }

        if ($lastError) {
            $this->log("  Détail auto-provision: " . substr($lastError, 0, 300));
        }

        return false;
    }

    private function ensureProjectDependencies(): void
    {
        if (!config('ship.auto_install_project_deps', true)) {
            $this->log("  Auto-install des dépendances désactivé.");
            return;
        }

        $this->assertSudoAvailable();

        $dbConnection = strtolower((string) ($this->envConfig['DB_CONNECTION'] ?? ''));
        $dbHost = strtolower((string) ($this->envConfig['DB_HOST'] ?? 'localhost'));
        $redisHost = strtolower((string) ($this->envConfig['REDIS_HOST'] ?? 'localhost'));
        $phpVersion = $this->project->php_version ?: '8.2';
        $phpBin = $this->resolvePhpBinary($phpVersion);

        $usesRedis = $this->envIsRedis('CACHE_STORE')
            || $this->envIsRedis('CACHE_DRIVER')
            || $this->envIsRedis('QUEUE_CONNECTION')
            || $this->envIsRedis('SESSION_DRIVER')
            || $this->envIsRedis('BROADCAST_DRIVER')
            || $this->envIsRedis('BROADCAST_CONNECTION');

        if ($usesRedis && $this->isLocalHost($redisHost)) {
            $this->ensureSystemPackage('redis-server', 'redis-server');
        }

        if (in_array($dbConnection, ['mysql', 'mariadb'], true)) {
            if ($this->isLocalHost($dbHost)) {
                $this->ensureSystemPackage('mysql-server', 'mysql');
            }
            $this->ensurePhpExtension('pdo_mysql', 'mysql', $phpVersion, $phpBin);
        }

        if ($dbConnection === 'pgsql') {
            if ($this->isLocalHost($dbHost)) {
                $this->ensureSystemPackage('postgresql', 'postgresql');
            }
            $this->ensurePhpExtension('pdo_pgsql', 'pgsql', $phpVersion, $phpBin);
        }

        if ($dbConnection === 'sqlite') {
            $this->ensureSystemPackage('sqlite3', null);
            $this->ensurePhpExtension('pdo_sqlite', 'sqlite3', $phpVersion, $phpBin);
        }

        if ($usesRedis) {
            $this->ensurePhpExtension('redis', 'redis', $phpVersion, $phpBin);
        }

        if ($this->project->run_npm_build) {
            $this->ensureBinary('node', 'nodejs');
            $this->ensureBinary('npm', 'npm');
        }
    }

    private function envIsRedis(string $key): bool
    {
        $value = strtolower((string) ($this->envConfig[$key] ?? ''));
        return $value === 'redis';
    }

    private function isLocalHost(string $host): bool
    {
        return $host === '' || $host === 'localhost' || $host === '127.0.0.1';
    }

    private function assertSudoAvailable(): void
    {
        try {
            $this->ssh->exec('sudo -n true');
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Sudo NOPASSWD requis pour installer des dépendances (apt/systemctl).'
            );
        }
    }

    private function ensureSystemPackage(string $package, ?string $service = null): void
    {
        $installed = trim($this->ssh->exec("dpkg -s {$package} >/dev/null 2>&1 && echo ok || echo missing"));

        if ($installed !== 'ok') {
            $this->log("  Installation {$package}...");
            $this->aptUpdateOnce();
            $this->ssh->exec("sudo -n apt-get install -y {$package}");
            $this->log("  {$package} installé ✓");
        } else {
            $this->log("  {$package} déjà installé ✓");
        }

        if ($service) {
            $this->ssh->exec("sudo -n systemctl enable --now {$service}");
        }
    }

    private function ensureBinary(string $binary, string $package): void
    {
        $exists = trim($this->ssh->exec("command -v {$binary} >/dev/null 2>&1 && echo ok || echo missing"));

        if ($exists === 'ok') {
            $this->log("  {$binary} déjà présent ✓");
            return;
        }

        $this->log("  Installation {$package} (pour {$binary})...");
        $this->aptUpdateOnce();
        $this->ssh->exec("sudo -n apt-get install -y {$package}");
        $this->log("  {$package} installé ✓");
    }

    private function aptUpdateOnce(): void
    {
        if ($this->aptUpdated) {
            return;
        }

        $this->ssh->exec('sudo -n apt-get update -y');
        $this->aptUpdated = true;
    }

    private function resolvePhpBinary(string $version): string
    {
        $candidate = "php{$version}";
        $hasCandidate = trim($this->ssh->exec("command -v {$candidate} >/dev/null 2>&1 && echo ok || echo missing"));

        return $hasCandidate === 'ok' ? $candidate : 'php';
    }

    private function ensurePhpExtension(string $extension, string $packageSuffix, string $phpVersion, string $phpBin): void
    {
        $loaded = trim($this->ssh->exec("{$phpBin} -r \"echo extension_loaded('{$extension}') ? 'ok' : 'missing';\""));
        if ($loaded === 'ok') {
            $this->log("  ext {$extension} déjà chargée ✓");
            return;
        }

        $package = "php{$phpVersion}-{$packageSuffix}";
        $this->log("  Installation {$package} (ext {$extension})...");
        $this->aptUpdateOnce();
        $this->ssh->exec("sudo -n apt-get install -y {$package}");
        $this->log("  {$package} installé ✓");

        $this->restartPhpFpm($phpVersion);
    }

    private function restartPhpFpm(string $phpVersion): void
    {
        if ($this->phpFpmRestarted) {
            return;
        }

        try {
            $this->ssh->exec("sudo -n systemctl restart php{$phpVersion}-fpm");
            $this->log("  php{$phpVersion}-fpm redémarré ✓");
        } catch (\Throwable $e) {
            $this->log("  ⚠️ Redémarrage php{$phpVersion}-fpm échoué.");
        }

        $this->phpFpmRestarted = true;
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

    private function isCertbotDnsNotReadyError(string $message): bool
    {
        return str_contains($message, 'DNS problem:')
            || str_contains($message, 'NXDOMAIN')
            || str_contains($message, 'No valid IP addresses found')
            || str_contains($message, 'looking up A for')
            || str_contains($message, 'looking up AAAA for');
    }

    private function createSymlinks(string $deployPath, string $releasePath): void
    {
        // .env
        $this->ssh->exec("ln -sfn {$deployPath}/shared/.env {$releasePath}/.env");

        // storage
        $this->ssh->exec("rm -rf {$releasePath}/storage");
        $this->ssh->exec("ln -sfn {$deployPath}/shared/storage {$releasePath}/storage");

        $this->log("  Lien symbolique .env ✓");
        $this->log("  Lien symbolique storage ✓");
    }

    private function fixFilesystemPermissions(string $deployPath, string $releasePath, string $serverUser): void
    {
        $commands = [
            "sudo mkdir -p {$releasePath}/bootstrap/cache",
            "sudo mkdir -p {$deployPath}/shared/storage/framework/{cache,sessions,views} {$deployPath}/shared/storage/logs",
            "sudo chmod 755 /var /var/www /var/www/projects",
            "sudo chown -R {$serverUser}:www-data {$deployPath}",
            "sudo find {$deployPath} -type d -exec chmod 755 {} +",
            "sudo find {$deployPath} -type f -exec chmod 644 {} +",
            "sudo chmod -R 775 {$deployPath}/shared/storage",
            "sudo chmod -R 775 {$releasePath}/bootstrap/cache",
            "sudo systemctl reload apache2",
        ];

        foreach ($commands as $command) {
            try {
                $this->ssh->exec($command);
            } catch (\Throwable $e) {
                $this->log("  ⚠️ Permission command failed: {$command}");
            }
        }

        $this->log("  Permissions Linux/Apache appliquées ✓");
    }

    private function verifyHttpAccess(string $domain, string $deployPath, string $releasePath, string $serverUser): void
    {
        $status = $this->getLocalHttpStatus($domain);

        if (in_array($status, ['200', '301', '302', '404'], true)) {
            $this->log("  Vérification HTTP locale ({$status}) ✓");
            return;
        }

        if ($status === '403') {
            $this->log("  ⚠️ Apache retourne 403, correction automatique des permissions...");
            $this->fixFilesystemPermissions($deployPath, $releasePath, $serverUser);
            $status = $this->getLocalHttpStatus($domain);

            if (in_array($status, ['200', '301', '302', '404'], true)) {
                $this->log("  403 corrigé ({$status}) ✓");
                return;
            }

            $this->log("  ⚠️ 403 persistant après auto-correction. Le déploiement continue.");
            $this->log("  Le système réessaiera la correction au prochain déploiement.");
            $this->dumpApacheDiagnostics($deployPath);
            return;
        }

        $this->log("  Vérification HTTP locale: status {$status} (non bloquant).");
    }

    private function getLocalHttpStatus(string $domain): string
    {
        $hostHeader = escapeshellarg("Host: {$domain}");
        $command = "if command -v curl >/dev/null 2>&1; then curl -s -o /dev/null -w '%{http_code}' -H {$hostHeader} http://127.0.0.1 || true; else echo 000; fi";

        return trim($this->ssh->exec($command));
    }

    private function dumpApacheDiagnostics(string $deployPath): void
    {
        $checks = [
            "sudo apache2ctl -S 2>/dev/null | tail -n 20",
            "sudo tail -n 20 /var/log/apache2/error.log 2>/dev/null || true",
            "sudo tail -n 20 {$deployPath}/logs/apache_error.log 2>/dev/null || true",
        ];

        foreach ($checks as $command) {
            try {
                $output = trim($this->ssh->exec($command));
                if ($output !== '') {
                    $this->log($output);
                }
            } catch (\Throwable $e) {
                // non bloquant
            }
        }
    }

    private function ensureAppKey(string $releasePath): void
    {
        $hasKey = trim($this->ssh->exec(
            "[ -f {$releasePath}/.env ] && grep -Eq '^APP_KEY=base64:[A-Za-z0-9+/=]+' {$releasePath}/.env && echo 1 || echo 0"
        ));

        if ($hasKey !== '1') {
            $this->ssh->exec("cd {$releasePath} && php artisan key:generate --force 2>&1");
            $this->log("  APP_KEY générée ✓");
        } else {
            $this->log("  APP_KEY déjà définie ✓");
        }
    }

    private function cleanOldReleases(string $deployPath): void
    {
        $max = config('deploy.max_releases', 5);
        $startAt = ((int) $max) + 1;

        $this->ssh->exec(
            "ls -1dt {$deployPath}/releases/*/ 2>/dev/null | tail -n +{$startAt} | xargs -r rm -rf"
        );

        $this->log("  Rétention : {$max} releases maximum ✓");
    }

    private function assertCurrentReleaseIntegrity(string $releasePath): void
    {
        $ok = trim($this->ssh->exec(
            "[ -f {$releasePath}/artisan ] && [ -f {$releasePath}/composer.json ] && echo ok || echo ko"
        ));

        if ($ok !== 'ok') {
            throw new RuntimeException(
                "La release courante est invalide après nettoyage. Vérifie la politique de rétention."
            );
        }
    }

    private function ensureLogrotate(string $deployPath, string $projectKey, string $systemUser): void
    {
        $rotate = (int) config('deploy.logrotate_rotate', 14);
        $systemUser = $systemUser !== '' ? $systemUser : 'www-data';

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

        $this->ssh->uploadContent($logrotate, $tmp);
        $this->ssh->exec("sudo mv {$tmp} {$target}");
        $this->ssh->exec("sudo chown root:root {$target}");
        $this->ssh->exec("sudo chmod 644 {$target}");
    }
    //  Broadcast log 
    private function step(string $message): void
    {
        $this->log("");
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
