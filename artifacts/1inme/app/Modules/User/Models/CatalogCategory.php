<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/** A category grouping catalog items and/or expenses. */
class CatalogCategory extends Model
{
    protected $fillable = [
        'user_id', 'billing_company_id', 'name', 'kind', 'sort',
    ];

    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(CatalogItem::class, 'category_id');
    }

    public function scopeForItems($q)    { return $q->whereIn('kind', ['item', 'both']); }
    public function scopeForExpenses($q) { return $q->whereIn('kind', ['expense', 'both']); }
}
