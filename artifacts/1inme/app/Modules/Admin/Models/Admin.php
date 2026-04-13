<?php

namespace App\Modules\Admin\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable;

    protected $guard = 'admin';

    protected $fillable = [
        'name', 'email', 'password', 'role_id', 'avatar', 'status', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function hasPermission(string $slug): bool
    {
        if (!$this->role) return false;
        if ($this->role->slug === 'super-admin') return true;
        return $this->role->permissions()->where('slug', $slug)->exists();
    }

    public function hasAnyPermission(array $slugs): bool
    {
        if (!$this->role) return false;
        if ($this->role->slug === 'super-admin') return true;
        return $this->role->permissions()->whereIn('slug', $slugs)->exists();
    }

    public function isSuperAdmin(): bool
    {
        return $this->role && $this->role->slug === 'super-admin';
    }
}
