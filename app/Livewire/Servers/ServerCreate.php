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
    public string $php_version    = '8.2';

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
            'php_version'      => 'required|in:7.4,8.0,8.1,8.2,8.3,8.4',
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
        $this->testResult = null;

        try {
            $ssh = new SshService(
                ip: $this->ip_address,
                user: $this->ssh_user,
                privateKey: $this->ssh_private_key,
                port: $this->ssh_port,
            );

            $phpOutput = $ssh->exec("php{$this->php_version} -v | head -1");
            $this->testResult  = "Connexion réussie ✓\n" . trim($phpOutput);
            $this->testSuccess = true;
            $ssh->disconnect();
        } catch (\Exception $e) {
            $this->testResult  = 'Erreur : ' . $e->getMessage();
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
            'php_version'       => $this->php_version,
            'vcpu'              => $metrics['vcpu'] ?? null,
            'ram_mb'            => $metrics['ram_mb'] ?? null,
            'disk_gb'           => $metrics['disk_gb'] ?? null,
            'status'            => $status,
            'last_error'        => $lastError,
            'last_connected_at' => $lastConnectedAt,
        ]);

        session()->flash('success', "Serveur \"{$server->name}\" ajouté avec succès !");
        $this->redirect(route('servers.show', $server), navigate: true);
    }

    public function render()
    {
        return view('livewire.servers.create');
    }
}
