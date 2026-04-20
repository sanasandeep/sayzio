<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class BillingAddress extends Model
{
    protected $fillable = [
        'user_id', 'country', 'region', 'postal_code', 'city',
        'line1', 'line2', 'business_name', 'tax_id', 'tax_id_kind',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
