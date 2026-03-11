<?php

namespace App\Http\Controllers;

use App\Models\Project;
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

        match ($event) {
            'push'    => $this->handlePush($project, $request),
            'ping'    => Log::info("Webhook ping reçu pour {$project->name}"),
            default   => null,
        };

        return response('OK', 200);
    }

    private function handlePush(Project $project, Request $request): void
    {
        $branch = str_replace('refs/heads/', '', $request->input('ref', ''));
        $commit = $request->input('head_commit.id');
        $message = $request->input('head_commit.message');
        $author  = $request->input('head_commit.author.name');

        // En V1 : on stocke l'info et on notifie via l'UI
        // (pas de déploiement automatique — prévu V2)
        Log::info("Push reçu sur {$project->name} — branch: {$branch} — commit: {$commit}");

        // Marquer le projet comme ayant un nouveau commit disponible
        // On stocke le dernier commit dans une metadata simple
        $project->update([
            // On pourrait ajouter un champ 'pending_commit' mais
            // en V1 on log juste l'info — le déploiement reste manuel
        ]);

        // TODO V2 : dispatch(new RunDeployment(...)) si auto-deploy activé
    }
}
