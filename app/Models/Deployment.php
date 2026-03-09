<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    //  Ajouter une ligne au log 
    public function appendLog(string $line): void
    {
        $this->update(['log' => $this->log . $line . "\n"]);
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
            'rolled_back' => 'Rollback',
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
