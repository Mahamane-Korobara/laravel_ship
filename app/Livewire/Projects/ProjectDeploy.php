<?php

namespace App\Livewire\Projects;

use App\Jobs\RunDeployment;
use App\Models\Deployment;
use App\Models\EnvVariable;
use App\Models\Project;
use App\Models\User;
use App\Services\GitHubService;
use App\Services\SshService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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
    public string $php_version   = '8.2';

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
            'php_version'   => 'required|in:7.4,8.0,8.1,8.2,8.3,8.4',
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
        $this->php_version   = $project->php_version;
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
        $this->validate();

        // Mettre à jour le projet
        $this->project->update([
            'name'            => $this->name,
            'github_branch'   => $this->github_branch,
            'server_id'       => $this->server_id,
            'domain'          => $this->domain ?: null,
            'php_version'     => $this->php_version,
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

        // Rediriger vers le terminal
        $this->redirect(route('deployments.show', $deployment), navigate: true);
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

            $envContent = $this->resolveEnvContent();
            $env = $this->parseEnv($envContent);

            $requirements = $this->buildRequirements($env);

            $ssh = new SshService(
                ip: $server->ip_address,
                user: $server->ssh_user,
                privateKey: $server->ssh_private_key,
                port: $server->ssh_port,
            );

            $phpBin = $this->resolvePhpBinary($ssh, $this->php_version);

            foreach ($requirements as $req) {
                if ($req['type'] === 'system') {
                    $ok = trim($ssh->exec("dpkg -s {$req['name']} >/dev/null 2>&1 && echo ok || echo missing")) === 'ok';
                    $this->dependencyAudit[] = $this->formatAudit($req['label'], $req['type'], $ok, $req['name']);
                    continue;
                }

                if ($req['type'] === 'binary') {
                    $ok = trim($ssh->exec("command -v {$req['name']} >/dev/null 2>&1 && echo ok || echo missing")) === 'ok';
                    $this->dependencyAudit[] = $this->formatAudit($req['label'], $req['type'], $ok, $req['name']);
                    continue;
                }

                if ($req['type'] === 'php-ext') {
                    $ext = $req['name'];
                    $ok = trim($ssh->exec("{$phpBin} -r \"echo extension_loaded('{$ext}') ? 'ok' : 'missing';\"")) === 'ok';
                    $this->dependencyAudit[] = $this->formatAudit($req['label'], $req['type'], $ok, $ext);
                }
            }

            $ssh->disconnect();
        } catch (\Throwable $e) {
            $this->auditError = $e->getMessage();
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

    private function resolveEnvContent(): string
    {
        if ($this->deploymentEnvFilePath && Storage::disk('local')->exists($this->deploymentEnvFilePath)) {
            return (string) Storage::disk('local')->get($this->deploymentEnvFilePath);
        }

        if ($this->project->env_file_path && Storage::disk('local')->exists($this->project->env_file_path)) {
            return (string) Storage::disk('local')->get($this->project->env_file_path);
        }

        $pairs = collect($this->envVars)
            ->filter(fn($var) => !empty($var['key']) && isset($var['value']))
            ->map(fn($var) => trim($var['key']) . '=' . $var['value'])
            ->values()
            ->all();

        return implode("\n", $pairs);
    }

    /**
     * @return array<string, string>
     */
    private function parseEnv(string $content): array
    {
        $parsed = [];

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $parsed[$key] = $value;
        }

        return $parsed;
    }

    private function resolvePhpBinary(SshService $ssh, string $version): string
    {
        $candidate = "php{$version}";
        $hasCandidate = trim($ssh->exec("command -v {$candidate} >/dev/null 2>&1 && echo ok || echo missing"));

        return $hasCandidate === 'ok' ? $candidate : 'php';
    }

    /**
     * @param array<string, string> $env
     * @return array<int, array{type:string,name:string,label:string}>
     */
    private function buildRequirements(array $env): array
    {
        $requirements = [];

        $dbConnection = strtolower((string) ($env['DB_CONNECTION'] ?? ''));
        $usesRedis = $this->envIsRedis($env, 'CACHE_STORE')
            || $this->envIsRedis($env, 'CACHE_DRIVER')
            || $this->envIsRedis($env, 'QUEUE_CONNECTION')
            || $this->envIsRedis($env, 'SESSION_DRIVER')
            || $this->envIsRedis($env, 'BROADCAST_DRIVER')
            || $this->envIsRedis($env, 'BROADCAST_CONNECTION')
            || strtolower((string) ($env['REDIS_HOST'] ?? '')) !== '';

        if ($usesRedis) {
            $requirements[] = ['type' => 'system', 'name' => 'redis-server', 'label' => 'Serveur Redis'];
            $requirements[] = ['type' => 'php-ext', 'name' => 'redis', 'label' => 'Extension PHP redis'];
        }

        if (in_array($dbConnection, ['mysql', 'mariadb'], true)) {
            $requirements[] = ['type' => 'system', 'name' => 'mysql-server', 'label' => 'Serveur MySQL'];
            $requirements[] = ['type' => 'php-ext', 'name' => 'pdo_mysql', 'label' => 'Extension PHP pdo_mysql'];
        }

        if ($dbConnection === 'pgsql') {
            $requirements[] = ['type' => 'system', 'name' => 'postgresql', 'label' => 'Serveur PostgreSQL'];
            $requirements[] = ['type' => 'php-ext', 'name' => 'pdo_pgsql', 'label' => 'Extension PHP pdo_pgsql'];
        }

        if ($dbConnection === 'sqlite') {
            $requirements[] = ['type' => 'system', 'name' => 'sqlite3', 'label' => 'SQLite'];
            $requirements[] = ['type' => 'php-ext', 'name' => 'pdo_sqlite', 'label' => 'Extension PHP pdo_sqlite'];
        }

        if ($this->run_npm_build) {
            $requirements[] = ['type' => 'binary', 'name' => 'node', 'label' => 'Node.js'];
            $requirements[] = ['type' => 'binary', 'name' => 'npm', 'label' => 'npm'];
        }

        $requirements[] = ['type' => 'binary', 'name' => 'composer', 'label' => 'Composer'];

        return $requirements;
    }

    private function envIsRedis(array $env, string $key): bool
    {
        $value = strtolower((string) ($env[$key] ?? ''));
        return $value === 'redis';
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
