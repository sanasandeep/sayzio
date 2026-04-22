<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class AiMindSource extends Model
{
    protected $table = 'ai_mind_sources';

    public const TYPE_TEXT     = 'text';
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_FAQ      = 'faq';
    public const TYPE_LINK     = 'link';
    public const TYPE_FEATURE  = 'feature';

    public const TYPES = [
        self::TYPE_TEXT, self::TYPE_DOCUMENT, self::TYPE_FAQ,
        self::TYPE_LINK, self::TYPE_FEATURE,
    ];

    public const STATUS_QUEUED     = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY      = 'ready';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_DISABLED   = 'disabled';

    protected $fillable = [
        'mind_id', 'type', 'title', 'body', 'url', 'feature_key',
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
}
