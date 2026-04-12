<?php

namespace App\Livewire\Servers;

use App\Models\Server;
use App\Services\SshService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ServerCreate extends Component
{
    public string $name           = '';
    public string $ip_address     = '';
    public string $ssh_user       = 'deployer';
    public int    $ssh_port       = 22;
    public string $ssh_private_key = '';
    public array  $labels         = [];

    public bool    $testing     = false;
    public ?string $testResult  = null;
    public bool    $testSuccess = false;

    protected function rules(): array
    {
        return [
            'name'             => 'required|string|max:255',
            'ip_address'       => 'required|ip',
            'ssh_user'         => 'required|string|max:100',
            'ssh_port'         => 'required|integer|min:1|max:65535',
            'ssh_private_key'  => 'required|string',
            'labels' => 'nullable|array',
        ];
    }

    protected $messages = [
        'ip_address.required'      => "L'adresse IP est obligatoire.",
        'ip_address.ip'            => "L'adresse IP n'est pas valide.",
        'ssh_private_key.required' => 'La clé SSH privée est obligatoire.',
    ];

    public function testConnection(): void
    {
        $this->validate();
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
                ip: $this->ip_address,
                user: $this->ssh_user,
                privateKey: $this->ssh_private_key,
                port: $this->ssh_port,
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
            $ssh->disconnect();
        } catch (\Exception $e) {
            $append('Erreur : ' . $e->getMessage());
            $this->testResult  = trim($buffer);
            $this->testSuccess = false;
        } finally {
            $this->testing = false;
        }
    }

    public function save(): void
    {
        $this->validate();

        $metrics = [];
        $status = $this->testSuccess ? 'active' : 'inactive';
        $lastConnectedAt = $this->testSuccess ? now() : null;
        $lastError = null;

        try {
            $ssh = new SshService(
                ip: $this->ip_address,
                user: $this->ssh_user,
                privateKey: $this->ssh_private_key,
                port: $this->ssh_port,
            );

            $metrics = $ssh->getSystemMetrics();
            $status = 'active';
            $lastConnectedAt = now();
            $ssh->disconnect();
        } catch (\Exception $e) {
            $lastError = $e->getMessage();
        }

        $server = Server::create([
            'user_id'           => Auth::id(),
            'name'              => $this->name,
            'ip_address'        => $this->ip_address,
            'ssh_user'          => $this->ssh_user,
            'ssh_port'          => $this->ssh_port,
            'ssh_private_key'   => $this->ssh_private_key,
            'vcpu'              => $metrics['vcpu'] ?? null,
            'ram_mb'            => $metrics['ram_mb'] ?? null,
            'disk_gb'           => $metrics['disk_gb'] ?? null,
            'status'            => $status,
            'last_error'        => $lastError,
            'last_connected_at' => $lastConnectedAt,
            'labels' => $this->labels ?: null,
        ]);

        session()->flash('success', "Serveur \"{$server->name}\" ajouté avec succès !");
        $this->dispatch('notify', message: "Serveur \"{$server->name}\" ajouté avec succès !", type: 'success');
        $this->redirect(route('servers.show', $server), navigate: true);
    }

    public function render()
    {
        return view('livewire.servers.create');
    }
}
