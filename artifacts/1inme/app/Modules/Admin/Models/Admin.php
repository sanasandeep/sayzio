<?php

namespace App\Modules\Admin\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable;

    protected $guard = 'admin';

    protected $fillable = [
        'name', 'email', 'password', 'role_id', 'avatar', 'status', 'last_login_at', 'user_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'user_id' => 'integer',
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

    /**
     * Web-guard User record (if any) that belongs to the same person as
     * this admin. Matched by email (case-insensitive) — the two auth
     * pools share no foreign key, so a matching email is what bridges a
     * back-office admin to a user dashboard. Cached per request.
     */
    protected ?\App\Modules\User\Models\User $cachedUserAccount = null;
    protected bool $userAccountResolved = false;

    public function userAccount(): ?\App\Modules\User\Models\User
    {
        if ($this->userAccountResolved) {
            return $this->cachedUserAccount;
        }
        $this->userAccountResolved = true;

        // Delegated to the hardened bridge: the back-office admin is bound to
        // its user via the explicit `admins.user_id` link (established only
        // under proof of mailbox ownership), not a bare email match.
        return $this->cachedUserAccount =
            \App\Modules\Common\Services\AdminUserBridge::resolveUserForAdmin($this);
    }

    /** True when this admin has a matching user record. */
    public function hasUserAccount(): bool
    {
        return $this->userAccount() !== null;
    }
}
