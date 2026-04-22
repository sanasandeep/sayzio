<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class CompanionThread extends Model
{
    protected $table = 'companion_threads';

    protected $fillable = [
        'user_id', 'workspace_id', 'title', 'last_message_at',
        'mind_ids', 'include_platform',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at'  => 'datetime',
            'mind_ids'         => 'array',
            'include_platform' => 'boolean',
        ];
    }

    public function messages()
    {
        return $this->hasMany(CompanionMessage::class, 'thread_id')->orderBy('id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
