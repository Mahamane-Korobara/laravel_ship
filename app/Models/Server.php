<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $ip_address
 * @property string $ssh_user
 * @property int $ssh_port
 * @property string $ssh_private_key
 * @property string $php_version
 * @property array|null $labels
 * @property string|null $agent_url
 * @property string|null $agent_token
 * @property bool $agent_enabled
 * @property \Illuminate\Support\Carbon|null $agent_last_seen_at
 * @property int|null $vcpu
 * @property int|null $ram_mb
 * @property int|null $disk_gb
 * @property string $status
 * @property string|null $last_error
 * @property \Illuminate\Support\Carbon|null $last_connected_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $masked_ip
 * @property-read string $status_color
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Project> $projects
 * @property-read int|null $projects_count
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereLastConnectedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereLastError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server wherePhpVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereSshPort($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereSshPrivateKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereSshUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereUserId($value)
 * @mixin \Eloquent
 */
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
        'labels',
        'agent_url',
        'agent_token',
        'agent_enabled',
        'agent_last_seen_at',
        'vcpu',
        'ram_mb',
        'disk_gb',
        'status',
        'last_error',
        'last_connected_at',
    ];

    protected $casts = [
        'last_connected_at' => 'datetime',
        'agent_last_seen_at' => 'datetime',
        'ssh_port'          => 'integer',
        'vcpu'              => 'integer',
        'ram_mb'            => 'integer',
        'disk_gb'           => 'integer',
        'labels'            => 'array',
        'agent_enabled'     => 'boolean',
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

    //  Chiffrement token agent
    public function setAgentTokenAttribute(?string $value): void
    {
        $this->attributes['agent_token'] = $value
            ? Crypt::encryptString($value)
            : null;
    }

    public function getAgentTokenAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
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
