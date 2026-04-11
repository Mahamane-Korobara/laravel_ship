<?php

namespace App\Livewire\Projects;

use App\Models\Project;
use App\Models\User;
use App\Services\GitHubService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
            $this->dispatch('notify', message: 'Compte GitHub non connecté.', type: 'error');
            $this->loading = false;
            return;
        }

        try {
            $github      = new GitHubService($user->github_token);
            $this->repos = $github->getUserRepos();
        } catch (\Exception $e) {
            $this->error = 'Impossible de charger les dépôts : ' . $e->getMessage();
            $this->dispatch('notify', message: 'Erreur lors du chargement des dépôts GitHub.', type: 'error');
            Log::error("Erreur chargement repos GitHub pour l'utilisateur {$user->id}: {$e->getMessage()}");
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
            $this->dispatch('notify', message: 'Ce dépôt est déjà importé.', type: 'error');
            return;
        }

        try {
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

            if (!$this->createGithubWebhook($user, $project, $repoFullName)) {
                $project->delete();
                session()->flash('error', 'Import annulé : impossible de créer le webhook GitHub.');
                $this->dispatch('notify', message: 'Erreur lors de la création du webhook GitHub.', type: 'error');
                return;
            }

            session()->flash('success', "Projet importé avec succès.");
            $this->dispatch('notify', message: 'Projet importé avec succès. Configuration disponible.', type: 'success');
            $this->redirect(route('projects.deploy', $project), navigate: true);
        } catch (\Throwable $e) {
            session()->flash('error', 'Import échoué : ' . $e->getMessage());
            $this->dispatch('notify', message: 'Erreur lors de l\'import du projet.', type: 'error');
            Log::error("Erreur import du dépôt {$repoFullName} pour l'utilisateur {$user->id}: {$e->getMessage()}");
        }
    }

    private function createGithubWebhook(User $user, Project $project, string $repoFullName): bool
    {
        if (!$user->hasGithubConnected()) {
            return false;
        }

        $secret = Str::random(40);

        try {
            $github = new GitHubService($user->github_token);
            $webhookUrl = route('webhooks.github', [], true);
            $hook = $github->createWebhook($repoFullName, $webhookUrl, $secret);

            $hookId = $hook['id'] ?? null;
            if (!$hookId) {
                Log::warning("Webhook créé sans ID pour {$repoFullName}");
                return false;
            }

            $project->update([
                'github_webhook_id' => (string) $hookId,
                'github_webhook_secret' => $secret,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning("Erreur création webhook GitHub {$repoFullName}: {$e->getMessage()}");
            return false;
        }
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
