<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class Server extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'ip_address',
        'ssh_user',
        'ssh_port',
        'ssh_private_key',
        'php_version',
        'status',
        'last_error',
        'last_connected_at',
    ];

    protected $casts = [
        'last_connected_at' => 'datetime',
        'ssh_port'          => 'integer',
    ];

    //  Chiffrement IP 
    public function setIpAddressAttribute(string $value): void
    {
        $this->attributes['ip_address'] = Crypt::encryptString($value);
    }

    public function getIpAddressAttribute(string $value): string
    {
        return Crypt::decryptString($value);
    }

    //  Chiffrement clé SSH 
    public function setSshPrivateKeyAttribute(string $value): void
    {
        $this->attributes['ssh_private_key'] = Crypt::encryptString($value);
    }

    public function getSshPrivateKeyAttribute(string $value): string
    {
        return Crypt::decryptString($value);
    }

    //  IP masquée pour l'UI 
    public function getMaskedIpAttribute(): string
    {
        $ip    = $this->ip_address;
        $parts = explode('.', $ip);

        if (count($parts) === 4) {
            return "***.***.***." . substr($parts[3], 0, 1) . 'xx';
        }

        return '***.***.***.***';
    }

    //  Couleur statut 
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active'   => 'green',
            'inactive' => 'yellow',
            'error'    => 'red',
            default    => 'gray',
        };
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    //  Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
