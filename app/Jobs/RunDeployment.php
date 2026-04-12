<?php

namespace App\Jobs;

use App\Models\Deployment;
use App\Models\Project;
use App\Services\DeploymentService;
use App\Services\RemoteRunnerFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunDeployment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout    = 300; // 5 minutes max par déploiement
    public int $tries      = 2;   // 1x retry automatique pour réseau flaky
    public int $maxExceptions = 2;

    public function __construct(
        public Deployment $deployment,
        public Project    $project,
    ) {}

    public function handle(): void
    {
        $runner = null;
        $factory = null;

        \Log::info('RunDeployment::handle() STARTED', [
            'deployment_id' => $this->deployment->id,
            'project_id' => $this->project->id,
            'server_id' => $this->project->server_id,
            'attempt' => $this->attempts(),
        ]);

        try {
            if ($this->project->server) {
                $factory = app(RemoteRunnerFactory::class);
                $runner = $factory->forServer($this->project->server);

                // DEBUG: Log si tunnel créé
                if ($factory->getTunnel()) {
                    \Log::info('RunDeployment: Tunnel SSH créé pour agent', [
                        'tunnel_pid' => $factory->getTunnel(),
                    ]);
                }
            }

            $service = new DeploymentService($this->deployment, $this->project, $runner);
            $service->run();

            \Log::info('RunDeployment::handle() COMPLETED successfully');
        } catch (\Throwable $e) {
            \Log::error('RunDeployment::handle() EXCEPTION', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'attempt' => $this->attempts(),
            ]);

            // Re-throw pour que Laravel gère les retries
            throw $e;
        } finally {
            // Sauvegarder les logs accumulés dans la BD avant de fermer le tunnel
            try {
                $this->deployment->update(['log' => $this->deployment->log]);
            } catch (\Throwable $logError) {
                \Log::warning('RunDeployment: Impossible de sauvegarder les logs', ['error' => $logError->getMessage()]);
            }

            // Fermer le tunnel SSH s'il existe (maintenant que le déploiement est fini)
            if ($factory && $factory->getTunnel()) {
                try {
                    $sshService = new \App\Services\SshService(
                        ip: $this->project->server->ip_address,
                        user: $this->project->server->ssh_user,
                        privateKey: $this->project->server->ssh_private_key,
                        port: $this->project->server->ssh_port,
                    );
                    $sshService->closeLocalTunnel($factory->getTunnel());
                    \Log::info('RunDeployment: Tunnel SSH fermé');
                } catch (\Exception $e) {
                    \Log::warning('RunDeployment: Impossible de fermer tunnel', ['error' => $e->getMessage()]);
                }
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->deployment->update([
            'status'      => 'failed',
            'finished_at' => now(),
        ]);

        $this->project->update(['status' => 'failed']);
    }
}
