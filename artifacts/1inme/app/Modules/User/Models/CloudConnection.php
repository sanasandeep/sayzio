<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class CloudConnection extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'user_id', 'provider',
        'account_label', 'account_email',
        'access_token_encrypted', 'refresh_token_encrypted',
        'expires_at', 'scopes', 'last_error', 'last_synced_at',
        'last_checked_at', 'last_error_at',
        'last_broken_email_sent_at', 'banner_dismissed_at',
    ];

    protected $casts = [
        'access_token_encrypted'    => 'encrypted',
        'refresh_token_encrypted'   => 'encrypted',
        'expires_at'                => 'datetime',
        'last_synced_at'            => 'datetime',
        'last_checked_at'           => 'datetime',
        'last_error_at'             => 'datetime',
        'last_broken_email_sent_at' => 'datetime',
        'banner_dismissed_at'       => 'datetime',
        'scopes'                    => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function providerLabel(): string
    {
        return CloudProviderApp::PROVIDER_LABELS[$this->provider] ?? $this->provider;
    }

    public function expiresSoon(): bool
    {
        if (!$this->expires_at) return false;
        return $this->expires_at->lte(now()->addMinutes(5));
    }

    public function isBroken(): bool
    {
        return filled($this->last_error);
    }

    /**
     * Should the in-app sidebar banner show this connection? True when the
     * connection is currently broken AND the user has either never dismissed
     * the warning, or the warning was dismissed BEFORE the most recent
     * breakage (so a recover-then-break-again cycle re-arms the banner).
     */
    public function shouldShowBanner(): bool
    {
        if (!$this->isBroken()) return false;
        if (!$this->banner_dismissed_at) return true;
        if (!$this->last_error_at) return true;
        return $this->last_error_at->greaterThan($this->banner_dismissed_at);
    }
}
