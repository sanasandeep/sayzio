<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class AiPersona extends Model
{
    protected $table = 'ai_personas';

    protected $fillable = [
        'user_id', 'name', 'audience', 'goals', 'tone', 'content', 'model',
    ];

    public function user() { return $this->belongsTo(User::class); }
}
