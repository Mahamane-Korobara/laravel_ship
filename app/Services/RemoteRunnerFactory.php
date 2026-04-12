<?php

namespace App\Services;

use App\Models\Server;

class RemoteRunnerFactory
{
    private $tunnel = null;

    public function forServer(Server $server): RemoteRunner
    {
        if ($server->agent_enabled && $server->agent_url && $server->agent_token) {
            // L'agent est activé - créer un tunnel SSH pour contourner le firewall
            // Le tunnel fait passer les appels HTTP via SSH (sécurisé + transparent)

            try {
                // Créer la connection SSH pour le tunnel
                $ssh = new SshService(
                    ip: $server->ip_address,
                    user: $server->ssh_user,
                    privateKey: $server->ssh_private_key,
                    port: $server->ssh_port,
                );

                // Créer le tunnel local : localhost:8081 -> serveur:8081
                $this->tunnel = $ssh->createLocalTunnel(8081, 8081);

                // L'agent est maintenant accessible via localhost:8081
                // (au lieu de l'IP publique qui est bloquée par firewall)
                $agentUrl = 'http://127.0.0.1:8081';

                return new AgentRunner($agentUrl, $server->agent_token, $server);
            } catch (\Throwable $e) {
                // Si le tunnel échoue, fallback vers SSH
                // (Log l'erreur mais ne bloque pas)
                \Log::warning('Tunnel SSH vers agent impossible: ' . $e->getMessage() . '. Fallback SSH.');

                return new SshService(
                    ip: $server->ip_address,
                    user: $server->ssh_user,
                    privateKey: $server->ssh_private_key,
                    port: $server->ssh_port,
                );
            }
        }

        return new SshService(
            ip: $server->ip_address,
            user: $server->ssh_user,
            privateKey: $server->ssh_private_key,
            port: $server->ssh_port,
        );
    }

    /**
     * Récupérer le tunnel créé (pour le garder vivant pendant le job)
     */
    public function getTunnel(): ?array
    {
        return $this->tunnel;
    }

    /**
     * Fermer le tunnel SSH si un a été créé
     */
    public function __destruct()
    {
        if ($this->tunnel) {
            // Cleanup le tunnel et le fichier clé temporaire
            if (isset($this->tunnel['process']) && is_resource($this->tunnel['process'])) {
                proc_terminate($this->tunnel['process']);
            }

            if (isset($this->tunnel['keyFile']) && file_exists($this->tunnel['keyFile'])) {
                @unlink($this->tunnel['keyFile']);
            }
        }
    }
}
