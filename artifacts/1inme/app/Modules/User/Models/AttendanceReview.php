<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceReview extends Model
{
    protected $table = 'attendance_reviews';

    protected $fillable = ['workspace_id', 'actor_user_id', 'actor_guard', 'subject_type', 'subject_id', 'decision', 'note', 'details', 'created_at'];

    public $timestamps = false;

    protected $casts = ['details' => 'array', 'created_at' => 'immutable_datetime'];
}
