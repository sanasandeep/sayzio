<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class DialerNote extends Model
{
    protected $fillable = ['user_id', 'title', 'body', 'number_e164', 'remind_at', 'done', 'color'];

    protected $casts = ['remind_at' => 'datetime', 'done' => 'boolean'];

    public function user() { return $this->belongsTo(User::class); }

    public function shares() { return $this->hasMany(DialerNoteShare::class); }
}
