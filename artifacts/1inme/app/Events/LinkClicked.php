<?php

namespace App\Events;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkClick;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired exactly once per real-human click on a tracked link.
 *
 * Bot/scraper hits are intentionally NOT broadcast: outbound webhooks,
 * "new visitor" notifications, push alerts, and any other listener
 * subscribed to this event will only ever see traffic from real
 * humans, matching the rest of the creator-facing analytics.
 *
 * @see \App\Modules\Common\Services\LinkTrackingService::track()
 */
class LinkClicked
{
    use Dispatchable;

    public function __construct(
        public Link $link,
        public LinkClick $click,
    ) {}
}
