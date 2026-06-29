<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

class AiMindSource extends Model
{
    protected $table = 'ai_mind_sources';

    public const TYPE_TEXT      = 'text';
    public const TYPE_DOCUMENT  = 'document';
    public const TYPE_FAQ       = 'faq';
    public const TYPE_LINK      = 'link';
    public const TYPE_FEATURE   = 'feature';
    public const TYPE_WEBHOOK   = 'webhook';
    public const TYPE_CONNECTOR = 'connector';

    public const TYPES = [
        self::TYPE_TEXT, self::TYPE_DOCUMENT, self::TYPE_FAQ,
        self::TYPE_LINK, self::TYPE_FEATURE,
        self::TYPE_WEBHOOK, self::TYPE_CONNECTOR,
    ];

    /** Connector auth methods. */
    public const CONNECTOR_AUTH_NONE   = 'none';
    public const CONNECTOR_AUTH_HEADER = 'header';
    public const CONNECTOR_AUTH_BEARER = 'bearer';

    public const CONNECTOR_AUTH_METHODS = [
        self::CONNECTOR_AUTH_NONE,
        self::CONNECTOR_AUTH_HEADER,
        self::CONNECTOR_AUTH_BEARER,
    ];

    public const STATUS_QUEUED     = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY      = 'ready';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_DISABLED   = 'disabled';

    protected $fillable = [
        'mind_id', 'type', 'title', 'body', 'url', 'feature_key',
        'page_pattern', 'assistant_surface',
        'storage_disk', 'storage_path', 'mime', 'size_bytes',
        'status', 'status_message', 'chunks_count',
        'refresh_minutes', 'last_ingested_at', 'next_refresh_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta'             => 'array',
            'chunks_count'     => 'integer',
            'refresh_minutes'  => 'integer',
            'size_bytes'       => 'integer',
            'last_ingested_at' => 'datetime',
            'next_refresh_at'  => 'datetime',
        ];
    }

    public function mind()   { return $this->belongsTo(AiMind::class, 'mind_id'); }
    public function chunks() { return $this->hasMany(AiMindChunk::class, 'source_id')->orderBy('ord'); }

    /** Whether this source pulls/refreshes from a remote endpoint on a schedule. */
    public function isScheduled(): bool
    {
        return in_array($this->type, [self::TYPE_LINK, self::TYPE_CONNECTOR], true);
    }

    // ---- Webhook source helpers ----------------------------------------

    /** Decrypt the inbound webhook signing token (null if unset/corrupt). */
    public function webhookToken(): ?string
    {
        $enc = $this->meta['webhook']['token'] ?? null;
        if (!is_string($enc) || $enc === '') return null;
        try {
            return Crypt::decryptString($enc);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Store the webhook token encrypted at rest. */
    public function setWebhookToken(string $plain): void
    {
        $meta = $this->meta ?? [];
        $meta['webhook'] = array_merge($meta['webhook'] ?? [], [
            'token' => Crypt::encryptString($plain),
        ]);
        $this->meta = $meta;
    }

    public function webhookLastReceivedAt(): ?Carbon
    {
        $ts = $this->meta['webhook']['last_received_at'] ?? null;
        if (!$ts) return null;
        try { return Carbon::parse($ts); } catch (\Throwable $e) { return null; }
    }

    public function markWebhookReceived(): void
    {
        $meta = $this->meta ?? [];
        $meta['webhook'] = array_merge($meta['webhook'] ?? [], [
            'last_received_at' => now()->toIso8601String(),
        ]);
        $this->meta = $meta;
    }

    // ---- API connector source helpers ----------------------------------

    public function connectorEndpoint(): ?string
    {
        return $this->meta['connector']['endpoint'] ?? null;
    }

    public function connectorAuthMethod(): string
    {
        $m = $this->meta['connector']['auth_method'] ?? self::CONNECTOR_AUTH_NONE;
        return in_array($m, self::CONNECTOR_AUTH_METHODS, true) ? $m : self::CONNECTOR_AUTH_NONE;
    }

    public function connectorHeaderName(): ?string
    {
        return $this->meta['connector']['header_name'] ?? null;
    }

    public function hasConnectorCredential(): bool
    {
        return !empty($this->meta['connector']['credential']);
    }

    /** Decrypt the connector credential (API key / bearer token). */
    public function connectorCredential(): ?string
    {
        $enc = $this->meta['connector']['credential'] ?? null;
        if (!is_string($enc) || $enc === '') return null;
        try {
            return Crypt::decryptString($enc);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Persist connector config. The credential is encrypted at rest;
     * pass null to leave the existing credential untouched.
     */
    public function setConnectorConfig(string $endpoint, string $authMethod, ?string $headerName, ?string $credential): void
    {
        $meta = $this->meta ?? [];
        $connector = $meta['connector'] ?? [];
        $connector['endpoint']    = $endpoint;
        $connector['auth_method'] = in_array($authMethod, self::CONNECTOR_AUTH_METHODS, true)
            ? $authMethod : self::CONNECTOR_AUTH_NONE;
        $connector['header_name'] = $headerName ?: null;
        if ($credential !== null && $credential !== '') {
            $connector['credential'] = Crypt::encryptString($credential);
        } elseif ($connector['auth_method'] === self::CONNECTOR_AUTH_NONE) {
            unset($connector['credential']);
        }
        $meta['connector'] = $connector;
        $this->meta = $meta;
    }
}
