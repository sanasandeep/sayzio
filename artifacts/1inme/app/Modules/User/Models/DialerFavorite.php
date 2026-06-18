<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class DialerFavorite extends Model
{
    protected $fillable = ['user_id', 'contact_id', 'number_e164', 'label', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function contact() { return $this->belongsTo(Contact::class); }

    public function user() { return $this->belongsTo(User::class); }
}
