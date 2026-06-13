<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Api\Resources\LinkResource;
use App\Modules\User\Controllers\LinkController as UserLinkController;
use App\Modules\User\Models\AbVariant;
use App\Modules\User\Models\Domain;
use App\Modules\User\Models\Link;
use App\Modules\User\Services\BlockAnalyticsAggregator;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LinkController extends Controller
{
    use ApiResponses;

    /**
     * Validation closure constraining domain_id to a domain the caller
     * can actually attach: their own verified+active domains plus
     * admin-global active domains tagged for their plan (or untagged
     * globals open to every plan). Mirrors the web LinkController rule so
     * mobile-created links honour the same allow-list.
     */
    protected function availableDomainRule($user): \Closure
    {
        return function ($attribute, $value, $fail) use ($user) {
            if (empty($value)) return;
            $allowed = Domain::availableTo($user)->pluck('id')->all();
            if (!in_array((int) $value, $allowed, true)) {
                $fail('That domain is not available on your plan.');
            }
        };
    }

    public function index(Request $request)
    {
        $q = Link::where('user_id', $request->user()->id)->with('domain');

        if ($type = $request->string('type')->toString()) {
            $q->where('type', $type);
        }
        if ($search = $request->string('q')->toString()) {
            $q->where(function ($w) use ($search) {
                $w->where('title', 'ilike', "%{$search}%")
                  ->orWhere('alias', 'ilike', "%{$search}%")
                  ->orWhere('long_url', 'ilike', "%{$search}%");
            });
        }

        $page = $q->orderByDesc('id')->paginate(min(100, max(1, (int) $request->input('per_page', 20))));

        return $this->ok([
            'items' => collect($page->items())->map(fn ($l) => LinkResource::toArray($l))->all(),
            'meta'  => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'       => ['required', Rule::in(['short', 'biolink', 'file', 'qr', 'event', 'vcard', 'social', 'sms', 'wifi', 'pdf'])],
            'alias'      => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('links', 'alias')],
            'title'      => ['nullable', 'string', 'max:200'],
            'long_url'   => ['nullable', 'url', 'max:2048'],
            'visibility' => ['nullable', Rule::in(['public', 'registered', 'followers', 'subscribers'])],
            'is_active'  => ['nullable', 'boolean'],
            'seo_title'  => ['nullable', 'string', 'max:200'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'expires_at' => ['nullable', 'date'],
            'settings'   => ['nullable', 'array'],
            // Custom/global domain to host this short link on. Optional —
            // when omitted the link uses the platform default host. The
            // mobile create flow pre-selects the admin-chosen primary
            // global domain, matching the web form.
            'domain_id'  => ['nullable', $this->availableDomainRule($request->user())],
            // Workspace tagging from the browser extension's workspace
            // selector. Optional — older clients (mobile) and
            // single-workspace accounts simply omit it. Persisted on the
            // links row only when the column exists, otherwise stashed
            // under settings.workspace_id so the choice isn't lost.
            'workspace_id' => ['nullable', 'integer'],
            // Auto-fire workspace tracking pixels (Meta / TikTok / Google
            // Ads) when this link is clicked. Optional — when omitted,
            // defaults to true if the workspace has any pixel configured,
            // false otherwise (so links created on workspaces with no
            // pixels stay direct 302s with zero perf cost).
            'auto_pixel'   => ['nullable', 'boolean'],
        ]);

        $alias = $data['alias'] ?? Str::lower(Str::random(7));
        while (Link::where('alias', $alias)->exists()) {
            $alias = Str::lower(Str::random(7));
        }

        $settingsPayload = $data['settings'] ?? [];
        // Fall back to the user's active workspace when the caller (e.g. the
        // mobile app) doesn't pass one, so the link isn't created with
        // workspace_id = null and hidden from the workspace-scoped web list.
        $workspaceId     = $this->resolveWorkspaceId($request->user(), $data['workspace_id'] ?? null);

        $attrs = [
            'user_id'    => $request->user()->id,
            'type'       => $data['type'],
            'alias'      => $alias,
            'title'      => $data['title'] ?? null,
            'long_url'   => $data['long_url'] ?? null,
            'visibility' => $data['visibility'] ?? 'public',
            'is_active'  => $data['is_active'] ?? true,
            'seo_title'  => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'domain_id'  => $data['domain_id'] ?? null,
        ];

        // workspace_id is not mass-assignable; when the column doesn't exist
        // we stash it under settings, otherwise it's set on the model below.
        $hasWorkspaceColumn = Schema::hasColumn('links', 'workspace_id');
        if ($workspaceId !== null && !$hasWorkspaceColumn) {
            $settingsPayload['workspace_id'] = (int) $workspaceId;
        }

        // Auto-pixel default: if the caller didn't specify, derive from
        // the target workspace's pixel configuration. Workspaces with at
        // least one pixel ID configured opt every new link in by default;
        // empty workspaces stay opted out so the redirect remains a
        // direct 302 with no interstitial cost.
        if (Schema::hasColumn('links', 'auto_pixel')) {
            if (array_key_exists('auto_pixel', $data)) {
                $attrs['auto_pixel'] = (bool) $data['auto_pixel'];
            } else {
                $attrs['auto_pixel'] = $this->workspaceHasPixels($workspaceId);
            }
        }

        $attrs['settings'] = $settingsPayload;

        $link = new Link($attrs);
        if ($workspaceId !== null && $hasWorkspaceColumn) {
            $link->workspace_id = (int) $workspaceId;
        }
        $link->save();

        return $this->created(['link' => LinkResource::toArray($link)]);
    }

    public function show(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->with('domain')->find($id);
        if (!$link) return $this->notFound('Link not found');
        return $this->ok(['link' => LinkResource::toArray($link)]);
    }

    public function update(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');

        $data = $request->validate([
            'title'      => ['sometimes', 'nullable', 'string', 'max:200'],
            'long_url'   => ['sometimes', 'nullable', 'url', 'max:2048'],
            'alias'      => ['sometimes', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('links', 'alias')->ignore($link->id)],
            'visibility' => ['sometimes', Rule::in(['public', 'registered', 'followers', 'subscribers'])],
            'is_active'  => ['sometimes', 'boolean'],
            'seo_title'  => ['sometimes', 'nullable', 'string', 'max:200'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'settings'   => ['sometimes', 'nullable', 'array'],
            'auto_pixel' => ['sometimes', 'boolean'],
            'domain_id'  => ['sometimes', 'nullable', $this->availableDomainRule($request->user())],
        ]);

        if (array_key_exists('settings', $data)) {
            // Deep-merge supplied keys into the existing settings JSON so
            // mobile clients can patch a single sub-key (e.g. just
            // `appearance.theme`) without clobbering the rest.
            $existing = (array) ($link->settings ?? []);
            $patch    = (array) ($data['settings'] ?? []);
            $data['settings'] = array_replace_recursive($existing, $patch);
        }

        $link->fill($data)->save();
        return $this->ok(['link' => LinkResource::toArray($link->fresh())]);
    }

    /**
     * Reset all click counters & analytics rows for a link the caller
     * owns. Used by the mobile "Reset" action under link settings.
     */
    public function reset(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');

        DB::transaction(function () use ($link) {
            $link->forceFill([
                'total_clicks'  => 0,
                'unique_clicks' => 0,
            ])->save();

            if (Schema::hasTable('click_events')) {
                DB::table('click_events')->where('link_id', $link->id)->delete();
            }
            if (Schema::hasTable('link_clicks')) {
                DB::table('link_clicks')->where('link_id', $link->id)->delete();
            }
        });

        return $this->ok(['link' => LinkResource::toArray($link->fresh())]);
    }

    /**
     * Read or update the per-biolink rate-limit override that
     * {@see \App\Modules\Common\Services\VisitorRateLimiter} consults
     * on every visit. PATCH-merges into `link.settings.rate_limit` so
     * partial updates (just toggling `enabled`, just bumping the IP
     * cap) don't clobber the other keys.
     */
    public function rateLimit(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');

        if ($request->isMethod('GET')) {
            return $this->ok([
                'rate_limit' => app(\App\Modules\Common\Services\VisitorRateLimiter::class)
                    ->configFor($link),
            ]);
        }

        $data = $request->validate([
            'enabled'    => ['sometimes', 'boolean'],
            'ip_per_min' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'fp_per_min' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ]);

        $settings = (array) ($link->settings ?? []);
        $current  = (array) ($settings['rate_limit'] ?? []);
        foreach ($data as $k => $v) {
            $current[$k] = $v;
        }
        $settings['rate_limit'] = $current;
        $link->settings = $settings;
        $link->save();

        return $this->ok([
            'rate_limit' => app(\App\Modules\Common\Services\VisitorRateLimiter::class)
                ->configFor($link->fresh()),
        ]);
    }

    /** True when the given workspace has any tracking pixel ID configured. */
    protected function workspaceHasPixels(?int $workspaceId): bool
    {
        if (!$workspaceId) return false;
        $ws = \App\Modules\User\Models\Workspace::query()->find($workspaceId);
        if (!$ws) return false;
        $p = (array) (data_get($ws->settings, 'pixels', []) ?? []);
        return !empty($p['meta_id']) || !empty($p['tiktok_id']) || !empty($p['google_id']);
    }

    public function destroy(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');
        $link->delete();
        return $this->noContent();
    }

    /**
     * Per-link analytics summary for the mobile dashboard. Aggregates
     * the click_events table over an optional [from, to] window and
     * groups by day, country, referrer, and device. Falls back to the
     * Link model's denormalised counters when the click_events table is
     * unavailable (older installs / read-replica latency) so the mobile
     * client always gets a usable response.
     */
    public function analytics(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');

        $from = $request->date('from') ?? now()->subDays(30);
        $to   = $request->date('to')   ?? now();

        $payload = [
            'link_id'       => $link->id,
            'alias'         => $link->alias,
            'total_clicks'  => (int) ($link->total_clicks ?? 0),
            'unique_clicks' => (int) ($link->unique_clicks ?? 0),
            'window'        => [
                'from' => $from->toIso8601String(),
                'to'   => $to->toIso8601String(),
            ],
            'by_day'      => [],
            'by_country'  => [],
            'by_referrer' => [],
            'by_device'   => [],
            'by_source'   => [],
        ];

        if (\Schema::hasTable('click_events')) {
            $base = \DB::table('click_events')
                ->where('link_id', $link->id)
                ->whereBetween('created_at', [$from, $to]);

            $payload['by_day'] = (clone $base)
                ->selectRaw("to_char(created_at, 'YYYY-MM-DD') as day, count(*) as clicks")
                ->groupBy('day')->orderBy('day')->get()->all();
            $payload['by_country'] = (clone $base)
                ->selectRaw('country, count(*) as clicks')
                ->groupBy('country')->orderByDesc('clicks')->limit(50)->get()->all();
            $payload['by_referrer'] = (clone $base)
                ->selectRaw('referrer_host, count(*) as clicks')
                ->groupBy('referrer_host')->orderByDesc('clicks')->limit(50)->get()->all();
            $payload['by_device'] = (clone $base)
                ->selectRaw('device_type, count(*) as clicks')
                ->groupBy('device_type')->orderByDesc('clicks')->get()->all();
        }

        // Per-block click summary — list of every block that received clicks
        // in the window with title/type so the mobile app can render a
        // tap-through list on the analytics screen.
        $payload['by_block'] = BlockAnalyticsAggregator::blockSummary($link, $from, $to);

        // A/B variant breakdown — populated when the link was created via
        // the browser extension's "Shorten as A/B test" flow. The popup
        // (and dashboard) renders the per-variant counts and surfaces the
        // current leader.
        $payload['ab_variants'] = $this->abVariantsPayload($link);

        // Mobile-app vs web split — pulled directly from `link_clicks` since
        // that's where the LinkTrackingService records the source tag. Works
        // independently of the optional `click_events` rollup table.
        // Exclude bot + throttled rows so the source split mirrors the
        // human-only totals shown elsewhere on the dashboard. Schema may
        // pre-date `is_throttled` on older installs — guard the column.
        $bySourceQuery = \DB::table('link_clicks')
            ->where('link_id', $link->id)
            ->whereBetween('clicked_at', [$from, $to])
            ->where('is_bot', false);
        if (Schema::hasColumn('link_clicks', 'is_throttled')) {
            $bySourceQuery->where('is_throttled', false);
        }
        $payload['by_source'] = $bySourceQuery
            ->selectRaw("COALESCE(source, 'unknown') as source, count(*) as clicks")
            ->groupBy('source')
            ->orderByDesc('clicks')
            ->get()
            ->all();

        // Bot + throttled traffic the global LinkClick scope hides from
        // the human-only stats above. We surface it here so the mobile
        // dashboard can render the "Blocked X bot attempts this week"
        // badge alongside a small per-day series matching the chart
        // window. Schema may pre-date `is_throttled` on older installs;
        // fall back to bot-only counting in that case.
        $blockedQuery = \DB::table('link_clicks')
            ->where('link_id', $link->id);
        if (Schema::hasColumn('link_clicks', 'is_throttled')) {
            $blockedQuery->where(function ($w) {
                $w->where('is_bot', true)->orWhere('is_throttled', true);
            });
        } else {
            $blockedQuery->where('is_bot', true);
        }

        $weekFrom = now()->subDays(7);
        $payload['blocked_total']      = (int) (clone $blockedQuery)
            ->whereBetween('clicked_at', [$from, $to])->count();
        $payload['blocked_this_week']  = (int) (clone $blockedQuery)
            ->where('clicked_at', '>=', $weekFrom)->count();
        $payload['blocked_by_day']     = (clone $blockedQuery)
            ->whereBetween('clicked_at', [$from, $to])
            ->selectRaw("to_char(clicked_at, 'YYYY-MM-DD') as day, count(*) as clicks")
            ->groupBy('day')->orderBy('day')->get()->all();
        $payload['rate_limit']         = app(\App\Modules\Common\Services\VisitorRateLimiter::class)
            ->configFor($link);

        // Per-rule breakdown for smart links — counts clicks attributed
        // to each rule id stamped by RedirectController. Joined with the
        // link's persisted rule list so the response can include each
        // rule's type/label, not just a raw id.
        if (Schema::hasColumn('link_clicks', 'matched_rule_id')) {
            $rows = DB::table('link_clicks')
                ->where('link_id', $link->id)
                ->whereBetween('clicked_at', [$from, $to])
                ->whereNotNull('matched_rule_id')
                ->selectRaw('matched_rule_id, count(*) as clicks')
                ->groupBy('matched_rule_id')
                ->orderByDesc('clicks')
                ->get();

            $rulesByLabel = collect((array) ($link->settings['smart_rules'] ?? []))
                ->keyBy(fn ($r) => $r['id'] ?? '');
            $payload['by_rule'] = $rows->map(function ($r) use ($rulesByLabel) {
                $rule = $rulesByLabel->get($r->matched_rule_id);
                return [
                    'rule_id' => $r->matched_rule_id,
                    'type'    => $rule['type'] ?? null,
                    'label'   => $rule['label'] ?? null,
                    'clicks'  => (int) $r->clicks,
                ];
            })->values()->all();
        } else {
            $payload['by_rule'] = [];
        }

        return $this->ok(['analytics' => $payload]);
    }

    /**
     * Per-block analytics drill-down. Returns clicks-per-day, top
     * referrers, device split and the visitor-type breakdown
     * (anonymous / registered / follower / subscriber) for one block on
     * the link. Powers the drill-down panel on the mobile analytics
     * screen and the equivalent modal on the web biolink analytics page.
     */
    public function blockAnalytics(Request $request, int $id, int $blockId)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');

        $from = $request->date('from') ?? now()->subDays(30);
        $to   = $request->date('to')   ?? now();

        return $this->ok([
            'analytics' => BlockAnalyticsAggregator::aggregate($link, $blockId, $from, $to),
        ]);
    }

    /**
     * Create a new short link with smart-routing rules attached. Mirrors
     * the regular store() flow but enforces the `link_smart_rules` plan
     * gate and runs the supplied rule list through the shared sanitizer.
     * Used by the browser extension's "Shorten as Smart Link" button.
     */
    public function storeSmart(Request $request)
    {
        $user = $request->user();
        if (!$user->planFeatureEnabled('link_smart_rules')) {
            return $this->fail('Smart links are not available on your current plan.', 402, 'plan_upgrade_required');
        }

        $data = $request->validate([
            'long_url'     => ['required', 'url', 'max:2048'],
            'title'        => ['nullable', 'string', 'max:200'],
            'alias'        => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('links', 'alias')],
            'workspace_id' => ['nullable', 'integer'],
            'rules'        => ['required', 'array', 'min:1'],
        ]);

        $rules = UserLinkController::sanitizeSmartRules(json_encode($data['rules']));
        if (empty($rules)) {
            return $this->fail('No valid smart rules supplied.', 422, 'invalid_rules');
        }
        $maxRules = $this->resolveMaxRules($user);
        if (count($rules) > $maxRules) {
            return $this->fail("Your plan allows up to {$maxRules} rules per smart link.", 422, 'rule_limit_exceeded');
        }

        $alias = $data['alias'] ?? Str::lower(Str::random(7));
        while (Link::where('alias', $alias)->exists()) {
            $alias = Str::lower(Str::random(7));
        }

        // Fall back to the user's active workspace when the caller doesn't
        // pass one, so mobile-created links aren't hidden from the web list.
        $workspaceId = $data['workspace_id'] ?? $this->activeWorkspaceId($user);
        $settings = ['smart_rules' => $rules];
        if (!empty($workspaceId)) {
            if (Schema::hasColumn('links', 'workspace_id')) {
                // attached below via $attrs
            } else {
                $settings['workspace_id'] = (int) $workspaceId;
            }
        }

        $attrs = [
            'user_id'   => $user->id,
            'type'      => 'short',
            'alias'     => $alias,
            'title'     => $data['title'] ?? null,
            'long_url'  => $data['long_url'],
            'is_active' => true,
            'settings'  => $settings,
        ];
        // workspace_id is not mass-assignable; set it directly after build.
        $link = new Link($attrs);
        if (!empty($workspaceId) && Schema::hasColumn('links', 'workspace_id')) {
            $link->workspace_id = (int) $workspaceId;
        }
        $link->save();
        return $this->created(['link' => LinkResource::toArray($link)]);
    }

    /**
     * Read the smart-routing rules attached to a link the caller owns.
     * Returns an empty list when the link has none — used by the
     * extension's inline rule editor.
     */
    public function getRules(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');
        $rules = (array) ($link->settings['smart_rules'] ?? []);
        return $this->ok([
            'link_id' => $link->id,
            'rules'   => array_values($rules),
            'max'     => $this->resolveMaxRules($request->user()),
        ]);
    }

    /**
     * Replace the smart-routing rules on an existing link. Unsets the
     * key entirely when the supplied list sanitizes down to nothing so
     * the link reverts to its plain destination URL.
     */
    public function putRules(Request $request, int $id)
    {
        $user = $request->user();
        $link = Link::where('user_id', $user->id)->find($id);
        if (!$link) return $this->notFound('Link not found');
        if (!$user->planFeatureEnabled('link_smart_rules')) {
            return $this->fail('Smart rules are not available on your current plan.', 402, 'plan_upgrade_required');
        }

        $data = $request->validate([
            'rules' => ['present', 'array'],
        ]);
        $rules = UserLinkController::sanitizeSmartRules(json_encode($data['rules']));
        $maxRules = $this->resolveMaxRules($user);
        if (count($rules) > $maxRules) {
            return $this->fail("Your plan allows up to {$maxRules} rules per smart link.", 422, 'rule_limit_exceeded');
        }

        $settings = (array) ($link->settings ?? []);
        if (empty($rules)) {
            unset($settings['smart_rules']);
        } else {
            $settings['smart_rules'] = $rules;
        }
        $link->settings = $settings;
        $link->save();

        return $this->ok([
            'link_id' => $link->id,
            'rules'   => $rules,
            'max'     => $maxRules,
        ]);
    }

    private function resolveMaxRules($user): int
    {
        $val = $user->getPlanFeature('max_smart_rules', null);
        if ($val === null) return 25;
        $n = (int) $val;
        if ($n < 0) return 25;
        return min(25, max(1, $n));
    }

    // ── A/B testing (browser-extension feature) ─────────────────────────
    //
    // Stored as an `ab_variants` row per destination, plus a flag and
    // (later) a winner pointer under `links.settings.ab_test`. The
    // redirect path performs sticky weighted assignment per visitor and
    // increments the per-variant counters.

    /**
     * Create a new short link AND its A/B variants in one shot, mirroring
     * the popup's single submission. Validates per-plan max-variants and
     * weight-sum-100 invariants.
     */
    public function storeAb(Request $request)
    {
        $data = $request->validate([
            'title'        => ['nullable', 'string', 'max:200'],
            'alias'        => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('links', 'alias')],
            'workspace_id' => ['nullable', 'integer'],
            'variants'     => ['required', 'array', 'min:2'],
            'variants.*.label' => ['nullable', 'string', 'max:120'],
            'variants.*.url'   => ['required', 'url', 'max:2048'],
            'variants.*.weight' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        if (!$this->planAllowsAbTests($user)) {
            return $this->forbidden('A/B testing is not available on your current plan.');
        }
        $maxVariants = $this->planMaxAbVariants($user);
        if (count($data['variants']) > $maxVariants) {
            return $this->forbidden("Your plan allows at most {$maxVariants} variants per A/B test.");
        }

        $sum = array_sum(array_map(fn ($v) => (int) $v['weight'], $data['variants']));
        if ($sum !== 100) {
            return $this->fail('Variant weights must sum to 100.', 422, 'invalid_weights');
        }

        $alias = $data['alias'] ?? Str::lower(Str::random(7));
        while (Link::where('alias', $alias)->exists()) {
            $alias = Str::lower(Str::random(7));
        }

        $settings = ['ab_test' => [
            'enabled'           => true,
            'created_at'        => now()->toIso8601String(),
            'winner_variant_id' => null,
        ]];
        // Fall back to the user's active workspace when the caller doesn't
        // pass one, so mobile-created links aren't hidden from the web list.
        $workspaceId = $data['workspace_id'] ?? $this->activeWorkspaceId($user);
        if ($workspaceId) {
            if (Schema::hasColumn('links', 'workspace_id')) {
                $linkExtra = ['workspace_id' => (int) $workspaceId];
            } else {
                $settings['workspace_id'] = (int) $workspaceId;
                $linkExtra = [];
            }
        } else {
            $linkExtra = [];
        }

        // Use the first variant's URL as the link's "default" long_url
        // so any non-AB code path (link expired, AB cleared, etc.) still
        // has a valid destination.
        $firstUrl = $data['variants'][0]['url'];

        $link = DB::transaction(function () use ($user, $alias, $data, $settings, $linkExtra, $firstUrl) {
            // workspace_id is not mass-assignable; set it directly after build.
            $link = new Link([
                'user_id'    => $user->id,
                'type'       => 'url',
                'alias'      => $alias,
                'title'      => $data['title'] ?? null,
                'long_url'   => $firstUrl,
                'visibility' => 'public',
                'is_active'  => true,
                'settings'   => $settings,
            ]);
            if (!empty($linkExtra['workspace_id'])) {
                $link->workspace_id = (int) $linkExtra['workspace_id'];
            }
            $link->save();

            foreach ($data['variants'] as $i => $v) {
                AbVariant::create([
                    'link_id'    => $link->id,
                    'label'      => $v['label'] ?? null,
                    'url'        => $v['url'],
                    'weight'     => (int) $v['weight'],
                    'sort_order' => $i,
                ]);
            }
            return $link;
        });

        return $this->created([
            'link'     => LinkResource::toArray($link->fresh()),
            'variants' => $this->abVariantsPayload($link),
        ]);
    }

    /**
     * Inspect a single A/B test — variants, per-variant counts, leader.
     * Used by the popup's recent-tests widget every time it's opened.
     */
    public function showAb(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');
        return $this->ok([
            'link'     => LinkResource::toArray($link),
            'variants' => $this->abVariantsPayload($link),
        ]);
    }

    /**
     * List all the user's A/B tests. Powers the popup's "Recent A/B
     * tests" section. Capped server-side at the most recent 10 to keep
     * the popup snappy.
     */
    public function indexAb(Request $request)
    {
        $links = Link::where('user_id', $request->user()->id)
            ->whereHas('abVariants')
            ->with(['abVariants'])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return $this->ok([
            'items' => $links->map(fn ($l) => [
                'link'     => LinkResource::toArray($l),
                'variants' => $this->abVariantsPayload($l),
            ])->all(),
        ]);
    }

    /**
     * End the test by re-pointing the short link permanently to the
     * chosen variant. Variants stay in the table (so the dashboard can
     * still render the historical results), but the redirect path will
     * stop bucketing and just send everyone to `long_url`.
     */
    public function declareAbWinner(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');

        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
        ]);

        $winner = AbVariant::where('link_id', $link->id)->find($data['variant_id']);
        if (!$winner) return $this->notFound('Variant not found');

        DB::transaction(function () use ($link, $winner) {
            AbVariant::where('link_id', $link->id)->update(['is_winner' => false]);
            $winner->forceFill(['is_winner' => true])->save();

            $settings = (array) ($link->settings ?? []);
            $settings['ab_test'] = array_merge((array) ($settings['ab_test'] ?? []), [
                'winner_variant_id' => $winner->id,
                'winner_url'        => $winner->url,
                'declared_at'       => now()->toIso8601String(),
            ]);
            $link->forceFill([
                'settings' => $settings,
                'long_url' => $winner->url,
            ])->save();
        });

        return $this->ok([
            'link'     => LinkResource::toArray($link->fresh()),
            'variants' => $this->abVariantsPayload($link->fresh()),
        ]);
    }

    /**
     * Per-variant counts + the current leader (by clicks). Returns an
     * empty array for non-AB links so callers can use a single shape.
     *
     * @return array{enabled:bool,winner_variant_id:?int,leader_variant_id:?int,variants:array}
     */
    protected function abVariantsPayload(Link $link): array
    {
        $variants = $link->abVariants()->get();
        if ($variants->isEmpty()) {
            return ['enabled' => false, 'winner_variant_id' => null, 'leader_variant_id' => null, 'variants' => []];
        }

        $items = $variants->map(fn ($v) => [
            'id'        => $v->id,
            'label'     => $v->label,
            'url'       => $v->url,
            'weight'    => (int) $v->weight,
            'visitors'  => (int) $v->visitors,
            'clicks'    => (int) $v->clicks,
            'is_winner' => (bool) $v->is_winner,
        ])->all();

        $leader = collect($items)->sortByDesc('clicks')->first();

        return [
            'enabled'            => true,
            'winner_variant_id'  => (int) (data_get($link->settings, 'ab_test.winner_variant_id') ?: 0) ?: null,
            'leader_variant_id'  => $leader ? (int) $leader['id'] : null,
            'variants'           => $items,
        ];
    }

    protected function planAllowsAbTests($user): bool
    {
        $features = $user->plan?->features ?? [];
        // Default: paid plans get it. Free plan keeps it off via explicit
        // `ab_tests => false`. Older installs without the flag fall back
        // to `link_smart_rules` so existing capability is honored.
        if (array_key_exists('ab_tests', $features)) {
            return (bool) $features['ab_tests'];
        }
        return (bool) ($features['link_smart_rules'] ?? false);
    }

    protected function planMaxAbVariants($user): int
    {
        $features = $user->plan?->features ?? [];
        $max = $features['ab_max_variants'] ?? 4;
        $max = (int) $max;
        return $max > 0 ? min($max, 4) : 4;
    }
}
