<?php

namespace Tests\Feature;

use App\Models\Deployment;
use App\Models\EnvVariable;
use App\Models\Project;
use App\Models\Server;
use App\Models\User;
use App\Services\DeploymentService;
use App\Services\SshService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DeploymentServiceDockerTest extends TestCase
{
    use RefreshDatabase;

    public function test_docker_deployment_flow_updates_statuses(): void
    {
        Carbon::setTestNow('2026-04-06 12:00:00');

        $user = User::factory()->create();

        $server = Server::create([
            'user_id' => $user->id,
            'name' => 'Local Docker',
            'ip_address' => '127.0.0.1',
            'ssh_user' => 'deployer',
            'ssh_port' => 22,
            'ssh_private_key' => 'dummy-key',
            'status' => 'active',
        ]);

        $project = Project::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'name' => 'Test Docker App',
            'github_repo' => 'acme/example',
            'github_branch' => 'main',
            'deploy_path' => '/var/www/projects/test-docker-app',
            'php_version' => '8.2',
            'run_migrations' => true,
            'run_seeders' => false,
            'run_npm_build' => false,
            'has_queue_worker' => false,
            'status' => 'idle',
        ]);

        EnvVariable::create([
            'project_id' => $project->id,
            'key' => 'APP_ENV',
            'value' => 'production',
            'is_secret' => false,
        ]);

        $deployment = Deployment::create([
            'project_id' => $project->id,
            'release_name' => 'pending',
            'git_branch' => 'main',
            'triggered_by' => 'manual',
            'status' => 'pending',
        ]);

        $fakeSsh = new FakeSshService();

        $service = new DeploymentService($deployment, $project, $fakeSsh);
        $service->run();

        $deployment->refresh();
        $project->refresh();

        $this->assertSame('success', $deployment->status);
        $this->assertSame('deployed', $project->status);
        $this->assertSame($deployment->release_name, $project->current_release);

        $this->assertNotEmpty($fakeSsh->uploads);
        $this->assertArrayHasKey('/var/www/projects/test-docker-app/shared/.env', $fakeSsh->uploads);
        $this->assertTrue($this->hasUploadEndingWith($fakeSsh->uploads, 'Dockerfile'));
        $this->assertTrue($this->hasUploadEndingWith($fakeSsh->uploads, 'docker-compose.yml'));
    }

    /**
     * @param array<string, string> $uploads
     */
    private function hasUploadEndingWith(array $uploads, string $suffix): bool
    {
        foreach (array_keys($uploads) as $path) {
            if (str_ends_with($path, $suffix)) {
                return true;
            }
        }

        return false;
    }
}

class FakeSshService extends SshService
{
    /** @var array<int, string> */
    public array $commands = [];

    /** @var array<string, string> */
    public array $uploads = [];

    public function __construct()
    {
        // Intentionally bypass SSH connection.
    }

    public function exec(string $command): string
    {
        $this->commands[] = $command;

        if (str_contains($command, 'echo ok')) {
            return 'ok';
        }

        if (str_contains($command, 'docker --version')) {
            return 'Docker version 25.0.0';
        }

        if (str_contains($command, 'docker compose version')) {
            return 'Docker Compose version v2.24.0';
        }

        if (str_contains($command, 'docker ps')) {
            return "ship-test\tUp";
        }

        if (str_contains($command, 'Dockerfile') && str_contains($command, 'echo yes')) {
            return 'no';
        }

        if (str_contains($command, 'echo yes')) {
            return 'yes';
        }

        return '';
    }

    public function execStreaming(string $command, callable $onOutput): void
    {
        $this->commands[] = $command;
        $onOutput('ok');
    }

    public function uploadContent(string $content, string $remotePath): void
    {
        $this->uploads[$remotePath] = $content;
    }

    public function disconnect(): void
    {
        // no-op
    }
}
