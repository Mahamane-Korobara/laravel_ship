<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $github_id
 * @property string|null $github_token
 * @property string|null $github_refresh_token
 * @property string|null $github_username
 * @property string|null $github_avatar
 * @property string|null $two_factor_secret
 * @property bool $two_factor_enabled
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Project> $projects
 * @property-read int|null $projects_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Server> $servers
 * @property-read int|null $servers_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGithubAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGithubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGithubRefreshToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGithubToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGithubUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'github_id',
        'github_token',
        'github_refresh_token',
        'github_username',
        'github_avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'github_token',
        'github_refresh_token',
        'two_factor_secret',
    ];

    protected $casts = [
        'email_verified_at'  => 'datetime',
        'password'           => 'hashed',
        'two_factor_enabled' => 'boolean',
    ];

    //  Chiffrement GitHub token
    public function setGithubTokenAttribute(?string $value): void
    {
        $this->attributes['github_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getGithubTokenAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setGithubRefreshTokenAttribute(?string $value): void
    {
        $this->attributes['github_refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getGithubRefreshTokenAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    //  Helpers
    public function hasGithubConnected(): bool
    {
        return !empty($this->attributes['github_token']);
    }

    //  Relations
    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
