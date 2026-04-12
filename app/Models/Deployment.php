<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property string $release_name
 * @property string|null $git_commit
 * @property string $git_branch
 * @property string $triggered_by
 * @property string $status
 * @property string|null $log
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property int|null $duration_seconds
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $duration_human
 * @property-read string $status_color
 * @property-read string $status_label
 * @property-read \App\Models\Project $project
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Deployment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Deployment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Deployment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Deployment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Deployment whereDurationSeconds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Deployment whereFinishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Deployment whereGitBranch($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Deployment whereGitCommit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Deployment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Deployment whereLog($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Deployment whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Deployment whereReleaseName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Deployment whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Deployment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Deployment whereTriggeredBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Deployment whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Deployment extends Model
{
    protected $fillable = [
        'project_id',
        'release_name',
        'git_commit',
        'git_branch',
        'triggered_by',
        'status',
        'log',
        'started_at',
        'finished_at',
        'duration_seconds',
        'env_file_path',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    //  Ajouter une ligne au log 
    public function appendLog(string $line): void
    {
        // Utilisé append() au lieu de update() pour éviter les UPDATE multiples à la BD
        // Cette méthode accumule les logs en mémoire et sera sauvegardée à la fin
        $this->log .= $line . "\n";
    }

    //  Durée lisible 
    public function getDurationHumanAttribute(): string
    {
        if (!$this->duration_seconds) return '—';
        $m = intdiv($this->duration_seconds, 60);
        $s = $this->duration_seconds % 60;
        return $m > 0 ? "{$m}m {$s}s" : "{$s}s";
    }

    //  Couleur statut 
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'success'     => 'green',
            'running'     => 'blue',
            'pending'     => 'yellow',
            'failed'      => 'red',
            'rolled_back' => 'orange',
            default       => 'gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'success'     => 'Succès',
            'running'     => 'En cours',
            'pending'     => 'En attente',
            'failed'      => 'Échec',
            'rolled_back' => 'Retour arrière',
            default       => 'Inconnu',
        };
    }

    //  Helpers 
    public function isRunning(): bool
    {
        return in_array($this->status, ['pending', 'running']);
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    //  Relations 
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
