<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

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
