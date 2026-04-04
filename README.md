# Laravel Ship

Plateforme PaaS auto-hebergee pour automatiser le cycle de vie des projets Laravel.

## Objectif
- Gerer des projets Laravel sur des VPS clients.
- Executer des deploiements reproductibles via SSH.
- Centraliser les logs et l'historique des deploiements.
- Integrer les webhooks GitHub (V1: notification + suivi des commits).

## Stack
- Laravel 12 + Livewire (UI reactive cote serveur)
- MySQL (stockage principal)
- Laravel Queues (jobs de deploiement)
- Laravel Reverb/Echo (logs temps reel si active)
- Tailwind CSS + Vite
- Alpine.js (UI interactions)

## Architecture technique (vue d'ensemble)

### 1) Presentation (UI)
- Livewire components dans `app/Livewire/*`
- Vues dans `resources/views/livewire/*`
- UI components reutilisables dans `resources/views/components/ui/*`

Pages principales:
- Tableau de bord: `app/Livewire/Dashboard.php`
- Projets: `app/Livewire/Projects/*`
- Serveurs: `app/Livewire/Servers/*`
- Deploiements: `app/Livewire/Deployments/*`

### 2) Domain / Models
Models cles:
- `User` : compte admin
- `Server` : VPS client (IP, SSH, metrics)
- `Project` : projet Laravel (repo, branche, domaine, options)
- `Deployment` : un deploiement (statut, log, duree)
- `EnvVariable` : variables .env par projet
- `ProjectWebhookEvent` : evenements GitHub recus

### 3) Services (orchestration)
- `SshService` : connexion SSH + execution/streaming + SFTP
- `DeploymentService` : pipeline complet de deploiement
- `ApacheService` : creation VirtualHost + pool PHP-FPM dedie
- `SslService` : certbot letsencrypt
- `GitHubService` : repos, branches, webhooks

### 4) Jobs + Events
- `RunDeployment` (Job): lance le deploiement en file de queue
- `DeploymentLogReceived` (Event): broadcast des logs en temps reel

### 5) Webhooks
- `WebhookController@github` : validation signature + enregistrement evenements

## Flux de deploiement (V1)
1. L'utilisateur lance un deploiement depuis l'UI.
2. Un `Deployment` est cree puis un `RunDeployment` est queue.
3. `DeploymentService` execute:
   - Creation structure (releases/shared/current)
   - Clone du repo GitHub
   - Ecriture .env (shared + symlink)
   - Composer install
   - Migrations / seeders (si actives)
   - Optimisations Laravel (config/route/view cache)
   - Permissions, logrotate
   - VirtualHost Apache + PHP-FPM pool dedie
   - Certificat SSL (si domaine pointe)
4. Les logs sont stockes dans `deployments.log` et diffuses en temps reel.

## Structure des dossiers

```
app/
  Livewire/                # Pages et logique UI
  Models/                  # Entites (User, Project, Deployment...)
  Services/                # SSH, Apache, SSL, GitHub, Deployment
  Jobs/                    # RunDeployment
  Events/                  # DeploymentLogReceived
resources/
  views/
    livewire/              # Vues Livewire
    components/ui/         # UI components (select, terminal, button, ...)
  js/                      # bootstrap.js, app.js
routes/
  web.php                  # Routes UI
  api.php                  # API si besoin
  auth.php                 # Auth
  console.php              # Commands
```

## Base de donnees (tables principales)
- users
- servers
- projects
- deployments
- env_variables
- project_webhook_events
- jobs / cache (Laravel)

Migrations: `database/migrations/*`

## Temps reel (logs)
- Channel: `deployment.{id}`
- Event: `DeploymentLogReceived`
- UI: terminal reutilisable (`resources/views/components/ui/terminal.blade.php`)

## Securite
- IP VPS et cle SSH chiffrees en base (`Server`)
- Secret webhook GitHub chiffre (`Project`)
- SSH par cle privee uniquement

## Developpement local
- `php artisan serve`
- `php artisan queue:work` (deploiements)
- `php artisan reverb:start` (si logs temps reel)
- `npm run dev`

## Notes
- Les webhooks GitHub stockent les commits; le deploiement auto sera gere en V2.
- Les pools PHP-FPM sont isoles par projet et version PHP.
