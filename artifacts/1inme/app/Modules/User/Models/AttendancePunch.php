<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class AttendancePunch extends Model
{
    protected $table = 'attendance_punches';

    protected $fillable = ['participant_id', 'challenge_id', 'action', 'occurred_at', 'photo_path', 'pose', 'captured_at', 'latitude', 'longitude', 'accuracy', 'review_status', 'source', 'ip_address', 'roster_id'];

    protected $casts = ['occurred_at' => 'immutable_datetime', 'captured_at' => 'immutable_datetime'];

    public function participant()
    {
        return $this->belongsTo(AttendanceParticipant::class);
    }
}
