<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class TaskComment extends Model
{
    protected $fillable = ['card_id', 'user_id', 'body'];

    public function card() { return $this->belongsTo(TaskCard::class, 'card_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
