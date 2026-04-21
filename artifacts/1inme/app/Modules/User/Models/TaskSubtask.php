<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class TaskSubtask extends Model
{
    protected $fillable = ['card_id', 'title', 'completed', 'position'];
    protected $casts = ['completed' => 'boolean'];

    public function card() { return $this->belongsTo(TaskCard::class, 'card_id'); }
}
