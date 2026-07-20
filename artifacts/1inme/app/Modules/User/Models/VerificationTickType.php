<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

class VerificationTickType extends Model
{
    protected $table = 'verification_tick_types';

    protected $fillable = [
        'slug', 'name', 'color', 'icon', 'admin_assigned_only', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'admin_assigned_only' => 'boolean',
        'is_active'           => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function scopePublicRequestable($query)
    {
        return $query->where('is_active', true)
            ->where('admin_assigned_only', false)
            ->orderBy('sort_order');
    }

    /** Cached catalog keyed by slug for quick lookups. */
    public static function catalog(): Collection
    {
        return static::orderBy('sort_order')->get();
    }

    /** Render a colored tick icon HTML snippet for this type. */
    public function tickHtml(string $sizeClass = 'text-sm'): string
    {
        $color = htmlspecialchars($this->color, ENT_QUOTES);
        $icon  = htmlspecialchars($this->icon, ENT_QUOTES);
        $name  = htmlspecialchars($this->name, ENT_QUOTES);
        return "<i class=\"fas {$icon} {$sizeClass}\" style=\"color:{$color};\" title=\"{$name} account\"></i>";
    }
}
