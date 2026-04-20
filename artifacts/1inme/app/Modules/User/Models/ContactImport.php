<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class ContactImport extends Model
{
    protected $fillable = [
        'user_id', 'original_filename', 'status',
        'total_rows', 'processed_rows', 'created_count', 'skipped_cap_count',
        'failed', 'rows', 'error', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'failed'        => 'array',
        'rows'          => 'array',
        'started_at'    => 'datetime',
        'completed_at'  => 'datetime',
    ];

    public function isInProgress(): bool
    {
        return in_array($this->status, ['pending', 'processing'], true);
    }

    public function progressPercent(): int
    {
        if ($this->total_rows <= 0) return 0;
        return (int) min(100, floor(($this->processed_rows / $this->total_rows) * 100));
    }
}
