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
        $size = htmlspecialchars($sizeClass, ENT_QUOTES);
        return "<svg class=\"inline-block shrink-0 {$size}\" style=\"color:{$color};width:1em;height:1em\" viewBox=\"0 0 24 24\" role=\"img\" aria-label=\"Verified\"><circle cx=\"12\" cy=\"12\" r=\"12\" fill=\"currentColor\"/><path d=\"M6.5 12l3.5 3.5 7.5-7.5\" fill=\"none\" stroke=\"white\" stroke-width=\"2.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/></svg>";
    }
}
