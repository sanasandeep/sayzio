<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\SocialProof;
use App\Modules\User\Models\SocialProofEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Public endpoints for the SocialProof embed widget.
 * No auth — these are intentionally open and CORS-permissive so any external
 * website can embed the widget script.
 */
class SocialProofPublicController extends Controller
{
    public function loaderJs(Request $request, string $uuid)
    {
        $proof = SocialProof::where('uuid', $uuid)->first();
        if (!$proof || !$proof->is_active) {
            $js = "/* 1inme social-proof: widget disabled or not found */\n";
            return response($js, 200, [
                'Content-Type'                => 'application/javascript; charset=utf-8',
                'Access-Control-Allow-Origin' => '*',
                'Cache-Control'               => 'public, max-age=60',
            ]);
        }

        $configUrl  = url('/sp/' . $uuid . '.json');
        $trackUrl   = url('/sp/' . $uuid . '/track');
        $runtimeUrl = url('/js/social-proof-widget.js');

        $js = <<<JS
            (function(){
              if (window.__1inmeSP && window.__1inmeSP.loaded) {
                window.__1inmeSP.boot && window.__1inmeSP.boot({uuid:"$uuid",configUrl:"$configUrl",trackUrl:"$trackUrl"});
                return;
              }
              window.__1inmeSP = window.__1inmeSP || { queue: [] };
              window.__1inmeSP.queue.push({uuid:"$uuid",configUrl:"$configUrl",trackUrl:"$trackUrl"});
              if (window.__1inmeSP.loading) return;
              window.__1inmeSP.loading = true;
              var s = document.createElement('script');
              s.src = "$runtimeUrl";
              s.async = true;
              document.head.appendChild(s);
            })();
            JS;

        return response($js, 200, [
            'Content-Type'                => 'application/javascript; charset=utf-8',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control'               => 'public, max-age=300',
        ]);
    }

    public function config(Request $request, string $uuid)
    {
        $proof = SocialProof::where('uuid', $uuid)->where('is_active', true)->first();
        if (!$proof) {
            return response()->json(['error' => 'not_found'], 404, $this->corsHeaders());
        }

        $notifications = is_array($proof->notifications) ? $proof->notifications : [];
        // Defensive normalization (in case of older un-normalized JSON)
        $notifications = array_map([SocialProof::class, 'normalizeNotification'], $notifications);
        // Filter inactive notifications + sort
        $notifications = array_values(array_filter($notifications, fn($n) => !empty($n['is_active'])));
        usort($notifications, fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

        // Per-biolink visitor-count gating (task #1180): when the campaign
        // owner's primary biolink has `biolink.privacy.hide_public_visitor_counts`
        // enabled (or unset — the privacy-first default), strip live-visitor
        // signals from the public config payload AND drop visitor_count
        // notifications from the widget so externally-embedded copies of
        // the widget still honour the toggle. Mirrors the data_get path
        // used in resources/views/common/blocks/social-proof.blade.php.
        $hideLive = $this->ownerHidesPublicVisitorCounts((int) $proof->user_id);
        if ($hideLive) {
            // Mirror the directory-side gating: strip every notification
            // type that surfaces a live visitor / click / conversion count.
            $liveCounterTypes = ['visitor_count', 'conversion_count'];
            $notifications = array_values(array_filter(
                $notifications,
                fn($n) => !in_array($n['type'] ?? '', $liveCounterTypes, true)
            ));
        }

        $payload = [
            'uuid'          => $proof->uuid,
            'name'          => $proof->name,
            'design'        => $proof->design   ?? SocialProof::defaultDesign(),
            'targeting'     => $proof->targeting?? SocialProof::defaultTargeting(),
            'notifications' => $notifications,
            'live_visitors' => $hideLive ? 0 : $this->liveVisitorCountFor($notifications),
        ];

        return response()->json($payload, 200, $this->corsHeaders());
    }

    /**
     * Returns true when the owner's primary (most-clicked active) biolink
     * has `biolink.privacy.hide_public_visitor_counts` enabled. Treats an
     * unset flag as "hidden" to match the privacy-first default already
     * used in social-proof.blade.php. Also returns true when the owner
     * has no active biolink (defensive).
     */
    private function ownerHidesPublicVisitorCounts(int $userId): bool
    {
        $bio = Link::where('user_id', $userId)
            ->where('type', 'biolink')
            ->where('is_active', true)
            ->orderByDesc('total_clicks')
            ->first(['settings']);
        if (!$bio) return true;
        $explicit = data_get($bio->settings, 'biolink.privacy.hide_public_visitor_counts', null);
        return $explicit === null ? true : (bool) $explicit;
    }

    public function track(Request $request, string $uuid)
    {
        $proof = SocialProof::where('uuid', $uuid)->where('is_active', true)->first();
        if (!$proof) {
            return response()->json(['ok' => false], 404, $this->corsHeaders());
        }

        $kind = $request->input('kind');
        if (!in_array($kind, ['impression', 'click', 'conversion'], true)) {
            return response()->json(['ok' => false, 'error' => 'bad_kind'], 422, $this->corsHeaders());
        }

        SocialProofEvent::create([
            'social_proof_id' => $proof->id,
            'notification_id' => substr((string)$request->input('notification_id', ''), 0, 64) ?: null,
            'kind'            => $kind,
            'page_url'        => substr((string)$request->input('page_url', ''), 0, 1000),
            'ip'              => $request->ip(),
            'user_agent'      => substr((string)$request->userAgent(), 0, 500),
            'created_at'      => now(),
        ]);

        $col = match ($kind) { 'impression' => 'impressions', 'click' => 'clicks', 'conversion' => 'conversions' };
        DB::table('social_proofs')->where('id', $proof->id)->increment($col);

        return response()->json(['ok' => true], 200, $this->corsHeaders());
    }

    public function preflight(Request $request, string $uuid)
    {
        return response('', 204, $this->corsHeaders());
    }

    private function corsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With',
            'Access-Control-Max-Age'       => '600',
        ];
    }

    /**
     * For visitor_count notifications: deterministic per-30s plausible number.
     * If the campaign has any visitor_count notifications, derive the number
     * from the first one's min/max settings.
     */
    private function liveVisitorCountFor(array $notifications): int
    {
        $vc = null;
        foreach ($notifications as $n) {
            if (($n['type'] ?? '') === 'visitor_count') { $vc = $n; break; }
        }
        if (!$vc) return 0;
        $s = $vc['settings'] ?? [];
        $min = max(0, (int)($s['min'] ?? 5));
        $max = max($min, (int)($s['max'] ?? $min + 10));
        if ($max === $min) return $min;
        $bucket = (int) floor(time() / 30);
        return $min + ($bucket % ($max - $min + 1));
    }
}
