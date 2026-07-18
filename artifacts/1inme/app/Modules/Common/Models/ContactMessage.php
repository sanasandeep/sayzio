<?php

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactMessage extends Model
{
    protected $fillable = ['name', 'email', 'subject', 'message', 'ip', 'status', 'contact_channel', 'contact_phone', 'replied_at'];

    protected $casts = [
        'replied_at' => 'datetime',
    ];

    public function replies(): HasMany
    {
        return $this->hasMany(ContactMessageReply::class)->orderBy('created_at');
    }

    public function isReplied(): bool
    {
        return $this->replied_at !== null;
    }
}
