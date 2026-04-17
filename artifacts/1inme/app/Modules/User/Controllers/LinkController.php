<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\CityLookupService;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
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

        // Optional per-alias filter — narrow analytics to clicks that came in
        // through a specific alias (e.g. "?alias=summer-promo").
        $aliasFilter = $request->query('alias');
        $availableAliases = $link->getAllAliases();
        if ($aliasFilter && !in_array($aliasFilter, $availableAliases, true)) {
            $aliasFilter = null;
        }

        // Per-alias breakdown (counts BEFORE applying alias filter so the user can
        // see all aliases and switch between them).
        $aliasBreakdown = $link->clicks()
            ->whereBetween('clicked_at', [$startDate, $endDate])
            ->whereNotNull('alias')
            ->selectRaw('alias, COUNT(*) as total')
            ->groupBy('alias')
            ->orderByDesc('total')
            ->get();

        $clicksQuery = $link->clicks()
            ->whereBetween('clicked_at', [$startDate, $endDate])
            ->when($aliasFilter, fn ($q) => $q->where('alias', $aliasFilter));

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
        // Previous-period end is *exclusive* of the current-period start so a
        // click occurring exactly at $startDate is counted once, not twice.
        $prevEndDate   = (clone $startDate)->subSecond();
        $prevStartDate = (clone $startDate)->subSeconds($rangeSeconds);
        $prevClicksQuery = $link->clicks()
            ->whereBetween('clicked_at', [$prevStartDate, $prevEndDate])
            // Mirror the alias filter onto the previous-period query so vs-prev
            // deltas (KPI tiles, per-platform comparisons) are apples-to-apples.
            ->when($aliasFilter, fn ($q) => $q->where('alias', $aliasFilter));

        $totalInRangePrev         = (clone $prevClicksQuery)->count();
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
                ->get(['id', 'type', 'settings', 'parent_id', 'is_active']);

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

        // ---- Performance Coach: derive a tiny block-inventory snapshot from
        // the `$blocksForLink` collection we already fetched above for
        // $blockMeta — no additional query. The coach engine itself issues
        // zero queries and simply transforms this context into insights.
        $blockInventory = ['clickable' => [], 'has_socials' => false, 'has_qr' => false,
                            'active_count' => 0, 'top_level_active_count' => 0,
                            'disabled_socials_block_id' => null];
        if ($link->type === 'biolink' && isset($blocksForLink)) {
            $nonInteractive = ['heading', 'heading_gradient', 'heading_logo', 'heading_morph',
                'paragraph', 'paragraph_rich', 'divider', 'spacer',
                'verified_heading', 'verified_avatar', 'alert', 'badge', 'avatar'];
            $socialTypes = ['socials', 'socials_multi', 'socials_custom'];
            foreach ($blocksForLink as $blk) {
                $isSocial = in_array($blk->type, $socialTypes, true);
                if (!$blk->is_active) {
                    // Track a disabled socials block so the coach can offer a
                    // one-click "enable" action instead of the generic tip.
                    if ($isSocial && $blockInventory['disabled_socials_block_id'] === null) {
                        $blockInventory['disabled_socials_block_id'] = $blk->id;
                    }
                    continue;
                }
                $blockInventory['active_count']++;
                if ($blk->parent_id === null) {
                    $blockInventory['top_level_active_count']++;
                    if (!in_array($blk->type, $nonInteractive, true)) {
                        $blockInventory['clickable'][] = $blk->id;
                    }
                }
                if ($isSocial)              $blockInventory['has_socials'] = true;
                if ($blk->type === 'qr_code') $blockInventory['has_qr']    = true;
            }
        }

        // 30-day score history for the sparkline rendered in the coach card.
        // Rows are produced by the `coach:snapshot-scores` nightly command.
        $performanceHistory = \App\Modules\User\Models\LinkPerformanceSnapshot::where('link_id', $link->id)
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->orderBy('date')
            ->get(['date', 'score', 'components_json'])
            ->map(fn ($r) => [
                'date'       => $r->date instanceof \Carbon\Carbon ? $r->date->toDateString() : (string) $r->date,
                'score'      => (int) $r->score,
                'components' => is_array($r->components_json) ? $r->components_json : null,
            ])
            ->values()
            ->all();

        $performance = \App\Modules\User\Services\LinkPerformanceCoach::build([
            'link'               => $link,
            'totalInRange'       => $totalInRange,
            'uniqueInRange'      => $uniqueInRange,
            'blockClicksInRange' => $blockClicksInRange,
            'pageVisitsInRange'  => $pageVisitsInRange,
            'totalSessions'      => $totalSessions,
            'avgSessionSeconds'  => $avgSessionSeconds,
            'bounceRate'         => $bounceRate,
            'blockStats'         => $blockStats,
            'topReferrers'       => $topReferrers,
            'totalInRangePrev'   => $totalInRangePrev,
            'aliasFilter'        => $aliasFilter,
            'period'             => $period,
            'blockInventory'     => $blockInventory,
        ]);

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
            'totalInRangePrev',
            'uniqueBlockClicksInRange', 'uniqueBlockClicksPrev',
            'aliasBreakdown', 'aliasFilter', 'availableAliases',
            'performance', 'performanceHistory'
        ));
    }

    /**
     * Update the per-link Performance Coach tuning (preset or custom thresholds).
     * Thresholds are stored on the link's `settings` JSON under `performance_coach`
     * so they persist between visits and re-apply on the next coach build.
     */
    public function updatePerformanceCoachSettings(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'preset' => ['required', 'string', 'in:' . implode(',', \App\Modules\User\Services\LinkPerformanceCoach::validPresetKeys())],
            'overrides' => 'nullable|array',
            'overrides.ctr_critical' => 'nullable|numeric|between:0,1',
            'overrides.ctr_warning' => 'nullable|numeric|between:0,1',
            'overrides.ctr_excellent' => 'nullable|numeric|between:0,1',
            'overrides.bounce_critical' => 'nullable|numeric|between:0,100',
            'overrides.bounce_warning' => 'nullable|numeric|between:0,100',
            'overrides.bounce_excellent' => 'nullable|numeric|between:0,100',
            'overrides.engagement_low_seconds' => 'nullable|numeric|between:1,600',
            'overrides.engagement_excellent_seconds' => 'nullable|numeric|between:1,600',
            'overrides.momentum_drop_critical' => 'nullable|numeric|between:-1,0',
            'overrides.momentum_drop_warning' => 'nullable|numeric|between:-1,0',
            'overrides.momentum_win_threshold' => 'nullable|numeric|between:0,5',
        ]);

        \App\Modules\User\Services\LinkPerformanceCoach::saveLinkSettings(
            $link,
            $validated['preset'],
            $validated['overrides'] ?? []
        );

        return redirect()
            ->to(url()->previous() ?: route('user.links.show', $link))
            ->with('success', 'Performance Coach thresholds updated.');
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

        // Second pass: rows with a known city + country_code but no stored
        // lat/lng (older clicks, or ones the backfill couldn't resolve).
        // Group by (city, country_code) and resolve each group to a coarser
        // pin via the offline CityLookupService so they still show up on
        // the map instead of being silently dropped.
        $cityRows = $link->clicks()
            ->whereBetween('clicked_at', [$startDate, $endDate])
            ->where(function ($q) {
                $q->whereNull('latitude')->orWhereNull('longitude');
            })
            ->whereNotNull('city')
            ->whereNotNull('country_code')
            ->selectRaw('city, country_code, count(*) as click_count')
            ->groupBy('city', 'country_code')
            ->get();

        if ($cityRows->isNotEmpty()) {
            $lookup = app(CityLookupService::class);
            $resolvedTotal = 0;
            foreach ($cityRows as $r) {
                $coords = $lookup->lookup($r->city, $r->country_code);
                if (!$coords) continue;
                $count = (int) $r->click_count;
                $resolvedTotal += $count;
                $shownClicks += $count;
                if ($count > $maxWeight) $maxWeight = $count;
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [(float) $coords['longitude'], (float) $coords['latitude']],
                    ],
                    'properties' => [
                        'lat'          => (float) $coords['latitude'],
                        'lng'          => (float) $coords['longitude'],
                        'count'        => $count,
                        'weight'       => $count,
                        'city'         => $r->city,
                        'country_code' => $r->country_code,
                        'country'      => $r->country_code,
                        'approximate'  => true,
                    ],
                ];
            }
            $totalGeoClicks += $resolvedTotal;
            // Keep busiest hotspots first after merging both passes.
            usort($features, fn($a, $b) => $b['properties']['count'] <=> $a['properties']['count']);
        }

        $points = array_map(fn($f) => [
            'lat'          => $f['properties']['lat'],
            'lng'          => $f['properties']['lng'],
            'count'        => $f['properties']['count'],
            'city'         => $f['properties']['city'],
            'country_code' => $f['properties']['country_code'],
            'approximate'  => $f['properties']['approximate'] ?? false,
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

        $points = $this->formatLivePoints($rows);

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

    /**
     * Server-Sent Events stream of recent visitors.
     *
     * The browser opens this once and receives new clicks as soon as the
     * server notices them — no client polling. Works for any Laravel setup
     * because it doesn't require Redis/Reverb/pub-sub: the server itself
     * tails the clicks table at a short interval and flushes each new row
     * out over the held connection.
     *
     * The connection is capped at ~55s so PHP-FPM/proxies don't kill it
     * mid-flight; EventSource reconnects automatically and the `lastId`
     * cursor (remembered client-side and echoed back in each payload)
     * prevents duplicate pins across reconnects.
     */
    public function heatmapLiveStream(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);

        $lastId = (int) $request->query('lastId', 0);
        $sinceTs = $request->query('since');
        // When the client has no cursor yet, seed from the existing 5-minute
        // window so the first batch of events matches what `heatmapLive()`
        // would have returned — keeps the "X live visitors" pill accurate
        // the moment the stream opens.
        $windowStart = $sinceTs
            ? \Carbon\Carbon::createFromTimestamp((int) $sinceTs)
            : now()->subMinutes(5);

        $maxDurationSec = 55;        // cap before forcing client reconnect
        $pollIntervalMs = 1000;      // server-side DB tail cadence
        $heartbeatEverySec = 15;     // keep proxies / browsers from timing out
        // `?once=1` emits just the initial snapshot and exits — used by
        // automated tests so they don't have to wait out the tail loop.
        $onceOnly = (bool) $request->query('once', false);

        return response()->stream(function () use ($link, $lastId, $windowStart, $maxDurationSec, $pollIntervalMs, $heartbeatEverySec, $onceOnly) {
            @ini_set('zlib.output_compression', '0');

            $emit = function (string $event, array $payload) {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($payload) . "\n\n";
                @ob_flush();
                @flush();
            };

            // Initial snapshot: same shape as the polling endpoint so the
            // client can reuse its existing rendering path.
            $initialRows = $link->clicks()
                ->where('clicked_at', '>=', $windowStart)
                ->when($lastId > 0, fn($q) => $q->where('id', '>', $lastId))
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderBy('id')
                ->limit(200)
                ->get(['id', 'latitude', 'longitude', 'city', 'country_code', 'clicked_at', 'ip_address']);

            foreach ($initialRows as $r) {
                if ((int) $r->id > $lastId) $lastId = (int) $r->id;
            }

            $uniqueVisitors = $link->clicks()
                ->where('clicked_at', '>=', now()->subMinutes(5))
                ->whereNotNull('ip_address')
                ->distinct('ip_address')
                ->count('ip_address');

            $emit('snapshot', [
                'points' => $this->formatLivePoints($initialRows),
                'meta'   => [
                    'count'           => $initialRows->count(),
                    'unique_visitors' => $uniqueVisitors,
                    'window_seconds'  => 300,
                    'server_ts'       => now()->getTimestamp(),
                    'last_id'         => $lastId,
                ],
            ]);

            if ($onceOnly) {
                $emit('bye', ['last_id' => $lastId]);
                return;
            }

            $deadline = microtime(true) + $maxDurationSec;
            $lastHeartbeat = microtime(true);

            while (microtime(true) < $deadline) {
                if (connection_aborted()) break;

                $newRows = $link->clicks()
                    ->where('id', '>', $lastId)
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->orderBy('id')
                    ->limit(100)
                    ->get(['id', 'latitude', 'longitude', 'city', 'country_code', 'clicked_at', 'ip_address']);

                if ($newRows->isNotEmpty()) {
                    foreach ($newRows as $r) {
                        if ((int) $r->id > $lastId) $lastId = (int) $r->id;
                    }
                    $unique = $link->clicks()
                        ->where('clicked_at', '>=', now()->subMinutes(5))
                        ->whereNotNull('ip_address')
                        ->distinct('ip_address')
                        ->count('ip_address');
                    $emit('clicks', [
                        'points' => $this->formatLivePoints($newRows),
                        'meta'   => [
                            'unique_visitors' => $unique,
                            'server_ts'       => now()->getTimestamp(),
                            'last_id'         => $lastId,
                        ],
                    ]);
                    $lastHeartbeat = microtime(true);
                } elseif (microtime(true) - $lastHeartbeat >= $heartbeatEverySec) {
                    // Comment frame = SSE "ping" — keeps intermediaries from
                    // closing an idle connection but isn't delivered as an event.
                    echo ": ping\n\n";
                    @ob_flush();
                    @flush();
                    $lastHeartbeat = microtime(true);
                }

                usleep($pollIntervalMs * 1000);
            }

            $emit('bye', ['last_id' => $lastId]);
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    /**
     * Shared point formatter for the polling and SSE live endpoints so both
     * return identical shapes to the browser.
     */
    private function formatLivePoints($rows): array
    {
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
        return $points;
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

        // For biolinks, all edit controls live on the unified Appearance
        // settings page so users have a single, premium place to manage them.
        if ($link->type === 'biolink') {
            return redirect()->route('user.links.settings.appearance', $link);
        }

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
            'expiry_url' => 'nullable|url:http,https|max:2048',
            'max_clicks' => 'nullable|integer|min:0|max:1000000000',
            'start_at' => 'nullable|date',
            'expire_on_first_click' => 'nullable|boolean',
            '_exp_mode' => 'nullable|in:none,date,clicks,first_click',
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

        // Expiry / availability conditions stored in JSON settings (no migration needed).
        // The `_exp_mode` field (none|date|clicks|first_click) tells us which
        // expiry mode the user picked so we can clear the others server-side.
        $expMode = $validated['_exp_mode'] ?? null;
        if ($expMode !== null) {
            if ($expMode !== 'date') {
                $validated['expires_at'] = null;
            }
            if ($expMode !== 'clicks') {
                $validated['max_clicks'] = null;
            }
            // Picking the One-Time mode is itself the activation — no separate checkbox.
            $validated['expire_on_first_click'] = ($expMode === 'first_click');
        }
        unset($validated['_exp_mode']);

        if (!empty($validated['expiry_url'])) {
            $settings['expiry_url'] = $validated['expiry_url'];
        } else {
            unset($settings['expiry_url']);
        }
        if (!empty($validated['max_clicks']) && (int) $validated['max_clicks'] > 0) {
            $settings['max_clicks'] = (int) $validated['max_clicks'];
        } else {
            unset($settings['max_clicks']);
        }
        if (!empty($validated['start_at'])) {
            $settings['start_at'] = $validated['start_at'];
        } else {
            unset($settings['start_at']);
        }
        if (!empty($validated['expire_on_first_click'])) {
            $settings['expire_on_first_click'] = true;
        } else {
            unset($settings['expire_on_first_click']);
        }

        $validated['settings'] = !empty($settings) ? $settings : null;
        unset(
            $validated['country_restrictions'], $validated['device_targeting'],
            $validated['expiry_url'], $validated['max_clicks'],
            $validated['start_at'], $validated['expire_on_first_click']
        );

        $pixelIds = $validated['pixel_ids'] ?? [];
        unset($validated['pixel_ids']);

        $link->update($validated);
        $link->pixels()->sync($pixelIds);

        return redirect()->route('user.links.show', $link)
            ->with('success', 'Link updated successfully.');
    }

    /**
     * Splash page settings — an intermediate "transition" page that visitors
     * see before reaching the destination of ANY link type. Stored in the
     * link's settings JSON under the "splash" key (no migration needed).
     */
    public function splashSettings(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);
        $link->load('splashPage');
        $splashPages = $request->user()->splashPages()->orderBy('name')->get(['id', 'name', 'title']);
        return view('user.links.settings.splash', compact('link', 'splashPages'));
    }

    public function updateSplash(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);
        $userId = $request->user()->id;
        $validated = $request->validate([
            'splash_enabled'  => 'sometimes|boolean',
            'splash_page_id'  => ['nullable', \Illuminate\Validation\Rule::exists('splash_pages', 'id')->where('user_id', $userId)],
        ]);
        $link->splash_enabled = $request->boolean('splash_enabled');
        $link->splash_page_id = $validated['splash_page_id'] ?? null;
        if ($link->splash_enabled && !$link->splash_page_id) {
            $link->splash_enabled = false;
        }
        $link->save();
        return redirect()->route('user.links.splash', $link)
            ->with('success', 'Splash settings saved.');
    }

    public function destroy(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);

        $link->delete();

        return redirect()->route('user.links.index')
            ->with('success', 'Link deleted successfully.');
    }

    /**
     * Reset analytics data for a link. By default wipes ALL click + engagement
     * data for the link; if `?alias=` is supplied and valid, only click rows
     * belonging to that alias are wiped (engagement sessions have no alias
     * column so they are preserved in the alias-scoped case).
     */
    public function resetStats(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);

        $aliasScope = $request->query('alias') ?: $request->input('alias');
        if ($aliasScope && !in_array($aliasScope, $link->getAllAliases(), true)) {
            $aliasScope = null;
        }

        if ($aliasScope) {
            $deleted = \DB::table('link_clicks')
                ->where('link_id', $link->id)
                ->where('alias', $aliasScope)
                ->delete();
            $msg = "Reset complete — removed {$deleted} click" . ($deleted === 1 ? '' : 's') . " for /{$aliasScope}.";
        } else {
            \DB::transaction(function () use ($link) {
                \DB::table('block_views')->where('link_id', $link->id)->delete();
                \DB::table('page_sessions')->where('link_id', $link->id)->delete();
                \DB::table('link_clicks')->where('link_id', $link->id)->delete();
            });
            $msg = 'All analytics data for this link has been reset.';
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }
        return redirect()->route('user.links.show', $link)->with('success', $msg);
    }


    public function toggleActive(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);

        $link->update(['is_active' => !$link->is_active]);

        return back()->with('success', 'Link status updated.');
    }

    /**
     * One-click action dispatched from a Performance Coach insight.
     *
     * Each supported action type maps 1:1 to a recommendation rendered by
     * LinkPerformanceCoach::build(). Ownership of the link (and every block
     * referenced in the payload) is re-verified here — we never trust the
     * block ids sent by the client, even though the coach only surfaces
     * actions for the current user's own blocks.
     */
    public function coachAction(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'action_type' => 'required|string|in:deactivate_blocks,promote_block,enable_block',
            'block_id'    => 'nullable|integer',
            'block_ids'   => 'nullable|array|max:50',
            'block_ids.*' => 'integer',
        ]);

        $type = $validated['action_type'];

        // Scope every referenced block id to THIS link so a crafted request
        // can't mutate a block on another link the user does (or doesn't) own.
        $scopedBlocks = function (array $ids) use ($link) {
            return \App\Modules\User\Models\BiolinkBlock::where('link_id', $link->id)
                ->whereIn('id', $ids)
                ->get();
        };

        $message = null;
        // Pre-change snapshot for the Undo control rendered on the next page
        // view. Each branch records the minimum state needed to invert the
        // mutation. Null => no undo offered (nothing actually changed).
        $undoData = null;

        if ($type === 'deactivate_blocks') {
            $ids = $validated['block_ids'] ?? [];
            if (empty($ids)) {
                return back()->with('error', 'No blocks specified.');
            }
            $blocks = $scopedBlocks($ids);
            if ($blocks->isEmpty()) {
                return back()->with('error', 'Those blocks no longer exist.');
            }
            // Only blocks that were actually active get flipped — those are
            // the ones Undo should re-enable.
            $changedIds = $blocks->where('is_active', true)->pluck('id')->all();
            if (!empty($changedIds)) {
                \App\Modules\User\Models\BiolinkBlock::where('link_id', $link->id)
                    ->whereIn('id', $changedIds)
                    ->update(['is_active' => false]);
                $undoData = ['ids' => array_values($changedIds)];
            }
            $n = count($changedIds);
            $message = $n === 1
                ? 'Hid 1 zero-click block.'
                : "Hid {$n} zero-click blocks.";
        } elseif ($type === 'enable_block') {
            $id = (int) ($validated['block_id'] ?? 0);
            if ($id <= 0) {
                return back()->with('error', 'No block specified.');
            }
            $block = $scopedBlocks([$id])->first();
            if (!$block) {
                return back()->with('error', 'That block no longer exists.');
            }
            if (!$block->is_active) {
                $block->update(['is_active' => true]);
                $undoData = ['id' => $block->id];
            }
            $message = 'Block enabled.';
        } elseif ($type === 'promote_block') {
            $id = (int) ($validated['block_id'] ?? 0);
            if ($id <= 0) {
                return back()->with('error', 'No block specified.');
            }
            $block = $scopedBlocks([$id])->first();
            if (!$block) {
                return back()->with('error', 'That block no longer exists.');
            }
            // Promotion is a reorder within the block's current parent scope
            // (top-level siblings, or a card's children). Assign the target
            // block sort_order=0, then renumber its siblings from 1 onward,
            // preserving their relative order.
            $siblingsQuery = \App\Modules\User\Models\BiolinkBlock::where('link_id', $link->id);
            if ($block->parent_id === null) {
                $siblingsQuery->whereNull('parent_id');
            } else {
                $siblingsQuery->where('parent_id', $block->parent_id);
            }
            $siblings = $siblingsQuery->orderBy('sort_order')->orderBy('id')->get();

            // Capture pre-reorder sort_order for every sibling so Undo can
            // restore the exact prior arrangement.
            $prevOrder = [];
            foreach ($siblings as $sib) {
                $prevOrder[(string) $sib->id] = (int) $sib->sort_order;
            }

            \Illuminate\Support\Facades\DB::transaction(function () use ($siblings, $block) {
                $next = 1;
                foreach ($siblings as $sib) {
                    if ($sib->id === $block->id) {
                        $sib->update(['sort_order' => 0]);
                    } else {
                        $sib->update(['sort_order' => $next++]);
                    }
                }
            });

            $undoData = ['order' => $prevOrder];
            $message = 'Moved to the top of the page.';
        }

        $response = back()->with('success', $message ?? 'Done.');

        if ($undoData !== null) {
            // Sign the undo payload so the bounded, idempotent undo action
            // doesn't require a new DB table. The token embeds link/user and
            // an expiry so it can't be replayed later or on another link.
            $token = Crypt::encrypt([
                'link_id'    => $link->id,
                'user_id'    => $request->user()->id,
                'type'       => $type,
                'data'       => $undoData,
                'expires_at' => now()->addMinutes(10)->timestamp,
            ]);
            $response->with('coach_undo', $token);
        }

        return $response;
    }

    /**
     * Reverse the most recent one-click coach action. The inverse is derived
     * from a short-lived, encrypted token issued by coachAction(); no new
     * table is needed. Re-applying the same token is a no-op because the
     * inverse writes specific prior values (active=true / specific sort
     * orders) which are already in place after the first undo.
     */
    public function coachUndo(Request $request)
    {
        $validated = $request->validate([
            'undo_token' => 'required|string',
        ]);

        try {
            $payload = Crypt::decrypt($validated['undo_token']);
        } catch (\Throwable $e) {
            return back()->with('error', 'This undo link is no longer valid.');
        }

        if (!is_array($payload)
            || ($payload['user_id'] ?? null) !== $request->user()->id
            || empty($payload['link_id'])
            || empty($payload['type'])
        ) {
            abort(403);
        }

        if (($payload['expires_at'] ?? 0) < now()->timestamp) {
            return back()->with('error', 'The undo window has expired.');
        }

        $link = Link::where('user_id', $request->user()->id)
            ->where('id', $payload['link_id'])
            ->first();
        if (!$link) {
            abort(404);
        }

        $type = $payload['type'];
        $data = $payload['data'] ?? [];
        $message = 'Change undone.';

        if ($type === 'deactivate_blocks') {
            $ids = array_values(array_filter(array_map('intval', $data['ids'] ?? [])));
            if (!empty($ids)) {
                \App\Modules\User\Models\BiolinkBlock::where('link_id', $link->id)
                    ->whereIn('id', $ids)
                    ->update(['is_active' => true]);
            }
            $message = count($ids) === 1
                ? 'Restored hidden block.'
                : 'Restored ' . count($ids) . ' hidden blocks.';
        } elseif ($type === 'enable_block') {
            $id = (int) ($data['id'] ?? 0);
            if ($id > 0) {
                \App\Modules\User\Models\BiolinkBlock::where('link_id', $link->id)
                    ->where('id', $id)
                    ->update(['is_active' => false]);
            }
            $message = 'Block disabled again.';
        } elseif ($type === 'promote_block') {
            $order = $data['order'] ?? [];
            if (is_array($order) && !empty($order)) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($link, $order) {
                    foreach ($order as $id => $sort) {
                        \App\Modules\User\Models\BiolinkBlock::where('link_id', $link->id)
                            ->where('id', (int) $id)
                            ->update(['sort_order' => (int) $sort]);
                    }
                });
            }
            $message = 'Restored previous block order.';
        } else {
            return back()->with('error', 'Unknown undo action.');
        }

        return redirect()->route('user.links.show', $link)->with('success', $message);
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
                // Aliases must be globally unique across BOTH tables — also
                // reject if the value is already used as an extra alias on
                // any other link (an extra owned by THIS link is fine; we'll
                // demote it implicitly below).
                function ($attr, $value, $fail) use ($link) {
                    // Reserved top-level paths must never be claimed (mirror LinkAliasController).
                    $reserved = \App\Modules\User\Controllers\LinkAliasController::reservedAliases();
                    if (in_array(strtolower($value), $reserved, true)) {
                        $fail("'{$value}' is a reserved name and cannot be used.");
                        return;
                    }
                    $exists = \App\Modules\User\Models\LinkAlias::where('alias', $value)
                        ->where('link_id', '!=', $link->id)
                        ->exists();
                    if ($exists) $fail('This alias is already taken. Please choose another.');
                },
            ],
        ], [
            'alias.regex' => 'Only letters, numbers, hyphens and underscores are allowed.',
            'alias.unique' => 'This alias is already taken. Please choose another.',
        ]);

        // If the new primary value is currently an EXTRA alias on this same
        // link, free that row first so the unique constraint on link_aliases
        // is not violated when we later demote (handled by promote() flow);
        // here we simply delete the dup since it's about to live on links.alias.
        \App\Modules\User\Models\LinkAlias::where('link_id', $link->id)
            ->where('alias', $validated['alias'])
            ->delete();

        $link->update(['alias' => $validated['alias']]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'alias' => $validated['alias']]);
        }

        return back()->with('success', 'URL alias updated successfully.');
    }
}
