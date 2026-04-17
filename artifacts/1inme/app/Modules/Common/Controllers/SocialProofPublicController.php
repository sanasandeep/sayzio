<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
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
    /**
     * Returns a small JS bootstrapper that loads the widget runtime then
     * configures it with the proof's UUID. Cached briefly at the edge.
     */
    public function loaderJs(Request $request, string $uuid)
    {
        // Verify the proof exists & is active before serving the loader so a
        // disabled/deleted proof returns a no-op script instead of a 404.
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

    /**
     * JSON config consumed by the runtime to render the widget.
     */
    public function config(Request $request, string $uuid)
    {
        $proof = SocialProof::with('items')
            ->where('uuid', $uuid)
            ->where('is_active', true)
            ->first();

        if (!$proof) {
            return response()->json(['error' => 'not_found'], 404, $this->corsHeaders());
        }

        $payload = [
            'uuid'      => $proof->uuid,
            'type'      => $proof->type,
            'name'      => $proof->name,
            'settings'  => $proof->settings ?? [],
            'design'    => $proof->design   ?? SocialProof::defaultDesign(),
            'targeting' => $proof->targeting?? SocialProof::defaultTargeting(),
            'items'     => $proof->items->map(fn($i) => [
                'name'       => $i->name,
                'location'   => $i->location,
                'action'     => $i->action,
                'image_url'  => $i->image_url,
                'link_url'   => $i->link_url,
                'time_label' => $i->time_label,
            ])->values(),
            'live_visitors' => $this->liveVisitorCount($proof),
        ];

        return response()->json($payload, 200, $this->corsHeaders());
    }

    /**
     * Track impression / click / conversion. Throttled at the route level.
     */
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
            'kind'            => $kind,
            'page_url'        => substr((string)$request->input('page_url', ''), 0, 1000),
            'ip'              => $request->ip(),
            'user_agent'      => substr((string)$request->userAgent(), 0, 500),
            'created_at'      => now(),
        ]);

        // Maintain the denormalized counters for fast dashboard reads.
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
     * For "visitor_count" type. If settings.mode == 'simulated' we don't query
     * anything — the runtime computes the number deterministically.
     * In simulated mode we return a plausible varying number derived from a
     * 30-second time bucket so the front-end can blend it.
     */
    private function liveVisitorCount(SocialProof $proof): int
    {
        $s = $proof->settings ?? [];
        $min = max(0, (int)($s['min'] ?? 5));
        $max = max($min, (int)($s['max'] ?? $min + 10));
        if ($max === $min) return $min;
        // Deterministic per 30s window so the number visibly varies but stably
        $bucket = (int) floor(time() / 30);
        return $min + ($bucket % ($max - $min + 1));
    }
}
