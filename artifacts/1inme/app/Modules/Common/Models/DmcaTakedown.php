<?php

namespace App\Modules\Common\Models;

use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Public DMCA / IP takedown intake (Task #1211). The form lives at
 * /legal/dmca and is intentionally dead simple: name, email, the
 * URL of the original work, and the URL on Sayzio alleged to
 * infringe, with the two statutory acknowledgements.
 *
 * Admins triage these from the new ModerationQueue, where every
 * `valid` submission ends with a one-click "Hide post" / "Suspend
 * creator" action that flips the relevant moderation flag.
 */
class DmcaTakedown extends Model
{
    protected $fillable = [
        'reporter_user_id', 'reporter_name', 'reporter_email', 'reporter_address',
        'rights_holder', 'original_work_url', 'infringing_url',
        'target_user_id', 'target_post_id',
        'good_faith_acknowledged', 'penalty_of_perjury_acknowledged', 'signature',
        'status', 'admin_note', 'actioned_at', 'actioned_by_user_id',
        'reporter_ip',
    ];

    protected $casts = [
        'good_faith_acknowledged'         => 'boolean',
        'penalty_of_perjury_acknowledged' => 'boolean',
        'actioned_at'                     => 'datetime',
    ];

    public const STATUSES = [
        'pending' => 'Pending review',
        'valid'   => 'Valid — content removed',
        'invalid' => 'Invalid / rejected',
        'removed' => 'Content removed',
        'counter' => 'Counter-notice received',
    ];

    public function reporter()   { return $this->belongsTo(User::class, 'reporter_user_id'); }
    public function actioner()   { return $this->belongsTo(User::class, 'actioned_by_user_id'); }
    public function targetUser() { return $this->belongsTo(User::class, 'target_user_id'); }
    public function targetPost() { return $this->belongsTo(CreatorPost::class, 'target_post_id'); }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
