<?php

namespace App\Modules\User\Models;

use App\Modules\Admin\Models\Plan;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name', 'email', 'mobile', 'password', 'phone', 'avatar', 'status', 'role',
        'plan_id', 'billing_cycle', 'plan_expires_at', 'trial_ends_at',
        'timezone', 'language', 'settings', 'email_verified_at', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'plan_expires_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'settings' => 'array',
        ];
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function links()
    {
        return $this->hasMany(Link::class);
    }

    public function pixels()
    {
        return $this->hasMany(Pixel::class);
    }

    public function domains()
    {
        return $this->hasMany(Domain::class);
    }

    public function files()
    {
        return $this->hasMany(UserFile::class);
    }

    public function getStorageUsedBytes(): int
    {
        return (int) $this->files()->sum('size_bytes');
    }

    public function getStorageLimitBytes(): int
    {
        $mb = (int) $this->getPlanFeature('storage_limit_mb', 100);
        return $mb * 1048576;
    }

    public function getStorageRemainingBytes(): int
    {
        return max(0, $this->getStorageLimitBytes() - $this->getStorageUsedBytes());
    }

    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function hasActivePlan(): bool
    {
        if ($this->isOnTrial()) return true;
        if (!$this->plan_expires_at) return false;
        return $this->plan_expires_at->isFuture();
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isOnFreePlan(): bool
    {
        return !$this->plan_id || ($this->plan && $this->plan->slug === 'free');
    }

    public function getPlanFeature(string $key, $default = null)
    {
        if (!$this->plan || !$this->plan->features) return $default;
        return $this->plan->features[$key] ?? $default;
    }
}
