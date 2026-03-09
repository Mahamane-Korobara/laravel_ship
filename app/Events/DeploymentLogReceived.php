<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeploymentLogReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int    $deploymentId,
        public string $line,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("deployment.{$this->deploymentId}");
    }

    public function broadcastAs(): string
    {
        return 'log.received';
    }

    public function broadcastWith(): array
    {
        return [
            'line'      => $this->line,
            'timestamp' => now()->toTimeString(),
        ];
    }
}
