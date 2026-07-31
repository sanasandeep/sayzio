<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkSlide;
use App\Modules\User\Models\LinkSlideDeck;
use App\Modules\User\Models\LinkSlideViewEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SlideDeckController extends Controller
{
    protected function authorizeLink(Link $link): void
    {
        abort_if(
            $link->user_id !== workspace_owner_id() || !$link->isBiolinkFamily(),
            403,
        );
    }

    protected function ensureDeck(Link $link): LinkSlideDeck
    {
        return static::ensureDeckFor($link);
    }

    /**
     * Shared deck bootstrap used by both the web editor and the REST API
     * (Api\SlideDeckController) so the seeded defaults can never drift.
     */
    public static function ensureDeckFor(Link $link): LinkSlideDeck
    {
        $deck = LinkSlideDeck::where('link_id', $link->id)->first();
        if ($deck) return $deck;

        $deck = LinkSlideDeck::create([
            'link_id'      => $link->id,
            'workspace_id' => $link->workspace_id,
            'version'      => 1,
            'is_published' => false,
            'settings'     => [
                'theme'        => ['background' => '#0f172a', 'accent' => '#3d6bff', 'text' => '#f8fafc'],
                'transition'   => 'slide',
                'auto_advance' => 0,
                'loop'         => false,
            ],
        ]);

        // Seed one welcome slide so the editor isn't empty on first open.
        LinkSlide::create([
            'deck_id'    => $deck->id,
            'sort_order' => 0,
            'title'      => 'Welcome',
            'block_ids'  => [],
            'background' => ['type' => 'color', 'color' => '#0f172a'],
            'animation'  => ['enter' => 'fade', 'duration_ms' => 400],
            'transition' => 'slide',
            'settings'   => [],
        ]);

        return $deck;
    }

    public function editor(Request $request, Link $link)
    {
        $this->authorizeLink($link);
        $deck = $this->ensureDeck($link);
        $slides = $deck->slides()->get();

        // Same signed-URL preview pattern conversational mode uses.
        $previewUrl = URL::temporarySignedRoute(
            'redirect.handle',
            now()->addHours(24),
            ['alias' => $link->alias, '_preview' => 1, '_slides_preview' => 1],
            false,
        );

        $blockOptions = BiolinkBlock::where('link_id', $link->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'type', 'settings'])
            ->map(function ($b) {
                $s = is_array($b->settings) ? $b->settings : [];
                $label = $s['title'] ?? $s['text'] ?? $s['label'] ?? $s['heading'] ?? $s['question'] ?? null;
                return [
                    'id'    => $b->id,
                    'type'  => $b->type,
                    'label' => is_string($label) ? mb_substr($label, 0, 60) : null,
                ];
            })
            ->values();

        $deckPayload = [
            'id'           => $deck->id,
            'is_published' => (bool) $deck->is_published,
            'version'      => (int) $deck->version,
            'settings'     => $deck->settings ?? [],
            'mode'         => data_get($link->settings, 'biolink.mode', 'list'),
            'slides'       => $slides->map(fn ($s) => [
                'id'         => $s->id,
                'sort_order' => (int) $s->sort_order,
                'title'      => $s->title,
                'block_ids'      => array_values($s->block_ids ?? []),
                'block_settings' => is_array(($s->settings['block_settings'] ?? null)) ? $s->settings['block_settings'] : (object) [],
                'background' => $s->background ?? ['type' => 'color', 'color' => '#0f172a'],
                'animation'  => $s->animation ?? ['enter' => 'fade', 'duration_ms' => 400],
                'transition' => $s->transition ?? 'slide',
                'settings'   => $s->settings ?? [],
            ])->values(),
        ];

        // In-slide block creation: a curated subset of common biolink block
        // types the user can create without leaving the slides editor. The
        // list respects the same per-plan allowlist the biolink editor
        // enforces (userCanUseBlockType), and creation itself still goes
        // through BiolinkBlockController::store so gating can't drift.
        $creatableTypes = collect([
            'heading', 'paragraph', 'paragraph_rich', 'image', 'image_grid',
            'link', 'link_big', 'list', 'divider', 'spacer', 'alert', 'badge',
            'socials', 'video', 'audio',
        ])
            ->filter(fn ($t) => workspace_owner()->userCanUseBlockType($t))
            ->map(fn ($t) => [
                'type'  => $t,
                'label' => BiolinkBlock::TYPES[$t]['label'] ?? $t,
            ])
            ->values();

        // Per-slide backgrounds re-use the same "template" catalog as the
        // page background so creators get a consistent picker. We pull the
        // active templates here and pass a lightweight payload to the editor.
        $bgTemplates = \App\Modules\Admin\Models\BgTemplate::active()->get(['id', 'name', 'slug', 'preview_color', 'category']);

        return view('user.links.slides.editor', [
            'link'         => $link,
            'deck'         => $deck,
            'deckPayload'  => $deckPayload,
            'blockOptions' => $blockOptions,
            'previewUrl'   => $previewUrl,
            'bgTemplates'  => $bgTemplates,
            'creatableTypes' => $creatableTypes,
        ]);
    }

    public function toggleMode(Request $request, Link $link)
    {
        $this->authorizeLink($link);
        $on = (bool) $request->boolean('enabled');

        $settings = $link->settings ?? [];
        $settings['biolink'] = $settings['biolink'] ?? [];
        $settings['biolink']['mode'] = $on ? 'slides' : 'list';
        $link->update(['settings' => $settings]);

        if ($on) $this->ensureDeck($link);

        return response()->json(['ok' => true, 'mode' => $settings['biolink']['mode']]);
    }

    /**
     * Replace deck (settings + ordered slides) wholesale. Mirrors the
     * ConversationFlowController::save shape — a single endpoint takes
     * the full editor state and atomically swaps the slide rows.
     */
    public function save(Request $request, Link $link)
    {
        $this->authorizeLink($link);
        $deck = $this->ensureDeck($link);

        $data = $request->validate(static::saveRules());

        $data = static::sanitizeSaveData($data, $link);

        static::persistDeck($deck, $link, $data);

        $deck->refresh();

        return response()->json([
            'ok'           => true,
            'is_published' => (bool) $deck->is_published,
            'version'      => (int) $deck->version,
        ]);
    }

    /**
     * Validation rules for a wholesale deck save. Shared with the REST API
     * controller (Api\SlideDeckController) so web and mobile can't drift.
     */
    public static function saveRules(): array
    {
        return [
            'settings'                  => 'nullable|array',
            'settings.theme'            => 'nullable|array',
            'settings.transition'       => 'nullable|string|in:slide,fade,zoom,flip,none',
            'settings.auto_advance'     => 'nullable|integer|min:0|max:60000',
            'settings.loop'             => 'nullable|boolean',
            // Faded ‹ › chevrons rendered on the public deck. Default: true.
            'settings.show_arrows'      => 'nullable|boolean',
            'is_published'              => 'nullable|boolean',
            'slides'                    => 'required|array|min:1|max:50',
            'slides.*.title'            => 'nullable|string|max:160',
            'slides.*.block_ids'        => 'nullable|array|max:10',
            'slides.*.block_ids.*'      => 'integer',
            // Per-block animation/placement overrides keyed by block id:
            // { "<block_id>": { "enter":"fade", "delay_ms":120, "duration_ms":400, "align":"center" } }
            'slides.*.block_settings'           => 'nullable|array',
            'slides.*.block_settings.*'         => 'nullable|array',
            'slides.*.block_settings.*.enter'        => 'nullable|string|in:fade,slide_up,slide_down,slide_left,slide_right,zoom,flip,none',
            'slides.*.block_settings.*.delay_ms'     => 'nullable|integer|min:0|max:10000',
            'slides.*.block_settings.*.duration_ms'  => 'nullable|integer|min:0|max:5000',
            'slides.*.block_settings.*.align'        => 'nullable|string|in:left,center,right,stretch',
            'slides.*.block_settings.*.grid_span'    => 'nullable|integer|min:1|max:12',
            'slides.*.background'       => 'nullable|array',
            'slides.*.background.type'  => 'nullable|string|in:color,gradient,image,slideshow,video,template',
            'slides.*.background.color' => 'nullable|string|max:60',
            'slides.*.background.from_color' => 'nullable|string|max:60',
            'slides.*.background.to_color'   => 'nullable|string|max:60',
            'slides.*.background.image_url'  => 'nullable|string|max:1024',
            // Slideshow: list of image URLs (max 8) and per-image dwell time.
            'slides.*.background.images'        => 'nullable|array|max:8',
            'slides.*.background.images.*'      => 'nullable|string|max:1024',
            'slides.*.background.interval_ms'   => 'nullable|integer|min:500|max:30000',
            // Video background: URL + playback flags.
            'slides.*.background.video_url'     => 'nullable|string|max:1024',
            'slides.*.background.video_loop'    => 'nullable|boolean',
            'slides.*.background.video_muted'   => 'nullable|boolean',
            'slides.*.background.video_autoplay'=> 'nullable|boolean',
            // Template: id from the bg_templates catalog.
            'slides.*.background.template_id'   => 'nullable|integer|min:1',
            // Shared overlay knobs (apply to every type for consistency).
            'slides.*.background.overlay_color'   => 'nullable|string|max:60',
            'slides.*.background.overlay_opacity' => 'nullable|numeric|min:0|max:1',
            'slides.*.animation'        => 'nullable|array',
            'slides.*.animation.enter'  => 'nullable|string|in:fade,slide_up,slide_down,slide_left,slide_right,zoom,flip,none',
            'slides.*.animation.duration_ms' => 'nullable|integer|min:0|max:5000',
            'slides.*.transition'       => 'nullable|string|in:slide,fade,zoom,flip,none',
            'slides.*.settings'         => 'nullable|array',
        ];
    }

    /**
     * Post-validation cleanup shared by web + API saves: restrict block_ids
     * to blocks owned by this link and sanitize background media URLs.
     */
    public static function sanitizeSaveData(array $data, Link $link): array
    {
        // Restrict block_ids to ones actually owned by this link.
        $allowedBlockIds = BiolinkBlock::where('link_id', $link->id)->pluck('id')->all();
        foreach ($data['slides'] as $i => $row) {
            $ids = $row['block_ids'] ?? [];
            $data['slides'][$i]['block_ids'] = array_values(array_intersect(
                array_map('intval', $ids), $allowedBlockIds,
            ));

            // Background media URLs render on the public slides page as
            // <source src> / background-image:url() / <img src>, so they must
            // pass the same sanitizer as biolink block URLs (https?://
            // absolute or /f/... vault paths only). Unsafe values
            // (javascript:, //evil.com, backslash smuggling) are blanked; a
            // blanked url makes the renderer fall back to a plain color.
            $bgc = $row['background'] ?? null;
            if (is_array($bgc)) {
                foreach (['image_url', 'video_url'] as $f) {
                    if (isset($bgc[$f]) && $bgc[$f] !== '') {
                        $bgc[$f] = BiolinkBlockController::sanitizeUrl(trim((string) $bgc[$f]));
                    }
                }
                if (isset($bgc['images']) && is_array($bgc['images'])) {
                    $bgc['images'] = array_values(array_filter(array_map(
                        fn ($u) => BiolinkBlockController::sanitizeUrl(trim((string) $u)),
                        $bgc['images'],
                    )));
                }
                $data['slides'][$i]['background'] = $bgc;
            }
        }

        return $data;
    }

    /**
     * Atomically replace deck settings + slides (and rebuild the published
     * snapshot when publishing). Shared by web + API saves. Expects data
     * already validated (saveRules) and sanitized (sanitizeSaveData).
     */
    public static function persistDeck(LinkSlideDeck $deck, Link $link, array $data): void
    {
        DB::transaction(function () use ($deck, $data, $link) {
            if (isset($data['settings'])) {
                $deck->settings = array_merge($deck->settings ?? [], $data['settings']);
            }

            // Swap slides atomically: nuke + reinsert (only ~50 rows max).
            $deck->slides()->delete();
            foreach ($data['slides'] as $i => $s) {
                $slideSettings = is_array($s['settings'] ?? null) ? $s['settings'] : [];
                // Persist per-block overrides inside the slide's settings JSON
                // so we don't have to introduce another table for what is
                // essentially a small map keyed by block id.
                if (!empty($s['block_settings']) && is_array($s['block_settings'])) {
                    $slideSettings['block_settings'] = $s['block_settings'];
                }
                LinkSlide::create([
                    'deck_id'    => $deck->id,
                    'sort_order' => $i,
                    'title'      => $s['title'] ?? null,
                    'block_ids'  => $s['block_ids'] ?? [],
                    'background' => $s['background'] ?? null,
                    'animation'  => $s['animation'] ?? null,
                    'transition' => $s['transition'] ?? ($deck->settings['transition'] ?? 'slide'),
                    'settings'   => $slideSettings,
                ]);
            }

            $publish = (bool) ($data['is_published'] ?? $deck->is_published);
            if ($publish) {
                $deck->is_published = true;
                $deck->version = (int) $deck->version + 1;
                $deck->save();
                $deck->load('slides');
                $deck->published_snapshot = static::buildSnapshot($deck, $link);
            }
            $deck->save();
        });
    }

    /**
     * Resolve the requested analytics window (period pill or custom from/to)
     * into an inclusive [start, end] day range. Falls back to the last 30
     * days when nothing valid is supplied. Returns null bounds for "all".
     */
    protected function resolveAnalyticsRange(Request $request): array
    {
        $period = (string) $request->query('period', '30d');
        $allowed = ['today', '7d', '30d', '90d', 'year', 'all', 'custom'];
        if (!in_array($period, $allowed, true)) {
            $period = '30d';
        }

        $today = now()->startOfDay();
        $end   = now()->endOfDay();
        $start = null;

        switch ($period) {
            case 'today': $start = $today->copy(); break;
            case '7d':    $start = $today->copy()->subDays(6); break;
            case '90d':   $start = $today->copy()->subDays(89); break;
            case 'year':  $start = $today->copy()->startOfYear(); break;
            case 'all':   $start = null; $end = null; break;
            case 'custom':
                $from = $request->query('from');
                $to   = $request->query('to');
                try {
                    if ($from) $start = Carbon::parse($from)->startOfDay();
                    if ($to)   $end   = Carbon::parse($to)->endOfDay();
                } catch (\Throwable $e) {
                    $start = $today->copy()->subDays(29);
                    $end   = now()->endOfDay();
                    $period = '30d';
                }
                if ($start && $end && $start->gt($end)) {
                    [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
                }
                break;
            case '30d':
            default:
                $start = $today->copy()->subDays(29);
                break;
        }

        return ['period' => $period, 'start' => $start, 'end' => $end];
    }

    /**
     * Per-deck slide analytics: total impressions, unique sessions, per-slide
     * view counts + drop-off, and a daily time series for the selected window.
     * Accepts ?period=today|7d|30d|90d|year|all|custom (with from/to). Mirrors
     * the JSON shape produced by ConversationFlowController::analytics so the
     * front-end pattern stays consistent.
     */
    public function analytics(Request $request, Link $link)
    {
        $this->authorizeLink($link);
        $deck = $this->ensureDeck($link);
        $slides = $deck->slides()->orderBy('sort_order')->get(['id', 'sort_order', 'title']);

        $range = $this->resolveAnalyticsRange($request);
        $start = $range['start'];
        $end   = $range['end'];

        // Entry pings are stored with dwell_ms = NULL; "exit" pings the
        // player fires when leaving a slide carry dwell_ms. Filtering
        // impression-style aggregations to NULL dwell rows keeps the
        // existing counts honest now that exit pings live in the same
        // table.
        $events = LinkSlideViewEvent::where('deck_id', $deck->id)
            ->whereNull('dwell_ms');
        if ($start) $events->where('occurred_at', '>=', $start);
        if ($end)   $events->where('occurred_at', '<=', $end);
        $totalImpressions = (clone $events)->count();
        $uniqueSessions   = (clone $events)
            ->whereNotNull('page_session_id')
            ->distinct('page_session_id')
            ->count('page_session_id');
        // Count distinct sessions that hit a completed event so the rate
        // can't exceed 100% if a session fires multiple completed pings.
        $completedCount   = (clone $events)
            ->where('completed', true)
            ->whereNotNull('page_session_id')
            ->distinct('page_session_id')
            ->count('page_session_id');

        // Per-slide impression and unique-session counts, keyed by slide_index.
        $perIndexCounts = (clone $events)
            ->select('slide_index', DB::raw('COUNT(*) as c'))
            ->groupBy('slide_index')
            ->pluck('c', 'slide_index')
            ->all();

        $perIndexUnique = (clone $events)
            ->whereNotNull('page_session_id')
            ->select('slide_index', DB::raw('COUNT(DISTINCT page_session_id) as c'))
            ->groupBy('slide_index')
            ->pluck('c', 'slide_index')
            ->all();

        // Per-slide average dwell time (ms). Pulled from "exit" pings the
        // player fires when leaving a slide; the server already caps each
        // ping at 10 minutes (see SlideEventController::view) so a tab
        // left open overnight can't poison the average. Scope to the same
        // window as the impression metrics so the avg-time figures match
        // the period pill the user picked.
        $dwellQ = LinkSlideViewEvent::where('deck_id', $deck->id)
            ->whereNotNull('dwell_ms');
        if ($start) $dwellQ->where('occurred_at', '>=', $start);
        if ($end)   $dwellQ->where('occurred_at', '<=', $end);
        $perIndexDwell = $dwellQ
            ->select('slide_index', DB::raw('AVG(dwell_ms) as a'), DB::raw('COUNT(*) as c'))
            ->groupBy('slide_index')
            ->get()
            ->keyBy('slide_index');

        // First-slide impressions form the funnel baseline for drop-off %.
        $firstImpressions = (int) ($perIndexCounts[$slides->first()->sort_order ?? 0] ?? 0);

        $perSlide = [];
        foreach ($slides as $s) {
            $idx = (int) $s->sort_order;
            $views = (int) ($perIndexCounts[$idx] ?? 0);
            $uniq  = (int) ($perIndexUnique[$idx] ?? 0);
            $dwellRow    = $perIndexDwell->get($idx);
            $avgDwellMs  = $dwellRow ? (int) round((float) $dwellRow->a) : null;
            $dwellSample = $dwellRow ? (int) $dwellRow->c : 0;
            $perSlide[] = [
                'index'         => $idx,
                'title'         => $s->title ? Str::limit($s->title, 60) : ('Slide ' . ($idx + 1)),
                'views'         => $views,
                'unique'        => $uniq,
                'drop_off_pct'  => $firstImpressions > 0
                    ? round((($firstImpressions - $views) / $firstImpressions) * 100, 1)
                    : 0,
                'avg_dwell_ms'  => $avgDwellMs,
                'dwell_samples' => $dwellSample,
            ];
        }

        // Daily view trend across the selected window (inclusive). Zero-fill
        // every day so the chart has a continuous x-axis on quiet days.
        // For "all", anchor the series to the first recorded event so we don't
        // walk an empty timeline forward from the epoch.
        $seriesStart = $start ? $start->copy()->startOfDay() : null;
        $seriesEnd   = $end   ? $end->copy()->startOfDay()   : now()->startOfDay();
        if (!$seriesStart) {
            $firstAt = (clone $events)->min('occurred_at');
            $seriesStart = $firstAt
                ? Carbon::parse($firstAt)->startOfDay()
                : $seriesEnd->copy();
        }
        $dayCount = $seriesStart->diffInDays($seriesEnd) + 1;

        $rawSeries = (clone $events)
            ->where('occurred_at', '>=', $seriesStart)
            ->where('occurred_at', '<=', $seriesEnd->copy()->endOfDay())
            ->select(DB::raw('DATE(occurred_at) as d'), DB::raw('COUNT(*) as c'))
            ->groupBy('d')
            ->pluck('c', 'd')
            ->all();

        $series = [];
        for ($i = 0; $i < $dayCount; $i++) {
            $day = $seriesStart->copy()->addDays($i)->toDateString();
            $series[] = ['date' => $day, 'views' => (int) ($rawSeries[$day] ?? 0)];
        }

        return response()->json([
            'deck' => [
                'id'      => $deck->id,
                'version' => (int) $deck->version,
            ],
            'range' => [
                'period' => $range['period'],
                'from'   => $start ? $start->toDateString() : ($series[0]['date'] ?? null),
                'to'     => $end   ? $end->toDateString()   : ($series[count($series) - 1]['date'] ?? null),
            ],
            'total_impressions' => $totalImpressions,
            'unique_sessions'   => $uniqueSessions,
            'completed'         => $completedCount,
            'completion_pct'    => $uniqueSessions > 0
                ? round(($completedCount / $uniqueSessions) * 100, 1)
                : 0,
            'slides'  => $perSlide,
            'series'  => $series,
        ]);
    }

    /**
     * Stream the per-slide analytics as a CSV download. Honors the same
     * period/from/to filters as the JSON analytics endpoint so the export
     * matches what's on screen.
     */
    public function exportCsv(Request $request, Link $link): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeLink($link);
        if (!workspace_owner()?->getPlanFeature('analytics_export', true)) {
            return back()->with('error', 'Exporting stats is a paid feature. Upgrade your plan to download CSV exports.');
        }
        $deck = $this->ensureDeck($link);
        $slides = $deck->slides()->orderBy('sort_order')->get(['id', 'sort_order', 'title']);

        $range = $this->resolveAnalyticsRange($request);
        $start = $range['start'];
        $end   = $range['end'];

        $events = LinkSlideViewEvent::where('deck_id', $deck->id);
        if ($start) $events->where('occurred_at', '>=', $start);
        if ($end)   $events->where('occurred_at', '<=', $end);
        $totalImpressions = (clone $events)->count();
        $uniqueSessions   = (clone $events)
            ->whereNotNull('page_session_id')
            ->distinct('page_session_id')
            ->count('page_session_id');
        $completedCount   = (clone $events)
            ->where('completed', true)
            ->whereNotNull('page_session_id')
            ->distinct('page_session_id')
            ->count('page_session_id');
        $completionPct = $uniqueSessions > 0
            ? round(($completedCount / $uniqueSessions) * 100, 1)
            : 0;

        $perIndexCounts = (clone $events)
            ->select('slide_index', DB::raw('COUNT(*) as c'))
            ->groupBy('slide_index')
            ->pluck('c', 'slide_index')
            ->all();
        $perIndexUnique = (clone $events)
            ->whereNotNull('page_session_id')
            ->select('slide_index', DB::raw('COUNT(DISTINCT page_session_id) as c'))
            ->groupBy('slide_index')
            ->pluck('c', 'slide_index')
            ->all();
        $firstImpressions = (int) ($perIndexCounts[$slides->first()->sort_order ?? 0] ?? 0);

        $fromLabel = $start ? $start->toDateString() : 'all';
        $toLabel   = $end   ? $end->toDateString()   : 'all';
        $rangeLabel = ($start || $end)
            ? ($fromLabel . ' to ' . $toLabel)
            : 'All time';

        $filename = sprintf(
            'slides-analytics-%s-%s-to-%s.csv',
            Str::slug($link->alias ?: ('link-' . $link->id)) ?: 'link',
            $fromLabel,
            $toLabel,
        );

        return new StreamedResponse(function () use (
            $link, $deck, $range, $rangeLabel, $totalImpressions, $uniqueSessions,
            $completedCount, $completionPct, $slides, $perIndexCounts,
            $perIndexUnique, $firstImpressions
        ) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens accents/emoji correctly.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Slides analytics export']);
            fputcsv($out, ['Link', ($link->title ?: $link->alias) . ' (/' . $link->alias . ')']);
            fputcsv($out, ['Deck version', (int) $deck->version]);
            fputcsv($out, ['Period', $range['period']]);
            fputcsv($out, ['Date range', $rangeLabel]);
            fputcsv($out, ['Generated at', now()->toDateTimeString()]);
            fputcsv($out, ['Total impressions', $totalImpressions]);
            fputcsv($out, ['Unique sessions', $uniqueSessions]);
            fputcsv($out, ['Completed decks', $completedCount]);
            fputcsv($out, ['Completion rate %', $completionPct]);
            fputcsv($out, []);

            fputcsv($out, ['Index', 'Title', 'Views', 'Unique sessions', 'Drop-off %']);
            foreach ($slides as $s) {
                $idx   = (int) $s->sort_order;
                $views = (int) ($perIndexCounts[$idx] ?? 0);
                $uniq  = (int) ($perIndexUnique[$idx] ?? 0);
                $drop  = $firstImpressions > 0
                    ? round((($firstImpressions - $views) / $firstImpressions) * 100, 1)
                    : 0;
                fputcsv($out, [
                    $idx + 1,
                    $s->title ?: ('Slide ' . ($idx + 1)),
                    $views,
                    $uniq,
                    $drop,
                ]);
            }

            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store, max-age=0',
        ]);
    }

    /** Render the analytics page (HTML wrapper). */
    public function analyticsPage(Request $request, Link $link)
    {
        $this->authorizeLink($link);
        $deck = $this->ensureDeck($link);
        return view('user.links.slides.analytics', [
            'link' => $link,
            'deck' => $deck,
        ]);
    }

    /**
     * Build a server-rendered snapshot of the deck so public viewers can
     * load a frozen copy of the slides + their hosted block HTML without
     * touching the live editor tables.
     */
    public static function buildSnapshot(LinkSlideDeck $deck, Link $link): array
    {
        $blockMap = BiolinkBlock::withoutGlobalScope('workspace')
            ->where('link_id', $link->id)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $slides = $deck->slides->map(function ($slide) use ($blockMap, $link) {
            $slideSettings   = is_array($slide->settings) ? $slide->settings : [];
            $perBlockOverrides = is_array($slideSettings['block_settings'] ?? null)
                ? $slideSettings['block_settings'] : [];

            $blocks = collect($slide->block_ids ?? [])
                ->map(function ($id) use ($blockMap, $link, $perBlockOverrides) {
                    $b = $blockMap->get((int) $id);
                    if (!$b) return null;
                    $s = is_array($b->settings) ? $b->settings : [];
                    $html = '';
                    try {
                        $html = view('common.partials.biolink-block-render', [
                            'block'     => $b,
                            's'         => $s,
                            'fontColor' => '#ffffff',
                            'link'      => $link,
                        ])->render();
                    } catch (\Throwable $e) {
                        logger()->warning('Slide block render failed: ' . $e->getMessage(), [
                            'block_id' => $b->id, 'link_id' => $link->id,
                        ]);
                    }
                    $override = $perBlockOverrides[(string) $b->id]
                        ?? $perBlockOverrides[(int) $b->id]
                        ?? null;
                    return [
                        'id'        => (int) $b->id,
                        'type'      => (string) $b->type,
                        'settings'  => $s,
                        'html'      => $html,
                        'animation' => is_array($override) ? [
                            'enter'       => $override['enter']       ?? 'fade',
                            'delay_ms'    => (int) ($override['delay_ms']    ?? 0),
                            'duration_ms' => (int) ($override['duration_ms'] ?? 400),
                            'align'       => $override['align']       ?? 'center',
                            'grid_span'   => isset($override['grid_span']) ? (int) $override['grid_span'] : 12,
                        ] : null,
                    ];
                })
                ->filter()
                ->values();

            return [
                'id'         => $slide->id,
                'sort_order' => (int) $slide->sort_order,
                'title'      => $slide->title,
                'background' => $slide->background ?? ['type' => 'color', 'color' => '#0f172a'],
                'animation'  => $slide->animation ?? ['enter' => 'fade', 'duration_ms' => 400],
                'transition' => $slide->transition ?? 'slide',
                'settings'   => $slide->settings ?? [],
                'blocks'     => $blocks,
            ];
        })->values()->all();

        return [
            'version'    => (int) $deck->version,
            'settings'   => $deck->settings ?? [],
            'slides'     => $slides,
            'snapshotted_at' => now()->toIso8601String(),
        ];
    }
}
