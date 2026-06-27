<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A user (dashboard account or OTP-verified viewer) following a
 * {@see Calendar}. Mirrors the creator {@see Follow} pivot: no updated_at,
 * a single created_at stamp, and a unique (calendar_id, follower_id) pair.
 */
class CalendarFollow extends Model
{
    public $timestamps = false;

    protected $fillable = ['calendar_id', 'follower_id', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function calendar()
    {
        return $this->belongsTo(Calendar::class);
    }

    public function follower()
    {
        return $this->belongsTo(User::class, 'follower_id');
    }
}
