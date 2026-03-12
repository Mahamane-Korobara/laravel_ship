<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Server;
use App\Services\SshService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('servers:sync-metrics {--only-missing}', function () {
    $onlyMissing = (bool) $this->option('only-missing');

    $query = Server::query();
    if ($onlyMissing) {
        $query->where(function ($q) {
            $q->whereNull('vcpu')
              ->orWhereNull('ram_mb')
              ->orWhereNull('disk_gb');
        });
    }

    $servers = $query->get();
    if ($servers->isEmpty()) {
        $this->info('Aucun serveur à mettre à jour.');
        return;
    }

    foreach ($servers as $server) {
        $this->line("→ {$server->name} ({$server->masked_ip})");

        try {
            $ssh = new SshService(
                ip: $server->ip_address,
                user: $server->ssh_user,
                privateKey: $server->ssh_private_key,
                port: $server->ssh_port,
            );

            $metrics = $ssh->getSystemMetrics();
            $ssh->disconnect();

            $server->update([
                'vcpu' => $metrics['vcpu'],
                'ram_mb' => $metrics['ram_mb'],
                'disk_gb' => $metrics['disk_gb'],
                'status' => 'active',
                'last_error' => null,
                'last_connected_at' => now(),
            ]);

            $this->info("   vCPU={$metrics['vcpu']} | RAM={$metrics['ram_mb']}MB | DISK={$metrics['disk_gb']}GB");
        } catch (\Exception $e) {
            $server->update([
                'status' => 'error',
                'last_error' => $e->getMessage(),
            ]);
            $this->error("   Erreur: {$e->getMessage()}");
        }
    }
})->purpose('Synchroniser vCPU/RAM/stockage des serveurs via SSH');
