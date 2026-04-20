<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class DialerLookup extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'number_e164', 'contact_id', 'looked_up_at'];
    protected $casts    = ['looked_up_at' => 'datetime'];

    public function contact() { return $this->belongsTo(Contact::class); }
}
