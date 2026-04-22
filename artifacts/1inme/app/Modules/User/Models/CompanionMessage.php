<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class CompanionMessage extends Model
{
    protected $table = 'companion_messages';

    public $timestamps = false;

    protected $fillable = ['thread_id', 'role', 'content', 'meta', 'created_at'];

    protected function casts(): array
    {
        return [
            'meta'       => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function thread()
    {
        return $this->belongsTo(CompanionThread::class, 'thread_id');
    }
}
