<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class ContactDeletionTombstone extends Model
{
    protected $fillable = [
        'user_id', 'google_contacts_account_id', 'google_resource_name',
        'attempts', 'last_error',
    ];
}
