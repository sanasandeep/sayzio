<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleContactsAccount extends Model
{
    protected $fillable = [
        'user_id', 'account_email', 'external_account_id',
        'access_token', 'refresh_token', 'token_expires_at', 'scope',
        'sync_token', 'last_synced_at', 'last_sync_status', 'last_sync_error',
        'needs_reauth_at', 'pull_enabled', 'push_enabled', 'settings',
    ];

    /** last_sync_status value used when the Google connection must be re-authorised. */
    public const STATUS_NEEDS_REAUTH = 'needs_reauth';

    protected function casts(): array
    {
        return [
            'settings'          => 'array',
            'token_expires_at'  => 'datetime',
            'needs_reauth_at'   => 'datetime',
            'last_synced_at'    => 'datetime',
            'pull_enabled'      => 'boolean',
            'push_enabled'      => 'boolean',
            'access_token'      => 'encrypted',
            'refresh_token'     => 'encrypted',
        ];
    }

    /** True when Google revoked/expired the connection and the user must reconnect. */
    public function needsReauth(): bool
    {
        return $this->needs_reauth_at !== null;
    }

    /**
     * Mark the account as needing a fresh OAuth grant. Idempotent — the first
     * stamp wins so we keep the time the revocation was first detected.
     */
    public function markNeedsReauth(?string $reason = null): void
    {
        $this->forceFill([
            'needs_reauth_at'  => $this->needs_reauth_at ?? now(),
            'last_sync_status' => self::STATUS_NEEDS_REAUTH,
            'last_sync_error'  => $reason ? \Illuminate\Support\Str::limit($reason, 500) : $this->last_sync_error,
        ])->save();
    }

    public function user()      { return $this->belongsTo(User::class); }
    public function contacts()  { return $this->hasMany(Contact::class, 'google_contacts_account_id'); }
}
