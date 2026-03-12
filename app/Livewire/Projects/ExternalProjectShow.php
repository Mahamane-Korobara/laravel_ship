<?php

namespace App\Livewire\Projects;

use App\Models\Server;
use App\Services\SshService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ExternalProjectShow extends Component
{
    public Server $server;
    public string $project;
    public string $path;
    public array $info = [];

    public function mount(Server $server, string $project): void
    {
        abort_if($server->user_id !== Auth::id(), 403);

        $this->server = $server;
        $this->project = $project;
        $this->path = "/var/www/projects/{$project}";

        $this->loadInfo();
    }

    private function loadInfo(): void
    {
        try {
            $ssh = new SshService(
                ip: $this->server->ip_address,
                user: $this->server->ssh_user,
                privateKey: $this->server->ssh_private_key,
                port: $this->server->ssh_port,
            );

            $basePath = trim($ssh->exec("if [ -d '{$this->path}/current' ]; then echo '{$this->path}/current'; else echo '{$this->path}'; fi"));
            $size = trim($ssh->exec("du -sh {$this->path} 2>/dev/null | awk '{print $1}'")) ?: '—';
            $lastMod = trim($ssh->exec("stat -c %y {$this->path} 2>/dev/null | cut -d'.' -f1")) ?: '—';
            $branch = trim($ssh->exec("git -C {$basePath} rev-parse --abbrev-ref HEAD 2>/dev/null || echo '—'"));
            $commit = trim($ssh->exec("git -C {$basePath} rev-parse --short HEAD 2>/dev/null || echo '—'"));
            $remote = trim($ssh->exec("git -C {$basePath} config --get remote.origin.url 2>/dev/null || echo '—'"));

            $this->info = [
                'basePath' => $basePath,
                'size' => $size,
                'lastMod' => $lastMod,
                'branch' => $branch,
                'commit' => $commit,
                'remote' => $remote,
            ];

            $ssh->disconnect();
        } catch (\Throwable $e) {
            $this->info = [
                'basePath' => $this->path,
                'size' => '—',
                'lastMod' => '—',
                'branch' => '—',
                'commit' => '—',
                'remote' => '—',
            ];
        }
    }

    public function render()
    {
        return view('livewire.projects.external-show');
    }
}
