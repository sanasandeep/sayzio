<?php

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = ['name', 'email', 'subject', 'message', 'ip', 'status', 'contact_channel', 'contact_phone'];
}
