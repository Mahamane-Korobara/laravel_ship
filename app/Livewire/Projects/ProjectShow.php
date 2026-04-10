<?php

namespace App\Livewire\Projects;

use App\Models\Deployment;
use App\Models\Project;
use App\Services\GitHubService;
use App\Services\RemoteRunner;
use App\Services\RemoteRunnerFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProjectShow extends Component
{
    public Project $project;

    public string $activeTab = 'overview';

    public array $rollbackReleases = [];
    public string $rollbackTarget = '';

    public array $backups = [];
    public bool $backupsLoaded = false;
    public ?string $backupsError = null;

    public function mount(Project $project): void
    {
        abort_if($project->user_id !== Auth::id(), 403);
        $this->project = $project;

        $this->activeTab = request()->get('tab', 'overview');

        $this->loadRollbackReleases();

        if ($this->activeTab === 'backups') {
            $this->loadBackups();
        }
    }

    public function render()
    {
        return view('livewire.projects.show', [
            'deployments' => $this->project->deployments()->take(10)->get(),
            'releases'    => $this->getReleases(),
        ]);
    }

    public function repairWebhook(): void
    {
        $project = $this->project->fresh();
        $user = Auth::user();

        if (!$user || !$user->hasGithubConnected()) {
            session()->flash('error', 'Connecte ton compte GitHub pour réparer le webhook.');
            return;
        }

        try {
            $github = new GitHubService($user->github_token);

            if (!empty($project->github_webhook_id)) {
                try {
                    $github->deleteWebhook($project->github_repo, $project->github_webhook_id);
                } catch (\Throwable $e) {
                    Log::warning("Erreur suppression webhook GitHub {$project->github_repo}: {$e->getMessage()}");
                }
            }

            $secret = Str::random(40);
            $webhookUrl = route('webhooks.github', [], true);
            $hook = $github->createWebhook($project->github_repo, $webhookUrl, $secret);

            $hookId = $hook['id'] ?? null;
            if (!$hookId) {
                throw new \RuntimeException('Webhook créé sans ID.');
            }

            $project->update([
                'github_webhook_id' => (string) $hookId,
                'github_webhook_secret' => $secret,
            ]);

            $this->project->refresh();
            session()->flash('success', 'Webhook GitHub réparé.');
        } catch (\Throwable $e) {
            session()->flash('error', 'Impossible de réparer le webhook : ' . $e->getMessage());
        }
    }

    public function rollbackSymlink(): void
    {
        $project = $this->project->fresh(['server']);

        if (!$project->server) {
            session()->flash('error', 'Aucun serveur lié à ce projet.');
            return;
        }

        $allowedReleases = array_column($this->rollbackReleases, 'name');
        if (!in_array($this->rollbackTarget, $allowedReleases, true)) {
            session()->flash('error', 'Version de retour arrière invalide.');
            return;
        }

        $server = $project->server;

        try {
            $ssh = app(RemoteRunnerFactory::class)->forServer($server);

            $deployPath = $project->deploy_path;
            $releasePath = "{$deployPath}/releases/{$this->rollbackTarget}";

            $exists = trim($ssh->exec("[ -d {$releasePath} ] && echo yes || echo no"));
            if ($exists !== 'yes') {
                $ssh->disconnect();
                session()->flash('error', 'Version introuvable sur le VPS.');
                return;
            }

            $ssh->exec("ln -sfn {$releasePath} {$deployPath}/current");
            $ssh->exec("if [ -f {$releasePath}/docker-compose.yml ]; then cd {$releasePath} && docker compose up -d --remove-orphans; fi");
            $ssh->disconnect();

            $now = now();
            Deployment::create([
                'project_id'       => $project->id,
                'release_name'     => $this->rollbackTarget,
                'git_branch'       => $project->github_branch,
                'triggered_by'     => 'manual',
                'status'           => 'rolled_back',
            'log'              => "Retour arrière vers {$this->rollbackTarget} (lien symbolique current mis à jour, conteneur relancé).",
                'started_at'       => $now,
                'finished_at'      => $now,
                'duration_seconds' => 1,
            ]);

            $project->update([
                'current_release' => $this->rollbackTarget,
                'status' => 'deployed',
            ]);

            $this->project->refresh();
            $this->loadRollbackReleases();

            session()->flash('success', 'Retour arrière effectué (lien symbolique current mis à jour).');
        } catch (\Throwable $e) {
            session()->flash('error', 'Retour arrière échoué : ' . $e->getMessage());
        }
    }

    public function loadBackups(): void
    {
        $this->backupsLoaded = true;
        $this->backupsError = null;
        $this->backups = [];

        $project = $this->project->fresh(['server']);
        if (!$project->server) {
            $this->backupsError = 'Aucun serveur lié à ce projet.';
            return;
        }

        try {
            $ssh = app(RemoteRunnerFactory::class)->forServer($project->server);

            $backupDir = "{$project->deploy_path}/backups";
            $command = "if [ -d \"{$backupDir}\" ]; then find \"{$backupDir}\" -maxdepth 1 -type f -name \"*.sql.gz\" -printf \"%f|%s|%TY-%Tm-%Td %TH:%TM:%TS\\n\" | sort -r; fi";
            $output = trim($ssh->exec($command));
            $ssh->disconnect();

            if ($output === '') {
                $this->backups = [];
                return;
            }

            $lines = array_filter(explode("\n", $output));
            $this->backups = array_map(function (string $line) {
                [$name, $size, $date] = array_pad(explode('|', $line, 3), 3, '');
                $sizeBytes = is_numeric($size) ? (int) $size : 0;
                $dateClean = trim($date);

                return [
                    'name' => $name,
                    'size' => $this->formatBytes($sizeBytes),
                    'date' => $dateClean !== '' ? substr($dateClean, 0, 19) : '—',
                ];
            }, $lines);
        } catch (\Throwable $e) {
            $this->backupsError = 'Impossible de charger les sauvegardes : ' . $e->getMessage();
        }
    }

    public function downloadBackup(string $filename)
    {
        $project = $this->project->fresh(['server']);
        if (!$project->server) {
            session()->flash('error', 'Aucun serveur lié à ce projet.');
            return;
        }

        $safeName = basename($filename);
        if ($safeName !== $filename || !preg_match('/^[A-Za-z0-9._-]+\\.sql\\.gz$/', $safeName)) {
            session()->flash('error', 'Fichier de sauvegarde invalide.');
            return;
        }

        $backupDir = "{$project->deploy_path}/backups";
        $remotePath = "{$backupDir}/{$safeName}";
        $localDir = storage_path('app/backup-downloads');
        $localPath = "{$localDir}/" . Str::random(8) . "-{$safeName}";

        try {
            $ssh = app(RemoteRunnerFactory::class)->forServer($project->server);

            $exists = trim($ssh->exec("if [ -f \"{$remotePath}\" ]; then echo yes; else echo no; fi"));
            if ($exists !== 'yes') {
                $ssh->disconnect();
                session()->flash('error', 'Sauvegarde introuvable sur le VPS.');
                return;
            }

            $ssh->downloadFile($remotePath, $localPath);
            $ssh->disconnect();
        } catch (\Throwable $e) {
            session()->flash('error', 'Téléchargement impossible : ' . $e->getMessage());
            return;
        }

        return response()->download($localPath, $safeName)->deleteFileAfterSend(true);
    }

    public function deleteProject(): void
    {
        $project = $this->project->fresh(['server', 'deployments', 'envVariables']);

        $this->deleteGithubWebhook($project);

        try {
            $this->cleanupRemoteProject($project);
        } catch (\Throwable $e) {
            session()->flash('error', "Suppression annulée : {$e->getMessage()}");
            return;
        }

        $this->cleanupLocalFiles($project);
        $project->envVariables()->delete();
        $project->deployments()->delete();
        $project->delete();

        session()->flash('success', "Projet supprimé et nettoyé.");
        $this->redirect(route('projects.index'), navigate: true);
    }

    private function deleteGithubWebhook(Project $project): void
    {
        $user = Auth::user();

        if (!$user || !$user->hasGithubConnected()) {
            return;
        }

        if (empty($project->github_webhook_id)) {
            return;
        }

        try {
            $github = new GitHubService($user->github_token);
            $github->deleteWebhook($project->github_repo, $project->github_webhook_id);
        } catch (\Throwable $e) {
            Log::warning("Erreur suppression webhook GitHub {$project->github_repo}: {$e->getMessage()}");
        }
    }

    private function loadRollbackReleases(): void
    {
        $current = $this->project->current_release;

        $this->rollbackReleases = $this->project
            ->deployments()
            ->where('status', 'success')
            ->orderByDesc('created_at')
            ->get(['release_name', 'created_at'])
            ->filter(fn($deployment) => $deployment->release_name !== $current)
            ->map(function ($deployment) {
                $label = $deployment->release_name;
                if ($deployment->created_at) {
                    $label .= ' · ' . $deployment->created_at->format('d M Y, H:i');
                }

                return [
                    'name' => $deployment->release_name,
                    'label' => $label,
                ];
            })
            ->values()
            ->toArray();

        $this->rollbackTarget = $this->rollbackReleases[0]['name'] ?? '';
    }

    private function getReleases(): array
    {
        $releasesPath = $this->project->releases_path;

        // En local on retourne vide, sur le VPS la liste vient des dossiers
        return $this->project->deployments()
            ->where('status', 'success')
            ->orderByDesc('created_at')
            ->take(5)
            ->pluck('release_name')
            ->toArray();
    }

    private function cleanupLocalFiles(Project $project): void
    {
        if ($project->env_file_path) {
            Storage::disk('local')->delete($project->env_file_path);
        }

        foreach ($project->deployments as $deployment) {
            if (!empty($deployment->env_file_path)) {
                Storage::disk('local')->delete($deployment->env_file_path);
            }
        }
    }

    private function cleanupRemoteProject(Project $project): void
    {
        $server = $project->server;
        if (!$server) {
            return;
        }

        $ssh = app(RemoteRunnerFactory::class)->forServer($server);

        $deployPath = $project->deploy_path;
        $dockerBin = $this->resolveDockerBin($ssh);
        $projectKey = strtolower(trim($project->name));
        $projectKey = preg_replace('/[^a-z0-9]+/', '-', $projectKey) ?? '';
        $projectKey = trim($projectKey, '-');
        if ($projectKey === '') {
            $projectKey = basename($deployPath);
        }

        //  Stopper et nettoyer le conteneur/app docker
        $ssh->exec("if [ -d {$deployPath}/releases ]; then for f in {$deployPath}/releases/*/docker-compose.yml; do [ -f \"\\$f\" ] && (cd \"\\$(dirname \"\\$f\")\" && {$dockerBin} compose down --remove-orphans >/dev/null 2>&1 || true); done; fi");
        $ssh->exec("{$dockerBin} rm -f ship-{$projectKey} >/dev/null 2>&1 || true");
        $ssh->exec("{$dockerBin} images --format '{{.Repository}}:{{.Tag}}' | grep -E '^{$projectKey}:' | xargs -r {$dockerBin} rmi -f || true");

        //  Nettoyer tous les fichiers du projet
        $ssh->exec("sudo rm -rf {$deployPath}");

        $ssh->disconnect();
    }

    private function resolveDockerBin(RemoteRunner $ssh): string
    {
        try {
            $ok = trim($ssh->exec("docker info >/dev/null 2>&1 && echo ok || echo fail")) === 'ok';
            if ($ok) {
                return 'docker';
            }
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $ok = trim($ssh->exec("sudo -n docker info >/dev/null 2>&1 && echo ok || echo fail")) === 'ok';
            if ($ok) {
                return 'sudo -n docker';
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return 'docker';
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $pow);

        return number_format($value, $value < 10 ? 1 : 0) . ' ' . $units[$pow];
    }
}
