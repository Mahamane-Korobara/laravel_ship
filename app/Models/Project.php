<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Crypt;

class Project extends Model
{
    protected $fillable = [
        'user_id',
        'server_id',
        'name',
        'github_repo',
        'github_branch',
        'github_webhook_id',
        'github_webhook_secret',
        'domain',
        'deploy_path',
        'php_version',
        'run_migrations',
        'run_seeders',
        'run_npm_build',
        'has_queue_worker',
        'status',
        'current_release',
    ];

    protected $casts = [
        'run_migrations'   => 'boolean',
        'run_seeders'      => 'boolean',
        'run_npm_build'    => 'boolean',
        'has_queue_worker' => 'boolean',
    ];

    //  Chiffrement webhook secret 
    public function setGithubWebhookSecretAttribute(?string $value): void
    {
        $this->attributes['github_webhook_secret'] = $value
            ? Crypt::encryptString($value)
            : null;
    }

    public function getGithubWebhookSecretAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    //  Chemins du projet 
    public function getReleasesPathAttribute(): string
    {
        return $this->deploy_path . '/releases';
    }

    public function getCurrentPathAttribute(): string
    {
        return $this->deploy_path . '/current';
    }

    public function getSharedPathAttribute(): string
    {
        return $this->deploy_path . '/shared';
    }

    public function getBackupsPathAttribute(): string
    {
        return $this->deploy_path . '/backups';
    }

    public function getLogsPathAttribute(): string
    {
        return $this->deploy_path . '/logs';
    }

    //  URL déployée 
    public function getUrlAttribute(): ?string
    {
        return $this->domain ? 'https://' . $this->domain : null;
    }

    //  Couleur statut 
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'deployed'  => 'green',
            'deploying' => 'blue',
            'failed'    => 'red',
            'idle'      => 'gray',
            default     => 'gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'deployed'  => 'Déployé',
            'deploying' => 'En cours...',
            'failed'    => 'Échec',
            'idle'      => 'Inactif',
            default     => 'Inconnu',
        };
    }

    // Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class)->latest();
    }

    public function lastDeployment(): HasOne
    {
        return $this->hasOne(Deployment::class)->latestOfMany();
    }

    public function envVariables(): HasMany
    {
        return $this->hasMany(EnvVariable::class);
    }
}
