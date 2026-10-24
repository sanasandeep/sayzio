<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRequest extends Model
{
    protected $table = 'attendance_requests';

    protected $fillable = ['participant_id', 'type', 'starts_on', 'ends_on', 'reason', 'punch_id', 'proposed_at', 'minutes', 'status', 'leave_type_id', 'requested_days', 'leave_year', 'metadata', 'settlement_amount_minor', 'settlement_currency', 'settlement_reference', 'settled_at'];

    protected $casts = ['proposed_at' => 'immutable_datetime', 'metadata' => 'array', 'settled_at' => 'immutable_datetime'];

    public function participant()
    {
        return $this->belongsTo(AttendanceParticipant::class);
    }
}
