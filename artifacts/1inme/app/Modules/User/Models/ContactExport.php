<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class ContactExport extends Model
{
    protected $fillable = [
        'user_id', 'format', 'scope', 'status',
        'contact_count', 'file_path', 'error',
        'expires_at', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'scope'        => 'array',
        'expires_at'   => 'datetime',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function isInProgress(): bool
    {
        return in_array($this->status, ['pending', 'processing'], true);
    }

    public function isReady(): bool
    {
        return $this->status === 'completed' && $this->file_path !== null;
    }

    public function formatLabel(): string
    {
        return $this->format === 'vcf' ? 'vCard (.vcf)' : 'CSV (.csv)';
    }

    public function downloadFilename(): string
    {
        $date = now()->format('Y-m-d');
        return "contacts-{$date}.{$this->format}";
    }

    public function mimeType(): string
    {
        return $this->format === 'vcf' ? 'text/vcard; charset=utf-8' : 'text/csv; charset=utf-8';
    }
}
