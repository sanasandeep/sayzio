<?php

namespace App\Modules\Common\Models;

use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Centralised moderation queue row for "report this creator /
 * comment / message / post" submissions (Task #1211). Mirrors the
 * existing BiolinkReport queue pattern (status flow, coalesce by
 * (target,reporter_ip) within a 24h window) so the admin UI can
 * surface both queues with the same interaction model.
 */
class UserReport extends Model
{
    protected $fillable = [
        'target_type', 'target_id',
        'reporter_user_id', 'reporter_ip',
        'reason', 'comment',
        'status', 'coalesced_count',
        'admin_note', 'actioned_at', 'actioned_by_user_id',
    ];

    protected $casts = [
        'actioned_at'       => 'datetime',
        'coalesced_count'   => 'integer',
    ];

    public const TARGET_TYPES = [
        'user'    => 'Creator profile',
        'comment' => 'Comment',
        'message' => 'Direct message',
        'post'    => 'Post',
    ];

    public const REASONS = [
        'spam'        => 'Spam',
        'harassment'  => 'Harassment / hate',
        'impersonation' => 'Impersonation',
        'self_harm'   => 'Self-harm',
        'minor'       => 'Involves a minor',
        'nudity'      => 'Unmarked nudity',
        'illegal'     => 'Illegal activity',
        'ip'          => 'Copyright / IP',
        'other'       => 'Something else',
    ];

    public const STATUSES = [
        'pending'    => 'Pending',
        'dismissed'  => 'Dismissed',
        'warned'     => 'Warned',
        'removed'    => 'Content removed',
        'suspended'  => 'Account suspended',
        'escalated'  => 'Escalated',
    ];

    public const COALESCE_WINDOW_HOURS = 24;

    public function reporter() { return $this->belongsTo(User::class, 'reporter_user_id'); }
    public function actioner() { return $this->belongsTo(User::class, 'actioned_by_user_id'); }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst((string) $this->reason);
    }

    public function targetTypeLabel(): string
    {
        return self::TARGET_TYPES[$this->target_type] ?? ucfirst((string) $this->target_type);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
