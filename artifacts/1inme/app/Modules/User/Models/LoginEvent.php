<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per successful login. Powers the suspicious-login email
 * pipeline and the "Recent logins" page in user settings + mobile
 * Account section.
 */
class LoginEvent extends Model
{
    protected $fillable = [
        'user_id',
        'channel',
        'ip',
        'country_code',
        'platform',
        'browser',
        'device_label',
        'user_agent',
        'personal_access_token_id',
        'session_id',
        'is_new',
        'new_reasons',
        'alert_sent',
        'revoked_at',
        'revoke_token',
    ];

    protected function casts(): array
    {
        return [
            'is_new'      => 'boolean',
            'alert_sent'  => 'boolean',
            'new_reasons' => 'array',
            'revoked_at'  => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
