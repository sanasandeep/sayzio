<?php

namespace App\Modules\User\Models;

use App\Modules\Admin\Models\Admin;
use Illuminate\Database\Eloquent\Model;

class AttendanceParticipant extends Model
{
    protected $attributes = ['state' => 'out'];

    protected $table = 'attendance_participants';

    protected $fillable = ['workspace_id', 'user_id', 'state'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'user_id');
    }

    public function getStaffAttribute()
    {
        return (int) $this->workspace_id === 0 ? $this->admin : $this->user;
    }

    public function punches()
    {
        return $this->hasMany(AttendancePunch::class, 'participant_id');
    }
}
