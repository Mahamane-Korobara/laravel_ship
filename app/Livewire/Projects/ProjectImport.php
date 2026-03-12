<?php

namespace App\Livewire\Projects;

use App\Models\Project;
use App\Models\User;
use App\Services\GitHubService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProjectImport extends Component
{
    public string  $search  = '';
    public array   $repos   = [];
    public bool    $loading = true;
    public ?string $error   = null;

    public function mount(): void
    {
        $this->loadRepos();
    }

    public function loadRepos(): void
    {
        $this->loading = true;
        $this->error   = null;

        /** @var User $user */
        $user = Auth::user();

        if (!$user->hasGithubConnected()) {
            $this->error   = 'Connecte ton compte GitHub pour importer un projet.';
            $this->loading = false;
            return;
        }

        try {
            $github      = new GitHubService($user->github_token);
            $this->repos = $github->getUserRepos();
        } catch (\Exception $e) {
            $this->error = 'Impossible de charger les dépôts : ' . $e->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    public function refreshRepos(): void
    {
        $this->search = '';
        $this->loadRepos();
    }

    public function importRepo(string $repoFullName): void
    {
        /** @var User $user */
        $user = Auth::user();

        $exists = Project::where('user_id', $user->id)
            ->where('github_repo', $repoFullName)
            ->exists();

        if ($exists) {
            session()->flash('error', 'Ce dépôt est déjà importé.');
            return;
        }

        $repoName = explode('/', $repoFullName)[1];
        $basePath = config('deploy.base_path', '/var/www/projects');

        $project = Project::create([
            'user_id'     => $user->id,
            'server_id'   => $user->servers()->first()?->id,
            'name'        => $repoName,
            'github_repo' => $repoFullName,
            'deploy_path' => "{$basePath}/{$repoName}",
            'status'      => 'idle',
        ]);

        $this->redirect(route('projects.deploy', $project), navigate: true);
    }

    public function getFilteredReposProperty(): array
    {
        if (empty($this->search)) return $this->repos;

        return array_values(array_filter(
            $this->repos,
            fn($r) => str_contains(strtolower($r['full_name']), strtolower($this->search))
                || str_contains(strtolower($r['description'] ?? ''), strtolower($this->search))
        ));
    }

    public function render()
    {
        /** @var User $user */
        $user = Auth::user();

        return view('livewire.projects.import', [
            'filteredRepos' => $this->filteredRepos,
            'importedRepos' => $user->projects()->pluck('github_repo')->toArray(),
        ]);
    }
}
