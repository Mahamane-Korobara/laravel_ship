<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function github(Request $request): Response
    {
        // Vérification signature 
        $signature = $request->header('X-Hub-Signature-256');

        if (!$signature) {
            return response('Signature manquante', 401);
        }

        // Trouver le projet via le repo GitHub
        $payload = $request->getContent();
        $repoName = $request->input('repository.full_name');

        if (!$repoName) {
            return response('Payload invalide', 400);
        }

        $project = Project::where('github_repo', $repoName)->first();

        if (!$project) {
            return response('Projet non trouvé', 404);
        }

        // Vérifier le secret webhook du projet
        if ($project->github_webhook_secret) {
            $expected = 'sha256=' . hash_hmac('sha256', $payload, $project->github_webhook_secret);

            if (!hash_equals($expected, $signature)) {
                Log::warning("Webhook signature invalide pour le projet {$project->name}");
                return response('Signature invalide', 401);
            }
        }

        //  Traitement de l'événement 
        $event = $request->header('X-GitHub-Event');
        $deliveryId = $request->header('X-GitHub-Delivery');

        match ($event) {
            'push'    => $this->handlePush($project, $request, $event, $deliveryId),
            'ping'    => Log::info("Webhook ping reçu pour {$project->name}"),
            default   => null,
        };

        $this->storeEvent($project, $event, $deliveryId, $request);

        return response('OK', 200);
    }

    private function handlePush(Project $project, Request $request, ?string $event, ?string $deliveryId): void
    {
        $branch = str_replace('refs/heads/', '', $request->input('ref', ''));
        $commit = $request->input('head_commit.id');
        $message = $request->input('head_commit.message');
        $author  = $request->input('head_commit.author.name');

        // En V1 : on stocke l'info et on notifie via l'UI
        // (pas de déploiement automatique — prévu V2)
        Log::info("Push reçu sur {$project->name} — branch: {$branch} — commit: {$commit}");

        if ($branch !== '' && $project->github_branch && $branch !== $project->github_branch) {
            return;
        }

        if (!$commit) {
            return;
        }

        $project->update([
            'webhook_pending' => true,
            'webhook_last_commit_sha' => $commit,
            'webhook_last_commit_message' => $message,
            'webhook_last_commit_author' => $author,
            'webhook_last_event' => $event,
            'webhook_last_delivery_id' => $deliveryId,
            'webhook_last_event_at' => now(),
        ]);

        // TODO V2 : dispatch(new RunDeployment(...)) si auto-deploy activé
    }

    private function storeEvent(Project $project, ?string $event, ?string $deliveryId, Request $request): void
    {
        $commit = $request->input('head_commit.id');
        $message = $request->input('head_commit.message');
        $author = $request->input('head_commit.author.name');
        $ref = $request->input('ref');

        ProjectWebhookEvent::create([
            'project_id' => $project->id,
            'event' => $event ?: 'unknown',
            'delivery_id' => $deliveryId,
            'ref' => $ref,
            'commit_sha' => $commit,
            'commit_message' => $message,
            'author' => $author,
        ]);
    }
}
