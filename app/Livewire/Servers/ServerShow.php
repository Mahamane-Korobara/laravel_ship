<?php

namespace App\Livewire\Servers;

use App\Models\Server;
use App\Services\SshService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ServerShow extends Component
{
    public Server $server;

    public bool    $testing    = false;
    public ?string $testResult = null;
    public bool    $testSuccess = false;

    public function mount(Server $server): void
    {
        abort_if($server->user_id !== Auth::id(), 403);
        $this->server = $server;
    }

    public function testConnection(): void
    {
        $this->testing    = true;
        $this->testResult = null;

        try {
            $ssh = new SshService(
                ip: $this->server->ip_address,
                user: $this->server->ssh_user,
                privateKey: $this->server->ssh_private_key,
                port: $this->server->ssh_port,
            );

            $output = $ssh->exec("echo 'OK' && php{$this->server->php_version} -v | head -1");
            $this->testResult  = trim($output);
            $this->testSuccess = true;

            $this->server->update([
                'status'            => 'active',
                'last_connected_at' => now(),
                'last_error'        => null,
            ]);
            $ssh->disconnect();
        } catch (\Exception $e) {
            $this->testResult  = $e->getMessage();
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

    public function render()
    {
        $remoteProjects = [];

        try {
            $ssh = new SshService(
                ip: $this->server->ip_address,
                user: $this->server->ssh_user,
                privateKey: $this->server->ssh_private_key,
                port: $this->server->ssh_port,
            );

            $output = trim($ssh->exec("ls -1 /var/www/projects 2>/dev/null || true"));
            $remoteProjects = $output === '' ? [] : array_values(array_filter(explode("\n", $output)));
            $ssh->disconnect();
        } catch (\Throwable $e) {
            // Ignore if SSH fails; UI will just show none.
        }

        return view('livewire.servers.show', [
            'projects' => $this->server->projects()->with('lastDeployment')->get(),
            'remoteProjects' => $remoteProjects,
        ]);
    }
}
