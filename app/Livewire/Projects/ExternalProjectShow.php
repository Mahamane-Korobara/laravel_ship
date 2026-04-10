<?php

namespace App\Livewire\Projects;

use App\Models\Server;
use App\Services\RemoteRunnerFactory;
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
        $this->path = "/containers/{$project}";

        $this->loadInfo();
    }

    private function loadInfo(): void
    {
        try {
            $ssh = app(RemoteRunnerFactory::class)->forServer($this->server);

            $inspect = trim($ssh->exec("docker inspect -f '{{.Config.Image}}|{{.State.Status}}|{{.Created}}' {$this->project} 2>/dev/null || echo '—|—|—'"));
            [$image, $status, $created] = array_pad(explode('|', $inspect, 3), 3, '—');

            $this->info = [
                'basePath' => $this->path,
                'size' => '—',
                'lastMod' => $created,
                'branch' => '—',
                'commit' => '—',
                'remote' => $image,
                'status' => $status,
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
