<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleContactsAccount extends Model
{
    protected $fillable = [
        'user_id', 'account_email', 'external_account_id',
        'access_token', 'refresh_token', 'token_expires_at', 'scope',
        'sync_token', 'last_synced_at', 'last_sync_status', 'last_sync_error',
        'needs_reauth_at', 'reauth_reminder_sent_at', 'pull_enabled', 'push_enabled', 'settings',
    ];

    /** last_sync_status value used when the Google connection must be re-authorised. */
    public const STATUS_NEEDS_REAUTH = 'needs_reauth';

    protected function casts(): array
    {
        return [
            'settings'          => 'array',
            'token_expires_at'  => 'datetime',
            'needs_reauth_at'   => 'datetime',
            'reauth_reminder_sent_at' => 'datetime',
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
        // Only the FIRST stamp of needs_reauth_at counts as a new expiry —
        // that keys the once-per-expiry user notification below so retrying
        // sync jobs never re-notify. Reconnecting nulls the column, arming
        // the notice again for a future expiry.
        $firstTransition = $this->needs_reauth_at === null;

        $this->forceFill([
            'needs_reauth_at'  => $this->needs_reauth_at ?? now(),
            'last_sync_status' => self::STATUS_NEEDS_REAUTH,
            'last_sync_error'  => $reason ? \Illuminate\Support\Str::limit($reason, 500) : $this->last_sync_error,
        ])->save();

        if ($firstTransition) {
            // Best-effort: alert delivery must never break the sync path
            // that detected the revocation.
            try {
                app(\App\Modules\User\Services\Contacts\GoogleContactsReauthNotifier::class)->send($this);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    'Google Contacts reauth notification failed: ' . $e->getMessage(),
                    ['account_id' => $this->id, 'user_id' => $this->user_id],
                );
            }
        }
    }

    public function user()      { return $this->belongsTo(User::class); }
    public function contacts()  { return $this->hasMany(Contact::class, 'google_contacts_account_id'); }
}
