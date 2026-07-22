<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class DialerNoteShare extends Model
{
    protected $fillable = ['dialer_note_id', 'phone_e164', 'shared_with_user_id'];

    public function note() { return $this->belongsTo(DialerNote::class, 'dialer_note_id'); }

    public function sharedWith() { return $this->belongsTo(User::class, 'shared_with_user_id'); }
}
