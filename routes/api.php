<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhook GitHub
|--------------------------------------------------------------------------
| Route exclue du middleware CSRF via bootstrap/app.php
| Sécurisée par vérification X-Hub-Signature-256
|--------------------------------------------------------------------------
*/

Route::post('/webhooks/github', [WebhookController::class, 'github'])
    ->name('webhooks.github');
