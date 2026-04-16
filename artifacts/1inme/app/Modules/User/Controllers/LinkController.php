<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class LinkController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->links()->with(['project', 'domain']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('alias', 'ilike', "%{$search}%")
                  ->orWhere('long_url', 'ilike', "%{$search}%");
            });
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($projectId = $request->get('project_id')) {
            $query->where('project_id', $projectId);
        }

        if ($request->get('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->get('status') === 'inactive') {
            $query->where('is_active', false);
        }

        $links = $query->latest()->paginate(15)->withQueryString();
        $projects = $request->user()->projects()->orderBy('name')->get();

        return view('user.links.index', compact('links', 'projects'));
    }

    public function create(Request $request)
    {
        $projects = $request->user()->projects()->orderBy('name')->get();
        $pixels = $request->user()->pixels()->orderBy('name')->get();
        $domains = $request->user()->domains()->where('is_verified', true)->get();

        return view('user.links.create', compact('projects', 'pixels', 'domains'));
    }

    public function store(Request $request)
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'type' => 'required|in:url,biolink,file,ics,vcf',
            'long_url' => 'required_if:type,url|nullable|url|max:2048',
            'redirect_type' => 'nullable|in:301,302',
            'alias' => 'nullable|string|max:50|unique:links,alias|alpha_dash',
            'title' => 'nullable|string|max:255',
            'project_id' => "nullable|exists:projects,id,user_id,{$userId}",
            'domain_id' => "nullable|exists:domains,id,user_id,{$userId}",
            'is_password_protected' => 'boolean',
            'password' => 'nullable|string|min:3|max:100',
            'expires_at' => 'nullable|date|after:now',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'utm_source' => 'nullable|string|max:255',
            'utm_medium' => 'nullable|string|max:255',
            'utm_campaign' => 'nullable|string|max:255',
            'utm_term' => 'nullable|string|max:255',
            'utm_content' => 'nullable|string|max:255',
            'pixel_ids' => 'nullable|array',
            'pixel_ids.*' => "exists:pixels,id,user_id,{$userId}",
            'seo_image' => 'nullable|image|max:2048',
            'favicon' => 'nullable|image|max:1024',
            'country_restrictions' => 'nullable|string|max:500',
            'device_targeting' => 'nullable|array',
            'device_targeting.*' => 'in:desktop,mobile,tablet',
        ]);

        if (empty($validated['alias'])) {
            $validated['alias'] = Link::generateAlias();
        }

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
            $validated['is_password_protected'] = true;
        }

        if ($request->hasFile('seo_image')) {
            $disk = config('filesystems.default') === 's3' ? 's3' : 'public';
            $validated['seo_image'] = $request->file('seo_image')->store('seo-images', $disk);
            if ($disk === 'public') {
                $validated['seo_image'] = Storage::disk('public')->url($validated['seo_image']);
            } else {
                $validated['seo_image'] = Storage::disk('s3')->url($validated['seo_image']);
            }
        }
        if ($request->hasFile('favicon')) {
            $disk = config('filesystems.default') === 's3' ? 's3' : 'public';
            $validated['favicon'] = $request->file('favicon')->store('favicons', $disk);
            if ($disk === 'public') {
                $validated['favicon'] = Storage::disk('public')->url($validated['favicon']);
            } else {
                $validated['favicon'] = Storage::disk('s3')->url($validated['favicon']);
            }
        }

        $settings = [];
        if (!empty($validated['country_restrictions'])) {
            $settings['country_restrictions'] = array_map('trim', explode(',', $validated['country_restrictions']));
        }
        if (!empty($validated['device_targeting'])) {
            $settings['device_targeting'] = $validated['device_targeting'];
        }
        $validated['settings'] = !empty($settings) ? $settings : null;
        unset($validated['country_restrictions'], $validated['device_targeting']);

        $validated['user_id'] = $request->user()->id;

        $pixelIds = $validated['pixel_ids'] ?? [];
        unset($validated['pixel_ids']);

        $link = Link::create($validated);

        if (!empty($pixelIds)) {
            $link->pixels()->sync($pixelIds);
        }

        return redirect()->route('user.links.index')
            ->with('success', 'Link created successfully.');
    }

    public function show(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);

        $link->load(['project', 'domain', 'pixels']);

        [$startDate, $endDate, $period, $groupBy] = $this->resolveAnalyticsRange($request);

        $clicksQuery = $link->clicks()
            ->whereBetween('clicked_at', [$startDate, $endDate]);

        $totalInRange = (clone $clicksQuery)->count();
        $uniqueInRange = (clone $clicksQuery)->distinct('ip_address')->count('ip_address');
        $blockClicksInRange = (clone $clicksQuery)->whereNotNull('block_id')->count();
        $pageVisitsInRange = (clone $clicksQuery)->whereNull('block_id')->count();

        $dateExpr = match ($groupBy) {
            'week' => "TO_CHAR(DATE_TRUNC('week', clicked_at), 'YYYY-MM-DD')",
            'month' => "TO_CHAR(DATE_TRUNC('month', clicked_at), 'YYYY-MM')",
            'year' => "TO_CHAR(DATE_TRUNC('year', clicked_at), 'YYYY')",
            default => "TO_CHAR(DATE_TRUNC('day', clicked_at), 'YYYY-MM-DD')",
        };

        $clicksOverTime = (clone $clicksQuery)
            ->selectRaw("$dateExpr as bucket, COUNT(*) as count, COUNT(DISTINCT ip_address) as unique_count")
            ->groupByRaw($dateExpr)
            ->orderBy('bucket')
            ->get();

        $topReferrers = (clone $clicksQuery)
            ->selectRaw("referrer, COUNT(*) as count")
            ->whereNotNull('referrer')->where('referrer', '!=', '')
            ->groupBy('referrer')->orderByDesc('count')->limit(10)->get();

        $browserStats = (clone $clicksQuery)
            ->selectRaw("browser, COUNT(*) as count")
            ->whereNotNull('browser')->groupBy('browser')->orderByDesc('count')->get();

        $osStats = (clone $clicksQuery)
            ->selectRaw("os, COUNT(*) as count")
            ->whereNotNull('os')->groupBy('os')->orderByDesc('count')->get();

        $countryStats = (clone $clicksQuery)
            ->selectRaw("country_code, COUNT(*) as count")
            ->whereNotNull('country_code')->groupBy('country_code')
            ->orderByDesc('count')->limit(20)->get();

        $cityStats = (clone $clicksQuery)
            ->selectRaw("city, country_code, COUNT(*) as count")
            ->whereNotNull('city')->groupBy('city', 'country_code')
            ->orderByDesc('count')->limit(20)->get();

        $deviceStats = (clone $clicksQuery)
            ->selectRaw("device_type, COUNT(*) as count")
            ->whereNotNull('device_type')->groupBy('device_type')->orderByDesc('count')->get();

        $languageStats = (clone $clicksQuery)
            ->selectRaw("language, COUNT(*) as count")
            ->whereNotNull('language')->groupBy('language')
            ->orderByDesc('count')->limit(15)->get();

        $blockStats = (clone $clicksQuery)
            ->selectRaw("block_id, block_type, destination_url, COUNT(*) as count, COUNT(DISTINCT ip_address) as unique_count")
            ->whereNotNull('block_id')
            ->groupBy('block_id', 'block_type', 'destination_url')
            ->orderByDesc('count')->limit(50)->get();

        // ---- Previous-period comparison (same span ending immediately before $startDate) ----
        $rangeSeconds  = max(1, $endDate->diffInSeconds($startDate));
        $prevEndDate   = (clone $startDate);
        $prevStartDate = (clone $startDate)->subSeconds($rangeSeconds);
        $prevClicksQuery = $link->clicks()
            ->whereBetween('clicked_at', [$prevStartDate, $prevEndDate]);

        $blockClicksInRangePrev   = (clone $prevClicksQuery)->whereNotNull('block_id')->count();
        $uniqueBlockClicksInRange = (clone $clicksQuery)->whereNotNull('block_id')->distinct('ip_address')->count('ip_address');
        $uniqueBlockClicksPrev    = (clone $prevClicksQuery)->whereNotNull('block_id')->distinct('ip_address')->count('ip_address');

        // Per-block previous-period counts (keyed by block_id) — used for KPI tiles
        $blockStatsPrev = (clone $prevClicksQuery)
            ->selectRaw("block_id, COUNT(*) as count, COUNT(DISTINCT ip_address) as unique_count")
            ->whereNotNull('block_id')
            ->groupBy('block_id')
            ->get()
            ->keyBy('block_id');

        // Per-(block + destination) previous-period counts — needed so socials*
        // and other multi-link blocks get a real per-platform "vs prev" delta.
        $blockStatsPrevByDest = (clone $prevClicksQuery)
            ->selectRaw("block_id, destination_url, COUNT(*) as count, COUNT(DISTINCT ip_address) as unique_count")
            ->whereNotNull('block_id')
            ->groupBy('block_id', 'destination_url')
            ->get()
            ->mapWithKeys(fn($r) => [$r->block_id . '|' . ($r->destination_url ?? '') => $r]);

        $utmStats = (clone $clicksQuery)
            ->selectRaw("utm_params, COUNT(*) as count")
            ->whereNotNull('utm_params')
            ->groupBy('utm_params')->orderByDesc('count')->limit(15)->get();

        $recentClicks = (clone $clicksQuery)
            ->orderByDesc('clicked_at')
            ->paginate(25)
            ->withQueryString();

        // Engagement: page sessions
        $sessionsQuery = \App\Modules\User\Models\PageSession::where('link_id', $link->id)
            ->whereBetween('started_at', [$startDate, $endDate]);

        $totalSessions = (clone $sessionsQuery)->count();
        $avgSessionSeconds = (int) round((clone $sessionsQuery)->avg('duration_seconds') ?? 0);
        $totalEngagedSeconds = (int) ((clone $sessionsQuery)->sum('duration_seconds') ?? 0);
        $bounceSessions = (clone $sessionsQuery)->where('duration_seconds', '<', 5)->count();
        $bounceRate = $totalSessions > 0 ? round(($bounceSessions / $totalSessions) * 100, 1) : 0;

        // Engagement: per-block dwell time
        $blockEngagement = \App\Modules\User\Models\BlockView::where('link_id', $link->id)
            ->whereBetween('first_viewed_at', [$startDate, $endDate])
            ->selectRaw("block_id, block_type,
                SUM(view_duration_ms) as total_ms,
                AVG(view_duration_ms) as avg_ms,
                SUM(impression_count) as impressions,
                COUNT(DISTINCT session_id) as unique_viewers")
            ->groupBy('block_id', 'block_type')
            ->orderByDesc('total_ms')
            ->limit(50)
            ->get();

        // Map block clicks for CTR calc
        $blockClickMap = [];
        foreach ($blockStats as $b) { $blockClickMap[$b->block_id] = $b->count; }

        // Build a lightweight metadata map (title/url/thumb) for each block on this link
        // so the engagement table can show *what* each block actually is, not just an id.
        $blockMeta = [];
        if ($link->type === 'biolink') {
            $blocksForLink = \App\Modules\User\Models\BiolinkBlock::where('link_id', $link->id)
                ->get(['id', 'type', 'settings']);

            $titleKeys = ['title', 'heading', 'text', 'label', 'name', 'caption', 'question', 'button_text', 'description', 'bio', 'code'];
            $urlKeys   = ['url', 'link', 'destination_url', 'href', 'embed_url', 'src', 'video_url'];
            $imgKeys   = ['image', 'image_url', 'thumbnail', 'avatar', 'logo', 'icon_url', 'poster', 'src', 'url'];

            // Recursively find the first non-empty string value for any of $keys
            $findString = function ($data, array $keys, int $depth = 0) use (&$findString) {
                if ($depth > 4 || !is_array($data)) return null;
                foreach ($keys as $k) {
                    if (!empty($data[$k]) && is_string($data[$k])) {
                        $v = trim(strip_tags($data[$k]));
                        if ($v !== '') return $v;
                    }
                }
                foreach ($data as $v) {
                    if (is_array($v)) {
                        $r = $findString($v, $keys, $depth + 1);
                        if ($r) return $r;
                    }
                }
                return null;
            };
            $findImage = function ($data, array $keys, int $depth = 0) use (&$findImage) {
                if ($depth > 4 || !is_array($data)) return null;
                foreach ($keys as $k) {
                    if (!empty($data[$k]) && is_string($data[$k]) && preg_match('~^(https?:|/)|\.(png|jpe?g|gif|webp|svg)(\?|$)~i', $data[$k])) {
                        return $data[$k];
                    }
                }
                foreach ($data as $v) {
                    if (is_array($v)) {
                        $r = $findImage($v, $keys, $depth + 1);
                        if ($r) return $r;
                    }
                }
                return null;
            };

            foreach ($blocksForLink as $blk) {
                $s = is_array($blk->settings) ? $blk->settings : (json_decode($blk->settings ?? '{}', true) ?: []);

                $title = null;
                // Socials should always render as "Socials: name1, name2..." not just "instagram"
                if ($blk->type === 'socials' && !empty($s['platforms']) && is_array($s['platforms'])) {
                    $names = [];
                    foreach (array_slice($s['platforms'], 0, 3) as $p) {
                        if (is_array($p)) $names[] = ucfirst($p['platform'] ?? $p['name'] ?? '');
                    }
                    $names = array_filter($names);
                    $more  = count($s['platforms']) - count($names);
                    $typeLabel = \App\Modules\User\Models\BiolinkBlock::TYPES['socials']['label'] ?? 'Socials';
                    $title = $typeLabel . ': ' . (empty($names) ? count($s['platforms']) . ' platforms' : implode(', ', $names) . ($more > 0 ? " +{$more}" : ''));
                } else {
                    $title = $findString($s, $titleKeys);
                    if ($title) $title = \Illuminate\Support\Str::limit($title, 60);
                }

                $url   = $findString($s, $urlKeys);
                $thumb = $findImage($s, $imgKeys);

                // Type-specific fallbacks for blocks that have no obvious text value
                if (!$title) {
                    $typeLabel = \App\Modules\User\Models\BiolinkBlock::TYPES[$blk->type]['label'] ?? ucfirst($blk->type);
                    if ($blk->type === 'socials' && !empty($s['platforms']) && is_array($s['platforms'])) {
                        $names = [];
                        foreach (array_slice($s['platforms'], 0, 3) as $p) {
                            if (is_array($p) && !empty($p['platform'])) $names[] = ucfirst($p['platform']);
                            elseif (is_array($p) && !empty($p['name'])) $names[] = $p['name'];
                        }
                        $more = count($s['platforms']) - count($names);
                        $title = $typeLabel . ': ' . (empty($names) ? count($s['platforms']) . ' platforms' : implode(', ', $names) . ($more > 0 ? " +{$more}" : ''));
                    } elseif (in_array($blk->type, ['faq', 'progress', 'list', 'list_numbered', 'list_pricing']) && !empty($s['items']) && is_array($s['items'])) {
                        $first = $s['items'][0] ?? null;
                        $firstText = is_array($first) ? ($first['question'] ?? $first['title'] ?? $first['label'] ?? $first['text'] ?? $first['name'] ?? null) : null;
                        $title = $typeLabel . ' (' . count($s['items']) . ($firstText ? '): "' . \Illuminate\Support\Str::limit(trim(strip_tags($firstText)), 35) . '"' : ' items)');
                    } else {
                        $title = $typeLabel;
                    }
                }

                // For multi-link blocks (socials*), expose a URL → platform map so the
                // analytics view can resolve each click row to the right platform name/icon.
                $platformsMap = [];
                if (in_array($blk->type, ['socials', 'socials_multi', 'socials_custom'])) {
                    $platList = $s['platforms'] ?? [];
                    if ($blk->type === 'socials_multi' && !empty($s['groups']) && is_array($s['groups'])) {
                        $platList = [];
                        foreach ($s['groups'] as $group) {
                            $platList = array_merge($platList, $group['platforms'] ?? []);
                        }
                    }
                    foreach ($platList as $p) {
                        if (!is_array($p)) continue;
                        $purl = $p['url'] ?? $p['link'] ?? null;
                        if (!$purl) continue;
                        $pname = strtolower(trim($p['name'] ?? $p['platform'] ?? ''));
                        $platformsMap[$purl] = [
                            'key'   => $pname,
                            'label' => $p['label'] ?? ($pname !== '' ? ucfirst($pname) : 'Link'),
                            'url'   => $purl,
                        ];
                    }
                }

                $blockMeta[$blk->id] = [
                    'title'     => $title,
                    'url'       => $url,
                    'thumb'     => $thumb,
                    'type'      => $blk->type,
                    'platforms' => $platformsMap,
                ];
            }
        }

        return view('user.links.show', compact(
            'link', 'clicksOverTime', 'topReferrers',
            'browserStats', 'osStats', 'countryStats', 'cityStats',
            'deviceStats', 'languageStats', 'blockStats', 'utmStats',
            'recentClicks', 'totalInRange', 'uniqueInRange',
            'blockClicksInRange', 'pageVisitsInRange',
            'period', 'groupBy', 'startDate', 'endDate',
            'totalSessions', 'avgSessionSeconds', 'totalEngagedSeconds',
            'bounceRate', 'blockEngagement', 'blockClickMap', 'blockMeta',
            'blockStatsPrev', 'blockStatsPrevByDest', 'blockClicksInRangePrev',
            'uniqueBlockClicksInRange', 'uniqueBlockClicksPrev'
        ));
    }

    private function resolveAnalyticsRange(Request $request): array
    {
        $period = $request->query('period', '30d');
        $groupBy = $request->query('group', 'day');
        if (!in_array($groupBy, ['day', 'week', 'month', 'year'])) $groupBy = 'day';

        $end = now()->endOfDay();
        $start = match ($period) {
            'today' => now()->startOfDay(),
            '7d' => now()->subDays(7)->startOfDay(),
            '90d' => now()->subDays(90)->startOfDay(),
            'year' => now()->subYear()->startOfDay(),
            'all' => now()->subYears(10)->startOfDay(),
            'custom' => $request->query('from') ? \Carbon\Carbon::parse($request->query('from'))->startOfDay() : now()->subDays(30)->startOfDay(),
            default => now()->subDays(30)->startOfDay(),
        };
        if ($period === 'custom' && $request->query('to')) {
            $end = \Carbon\Carbon::parse($request->query('to'))->endOfDay();
        }
        return [$start, $end, $period, $groupBy];
    }

    public function recentClicksPartial(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);
        [$startDate, $endDate] = $this->resolveAnalyticsRange($request);

        $recentClicks = $link->clicks()
            ->whereBetween('clicked_at', [$startDate, $endDate])
            ->orderByDesc('clicked_at')
            ->paginate(25)
            ->withQueryString();

        $blockTypes = \App\Modules\User\Models\BiolinkBlock::TYPES;

        return view('user.links.partials.recent-clicks-table', compact('recentClicks', 'blockTypes'));
    }

    public function heatmap(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);
        [$startDate, $endDate] = $this->resolveAnalyticsRange($request);

        // True period total (never truncated by the points cap below).
        $totalGeoClicks = (int) $link->clicks()
            ->whereBetween('clicked_at', [$startDate, $endDate])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->count();

        // Aggregate by exact (lat, lng) so each point is a real city marker.
        // Cross-DB: only uses GROUP BY + COUNT + MAX. Capped at the busiest
        // 5000 hotspots to bound response size.
        $rows = $link->clicks()
            ->whereBetween('clicked_at', [$startDate, $endDate])
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->select('latitude', 'longitude')
            ->selectRaw('count(*) as click_count')
            ->selectRaw('max(city) as city')
            ->selectRaw('max(country_code) as country_code')
            ->groupBy('latitude', 'longitude')
            ->orderByDesc('click_count')
            ->limit(5000)
            ->get();

        $features = [];
        $maxWeight = 0;
        $shownClicks = 0;
        foreach ($rows as $r) {
            $count = (int) $r->click_count;
            $shownClicks += $count;
            if ($count > $maxWeight) $maxWeight = $count;
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $r->longitude, (float) $r->latitude],
                ],
                'properties' => [
                    'lat'          => (float) $r->latitude,
                    'lng'          => (float) $r->longitude,
                    'count'        => $count,
                    'weight'       => $count,
                    'city'         => $r->city,
                    'country_code' => $r->country_code,
                    'country'      => $r->country_code,
                ],
            ];
        }

        $points = array_map(fn($f) => [
            'lat'          => $f['properties']['lat'],
            'lng'          => $f['properties']['lng'],
            'count'        => $f['properties']['count'],
            'city'         => $f['properties']['city'],
            'country_code' => $f['properties']['country_code'],
        ], $features);

        return response()->json([
            'type'     => 'FeatureCollection',
            'features' => $features,
            'points'   => $points,
            'meta'     => [
                'max_weight'    => $maxWeight,
                'point_count'   => count($features),
                'total_clicks'  => $totalGeoClicks,
                'shown_clicks'  => $shownClicks,
                'period_start'  => $startDate->toIso8601String(),
                'period_end'    => $endDate->toIso8601String(),
            ],
        ]);
    }

    public function heatmapLive(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);

        // "Live" window: last 5 minutes of clicks. Short enough to feel real-time,
        // long enough to keep a few pulses on screen between 10s polls.
        $windowStart = now()->subMinutes(5);

        $rows = $link->clicks()
            ->where('clicked_at', '>=', $windowStart)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('clicked_at')
            ->limit(200)
            ->get(['id', 'latitude', 'longitude', 'city', 'country_code', 'clicked_at', 'ip_address']);

        $points = [];
        foreach ($rows as $r) {
            $points[] = [
                'id'           => (int) $r->id,
                'lat'          => (float) $r->latitude,
                'lng'          => (float) $r->longitude,
                'city'         => $r->city,
                'country_code' => $r->country_code,
                'clicked_at'   => optional($r->clicked_at)->toIso8601String(),
                'ts'           => optional($r->clicked_at)->getTimestamp(),
            ];
        }

        $uniqueVisitors = $rows->pluck('ip_address')->filter()->unique()->count();

        return response()->json([
            'points' => $points,
            'meta'   => [
                'count'           => count($points),
                'unique_visitors' => $uniqueVisitors,
                'window_seconds'  => 300,
                'server_time'     => now()->toIso8601String(),
                'server_ts'       => now()->getTimestamp(),
            ],
        ]);
    }

    public function exportClicks(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);
        [$startDate, $endDate] = $this->resolveAnalyticsRange($request);

        $filename = 'clicks-' . $link->alias . '-' . now()->format('Y-m-d-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $columns = ['Clicked At', 'IP', 'Country', 'City', 'Browser', 'OS', 'Device', 'Language', 'Referrer', 'Block ID', 'Block Type', 'Destination URL', 'UTM Source', 'UTM Medium', 'UTM Campaign'];

        return response()->stream(function () use ($link, $startDate, $endDate, $columns) {
            $h = fopen('php://output', 'w');
            fputcsv($h, $columns);
            $link->clicks()
                ->whereBetween('clicked_at', [$startDate, $endDate])
                ->orderByDesc('clicked_at')
                ->chunk(500, function ($rows) use ($h) {
                    foreach ($rows as $r) {
                        $u = $r->utm_params ?? [];
                        fputcsv($h, [
                            optional($r->clicked_at)->format('Y-m-d H:i:s'),
                            $r->ip_address, $r->country_code, $r->city,
                            $r->browser, $r->os, $r->device_type, $r->language,
                            $r->referrer, $r->block_id, $r->block_type, $r->destination_url,
                            $u['utm_source'] ?? '', $u['utm_medium'] ?? '', $u['utm_campaign'] ?? '',
                        ]);
                    }
                });
            fclose($h);
        }, 200, $headers);
    }

    public function edit(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);

        $projects = $request->user()->projects()->orderBy('name')->get();
        $pixels = $request->user()->pixels()->orderBy('name')->get();
        $domains = $request->user()->domains()->where('is_verified', true)->get();
        $link->load('pixels');

        return view('user.links.edit', compact('link', 'projects', 'pixels', 'domains'));
    }

    public function update(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);

        $userId = $request->user()->id;

        $validated = $request->validate([
            'long_url' => 'nullable|url|max:2048',
            'redirect_type' => 'nullable|in:301,302',
            'title' => 'nullable|string|max:255',
            'project_id' => "nullable|exists:projects,id,user_id,{$userId}",
            'domain_id' => "nullable|exists:domains,id,user_id,{$userId}",
            'is_active' => 'boolean',
            'is_password_protected' => 'boolean',
            'password' => 'nullable|string|min:3|max:100',
            'expires_at' => 'nullable|date',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'utm_source' => 'nullable|string|max:255',
            'utm_medium' => 'nullable|string|max:255',
            'utm_campaign' => 'nullable|string|max:255',
            'utm_term' => 'nullable|string|max:255',
            'utm_content' => 'nullable|string|max:255',
            'pixel_ids' => 'nullable|array',
            'pixel_ids.*' => "exists:pixels,id,user_id,{$userId}",
            'seo_image' => 'nullable|image|max:2048',
            'favicon' => 'nullable|image|max:1024',
            'country_restrictions' => 'nullable|string|max:500',
            'device_targeting' => 'nullable|array',
            'device_targeting.*' => 'in:desktop,mobile,tablet',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
            $validated['is_password_protected'] = true;
        } else {
            unset($validated['password']);
            if (empty($validated['is_password_protected'])) {
                $validated['password'] = null;
                $validated['is_password_protected'] = false;
            }
        }

        if ($request->hasFile('seo_image')) {
            $disk = config('filesystems.default') === 's3' ? 's3' : 'public';
            $validated['seo_image'] = $request->file('seo_image')->store('seo-images', $disk);
            if ($disk === 'public') {
                $validated['seo_image'] = Storage::disk('public')->url($validated['seo_image']);
            } else {
                $validated['seo_image'] = Storage::disk('s3')->url($validated['seo_image']);
            }
        } else {
            unset($validated['seo_image']);
        }

        if ($request->hasFile('favicon')) {
            $disk = config('filesystems.default') === 's3' ? 's3' : 'public';
            $validated['favicon'] = $request->file('favicon')->store('favicons', $disk);
            if ($disk === 'public') {
                $validated['favicon'] = Storage::disk('public')->url($validated['favicon']);
            } else {
                $validated['favicon'] = Storage::disk('s3')->url($validated['favicon']);
            }
        } else {
            unset($validated['favicon']);
        }

        $settings = $link->settings ?? [];
        if (isset($validated['country_restrictions']) && $validated['country_restrictions']) {
            $settings['country_restrictions'] = array_map('trim', explode(',', $validated['country_restrictions']));
        } else {
            unset($settings['country_restrictions']);
        }
        if (!empty($validated['device_targeting'])) {
            $settings['device_targeting'] = $validated['device_targeting'];
        } else {
            unset($settings['device_targeting']);
        }
        $validated['settings'] = !empty($settings) ? $settings : null;
        unset($validated['country_restrictions'], $validated['device_targeting']);

        $pixelIds = $validated['pixel_ids'] ?? [];
        unset($validated['pixel_ids']);

        $link->update($validated);
        $link->pixels()->sync($pixelIds);

        return redirect()->route('user.links.show', $link)
            ->with('success', 'Link updated successfully.');
    }

    public function destroy(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);

        $link->delete();

        return redirect()->route('user.links.index')
            ->with('success', 'Link deleted successfully.');
    }

    public function toggleActive(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);

        $link->update(['is_active' => !$link->is_active]);

        return back()->with('success', 'Link status updated.');
    }

    public function updateAlias(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'alias' => [
                'required',
                'string',
                'min:3',
                'max:60',
                'regex:/^[a-zA-Z0-9_-]+$/',
                'unique:links,alias,' . $link->id,
            ],
        ], [
            'alias.regex' => 'Only letters, numbers, hyphens and underscores are allowed.',
            'alias.unique' => 'This alias is already taken. Please choose another.',
        ]);

        $link->update(['alias' => $validated['alias']]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'alias' => $validated['alias']]);
        }

        return back()->with('success', 'URL alias updated successfully.');
    }
}
