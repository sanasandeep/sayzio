<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserNotification extends Model
{
    use SoftDeletes;

    // Dismissing a notification soft-deletes the row (stamps `dismissed_at`)
    // instead of permanently removing it, so an accidental dismissal can be
    // undone. The SoftDeletes trait wires the global scope, restore(),
    // onlyTrashed(), etc. to this column automatically.
    const DELETED_AT = 'dismissed_at';

    public $timestamps = false;
    protected $fillable = ['user_id', 'type', 'data', 'read_at', 'emailed_at', 'created_at', 'dismissed_at'];
    protected $casts = ['data' => 'array', 'read_at' => 'datetime', 'emailed_at' => 'datetime', 'created_at' => 'datetime', 'dismissed_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
}
