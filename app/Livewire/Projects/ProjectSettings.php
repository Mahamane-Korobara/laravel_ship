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
    public string $php_version   = '8.2';
    public ?int   $server_id     = null;
    public bool   $run_migrations   = true;
    public bool   $run_seeders      = false;
    public bool   $run_npm_build    = false;
    public bool   $has_queue_worker = false;

    public bool $confirmDelete = false;

    protected function rules(): array
    {
        return [
            'name'          => 'required|string|max:255',
            'github_branch' => 'required|string',
            'domain'        => 'nullable|string|max:255',
            'php_version'   => 'required|in:7.4,8.0,8.1,8.2,8.3,8.4',
            'server_id'     => 'nullable|exists:servers,id',
        ];
    }

    public function mount(Project $project): void
    {
        abort_if($project->user_id !== Auth::id(), 403);
        $this->project = $project;

        $this->name             = $project->name;
        $this->github_branch    = $project->github_branch;
        $this->domain           = $project->domain ?? '';
        $this->php_version      = $project->php_version;
        $this->server_id        = $project->server_id;
        $this->run_migrations   = $project->run_migrations;
        $this->run_seeders      = $project->run_seeders;
        $this->run_npm_build    = $project->run_npm_build;
        $this->has_queue_worker = $project->has_queue_worker;
    }

    public function save(): void
    {
        $this->validate();

        $this->project->update([
            'name'            => $this->name,
            'github_branch'   => $this->github_branch,
            'domain'          => $this->domain ?: null,
            'php_version'     => $this->php_version,
            'server_id'       => $this->server_id,
            'run_migrations'  => $this->run_migrations,
            'run_seeders'     => $this->run_seeders,
            'run_npm_build'   => $this->run_npm_build,
            'has_queue_worker' => $this->has_queue_worker,
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
