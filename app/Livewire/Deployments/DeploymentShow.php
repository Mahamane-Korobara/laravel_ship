<?php
// Terminal temps réel

namespace App\Livewire\Deployments;

use App\Models\Deployment;
use App\Models\Project;
use App\Services\RemoteRunnerFactory;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
class DeploymentShow extends Component
{
    public Deployment $deployment;
    public Project    $project;

    public array  $logs      = [];
    public string $status    = 'pending';
    public bool   $completed = false;
    public int    $deploymentId;

    public array  $rollbackReleases = [];
    public string $rollbackTarget = '';

    public string $docker_logs = '';
    public string $container_status = '';

    public function mount(Deployment $deployment): void
    {
        abort_if($deployment->project->user_id !== Auth::id(), 403);

        $this->deployment = $deployment;
        $this->project    = $deployment->project;
        $this->status     = $deployment->status;
        $this->deploymentId = $deployment->id;

        // Charger les logs existants si le déploiement est déjà en cours ou terminé
        if ($deployment->log) {
            $this->logs = array_filter(explode("\n", $deployment->log));
        }

        $this->completed = in_array($deployment->status, ['success', 'failed', 'rolled_back']);

        $this->loadRollbackReleases();
        $this->docker_logs = $deployment->docker_logs ?? '';
        $this->container_status = $deployment->container_status ?? '';
    }

    #[On('echo:deployment.{deploymentId},log.received')]
    public function handleRealtimeLog(array $payload): void
    {
        $line = trim($payload['line'] ?? '');
        if ($line === '') {
            return;
        }

        $this->logs[] = $line;
    }

    public function refreshDeploymentState(): void
    {
        $this->deployment->refresh();
        $this->logs = array_filter(explode("\n", (string) $this->deployment->log));
        $this->status = $this->deployment->status;

        if (in_array($this->status, ['success', 'failed', 'rolled_back'])) {
            $this->completed = true;
        }
    }

    public function cancelDeployment(): void
    {
        if ($this->deployment->status !== 'pending') return;

        $this->deployment->update(['status' => 'failed']);
        $this->project->update(['status' => 'failed']);
        $this->status    = 'failed';
        $this->completed = true;
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

    public function render()
    {
        return view('livewire.deployments.show', [
            'deployment' => $this->deployment,
            'project' => $this->project,
            'logs' => $this->logs,
            'status' => $this->status,
            'completed' => $this->completed,
            'deploymentId' => $this->deploymentId,
            'rollbackReleases' => $this->rollbackReleases,
            'rollbackTarget' => $this->rollbackTarget,
            'docker_logs' => $this->docker_logs,
            'container_status' => $this->container_status,
        ]);
    }
}
