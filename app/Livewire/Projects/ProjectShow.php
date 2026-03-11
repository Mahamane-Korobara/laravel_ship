<?php

namespace App\Livewire\Projects;

use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProjectShow extends Component
{
    public Project $project;

    public function mount(Project $project): void
    {
        abort_if($project->user_id !== Auth::id(), 403);
        $this->project = $project;
    }

    public function render()
    {
        return view('livewire.projects.show', [
            'deployments' => $this->project->deployments()->take(10)->get(),
            'releases'    => $this->getReleases(),
        ]);
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
}
