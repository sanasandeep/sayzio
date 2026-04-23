<?php

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per click on the site-assistant low-balance hint CTA
 * (Top up / See plans). Powers the click-through counter on the
 * Site Assistant analytics page so admins can tell whether the
 * hint actually moves visitors toward a top-up / pricing page.
 */
class SiteAssistantLowBalanceClick extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'surface',
        'target_url',
        'ip_address',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];
}
