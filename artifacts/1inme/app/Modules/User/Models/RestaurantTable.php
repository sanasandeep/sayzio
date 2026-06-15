<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RestaurantTable extends Model
{
    protected $fillable = [
        'menu_id', 'label', 'code', 'sort_order',
    ];

    protected static function booted(): void
    {
        static::creating(function (RestaurantTable $table) {
            if (empty($table->code)) {
                $table->code = self::generateCode();
            }
        });
    }

    public static function generateCode(): string
    {
        do {
            $code = Str::lower(Str::random(8));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public function menu()
    {
        return $this->belongsTo(RestaurantMenu::class, 'menu_id');
    }

    public function orders()
    {
        return $this->hasMany(RestaurantOrder::class, 'table_id');
    }
}
