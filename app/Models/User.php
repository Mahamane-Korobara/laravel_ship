<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;

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
