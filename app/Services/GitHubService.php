<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use Exception;

class GitHubService
{
    private PendingRequest $client;

    public function __construct(private string $token)
    {
        $this->client = Http::withHeaders([
            'Authorization'        => 'Bearer ' . $token,
            'Accept'               => 'application/vnd.github.v3+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ])->baseUrl('https://api.github.com')->timeout(15);
    }

    //  Repos
    public function getUserRepos(): array
    {
        $repos = [];
        $page  = 1;

        do {
            $response = $this->client->get('/user/repos', [
                'per_page' => 100,
                'page'     => $page,
                'sort'     => 'updated',
                'type'     => 'all',
            ]);

            if (!$response->successful()) {
                throw new \Exception('Erreur API GitHub : ' . $response->body());
            }

            $data  = $response->json();
            $repos = array_merge($repos, $data);
            $page++;
        } while (count($data) === 100);

        return array_map(fn($repo) => [
            'id'          => $repo['id'],
            'full_name'   => $repo['full_name'],
            'name'        => $repo['name'],
            'description' => $repo['description'] ?? null,
            'private'     => $repo['private'],
            'language'    => $repo['language'] ?? null,
            'updated_at'  => $repo['updated_at'],
            'stars'       => $repo['stargazers_count'],
            'url'         => $repo['html_url'],
        ], $repos);
    }

    //  Branches 
    public function getBranches(string $repo): array
    {
        $response = $this->client->get("/repos/{$repo}/branches");

        if (!$response->successful()) {
            throw new Exception('Impossible de récupérer les branches.');
        }

        return array_column($response->json(), 'name');
    }

    //  Dernier commit 
    public function getLastCommit(string $repo, string $branch): array
    {
        $response = $this->client->get("/repos/{$repo}/commits/{$branch}");

        if (!$response->successful()) {
            return ['sha' => null, 'message' => null, 'author' => null];
        }

        $data = $response->json();

        return [
            'sha'     => $data['sha'],
            'message' => $data['commit']['message'] ?? null,
            'author'  => $data['commit']['author']['name'] ?? null,
        ];
    }

    //  Webhook
    public function createWebhook(string $repo, string $webhookUrl, string $secret): array
    {
        $response = $this->client->post("/repos/{$repo}/hooks", [
            'name'   => 'web',
            'active' => true,
            'events' => ['push', 'release'],
            'config' => [
                'url'          => $webhookUrl,
                'content_type' => 'json',
                'secret'       => $secret,
                'insecure_ssl' => '0',
            ],
        ]);

        if (!$response->successful()) {
            throw new Exception('Impossible de créer le webhook : ' . $response->body());
        }

        return $response->json();
    }

    public function deleteWebhook(string $repo, string $hookId): void
    {
        $this->client->delete("/repos/{$repo}/hooks/{$hookId}");
    }
}
