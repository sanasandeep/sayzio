<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'type', 'data', 'read_at', 'created_at'];
    protected $casts = ['data' => 'array', 'read_at' => 'datetime', 'created_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
}
