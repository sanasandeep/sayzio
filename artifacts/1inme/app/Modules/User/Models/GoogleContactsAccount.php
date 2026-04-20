<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleContactsAccount extends Model
{
    protected $fillable = [
        'user_id', 'account_email', 'external_account_id',
        'access_token', 'refresh_token', 'token_expires_at', 'scope',
        'sync_token', 'last_synced_at', 'last_sync_status', 'last_sync_error',
        'pull_enabled', 'push_enabled', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings'          => 'array',
            'token_expires_at'  => 'datetime',
            'last_synced_at'    => 'datetime',
            'pull_enabled'      => 'boolean',
            'push_enabled'      => 'boolean',
            'access_token'      => 'encrypted',
            'refresh_token'     => 'encrypted',
        ];
    }

    public function user()      { return $this->belongsTo(User::class); }
    public function contacts()  { return $this->hasMany(Contact::class, 'google_contacts_account_id'); }
}
