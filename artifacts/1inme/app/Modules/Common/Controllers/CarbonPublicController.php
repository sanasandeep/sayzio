<?php

namespace App\Modules\Common\Controllers;

use App\Modules\Common\Services\Carbon\CarbonSettingsResolver;
use App\Modules\Common\Services\Carbon\Providers\CloverlyOffsetProvider;
use App\Modules\User\Models\BiolinkCarbonSnapshot;
use App\Modules\User\Models\CarbonOffsetPurchase;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * Public-facing carbon endpoints:
 *
 *  - /community/{link}/carbon: returns the latest snapshot + offset
 *    info as JSON for the badge popover. Always-on (badge JS hits
 *    this on hover/open) so it's deliberately cacheable & cheap.
 *  - /webhooks/carbon/{provider}: receives provider-side purchase
 *    confirmations and writes back certificate_url + project_name.
 *    Signature verification lives in each adapter; we never trust
 *    the payload to identify the workspace by itself.
 */
class CarbonPublicController extends Controller
{
    public function __construct(private CarbonSettingsResolver $settings) {}

    public function badge(Request $request, Link $link)
    {
        if (!$this->settings->badgeVisibleForLink($link)) {
            return response()->json(['ok' => false], 404);
        }

        $snap = BiolinkCarbonSnapshot::query()->withoutGlobalScope('workspace')
            ->where('link_id', $link->id)
            ->whereIn('offset_status', ['purchased', 'sandbox'])
            ->orderByDesc('period_start')
            ->first();

        // No actually-offset snapshot yet → no badge claim. The
        // public page must NEVER advertise carbon-neutral status for
        // a month whose offset purchase is pending, capped, or failed.
        if (!$snap) {
            return response()->json(['ok' => false, 'reason' => 'no_offset'], 404);
        }

        $purchase = $snap->offset_purchase_id
            ? CarbonOffsetPurchase::query()->withoutGlobalScope('workspace')->find($snap->offset_purchase_id)
            : null;

        return response()->json([
            'ok'            => true,
            'period'        => $snap?->period_start?->format('Y-m'),
            'grams_co2'     => $snap?->grams_co2,
            'grams_offset'  => $snap?->grams_offset,
            'page_views'    => $snap?->page_views,
            'project_name'  => $purchase?->project_name,
            'certificate'   => $purchase?->certificate_url,
            'provider'      => $purchase?->provider,
            'methodology_url' => route('public.carbon.methodology'),
            'model_version' => $snap?->model_version,
            'sandbox'       => $purchase?->status === 'sandbox',
        ]);
    }

    public function webhook(Request $request, string $provider)
    {
        $adapter = match ($provider) {
            'cloverly' => new CloverlyOffsetProvider(),
            default    => null,
        };
        abort_unless($adapter, 404);

        if (!$adapter->verifyWebhook($request)) {
            Log::warning('carbon_webhook_unverified', ['provider' => $provider]);
            return response()->json(['ok' => false], 401);
        }

        $event = $adapter->parseWebhook($request);
        if (empty($event['provider_ref'])) {
            return response()->json(['ok' => true, 'note' => 'no ref']);
        }

        $purchase = CarbonOffsetPurchase::query()->withoutGlobalScope('workspace')
            ->where('provider', $provider)
            ->where('provider_ref', $event['provider_ref'])
            ->first();
        if (!$purchase) return response()->json(['ok' => true, 'note' => 'unknown ref']);

        if ($event['certificate_url']) $purchase->certificate_url = $event['certificate_url'];
        if ($event['project_name'])    $purchase->project_name    = $event['project_name'];
        if ($event['status'] === 'succeeded' && $purchase->status !== 'succeeded') {
            $purchase->status = 'succeeded';
        }
        if ($event['status'] === 'failed') {
            // Provider walked back the purchase after the fact (e.g.
            // payment chargeback). Zero out the offset and mark the
            // owning snapshot failed so the badge stops claiming
            // carbon neutrality on next request.
            $purchase->status       = 'failed';
            $purchase->grams_offset = 0;
            $snap = BiolinkCarbonSnapshot::query()->withoutGlobalScope('workspace')
                ->where('offset_purchase_id', $purchase->id)->first();
            if ($snap) {
                $snap->offset_status = 'failed';
                $snap->grams_offset  = 0;
                $snap->save();
            }
        }
        $purchase->save();

        return response()->json(['ok' => true]);
    }

    public function methodology()
    {
        return view('common.carbon.methodology');
    }
}
