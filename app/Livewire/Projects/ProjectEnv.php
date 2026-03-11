<?php

namespace App\Livewire\Projects;

use App\Models\EnvVariable;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ProjectEnv extends Component
{
    public Project $project;
    public string $newKey = '';
    public string $newValue = '';
    public array $revealed = [];

    public function mount(Project $project): void
    {
        abort_if($project->user_id !== Auth::id(), 403);
        $this->project = $project;
    }

    public function addVariable(): void
    {
        $validated = $this->validate([
            'newKey' => 'required|string|max:255',
            'newValue' => 'required|string',
        ]);

        $this->project->envVariables()->create([
            'key' => $validated['newKey'],
            'value' => $validated['newValue'],
            'is_secret' => true,
        ]);

        $this->newKey = '';
        $this->newValue = '';
    }

    public function deleteVariable(int $id): void
    {
        EnvVariable::query()
            ->where('project_id', $this->project->id)
            ->where('id', $id)
            ->delete();

        $this->revealed = array_values(array_diff($this->revealed, [$id]));
    }

    public function toggleReveal(int $id): void
    {
        if (in_array($id, $this->revealed, true)) {
            $this->revealed = array_values(array_diff($this->revealed, [$id]));
            return;
        }

        $this->revealed[] = $id;
    }

    public function render()
    {
        $this->project->refresh();

        return view('livewire.projects.env', [
            'project' => $this->project,
            'envVariables' => $this->project->envVariables()->orderBy('key')->get(),
        ]);
    }
}
