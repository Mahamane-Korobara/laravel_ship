<?php

use App\Http\Controllers\Auth\GithubController;
use App\Livewire\Dashboard;
use App\Livewire\Servers\ServerList;
use App\Livewire\Servers\ServerCreate;
use App\Livewire\Servers\ServerShow;
use App\Livewire\Projects\ProjectImport;
use App\Livewire\Projects\ProjectList;
use App\Livewire\Projects\ProjectShow;
use App\Livewire\Projects\ProjectDeploy;
use App\Livewire\Projects\ProjectSettings;
use App\Livewire\Deployments\DeploymentShow;
use App\Livewire\Deployments\DeploymentLogs;
use Illuminate\Support\Facades\Route;

// GitHub OAuth
Route::get('/auth/github', [GithubController::class, 'redirect'])->name('auth.github');
Route::get('/auth/github/callback', [GithubController::class, 'callback'])->name('auth.github.callback');

// Auth Breeze
require __DIR__ . '/auth.php';

// Routes protégées
Route::middleware(['auth'])->group(function () {

    // Dashboard
    Route::get('/', Dashboard::class)->name('dashboard');

    // Serveurs
    Route::get('/servers', ServerList::class)->name('servers.index');
    Route::get('/servers/create', ServerCreate::class)->name('servers.create');
    Route::get('/servers/{server}', ServerShow::class)->name('servers.show');

    // Projets
    Route::get('/projects', ProjectList::class)->name('projects.index');
    Route::get('/projects/import', ProjectImport::class)->name('projects.import');
    Route::get('/projects/{project}', ProjectShow::class)->name('projects.show');
    Route::get('/projects/{project}/deploy', ProjectDeploy::class)->name('projects.deploy');
    Route::get('/projects/{project}/settings', ProjectSettings::class)->name('projects.settings');

    // Déploiements
    Route::get('/deployments/{deployment}', DeploymentShow::class)->name('deployments.show');
    Route::get('/deployments/{deployment}/logs', DeploymentLogs::class)->name('deployments.logs');
});
