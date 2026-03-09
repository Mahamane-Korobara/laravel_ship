<?php

namespace App\Services;

use App\Events\DeploymentLogReceived;
use App\Models\Deployment;
use App\Models\Project;

class DeploymentService
{
    private SshService    $ssh;
    private ApacheService $apache;
    private SslService    $ssl;

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
                $this->log($this->ssh->exec("cd {$releasePath} && php artisan migrate --force 2>&1"));
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

            $this->step("🧹 Nettoyage des anciennes releases...");
            $this->cleanOldReleases($deployPath);

            if ($this->project->domain) {
                $this->step("🌐 Configuration Apache VirtualHost...");
                $this->apache->createVirtualHost(
                    name: $this->project->name,
                    domain: $this->project->domain,
                    deployPath: $deployPath,
                    phpVersion: $this->project->php_version,
                );
                $this->log("  VirtualHost {$this->project->domain} activé ✓");

                $this->step("🔒 Obtention du certificat SSL...");
                $this->ssl->obtain($this->project->domain, config('deploy.admin_email'));
                $this->log("  SSL Let's Encrypt activé ✓");
            }

            if ($this->project->has_queue_worker) {
                $this->step("⚙️  Redémarrage du worker queue...");
                $this->ssh->exec("cd {$releasePath} && php artisan queue:restart 2>&1");
                $this->log("  Worker redémarré ✓");
            }

            //  Succès 
            $duration = now()->diffInSeconds($this->deployment->started_at);

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

        $this->log("↩️  Rollback vers {$targetRelease} effectué");
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

        $this->ssh->execStreaming(
            "git clone --depth=1 --branch={$branch} https://github.com/{$repo}.git {$releasePath} 2>&1",
            fn($line) => $this->log($line)
        );

        // Récupérer le hash du commit
        $commit = trim($this->ssh->exec("cd {$releasePath} && git rev-parse HEAD"));
        $this->deployment->update(['git_commit' => $commit]);
        $this->log("  Commit : {$commit}");
    }

    private function writeEnv(string $deployPath, string $releasePath): void
    {
        $sharedEnvPath = "{$deployPath}/shared/.env";

        // Construire le contenu .env depuis la DB
        $envContent = $this->project->envVariables
            ->map(fn($var) => "{$var->key}={$var->value}")
            ->join("\n");

        $this->ssh->uploadContent($envContent, $sharedEnvPath);
        $this->log("  .env écrit dans shared/ ✓");
    }

    private function createSymlinks(string $deployPath, string $releasePath): void
    {
        // .env
        $this->ssh->exec("ln -sfn {$deployPath}/shared/.env {$releasePath}/.env");

        // storage
        $this->ssh->exec("rm -rf {$releasePath}/storage");
        $this->ssh->exec("ln -sfn {$deployPath}/shared/storage {$releasePath}/storage");

        $this->log("  .env symlink ✓");
        $this->log("  storage symlink ✓");
    }

    private function ensureAppKey(string $releasePath): void
    {
        $hasKey = $this->ssh->exec(
            "grep -c 'APP_KEY=base64' {$releasePath}/.env || echo '0'"
        );

        if (trim($hasKey) === '0') {
            $this->ssh->exec("cd {$releasePath} && php artisan key:generate --force 2>&1");
            $this->log("  APP_KEY générée ✓");
        } else {
            $this->log("  APP_KEY déjà définie ✓");
        }
    }

    private function cleanOldReleases(string $deployPath): void
    {
        $max = config('deploy.max_releases', 5);

        $this->ssh->exec(
            "ls -1dt {$deployPath}/releases/*/ 2>/dev/null | tail -n +$((max + 1)) | xargs rm -rf"
        );

        $this->log("  Rétention : {$max} releases maximum ✓");
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
