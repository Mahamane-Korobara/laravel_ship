<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectWebhookEvent extends Model
{
    protected $fillable = [
        'project_id',
        'event',
        'delivery_id',
        'ref',
        'commit_sha',
        'commit_message',
        'author',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
