<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'type', 'in_app', 'email', 'push'];

    protected $casts = [
        'in_app' => 'boolean',
        'email'  => 'boolean',
        'push'   => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
