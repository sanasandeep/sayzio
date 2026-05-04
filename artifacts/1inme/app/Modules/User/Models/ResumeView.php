<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-visitor audit row for /{handle}/resume.
 *
 * One row per unique-IP-per-day visitor (same dedup as view_count) so
 * the resume owner can see an audit log without us inflating it from
 * refreshes. Owner views and bot crawls are intentionally not logged.
 */
class ResumeView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'resume_id', 'viewer_user_id', 'viewer_handle',
        'country_code', 'referrer', 'user_agent', 'ip_hash', 'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

    public function resume()
    {
        return $this->belongsTo(Resume::class);
    }
}
