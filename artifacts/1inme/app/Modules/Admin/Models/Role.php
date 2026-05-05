<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'guard'];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function admins()
    {
        return $this->hasMany(Admin::class);
    }

    public function users()
    {
        return $this->belongsToMany(\App\Modules\User\Models\User::class, 'user_roles');
    }
}
