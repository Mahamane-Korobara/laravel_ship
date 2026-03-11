<?php
// Terminal temps réel

namespace App\Livewire\Deployments;

use App\Models\Deployment;
use App\Models\Project;
use App\Jobs\RunDeployment;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class DeploymentShow extends Component
{
    public Deployment $deployment;
    public Project    $project;

    public array  $logs      = [];
    public string $status    = 'pending';
    public bool   $completed = false;

    public function mount(Deployment $deployment): void
    {
        abort_if($deployment->project->user_id !== Auth::id(), 403);

        $this->deployment = $deployment;
        $this->project    = $deployment->project;
        $this->status     = $deployment->status;

        // Charger les logs existants si le déploiement est déjà en cours ou terminé
        if ($deployment->log) {
            $this->logs = array_filter(explode("\n", $deployment->log));
        }

        $this->completed = in_array($deployment->status, ['success', 'failed', 'rolled_back']);
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

    public function rollback(string $releaseName): void
    {
        abort_if($this->project->user_id !== Auth::id(), 403);

        $rollbackDeployment = Deployment::create([
            'project_id'   => $this->project->id,
            'release_name' => $releaseName,
            'git_branch'   => $this->project->github_branch,
            'triggered_by' => 'manual',
            'status'       => 'pending',
        ]);

        RunDeployment::dispatch($rollbackDeployment, $this->project->fresh());
        $this->redirect(route('deployments.show', $rollbackDeployment), navigate: true);
    }

    public function render()
    {
        return view('livewire.deployments.show');
    }
}
