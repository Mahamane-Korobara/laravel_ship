<?php

namespace App\Livewire\Servers;

use App\Models\Server;
use App\Services\AgentInstaller;
use App\Services\SshService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ServerShow extends Component
{
    public Server $server;

    public bool    $testing    = false;
    public ?string $testResult = null;
    public bool    $testSuccess = false;
    public bool    $installingAgent = false;
    public bool    $removingAgent = false;
    public ?string $agentResult = null;

    public function mount(Server $server): void
    {
        abort_if($server->user_id !== Auth::id(), 403);
        $this->server = $server;
    }

    public function testConnection(): void
    {
        $this->testing    = true;
        $this->testResult = '';
        $this->testSuccess = false;
        $buffer = '';
        $append = function (string $line) use (&$buffer) {
            $buffer .= $line . "\n";
            $this->stream('testResult', $line . "\n");
        };
        $runStreaming = function (SshService $ssh, string $command) use ($append): string {
            $output = '';
            $ssh->execStreaming($command, function (string $line) use (&$output, $append) {
                $output .= $line . "\n";
                $append($line);
            });
            return trim($output);
        };

        try {
            $append('→ Connexion SSH...');
            $ssh = new SshService(
                ip: $this->server->ip_address,
                user: $this->server->ssh_user,
                privateKey: $this->server->ssh_private_key,
                port: $this->server->ssh_port,
            );

            $append('→ Vérification Docker...');
            $dockerVersion = $runStreaming($ssh, "docker --version 2>/dev/null || true");
            if ($dockerVersion === '') {
                $dockerVersion = $runStreaming($ssh, "sudo -n docker --version 2>/dev/null || true");
            }

            $dockerBin = $dockerVersion !== '' ? 'docker' : 'sudo -n docker';
            if ($dockerVersion === '') {
                $append('Docker: non detecte');
            }

            $append('→ Vérification Docker Compose...');
            $composeVersion = $runStreaming($ssh, "{$dockerBin} compose version 2>/dev/null || true");
            if ($composeVersion === '') {
                $append('Docker Compose: non detecte');
            }

            $append('→ Listing des conteneurs...');
            $psOutput = $runStreaming($ssh, "{$dockerBin} ps --format 'table {{.Names}}\\t{{.Status}}' 2>/dev/null || true");
            if ($psOutput === '') {
                $append('Aucun conteneur en cours.');
            }
            $append('Connexion réussie ✓');
            $this->testResult  = trim($buffer);
            $this->testSuccess = $dockerVersion !== '' && $composeVersion !== '';

            $this->server->update([
                'status'            => 'active',
                'last_connected_at' => now(),
                'last_error'        => null,
            ]);
            $ssh->disconnect();
        } catch (\Exception $e) {
            $append('Erreur : ' . $e->getMessage());
            $this->testResult  = trim($buffer);
            $this->testSuccess = false;

            $this->server->update([
                'status'     => 'error',
                'last_error' => $e->getMessage(),
            ]);
        } finally {
            $this->testing = false;
        }
    }

    public function delete(): void
    {
        abort_if(
            $this->server->projects()->count() > 0,
            422,
            'Impossible de supprimer un serveur avec des projets actifs.'
        );

        $this->server->delete();
        $this->redirect(route('servers.index'), navigate: true);
    }

    public function installAgent(): void
    {
        $this->installingAgent = true;
        $this->agentResult = '';
        $buffer = '';
        $append = function (string $line) use (&$buffer) {
            $buffer .= $line . "\n";
            $this->stream('agentResult', $line . "\n");
        };

        try {
            $token = Str::random(40);
            $port = (int) config('ship.agent_port', 8081);
            $agentUrl = "http://{$this->server->ip_address}:{$port}";

            $installer = new AgentInstaller();
            $installer->install($this->server, $token, $port, $append);

            $this->server->update([
                'agent_url' => $agentUrl,
                'agent_token' => $token,
                'agent_enabled' => true,
                'agent_last_seen_at' => now(),
            ]);

            $append('Agent installé et actif.');
            $this->agentResult = trim($buffer);
        } catch (\Throwable $e) {
            $append('Erreur agent : ' . $e->getMessage());
            $this->agentResult = trim($buffer);
        } finally {
            $this->installingAgent = false;
        }
    }

    public function removeAgent(): void
    {
        $this->removingAgent = true;
        $this->agentResult = '';
        $buffer = '';
        $append = function (string $line) use (&$buffer) {
            $buffer .= $line . "\n";
            $this->stream('agentResult', $line . "\n");
        };

        try {
            $installer = new AgentInstaller();
            $installer->uninstall($this->server, $append);

            $this->server->update([
                'agent_url' => null,
                'agent_token' => null,
                'agent_enabled' => false,
                'agent_last_seen_at' => null,
            ]);

            $append('Agent supprimé avec succès.');
            $this->agentResult = trim($buffer);
        } catch (\Throwable $e) {
            $append('Erreur agent : ' . $e->getMessage());
            $this->agentResult = trim($buffer);
        } finally {
            $this->removingAgent = false;
        }
    }


    public function render()
    {
        $remoteContainers = [];

        try {
            $ssh = new SshService(
                ip: $this->server->ip_address,
                user: $this->server->ssh_user,
                privateKey: $this->server->ssh_private_key,
                port: $this->server->ssh_port,
            );

            $output = trim($ssh->exec("docker ps --format '{{.Names}}' 2>/dev/null || true"));
            if ($output === '') {
                $output = trim($ssh->exec("sudo -n docker ps --format '{{.Names}}' 2>/dev/null || true"));
            }
            $remoteContainers = $output === '' ? [] : array_values(array_filter(explode("\n", $output)));
            $ssh->disconnect();
        } catch (\Throwable $e) {
            // Ignore if SSH fails; UI will just show none.
        }

        return view('livewire.servers.show', [
            'projects' => $this->server->projects()->with('lastDeployment')->get(),
            'remoteContainers' => $remoteContainers,
        ]);
    }
}
