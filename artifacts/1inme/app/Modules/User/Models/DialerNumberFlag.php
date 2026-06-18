<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class DialerNumberFlag extends Model
{
    protected $fillable = ['user_id', 'number_e164', 'is_spam', 'is_blocked'];

    protected $casts = ['is_spam' => 'boolean', 'is_blocked' => 'boolean'];

    public function user() { return $this->belongsTo(User::class); }
}
