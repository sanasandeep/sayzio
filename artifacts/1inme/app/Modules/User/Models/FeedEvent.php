<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class FeedEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'type', 'subject_id', 'subject_type', 'data', 'occurred_at'];
    protected $casts = ['data' => 'array', 'occurred_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
}
