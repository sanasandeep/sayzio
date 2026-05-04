<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class InboxThreadAssignment extends Model
{
    public $timestamps = false;

    protected $table = 'inbox_thread_assignments';

    protected $fillable = [
        'thread_id', 'from_user_id', 'to_user_id', 'actor_user_id',
        'action', 'note', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function thread()
    {
        return $this->belongsTo(InboxThread::class, 'thread_id');
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
