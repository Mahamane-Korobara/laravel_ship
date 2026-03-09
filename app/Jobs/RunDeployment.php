<?php

namespace App\Jobs;

use App\Models\Deployment;
use App\Models\Project;
use App\Services\DeploymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunDeployment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout    = 600; // 10 minutes max par déploiement
    public int $tries      = 1;   // Pas de retry automatique
    public int $maxExceptions = 1;

    public function __construct(
        public Deployment $deployment,
        public Project    $project,
    ) {}

    public function handle(): void
    {
        $service = new DeploymentService($this->deployment, $this->project);
        $service->run();
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
