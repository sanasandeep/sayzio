<?php

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per dispatched site-assistant cut-off alert. Powers the
 * "Recent alerts" panel on the Site Assistant analytics page so
 * admins can see a trail of when the alert system fired and the
 * abandon-rate / sample size that triggered each alert.
 */
class SiteAssistantCutoffAlert extends Model
{
    protected $fillable = [
        'dispatched_at',
        'abandon_rate',
        'threshold',
        'total',
        'retried',
        'window_hours',
        'in_app_delivered',
        'emails_sent',
    ];

    protected $casts = [
        'dispatched_at'    => 'datetime',
        'abandon_rate'     => 'integer',
        'threshold'        => 'integer',
        'total'            => 'integer',
        'retried'          => 'integer',
        'window_hours'     => 'integer',
        'in_app_delivered' => 'integer',
        'emails_sent'      => 'integer',
    ];
}
