<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAssetFolder extends Model
{
    protected $fillable = ['name', 'slug', 'admin_id'];

    public function assets()
    {
        return $this->hasMany(AdminAsset::class, 'folder', 'slug');
    }

    public function getFileCountAttribute(): int
    {
        return AdminAsset::where('folder', $this->slug)->count();
    }
}
