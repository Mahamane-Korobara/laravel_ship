<?php

namespace App\Services;

use App\Models\Server;
use GuzzleHttp\Client;
use RuntimeException;

class AgentRunner implements RemoteRunner
{
    private Client $client;
    // Timeout pour les opérations longues (pre-pull Docker, build, clone repo, etc) -> 2 minutes = 120s
    private int $timeout = 120;

    public function __construct(
        private string $baseUrl,
        private string $token,
        private ?Server $server = null,
    ) {
        $this->client = new Client([
            'base_uri' => rtrim($this->baseUrl, '/') . '/',
            'timeout' => $this->timeout,  // Timeout total pour la requête
            'connect_timeout' => 5,      // 5s pour se connecter via tunnel
        ]);
    }

    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        $this->client = new Client([
            'base_uri' => rtrim($this->baseUrl, '/') . '/',
            'timeout' => $seconds,
            'connect_timeout' => 5,
        ]);
        return $this;
    }

    public function exec(string $command): string
    {
        $payload = $this->request('POST', 'exec', [
            'cmd' => $command,
        ]);

        if (!isset($payload['exit_code'])) {
            throw new RuntimeException('Agent: reponse invalide.');
        }

        if ((int) $payload['exit_code'] !== 0) {
            $output = (string) ($payload['output'] ?? '');
            throw new RuntimeException("Commande agent échouée (exit {$payload['exit_code']}): {$command}\n{$output}");
        }

        return (string) ($payload['output'] ?? '');
    }

    public function execStreaming(string $command, callable $onOutput): void
    {
        $output = $this->exec($command);
        foreach (explode("\n", $output) as $line) {
            if (trim($line) !== '') {
                $onOutput(trim($line));
            }
        }
    }

    public function uploadContent(string $content, string $remotePath): void
    {
        $payload = $this->request('POST', 'upload', [
            'path' => $remotePath,
            'content' => base64_encode($content),
        ]);

        if (!($payload['ok'] ?? false)) {
            throw new RuntimeException('Agent: upload impossible.');
        }
    }

    public function downloadFile(string $remotePath, string $localPath): void
    {
        $payload = $this->request('POST', 'download', [
            'path' => $remotePath,
        ]);

        if (!isset($payload['content'])) {
            throw new RuntimeException('Agent: fichier introuvable.');
        }

        $dir = dirname($localPath);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Impossible de créer le dossier local: {$dir}");
        }

        file_put_contents($localPath, base64_decode((string) $payload['content']));
    }

    public function disconnect(): void
    {
        // noop
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        $response = $this->client->request($method, ltrim($path, '/'), [
            'headers' => [
                'X-Ship-Token' => $this->token,
                'Accept' => 'application/json',
            ],
            'json' => $payload,
        ]);

        $data = json_decode((string) $response->getBody(), true);
        if (!is_array($data)) {
            throw new RuntimeException('Agent: réponse JSON invalide.');
        }

        if ($this->server) {
            $this->server->update(['agent_last_seen_at' => now()]);
        }

        return $data;
    }
}
