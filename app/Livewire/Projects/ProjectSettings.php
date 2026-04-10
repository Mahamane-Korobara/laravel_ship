<?php

namespace App\Livewire\Projects;

use App\Models\Project;
use App\Models\Server;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProjectSettings extends Component
{
    public Project $project;

    public string $name          = '';
    public string $github_branch = 'main';
    public string $domain        = '';
    public ?int   $server_id     = null;
    public bool   $run_migrations   = true;
    public bool   $run_seeders      = false;
    public bool   $run_npm_build    = false;
    public bool   $has_queue_worker = false;
    public string $docker_image = '';
    public string $registry = '';
    public array $tags = [];

    public bool $confirmDelete = false;

    protected function rules(): array
    {
        return [
            'name'          => 'required|string|max:255',
            'github_branch' => 'required|string',
            'domain'        => 'nullable|string|max:255',
            'server_id'     => 'nullable|exists:servers,id',
            'docker_image' => 'nullable|string|max:255',
            'registry' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
        ];
    }

    public function mount(Project $project): void
    {
        abort_if($project->user_id !== Auth::id(), 403);
        $this->project = $project;

        $this->name             = $project->name;
        $this->github_branch    = $project->github_branch;
        $this->domain           = $project->domain ?? '';
        $this->server_id        = $project->server_id;
        $this->run_migrations   = $project->run_migrations;
        $this->run_seeders      = $project->run_seeders;
        $this->run_npm_build    = $project->run_npm_build;
        $this->has_queue_worker = $project->has_queue_worker;
        $this->docker_image = $project->docker_image ?? '';
        $this->registry = $project->registry ?? '';
        $this->tags = $project->tags ?? [];
    }

    public function save(): void
    {
        $this->validate();

        $this->project->update([
            'name'            => $this->name,
            'github_branch'   => $this->github_branch,
            'domain'          => $this->domain ?: null,
            'server_id'       => $this->server_id,
            'run_migrations'  => $this->run_migrations,
            'run_seeders'     => $this->run_seeders,
            'run_npm_build'   => $this->run_npm_build,
            'has_queue_worker' => $this->has_queue_worker,
            'docker_image' => $this->docker_image ?: null,
            'registry' => $this->registry ?: null,
            'tags' => $this->tags ?: null,
        ]);

        session()->flash('success', 'Paramètres sauvegardés.');
    }

    public function deleteProject(): void
    {
        $this->project->delete();
        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.projects.settings', [
            'servers' => Server::query()
                ->where('user_id', Auth::id())
                ->orderBy('name')
                ->get(),
        ]);
    }
}
