<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * @property int $id
 * @property int $project_id
 * @property string $key
 * @property string $value
 * @property bool $is_secret
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $masked_value
 * @property-read \App\Models\Project $project
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EnvVariable newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EnvVariable newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EnvVariable query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EnvVariable whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EnvVariable whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EnvVariable whereIsSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EnvVariable whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EnvVariable whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EnvVariable whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EnvVariable whereValue($value)
 * @mixin \Eloquent
 */
class EnvVariable extends Model
{
    protected $fillable = ['project_id', 'key', 'value', 'is_secret'];

    protected $casts = [
        'is_secret' => 'boolean',
    ];

    //  Chiffrement valeur 
    public function setValueAttribute(string $value): void
    {
        $this->attributes['value'] = Crypt::encryptString($value);
    }

    public function getValueAttribute(string $value): string
    {
        return Crypt::decryptString($value);
    }

    //  Valeur masquée pour l'UI
    public function getMaskedValueAttribute(): string
    {
        return $this->is_secret ? '••••••••••••' : $this->value;
    }

    //  Relations 
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
