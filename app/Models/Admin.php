<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class Admin extends Authenticatable implements FilamentUser
{
    use HasFactory, HasRoles, Notifiable;

    protected string $guard_name = 'admin';

    protected $fillable = [
        'name', 'email', 'password', 'is_active',
        'last_login_at', 'dashboard_preferences', 'role',
        'email_verified_at',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'dashboard_preferences' => 'array',
        ];
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function mediaFiles(): HasMany
    {
        return $this->hasMany(MediaFile::class, 'uploaded_by');
    }

    public function orderNotes(): HasMany
    {
        return $this->hasMany(OrderNote::class);
    }

    public function blockedIps(): HasMany
    {
        return $this->hasMany(IpBlocklist::class, 'blocked_by');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_active;
    }
}
