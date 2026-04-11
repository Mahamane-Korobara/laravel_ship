<?php

namespace App\Livewire\Projects;

use App\Jobs\RunDeployment;
use App\Models\Deployment;
use App\Models\EnvVariable;
use App\Models\Project;
use App\Models\User;
use App\Services\GitHubService;
use App\Services\RemoteRunnerFactory;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

#[Layout('layouts.app')]
class ProjectDeploy extends Component
{
    public Project $project;

    // Formulaire
    public string $name          = '';
    public string $github_branch = 'main';
    public int    $server_id     = 0;
    public string $domain        = '';

    // Options
    public bool $run_migrations   = true;
    public bool $run_seeders      = false;
    public bool $run_npm_build    = false;
    public bool $has_queue_worker = false;

    // Variables .env
    public array $envVars = [];
    public ?string $deploymentEnvFilePath = null;

    // Branches disponibles
    public array   $branches = ['main', 'master'];
    public bool    $loadingBranches = false;

    // Audit dépendances
    public array $dependencyAudit = [];
    public bool $auditRunning = false;
    public ?string $auditError = null;

    protected function rules(): array
    {
        $rules = [
            'name'          => 'required|string|max:255',
            'github_branch' => 'required|string',
            'server_id'     => 'required|integer|exists:servers,id',
            'domain'        => 'nullable|string|max:255',
        ];

        if (!$this->deploymentEnvFilePath) {
            $rules['envVars.*.key'] = 'required|string|max:255';
            $rules['envVars.*.value'] = 'required|string';
        } else {
            $rules['envVars.*.key'] = 'nullable|string|max:255';
            $rules['envVars.*.value'] = 'nullable|string';
        }

        return $rules;
    }

    public function mount(Project $project): void
    {
        abort_if($project->user_id !== Auth::id(), 403);
        $this->project = $project;

        // Pré-remplir le formulaire
        $this->name          = $project->name;
        $this->github_branch = $project->github_branch;
        $this->server_id     = $project->server_id ?? 0;
        $this->domain        = $project->domain ?? '';
        $this->run_migrations   = $project->run_migrations;
        $this->run_seeders      = $project->run_seeders;
        $this->run_npm_build    = $project->run_npm_build;
        $this->has_queue_worker = $project->has_queue_worker;

        // Charger les variables .env existantes
        $this->envVars = $project->envVariables
            ->map(fn($v) => ['key' => $v->key, 'value' => $v->value, 'is_secret' => $v->is_secret])
            ->toArray();

        if (empty($this->envVars)) {
            $this->addEnvVar();
        }

        // Charger les branches GitHub
        $this->loadBranches();
    }

    public function loadBranches(): void
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasGithubConnected()) {
            return;
        }

        $this->loadingBranches = true;

        try {
            $github         = new GitHubService($user->github_token);
            $this->branches = $github->getBranches($this->project->github_repo);
        } catch (\Exception $e) {
            $this->branches = ['main', 'master'];
        } finally {
            $this->loadingBranches = false;
        }
    }

    public function addEnvVar(): void
    {
        $this->envVars[] = ['key' => '', 'value' => '', 'is_secret' => true];
    }

    public function removeEnvVar(int $index): void
    {
        unset($this->envVars[$index]);
        $this->envVars = array_values($this->envVars);
    }

    #[On('env-file-uploaded')]
    public function handleEnvFileUpload(string $filePath): void
    {
        $this->deploymentEnvFilePath = $filePath;
        $this->resetValidation(['envVars.*.key', 'envVars.*.value']);
    }

    #[On('env-file-cleared')]
    public function handleEnvFileCleared(): void
    {
        $this->deploymentEnvFilePath = null;
    }

    public function deploy(): void
    {
        try {
            $this->validate();

            // Mettre à jour le projet
            $this->project->update([
                'name'            => $this->name,
                'github_branch'   => $this->github_branch,
                'server_id'       => $this->server_id,
                'domain'          => $this->domain ?: null,
                'run_migrations'  => $this->run_migrations,
                'run_seeders'     => $this->run_seeders,
                'run_npm_build'   => $this->run_npm_build,
                'has_queue_worker' => $this->has_queue_worker,
                'webhook_pending' => false,
            ]);

            // Sauvegarder les variables .env (chiffrées)
            $this->project->envVariables()->delete();

            foreach ($this->envVars as $var) {
                if (!empty($var['key']) && !empty($var['value'])) {
                    EnvVariable::create([
                        'project_id' => $this->project->id,
                        'key'        => trim($var['key']),
                        'value'      => $var['value'],
                        'is_secret'  => $var['is_secret'] ?? true,
                    ]);
                }
            }

            // Créer le déploiement
            $deployment = Deployment::create([
                'project_id'   => $this->project->id,
                'release_name' => now()->format('Ymd_His'),
                'git_branch'   => $this->github_branch,
                'triggered_by' => 'manual',
                'status'       => 'pending',
                'env_file_path' => $this->deploymentEnvFilePath,
            ]);

            // Pousser le job dans la queue
            RunDeployment::dispatch($deployment, $this->project->fresh());

            session()->flash('success', 'Déploiement lancé avec succès.');
            $this->dispatch('notify', message: 'Déploiement en cours...', type: 'success');
            // Rediriger vers le terminal
            $this->redirect(route('deployments.show', $deployment), navigate: true);
        } catch (\Exception $e) {
            session()->flash('error', 'Erreur lors du déploiement : ' . $e->getMessage());
            $this->dispatch('notify', message: 'Erreur lors du déploiement.', type: 'error');
        }
    }

    public function runDependencyAudit(): void
    {
        $this->auditRunning = true;
        $this->auditError = null;
        $this->dependencyAudit = [];

        try {
            if (!$this->server_id) {
                throw new RuntimeException('Choisis un serveur avant l’audit.');
            }

            /** @var User $user */
            $user = Auth::user();
            $server = $user->servers()->where('id', $this->server_id)->first();

            if (!$server) {
                throw new RuntimeException('Serveur introuvable.');
            }

            $ssh = app(RemoteRunnerFactory::class)->forServer($server);

            $dockerVersion = trim($ssh->exec("docker --version 2>/dev/null || true"));
            if ($dockerVersion === '') {
                $dockerVersion = trim($ssh->exec("sudo -n docker --version 2>/dev/null || true"));
            }

            $dockerBin = $dockerVersion !== '' ? 'docker' : 'sudo -n docker';
            $composeVersion = trim($ssh->exec("{$dockerBin} compose version 2>/dev/null || true"));
            $psOutput = trim($ssh->exec("{$dockerBin} ps --format '{{.Names}}' 2>/dev/null || true"));

            $checks = [
                ['label' => 'Docker Engine', 'value' => $dockerVersion],
                ['label' => 'Docker Compose', 'value' => $composeVersion],
                ['label' => 'Acces Docker', 'value' => $psOutput],
            ];

            foreach ($checks as $check) {
                $ok = $check['value'] !== '';
                $this->dependencyAudit[] = $this->formatAudit(
                    $check['label'],
                    'docker',
                    $ok,
                    $ok ? $check['value'] : 'non detecte'
                );
            }

            $ssh->disconnect();
            $this->dispatch('notify', message: 'Audit des dépendances complété avec succès.', type: 'success');
        } catch (\Throwable $e) {
            $this->auditError = $e->getMessage();
            $this->dispatch('notify', message: 'Erreur lors de l\'audit : ' . $e->getMessage(), type: 'error');
        } finally {
            $this->auditRunning = false;
        }
    }

    public function render()
    {
        /** @var User $user */
        $user = Auth::user();

        return view('livewire.projects.deploy', [
            'servers' => $user->servers()->where('status', 'active')->get(),
        ]);
    }


    private function formatAudit(string $label, string $type, bool $ok, string $identifier): array
    {
        return [
            'label' => $label,
            'type' => $type,
            'identifier' => $identifier,
            'status' => $ok ? 'ok' : 'missing',
        ];
    }
}
