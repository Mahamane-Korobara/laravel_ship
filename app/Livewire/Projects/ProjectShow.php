<?php

namespace App\Livewire\Projects;

use App\Models\Project;
use App\Services\ApacheService;
use App\Services\SshService;
use App\Services\SslService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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

    public function deleteProject(): void
    {
        $project = $this->project->fresh(['server', 'deployments', 'envVariables']);

        try {
            $this->cleanupRemoteProject($project);
        } catch (\Throwable $e) {
            session()->flash('error', "Suppression annulée : {$e->getMessage()}");
            return;
        }

        $this->cleanupLocalFiles($project);
        $project->envVariables()->delete();
        $project->deployments()->delete();
        $project->delete();

        session()->flash('success', "Projet supprimé et nettoyé.");
        $this->redirect(route('projects.index'), navigate: true);
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

    private function cleanupLocalFiles(Project $project): void
    {
        if ($project->env_file_path) {
            Storage::disk('local')->delete($project->env_file_path);
        }

        foreach ($project->deployments as $deployment) {
            if (!empty($deployment->env_file_path)) {
                Storage::disk('local')->delete($deployment->env_file_path);
            }
        }
    }

    private function cleanupRemoteProject(Project $project): void
    {
        $server = $project->server;
        if (!$server) {
            return;
        }

        $ssh = new SshService(
            ip: $server->ip_address,
            user: $server->ssh_user,
            privateKey: $server->ssh_private_key,
            port: $server->ssh_port,
        );

        $deployPath = $project->deploy_path;
        $projectKey = basename($deployPath);

        //  Supprimer SSL + vhost
        if ($project->domain) {
            $apache = new ApacheService($ssh);
            $ssl = new SslService($ssh);
            $ssl->remove($project->domain);
            $apache->removeVirtualHost($projectKey);
            $apache->removeProjectPhpFpmPool($projectKey);
            $ssh->exec("sudo rm -f /var/log/apache2/{$projectKey}-access.log /var/log/apache2/{$projectKey}-error.log || true");
        }

        //  Supprimer la base de donnees si possible (mysql/mariadb)
        $this->dropRemoteDatabaseIfPossible($ssh, $deployPath);

        //  Nettoyer tous les fichiers du projet
        $ssh->exec("sudo rm -rf {$deployPath}");

        $ssh->disconnect();
    }

    private function dropRemoteDatabaseIfPossible(SshService $ssh, string $deployPath): void
    {
        $envPath = "{$deployPath}/shared/.env";
        $command = <<<BASH
if [ -f "{$envPath}" ]; then
  DB_CONN=\$(grep -E '^DB_CONNECTION=' "{$envPath}" | head -n1 | cut -d= -f2- | tr -d '\\r\"');
  if [ "\$DB_CONN" = "mysql" ] || [ "\$DB_CONN" = "mariadb" ]; then
    DB_NAME=\$(grep -E '^DB_DATABASE=' "{$envPath}" | head -n1 | cut -d= -f2- | tr -d '\\r\"');
    DB_USER=\$(grep -E '^DB_USERNAME=' "{$envPath}" | head -n1 | cut -d= -f2- | tr -d '\\r\"');
    DB_PASS=\$(grep -E '^DB_PASSWORD=' "{$envPath}" | head -n1 | cut -d= -f2- | tr -d '\\r\"');
    DB_HOST=\$(grep -E '^DB_HOST=' "{$envPath}" | head -n1 | cut -d= -f2- | tr -d '\\r\"');
    DB_PORT=\$(grep -E '^DB_PORT=' "{$envPath}" | head -n1 | cut -d= -f2- | tr -d '\\r\"');
    if [ -n "\$DB_NAME" ] && [ -n "\$DB_USER" ]; then
      DB_HOST=\${DB_HOST:-localhost}
      DB_PORT=\${DB_PORT:-3306}
      MYSQL_PWD="\$DB_PASS" mysql -h "\$DB_HOST" -P "\$DB_PORT" -u "\$DB_USER" -e "DROP DATABASE IF EXISTS \\\\\`\$DB_NAME\\\\\`;" || true
    fi
  fi
fi
BASH;

        $ssh->exec($command);
    }
}
