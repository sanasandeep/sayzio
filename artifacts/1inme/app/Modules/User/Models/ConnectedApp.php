<?php

namespace App\Modules\User\Models;

use App\Modules\User\Support\ConnectedApps\ConnectedAppRegistry;
use Illuminate\Database\Eloquent\Model;

/**
 * A single creator ↔ external app connection (CRM or analytics).
 *
 * Token/secret columns use the `encrypted` cast so nothing sensitive is
 * stored in the clear. GA4 connections keep the Measurement ID in
 * `settings['measurement_id']` and the API secret in `access_token`
 * (encrypted) — see ConnectedAppRegistry for the per-provider contract.
 */
class ConnectedApp extends Model
{
    public const STATUS_CONNECTED = 'connected';
    public const STATUS_PAUSED    = 'paused';
    public const STATUS_ERROR     = 'error';

    protected $fillable = [
        'user_id', 'provider', 'kind', 'status',
        'access_token', 'refresh_token', 'token_expires_at',
        'instance_url', 'external_account_id', 'account_label', 'account_email', 'scope',
        'field_mappings', 'settings', 'pull_cursor', 'pull_enabled', 'push_enabled',
        'last_synced_at', 'last_pull_at', 'last_sync_status', 'last_sync_error',
        'records_sent', 'records_pulled', 'paused_at', 'connected_at',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'last_synced_at'   => 'datetime',
            'last_pull_at'     => 'datetime',
            'paused_at'        => 'datetime',
            'connected_at'     => 'datetime',
            'field_mappings'   => 'array',
            'settings'         => 'array',
            'pull_enabled'     => 'boolean',
            'push_enabled'     => 'boolean',
            'records_sent'     => 'integer',
            'records_pulled'   => 'integer',
            'access_token'     => 'encrypted',
            'refresh_token'    => 'encrypted',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Data-driven metadata for this connection's provider. */
    public function meta(): array
    {
        return ConnectedAppRegistry::provider($this->provider) ?? [];
    }

    public function isCrm(): bool
    {
        return $this->kind === 'crm';
    }

    public function isAnalytics(): bool
    {
        return $this->kind === 'analytics';
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_CONNECTED && $this->paused_at === null;
    }

    public function providerLabel(): string
    {
        return $this->meta()['label'] ?? ucfirst($this->provider);
    }

    /** A resolved setting value with default. */
    public function setting(string $key, mixed $default = null): mixed
    {
        $settings = $this->settings ?? [];
        return $settings[$key] ?? $default;
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeCrm($query)
    {
        return $query->where('kind', 'crm');
    }

    public function scopeAnalytics($query)
    {
        return $query->where('kind', 'analytics');
    }

    /**
     * Compact array for API / mobile clients. Never leaks tokens or secrets.
     *
     * @return array<string,mixed>
     */
    public function toPublicArray(): array
    {
        $meta = $this->meta();
        return [
            'id'             => (int) $this->id,
            'provider'       => $this->provider,
            'label'          => $meta['label'] ?? ucfirst($this->provider),
            'kind'           => $this->kind,
            'icon'           => $meta['icon'] ?? null,
            'status'         => $this->status,
            'active'         => $this->isActive(),
            'paused'         => $this->paused_at !== null,
            'account_label'  => $this->account_label,
            'account_email'  => $this->account_email,
            'push_enabled'   => (bool) $this->push_enabled,
            'pull_enabled'   => (bool) $this->pull_enabled,
            'field_mappings' => $this->field_mappings ?? ($meta['default_field_mappings'] ?? []),
            'records_sent'   => (int) $this->records_sent,
            'records_pulled' => (int) $this->records_pulled,
            'last_synced_at' => optional($this->last_synced_at)->toIso8601String(),
            'last_pull_at'   => optional($this->last_pull_at)->toIso8601String(),
            'last_sync_status' => $this->last_sync_status,
            'last_sync_error'  => $this->last_sync_error,
            'measurement_id' => $this->isAnalytics() ? $this->setting('measurement_id') : null,
        ];
    }
}
