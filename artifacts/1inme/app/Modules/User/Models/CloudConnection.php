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
    ];

    protected $casts = [
        'access_token_encrypted'  => 'encrypted',
        'refresh_token_encrypted' => 'encrypted',
        'expires_at'              => 'datetime',
        'last_synced_at'          => 'datetime',
        'scopes'                  => 'array',
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
}
