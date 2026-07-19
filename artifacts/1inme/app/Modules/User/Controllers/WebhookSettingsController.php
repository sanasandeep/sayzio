<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\InboxForwardDelivery;
use App\Modules\User\Models\InboxForwardDestination;
use App\Modules\User\Services\InboxAggregator;
use App\Modules\User\Services\InboxForwarder;
use Illuminate\Http\Request;

/**
 * Webhook Triggers management — Settings → Developer / API → Webhooks.
 *
 * A focused view of the InboxForwardDestination/InboxForwardDelivery
 * infrastructure that surfaces the link-event sources (link_created,
 * link_expired, click_milestone) alongside the existing inbox sources.
 *
 * Routes reuse store/update/toggle/test/destroy/retry from the underlying
 * forwarding controller; this controller only adds the settings-hub view.
 *
 * Plan-gated: requires the `webhook_triggers` plan feature. Users without
 * it see an upgrade prompt instead of the management surface.
 */
class WebhookSettingsController
{
    public function index(Request $request)
    {
        $owner  = workspace_owner();
        $userId = workspace_owner_id();

        $hasFeature = (bool) $owner->getPlanFeature('webhook_triggers', false);

        $destinations = InboxForwardDestination::where('user_id', $userId)
            ->orderBy('created_at')->get();
        $deliveries = InboxForwardDelivery::where('user_id', $userId)
            ->with('destination:id,label,type')
            ->latest()->limit(50)->get();

        $sourceLabels    = InboxAggregator::sourceLabels();
        $linkEventLabels = InboxAggregator::linkEventLabels();

        return view('user.settings.webhooks', compact(
            'hasFeature', 'destinations', 'deliveries', 'sourceLabels', 'linkEventLabels'
        ));
    }
}
