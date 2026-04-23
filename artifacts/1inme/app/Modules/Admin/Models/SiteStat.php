<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class SiteStat extends Model
{
    protected $table = 'site_stats';

    protected $fillable = [
        'label', 'value', 'suffix', 'icon', 'color', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeOrdered($q) { return $q->orderBy('sort_order')->orderBy('id'); }

    /** Numeric portion stripped of commas/letters, for the count-up animation. */
    public function numericTarget(): ?float
    {
        $clean = preg_replace('/[^0-9.]/', '', (string) $this->value);
        return $clean === '' ? null : (float) $clean;
    }
}
