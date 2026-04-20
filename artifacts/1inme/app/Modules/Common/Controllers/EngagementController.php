<?php

namespace App\Modules\Common\Controllers;

use App\Modules\Common\Services\GeoIpService;
use App\Modules\User\Models\BlockView;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\PageSession;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class EngagementController extends Controller
{
    public function startSession(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link) abort(404);
        $sessionId = (string) Str::uuid();

        $ua = $request->userAgent();
        $geo = app(GeoIpService::class)->detectGeo($request->ip());

        PageSession::create([
            'link_id' => $link->id,
            'session_id' => $sessionId,
            'ip_address' => $request->ip(),
            'country_code' => $geo['country_code'] ?? null,
            'city' => $geo['city'] ?? null,
            'latitude' => $geo['latitude'] ?? null,
            'longitude' => $geo['longitude'] ?? null,
            'browser' => $this->detectBrowser($ua),
            'os' => $this->detectOS($ua),
            'device_type' => $this->detectDeviceType($ua),
            'referrer' => $request->header('referer'),
            'language' => substr((string) $request->header('Accept-Language'), 0, 2) ?: null,
            'started_at' => now(),
            'last_seen_at' => now(),
            'duration_seconds' => 0,
        ]);

        return response()->json(['session_id' => $sessionId]);
    }

    public function heartbeat(Request $request, string $alias)
    {
        $data = $request->validate([
            'session_id' => 'required|string|size:36',
            'duration_seconds' => 'required|integer|min:0|max:86400',
            'ended' => 'nullable|boolean',
            'block_views' => 'nullable|array',
            'block_views.*.block_id' => 'required|integer',
            'block_views.*.block_type' => 'nullable|string|max:60',
            'block_views.*.view_duration_ms' => 'required|integer|min:0',
            'block_views.*.impression_count' => 'nullable|integer|min:0',
        ]);

        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link) abort(404);

        $session = PageSession::where('session_id', $data['session_id'])
            ->where('link_id', $link->id)->first();
        if (!$session) {
            return response()->json(['ok' => false], 404);
        }

        $session->update([
            'last_seen_at' => now(),
            'duration_seconds' => max($session->duration_seconds, (int) $data['duration_seconds']),
            'ended' => (bool) ($data['ended'] ?? false),
        ]);

        if (!empty($data['block_views'])) {
            foreach ($data['block_views'] as $bv) {
                $existing = BlockView::where('session_id', $data['session_id'])
                    ->where('block_id', $bv['block_id'])->first();
                $addMs = max(0, (int) $bv['view_duration_ms']);
                $addImpr = max(0, (int) ($bv['impression_count'] ?? 0));
                if ($existing) {
                    $existing->update([
                        'view_duration_ms' => $existing->view_duration_ms + $addMs,
                        'impression_count' => $existing->impression_count + $addImpr,
                        'last_viewed_at' => now(),
                    ]);
                } else {
                    BlockView::create([
                        'link_id' => $link->id,
                        'block_id' => $bv['block_id'],
                        'block_type' => $bv['block_type'] ?? null,
                        'session_id' => $data['session_id'],
                        'view_duration_ms' => $addMs,
                        'impression_count' => $addImpr > 0 ? $addImpr : 1,
                        'first_viewed_at' => now(),
                        'last_viewed_at' => now(),
                    ]);
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    private function detectBrowser(?string $ua): ?string
    {
        if (!$ua) return null;
        foreach (['Edge'=>'/Edg\//i','Chrome'=>'/Chrome\//i','Firefox'=>'/Firefox\//i','Safari'=>'/Safari\//i','Opera'=>'/OPR\//i'] as $n=>$p) if (preg_match($p, $ua)) return $n;
        return 'Other';
    }
    private function detectOS(?string $ua): ?string
    {
        if (!$ua) return null;
        foreach (['Windows'=>'/Windows/i','iOS'=>'/iPhone|iPad/i','Android'=>'/Android/i','macOS'=>'/Macintosh/i','Linux'=>'/Linux/i'] as $n=>$p) if (preg_match($p, $ua)) return $n;
        return 'Other';
    }
    private function detectDeviceType(?string $ua): ?string
    {
        if (!$ua) return 'desktop';
        if (preg_match('/Mobile|Android.*Mobile|iPhone/i', $ua)) return 'mobile';
        if (preg_match('/iPad|Tablet/i', $ua)) return 'tablet';
        return 'desktop';
    }
}
