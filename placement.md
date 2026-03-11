# Laravel Ship — Instructions de placement des fichiers

## Structure à respecter dans ton projet Laravel

```
laravel-ship/
│
├── app/
│   ├── Http/Controllers/
│   │   ├── Auth/GithubController.php      ← déjà fait par toi
│   │   └── WebhookController.php          ← WebhookController.php
│   │
│   ├── Jobs/
│   │   └── RunDeployment.php              ← RunDeployment.php (section Jobs)
│   │
│   ├── Events/
│   │   └── DeploymentLogReceived.php      ← déjà fait par toi
│   │
│   ├── Livewire/
│   │   ├── Dashboard.php                  ← copier depuis Servers.php (section Dashboard)
│   │   ├── Servers/
│   │   │   ├── ServerList.php             ← copier depuis Servers.php
│   │   │   ├── ServerCreate.php           ← copier depuis Servers.php
│   │   │   └── ServerShow.php             ← copier depuis Servers.php
│   │   ├── Projects/
│   │   │   ├── ProjectImport.php          ← copier depuis Projects.php
│   │   │   ├── ProjectShow.php            ← copier depuis Projects.php
│   │   │   ├── ProjectDeploy.php          ← copier depuis Projects.php
│   │   │   └── ProjectSettings.php        ← copier depuis Projects.php
│   │   └── Deployments/
│   │       ├── DeploymentShow.php         ← copier depuis Deployments.php
│   │       └── DeploymentLogs.php         ← copier depuis Deployments.php
│   │
│   ├── Models/
│   │   ├── User.php                       ← User.php
│   │   ├── Server.php                     ← Server.php
│   │   ├── Project.php                    ← Project.php
│   │   ├── Deployment.php                 ← Deployment.php
│   │   └── EnvVariable.php                ← EnvVariable.php
│   │
│   └── Services/
│       ├── GitHubService.php              ← GitHubService.php
│       ├── SshService.php                 ← SshService.php
│       ├── ApacheService.php              ← ApacheService.php
│       ├── SslService.php                 ← SslService.php
│       └── DeploymentService.php          ← DeploymentService.php
│
├── config/
│   └── deploy.php                         ← deploy.php
│
├── database/migrations/
│   ├── 2026_03_07_000001_add_github_fields_to_users_table.php
│   ├── 2026_03_07_000002_create_servers_table.php
│   ├── 2026_03_07_000003_create_projects_table.php
│   ├── 2026_03_07_000004_create_deployments_table.php
│   └── 2026_03_07_000005_create_env_variables_table.php
│
└── routes/
    ├── web.php                            ← web.php (remplacer)
    └── api.php                            ← api.php (remplacer)
```

---

## ⚠️ Fichiers multi-classes à séparer

Les fichiers `Servers.php`, `Projects.php`, `Deployments.php`
contiennent plusieurs classes. Tu dois les **séparer** en fichiers individuels.

Exemple pour `Servers.php` :

- Extraire la classe `Dashboard` → `app/Livewire/Dashboard.php`
- Extraire la classe `ServerList` → `app/Livewire/Servers/ServerList.php`
- Extraire la classe `ServerCreate` → `app/Livewire/Servers/ServerCreate.php`
- Extraire la classe `ServerShow` → `app/Livewire/Servers/ServerShow.php`

---

## Ajouter GitHub dans config/services.php

```php
'github' => [
    'client_id'     => env('GITHUB_CLIENT_ID'),
    'client_secret' => env('GITHUB_CLIENT_SECRET'),
    'redirect'      => env('GITHUB_REDIRECT_URI'),
],
```

---

## Exclure le webhook du CSRF dans bootstrap/app.php

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'api/webhooks/github',
    ]);
})
```

---

## Commandes à lancer après placement des fichiers

```bash
# Migrations
php artisan migrate

# Vider les caches
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Vérifier les routes
php artisan route:list

# Lancer les 4 services
php artisan serve                        # Terminal 1
php artisan queue:listen redis --tries=1 # Terminal 2
php artisan reverb:start                 # Terminal 3
npm run dev                              # Terminal 4
```

---

## Ce qu'il reste (Codex s'en occupe)

- `resources/views/layouts/app.blade.php`
- `resources/views/auth/login.blade.php`
- `resources/views/livewire/dashboard.blade.php`
- `resources/views/livewire/servers/index.blade.php`
- `resources/views/livewire/servers/create.blade.php`
- `resources/views/livewire/servers/show.blade.php`
- `resources/views/livewire/projects/import.blade.php`
- `resources/views/livewire/projects/show.blade.php`
- `resources/views/livewire/projects/deploy.blade.php`
- `resources/views/livewire/projects/settings.blade.php`
- `resources/views/livewire/deployments/show.blade.php`
- `resources/views/livewire/deployments/logs.blade.php`
