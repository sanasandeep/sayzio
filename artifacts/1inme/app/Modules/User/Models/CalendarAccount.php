<?php

namespace App\Modules\User\Models;


use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class CalendarAccount extends Model
{
    
    use BelongsToWorkspace;
protected $fillable = [
        'user_id', 'provider', 'display_name', 'account_email',
        'external_account_id', 'access_token', 'refresh_token',
        'token_expires_at', 'scope', 'default_calendar_id',
        'settings', 'sync_token', 'last_synced_at', 'last_sync_status',
        'last_sync_error', 'mirror_enabled', 'push_enabled',
    ];

    protected function casts(): array
    {
        return [
            'settings'          => 'array',
            'token_expires_at'  => 'datetime',
            'last_synced_at'    => 'datetime',
            'mirror_enabled'    => 'boolean',
            'push_enabled'      => 'boolean',
            'access_token'      => 'encrypted',
            'refresh_token'     => 'encrypted',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function mirrors()
    {
        return $this->hasMany(CalendarEventMirror::class);
    }

    public function providerLabel(): string
    {
        return [
            'google'    => 'Google Calendar',
            'microsoft' => 'Microsoft Outlook',
            'caldav'    => 'CalDAV (iCloud / Fastmail / etc.)',
        ][$this->provider] ?? ucfirst($this->provider);
    }

    public function providerIcon(): string
    {
        return [
            'google'    => 'fab fa-google',
            'microsoft' => 'fab fa-microsoft',
            'caldav'    => 'fas fa-calendar-alt',
        ][$this->provider] ?? 'fas fa-calendar-alt';
    }
}
