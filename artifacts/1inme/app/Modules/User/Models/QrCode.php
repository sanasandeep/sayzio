<?php

namespace App\Modules\User\Models;

use App\Modules\User\Support\QrCodeTypeRegistry;
use Illuminate\Database\Eloquent\Model;

class QrCode extends Model
{
    protected $table = 'qr_codes';

    protected $fillable = [
        'project_id', 'link_id', 'name', 'type',
        'payload', 'design', 'preview_url',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'design'  => 'array',
        ];
    }

    public function user()    { return $this->belongsTo(User::class); }
    public function project() { return $this->belongsTo(Project::class); }
    public function link()    { return $this->belongsTo(Link::class); }

    /**
     * Authoritative QR contents — link short URL when attached, otherwise the
     * registry-encoded payload string (single source of truth for both preview
     * and library thumbnails).
     */
    public function getEncodedPayloadAttribute(): string
    {
        if ($this->link_id && $this->relationLoaded('link') && $this->link) {
            return $this->link->getShortUrl();
        }
        if ($this->link_id && $this->link) {
            return $this->link->getShortUrl();
        }
        try {
            return QrCodeTypeRegistry::buildPayloadString($this->type, (array) ($this->payload ?? []));
        } catch (\Throwable $e) {
            return '';
        }
    }
}
