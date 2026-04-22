<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One persisted Ask-Coach conversation. Scoped per (user, workspace) so
 * switching workspaces gives the user a clean slate.
 */
class AskCoachThread extends Model
{
    protected $table = 'ask_coach_threads';

    protected $fillable = ['user_id', 'workspace_id', 'title', 'last_message_at'];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function messages()
    {
        return $this->hasMany(AskCoachMessage::class, 'thread_id')->orderBy('id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
