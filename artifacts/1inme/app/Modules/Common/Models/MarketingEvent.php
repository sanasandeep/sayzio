<?php

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lightweight server-side record of a click on a marketing-page CTA
 * (e.g. the landing-page pricing teaser drill-downs to /pricing
 * and the in-page "Coin packages" tab on
 * /pricing?view=coins). The GA4 + Meta Pixel snippets in
 * `public.partials.marketing-tracking` already mirror these events
 * to the external pipelines; this table powers the in-app admin
 * report under Admin → Marketing Events.
 */
class MarketingEvent extends Model
{
    protected $fillable = [
        'source',
        'target',
        'ip_address',
        'referrer',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];
}
