<?php

namespace App\Modules\User\Models;


use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class InboxReply extends Model
{
    
    use BelongsToWorkspace;
protected $fillable = [
        'user_id', 'item_type', 'item_id', 'to_email',
        'from_email', 'from_name', 'subject', 'body',
        'status', 'error', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(\App\Modules\User\Models\User::class);
    }
}
