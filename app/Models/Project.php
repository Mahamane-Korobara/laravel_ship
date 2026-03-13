<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Crypt;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $server_id
 * @property string $name
 * @property string $github_repo
 * @property string $github_branch
 * @property string|null $github_webhook_id
 * @property string|null $github_webhook_secret
 * @property bool $webhook_pending
 * @property string|null $webhook_last_commit_sha
 * @property string|null $webhook_last_commit_message
 * @property string|null $webhook_last_commit_author
 * @property string|null $webhook_last_event
 * @property string|null $webhook_last_delivery_id
 * @property \Illuminate\Support\Carbon|null $webhook_last_event_at
 * @property string|null $domain
 * @property string $deploy_path
 * @property string $php_version
 * @property bool $run_migrations
 * @property bool $run_seeders
 * @property bool $run_npm_build
 * @property bool $has_queue_worker
 * @property string $status
 * @property string|null $current_release
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Deployment> $deployments
 * @property-read int|null $deployments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EnvVariable> $envVariables
 * @property-read int|null $env_variables_count
 * @property-read string $backups_path
 * @property-read string $current_path
 * @property-read string $logs_path
 * @property-read string $releases_path
 * @property-read string $shared_path
 * @property-read string $status_color
 * @property-read string $status_label
 * @property-read string|null $url
 * @property-read \App\Models\Deployment|null $lastDeployment
 * @property-read \App\Models\Server|null $server
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereCurrentRelease($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereDeployPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereDomain($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereGithubBranch($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereGithubRepo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereGithubWebhookId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereGithubWebhookSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereHasQueueWorker($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project wherePhpVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereRunMigrations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereRunNpmBuild($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereRunSeeders($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereServerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereUserId($value)
 * @mixin \Eloquent
 */
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
        'webhook_pending',
        'webhook_last_commit_sha',
        'webhook_last_commit_message',
        'webhook_last_commit_author',
        'webhook_last_event',
        'webhook_last_delivery_id',
        'webhook_last_event_at',
        'domain',
        'deploy_path',
        'php_version',
        'run_migrations',
        'run_seeders',
        'run_npm_build',
        'has_queue_worker',
        'status',
        'current_release',
        'env_file_path',
    ];

    protected $casts = [
        'run_migrations'   => 'boolean',
        'run_seeders'      => 'boolean',
        'run_npm_build'    => 'boolean',
        'has_queue_worker' => 'boolean',
        'webhook_pending' => 'boolean',
        'webhook_last_event_at' => 'datetime',
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

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(ProjectWebhookEvent::class)->latest();
    }
}
