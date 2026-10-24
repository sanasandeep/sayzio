<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceChallenge extends Model
{
    protected $table = 'attendance_challenges';

    protected $fillable = ['id', 'participant_id', 'action', 'pose', 'expires_at', 'consumed_at'];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = ['expires_at' => 'immutable_datetime', 'consumed_at' => 'immutable_datetime'];
}
