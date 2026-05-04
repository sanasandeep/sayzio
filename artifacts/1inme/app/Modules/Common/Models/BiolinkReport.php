<?php

namespace App\Modules\Common\Models;

use App\Modules\User\Models\Link;
use Illuminate\Database\Eloquent\Model;

class BiolinkReport extends Model
{
    protected $fillable = [
        'link_id', 'reason', 'comment', 'reporter_ip', 'user_agent',
        'status', 'coalesced_count', 'actioned_at', 'admin_note',
    ];

    protected $casts = [
        'actioned_at' => 'datetime',
        'coalesced_count' => 'integer',
    ];

    public const REASONS = [
        'spam'    => 'Spam or misleading',
        'scam'    => 'Scam or fraud',
        'hateful' => 'Hateful or harassing',
        'ip'      => 'IP / copyright infringement',
        'other'   => 'Something else',
    ];

    public const COALESCE_WINDOW_HOURS = 24;

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst((string) $this->reason);
    }
}
