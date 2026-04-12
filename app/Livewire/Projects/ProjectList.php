<?php

namespace App\Livewire\Projects;

use App\Services\RemoteRunnerFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProjectList extends Component
{
    public string $search = '';
    public string $view = 'grid';

    public function setView(string $view): void
    {
        if (!in_array($view, ['grid', 'list'], true)) {
            return;
        }

        $this->view = $view;
    }

    public function deleteProjectCache(?int $userId = null): void
    {
        $id = $userId ?? Auth::id();
        Cache::forget("user:{$id}:remote-projects");
    }

    public function render()
    {
        $user = Auth::user();
        $projects = $user->projects()
            ->with('server')
            ->latest()
            ->get();

        $items = collect();
        foreach ($projects as $project) {
            $items->push([
                'type' => 'managed',
                'id' => $project->id,
                'name' => $project->name,
                'github_repo' => $project->github_repo,
                'branch' => $project->github_branch,
                'domain' => $project->domain,
                'updated_at' => $project->updated_at,
                'status' => $project->status,
                'status_label' => $project->status_label,
                'webhook_pending' => $project->webhook_pending,
                'route' => route('projects.show', $project),
                'server_name' => $project->server?->name,
            ]);
        }

        $remoteItems = Cache::remember(
            "user:{$user->id}:remote-projects",
            now()->addSeconds(60),
            function () use ($user, $projects) {
                $existingPaths = $projects->pluck('deploy_path')->filter()->all();
                $existingNames = $projects->pluck('name')->map(fn($n) => strtolower($n))->all();
                $found = [];

                foreach ($user->servers as $server) {
                    try {
                        $ssh = app(RemoteRunnerFactory::class)->forServer($server);
                        // Timeout court pour la découverte (5s au lieu de 60s)
                        if (method_exists($ssh, 'setTimeout')) {
                            $ssh->setTimeout(5);
                        }
                        $output = trim($ssh->exec("docker ps --format '{{.Names}}' 2>/dev/null || true"));
                        $ssh->disconnect();

                        if ($output === '') {
                            continue;
                        }

                        foreach (array_filter(explode("\n", $output)) as $folder) {
                            $path = "/containers/{$folder}";
                            if (in_array($path, $existingPaths, true) || in_array(strtolower($folder), $existingNames, true)) {
                                continue;
                            }

                            $found[] = [
                                'type' => 'external',
                                'name' => $folder,
                                'server_id' => $server->id,
                                'server_name' => $server->name,
                                'path' => $path,
                                'route' => route('projects.external.show', ['server' => $server->id, 'project' => $folder]),
                            ];
                        }
                    } catch (\Throwable $e) {
                        // Ignore unreachable servers
                    }
                }

                return $found;
            }
        );

        foreach ($remoteItems as $remote) {
            $items->push($remote);
        }

        if ($this->search !== '') {
            $q = strtolower($this->search);
            $items = $items->filter(function ($item) use ($q) {
                return str_contains(strtolower($item['name'] ?? ''), $q)
                    || str_contains(strtolower($item['github_repo'] ?? ''), $q)
                    || str_contains(strtolower($item['domain'] ?? ''), $q)
                    || str_contains(strtolower($item['server_name'] ?? ''), $q);
            })->values();
        }

        return view('livewire.projects.index', [
            'items' => $items,
        ]);
    }
}
