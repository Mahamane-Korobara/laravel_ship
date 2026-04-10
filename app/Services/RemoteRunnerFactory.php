<?php

namespace App\Services;

use App\Models\Server;

class RemoteRunnerFactory
{
    public function forServer(Server $server): RemoteRunner
    {
        if ($server->agent_enabled && $server->agent_url && $server->agent_token) {
            return new AgentRunner($server->agent_url, $server->agent_token, $server);
        }

        return new SshService(
            ip: $server->ip_address,
            user: $server->ssh_user,
            privateKey: $server->ssh_private_key,
            port: $server->ssh_port,
        );
    }
}

