<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Concerns\RespondsWithUploadErrors;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Services\WorkspaceActivityRecorder;
use App\Modules\User\Support\BlockDefaults;
use App\Modules\User\Support\BlockRenderCoverage;
use App\Modules\User\Support\BlockVariantCatalog;
use App\Modules\User\Support\FontCatalog;
use App\Services\OgMetadataService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BiolinkBlockController extends Controller
{
    use RespondsWithUploadErrors;

    public function editor(Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id() || !$link->isBiolinkFamily(), 403);
        $blocks = $link->biolinkBlocks()->whereNull('parent_id')->orderBy('sort_order')->get();
        $blocks->load('children');
        $blockTypes = BiolinkBlock::TYPES;
        $blockCategories = BiolinkBlock::CATEGORIES;

        // Poll tallies for this biolink's poll blocks. We collect every poll
        // block id (top-level + children of card containers), then run a
        // single aggregate query so the block list renders without N+1
        // overhead even for biolinks with several polls.
        $pollBlockIds = [];
        foreach ($blocks as $b) {
            if ($b->type === 'poll') $pollBlockIds[] = $b->id;
            foreach ($b->children ?? [] as $c) {
                if ($c->type === 'poll') $pollBlockIds[] = $c->id;
            }
        }
        $pollTallies = [];
        if (!empty($pollBlockIds)) {
            $rows = \App\Modules\User\Models\PollVote::whereIn('block_id', $pollBlockIds)
                ->selectRaw('block_id, option_index, COUNT(*) as c')
                ->groupBy('block_id', 'option_index')
                ->get();
            foreach ($rows as $row) {
                $bid = (int) $row->block_id;
                $idx = (int) $row->option_index;
                $pollTallies[$bid]['counts'][$idx] = (int) $row->c;
                $pollTallies[$bid]['total'] = ($pollTallies[$bid]['total'] ?? 0) + (int) $row->c;
            }
        }

        // The three palette dropdown lists (Forms / Buzz / AI Companions) are
        // small, change rarely, and are re-fetched on every editor open. Over a
        // distant DB each is its own round-trip, so we cache them per owner +
        // active workspace with a short backstop TTL. The lists are busted
        // immediately on any create/update/delete of the underlying model
        // (see each model's booted() editor-cache hook), so the TTL only guards
        // against rare bulk writes that bypass model events.
        $wsId = (app()->bound('current_workspace') && app('current_workspace'))
            ? app('current_workspace')->id
            : 'none';
        $ownerId = workspace_owner_id();

        $userForms = \Illuminate\Support\Facades\Cache::remember(
            "editor:forms:" . auth()->id() . ":{$wsId}",
            120,
            fn () => auth()->user()->forms()
                ->orderByDesc('id')
                ->get(['id', 'title', 'slug', 'is_active'])
                ->map(fn ($f) => [
                    'id'        => $f->id,
                    'title'     => $f->title,
                    'slug'      => $f->slug,
                    'is_active' => (bool) $f->is_active,
                ])->values()->all()
        );

        $userBuzz = \Illuminate\Support\Facades\Cache::remember(
            "editor:buzz:{$ownerId}:{$wsId}",
            120,
            fn () => \App\Modules\User\Models\SocialProof::where('user_id', $ownerId)
                ->orderByDesc('id')
                ->get(['id', 'name', 'type', 'is_active'])
                ->map(fn ($b) => [
                    'id'        => $b->id,
                    'name'      => $b->name,
                    'type'      => $b->type,
                    'is_active' => (bool) $b->is_active,
                ])->values()->all()
        );

        // AI Companions the owner can drop into a biolink block. We
        // restrict to the `biolink` placement so users don't accidentally
        // pick an embed-only or inbox-only companion.
        $userCompanions = \Illuminate\Support\Facades\Cache::remember(
            "editor:companions:{$ownerId}",
            120,
            fn () => \App\Modules\User\Models\AiCompanion::where('user_id', $ownerId)
                ->where('placement', 'biolink')
                ->orderByDesc('id')
                ->get(['id', 'public_id', 'name', 'is_disabled'])
                ->map(fn ($c) => [
                    'id'          => $c->id,
                    'public_id'   => $c->public_id,
                    'name'        => $c->name,
                    'is_disabled' => (bool) $c->is_disabled,
                ])->values()->all()
        );

        return view('user.links.biolink-editor', compact(
            'link', 'blocks', 'blockTypes', 'blockCategories', 'userForms', 'userBuzz', 'userCompanions', 'pollTallies'
        ));
    }

    public function settings(Link $link)
    {
        if ($link->isDesignLocked()) {
            return redirect()->route('user.links.settings.advanced', $link);
        }
        return redirect()->route('user.links.settings.appearance', $link);
    }

    public function settingsAppearance(Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id() || !$link->isBiolinkFamily(), 403);
        if ($link->isDesignLocked()) {
            return redirect()->route('user.links.settings.advanced', $link);
        }
        // Order templates as a "color grid": neutrals first (sorted by
        // lightness, brightest → darkest), then a rainbow sweep through
        // the colour wheel (red → orange → yellow → green → cyan → blue
        // → purple → magenta → red), with lightness as a tiebreaker
        // inside each hue band so adjacent swatches look related.
        $bgTemplates = \App\Modules\Admin\Models\BgTemplate::active()->get()
            ->sortBy(fn ($t) => $this->bgTemplateColorSortKey($t))
            ->values();
        $link->load(['pixels', 'aliases']);
        $projects = auth()->user()->projects()->orderBy('name')->get();
        $pixels = auth()->user()->pixels()->orderBy('name')->get();
        $domains = \App\Modules\User\Models\Domain::availableTo(auth()->user())->get();
        return view('user.links.settings.appearance', compact('link', 'bgTemplates', 'projects', 'pixels', 'domains'));
    }

    /**
     * Build a sort key that arranges background templates as a colour
     * grid (neutrals first, then a rainbow sweep). Returns a numeric
     * key that's safe to use with Collection::sortBy.
     *
     * Format: bucket * 1_000_000 + hueBand * 1000 + lightness, where
     *   bucket    = 0 for neutrals (saturation < ~12%), 1 for colours
     *   hueBand   = floor(hue/15)  → 24 bands, 15° wide
     *   lightness = 0..999, descending light first within the band
     */
    private function bgTemplateColorSortKey(\App\Modules\Admin\Models\BgTemplate $t): int
    {
        $rgb = $this->extractFirstRgb((string) $t->preview_color);
        if ($rgb === null) {
            // Things we couldn't parse (data-URI svg patterns etc.) get
            // pushed to the very end so the rainbow stays clean.
            return 9_000_000_000;
        }
        [$h, $s, $l] = $this->rgbToHsl($rgb[0], $rgb[1], $rgb[2]);

        // Neutrals: very low saturation OR extreme lightness (near
        // pure black / white) — order by lightness descending.
        if ($s < 12 || $l > 96 || $l < 6) {
            $lightDesc = (int) round(999 - ($l * 9.99));
            return 0 + $lightDesc;
        }

        $hueBand   = (int) floor($h / 15);                     // 0..23
        $lightDesc = (int) round(999 - ($l * 9.99));           // light → dark
        return 1_000_000 + ($hueBand * 1000) + $lightDesc;
    }

    /**
     * Extract the first colour referenced in a CSS background string.
     * Handles `#rgb`, `#rrggbb`, `rgb()` and `rgba()`. Returns null if
     * nothing parseable is found (e.g. pure SVG data-URI patterns).
     *
     * @return array{0:int,1:int,2:int}|null
     */
    private function extractFirstRgb(string $css): ?array
    {
        if (preg_match('/rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})/i', $css, $m)) {
            return [(int)$m[1], (int)$m[2], (int)$m[3]];
        }
        if (preg_match('/#([0-9a-f]{6})\b/i', $css, $m)) {
            $hex = $m[1];
            return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        }
        if (preg_match('/#([0-9a-f]{3})\b/i', $css, $m)) {
            $hex = $m[1];
            return [hexdec(str_repeat($hex[0], 2)), hexdec(str_repeat($hex[1], 2)), hexdec(str_repeat($hex[2], 2))];
        }
        return null;
    }

    /**
     * Convert sRGB (0–255) to HSL where H is 0–360, S/L are 0–100.
     *
     * @return array{0:float,1:float,2:float}
     */
    private function rgbToHsl(int $r, int $g, int $b): array
    {
        // Clamp to the legal sRGB range so a malformed `rgb(300, 300, 300)`
        // captured from CSS can't break the maths downstream.
        $r = max(0, min(255, $r));
        $g = max(0, min(255, $g));
        $b = max(0, min(255, $b));

        $rf = $r / 255; $gf = $g / 255; $bf = $b / 255;
        $max = max($rf, $gf, $bf); $min = min($rf, $gf, $bf);
        $l = ($max + $min) / 2;
        $d = $max - $min;

        // Pick the saturation denominator first so we can test it for
        // zero before dividing. Both denominators collapse to 0 for
        // pure black / pure white (and for any case where $d itself is
        // zero), so this also subsumes the achromatic guard.
        $denom = $l > 0.5 ? (2 - $max - $min) : ($max + $min);
        if ($d <= 0.0 || $denom <= 0.0) {
            return [0.0, 0.0, $l * 100];
        }

        $s = $d / $denom;
        switch ($max) {
            case $rf: $h = (($gf - $bf) / $d) + ($gf < $bf ? 6 : 0); break;
            case $gf: $h = (($bf - $rf) / $d) + 2; break;
            default:  $h = (($rf - $gf) / $d) + 4;
        }
        $h *= 60;
        return [$h, $s * 100, $l * 100];
    }

    public function settingsLayout(Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id() || !$link->isBiolinkFamily(), 403);
        if ($link->isDesignLocked()) {
            return redirect()->route('user.links.settings.advanced', $link);
        }
        return view('user.links.settings.layout', compact('link'));
    }

    public function settingsBlockTheme(Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id() || !$link->isBiolinkFamily(), 403);
        if ($link->isDesignLocked()) {
            return redirect()->route('user.links.settings.advanced', $link);
        }
        return view('user.links.settings.block-theme', compact('link'));
    }

    public function settingsAdvanced(Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id() || !$link->isBiolinkFamily(), 403);
        return view('user.links.settings.advanced', compact('link'));
    }

    public function settingsEmbed(Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id() || !$link->isBiolinkFamily(), 403);
        return view('user.links.settings.embed', compact('link'));
    }

    public function store(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id() || !$link->isBiolinkFamily(), 403);

        $validated = $request->validate([
            'type' => 'required|string|in:' . implode(',',
                array_unique(array_merge(
                    array_keys(BiolinkBlock::TYPES),
                    array_keys(\App\Modules\User\Support\BlockTypeRegistry::aliases())
                ))),
            'settings' => 'nullable|array',
            'is_active' => 'boolean',
            'parent_id' => 'nullable|integer|exists:biolink_blocks,id',
            'insert_after' => 'nullable|integer|exists:biolink_blocks,id',
        ]);

        // Plan gating: block_types_allowed restricts the catalog of block
        // slugs a non-super-admin can add. '*' or missing entry = all.
        if (!workspace_owner()->userCanUseBlockType($validated['type'])) {
            $message = "The '" . ($validated['type']) . "' block isn't available on your current plan. Upgrade to unlock it.";
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'error' => $message], 403);
            }
            return back()->with('error', $message);
        }

        // Selling gate: the product block powers "Sell from your bio", which
        // is the `ecommerce` plan feature shown in the pricing matrix. Tiers
        // whose block allowlist includes `product` set ecommerce=true (see
        // PlansAndAddonsSeeder), so this flag is the authoritative gate and
        // keeps the pricing matrix honest with real enforcement. Super-admins
        // bypass via the plan-limits permission, matching userCanUseBlockType.
        if (\App\Modules\User\Support\BlockTypeRegistry::canonical($validated['type']) === 'product'
            && !workspace_owner()->hasPermission('user.plan_limits.bypass')
            && !workspace_owner()->planFeatureEnabled('ecommerce')) {
            $message = "Selling from your bio isn't available on your current plan. Upgrade to add product blocks with checkout.";
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'error' => $message], 403);
            }
            return back()->with('error', $message);
        }

        $parentId = $validated['parent_id'] ?? null;
        $insertAfterId = $validated['insert_after'] ?? null;

        if ($insertAfterId) {
            $afterBlock = BiolinkBlock::where('id', $insertAfterId)->where('link_id', $link->id)->firstOrFail();
            $parentId = $afterBlock->parent_id;
            $newSortOrder = $afterBlock->sort_order + 1;

            if ($parentId) {
                BiolinkBlock::where('parent_id', $parentId)
                    ->where('link_id', $link->id)
                    ->where('sort_order', '>=', $newSortOrder)
                    ->increment('sort_order');
            } else {
                $link->biolinkBlocks()
                    ->whereNull('parent_id')
                    ->where('sort_order', '>=', $newSortOrder)
                    ->increment('sort_order');
            }
            $sortOrder = $newSortOrder;
        } else {
            if ($parentId) {
                $parentBlock = BiolinkBlock::where('id', $parentId)->where('link_id', $link->id)->whereIn('type', BiolinkBlock::CONTAINER_TYPES)->firstOrFail();
                $maxSort = $parentBlock->children()->max('sort_order') ?? -1;
            } else {
                $maxSort = $link->biolinkBlocks()->whereNull('parent_id')->max('sort_order') ?? -1;
            }
            $sortOrder = $maxSort + 1;
        }

        // Placement render-gap guard: refuse a placement that would render
        // blank — a child-only block at the page root, or a top-level-only
        // block inside a container. Mirrors BlockRenderCoverage so the editor
        // can't silently create the same gap the template validators catch.
        $placementError = $this->placementRenderError($validated['type'], $parentId !== null);
        if ($placementError !== null) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'error' => $placementError], 422);
            }
            return back()->with('error', $placementError);
        }

        // Treat an empty settings array as "not provided" so
        // partial-settings callers (mobile, gallery shortcuts) still
        // receive the seeded defaults.
        $defaults = BlockDefaults::contentForType($validated['type']);
        $incoming = $validated['settings'] ?? [];
        $settings = empty($incoming)
            ? $defaults
            : array_replace($defaults, $incoming);

        // Seed `_style` only when the caller didn't supply one.
        if (!isset($settings['_style']) || !is_array($settings['_style']) || $settings['_style'] === []) {
            $settings['_style'] = $this->sanitizeBlockStyle(array_merge(
                BiolinkBlock::STYLE_DEFAULTS,
                BlockDefaults::styleForType($validated['type'])
            ));
        }

        // Design lock: new blocks on a locked page always inherit the
        // template's styling for their type (falling back to defaults when
        // the template had no styled block of this type). Any client-sent
        // `_style` is ignored — styling is server-owned while locked.
        if ($link->isDesignLocked()) {
            $settings['_style'] = $this->sanitizeBlockStyle(array_merge(
                BiolinkBlock::STYLE_DEFAULTS,
                BlockDefaults::styleForType($validated['type']),
                $link->designLockStyleFor($validated['type']) ?? []
            ));
            unset($settings['_style_custom_snapshot']);
        }

        // Snapshot the seeded *content* fields so update() can later tell
        // whether the creator actually edited the placeholder copy or
        // just saved unrelated changes (visibility, schedule, design,
        // max_clicks, etc). Filtering out meta keys keeps the comparison
        // tight: it only fires when a seeded text/media field differs.
        if (!empty($settings['_placeholder'])) {
            $seedKeys = array_diff(
                array_keys($settings),
                ['_placeholder', '_placeholder_seed', '_style', '_style_custom_snapshot', '_visibility', '_link', '_limits', '_variant', '_variant_version']
            );
            $seed = [];
            foreach ($seedKeys as $k) { $seed[$k] = $settings[$k]; }
            $settings['_placeholder_seed'] = $seed;
        }

        $settings = $this->sanitizeSettings($validated['type'], $settings);

        $block = $link->biolinkBlocks()->create([
            'type' => $validated['type'],
            'settings' => $settings,
            'sort_order' => $sortOrder,
            'is_active' => $validated['is_active'] ?? true,
            'parent_id' => $parentId,
        ]);

        // Notify followers about new biolink content (daily debounce per creator).
        $this->emitBlockAddedFeedEvent($link, $block);

        $this->recordBlockActivity('biolink.block.create', $link, $block);

        if ($request->ajax()) {
            $block->load('children');
            $viewData = [
                'link'        => $link,
                'blockTypes'  => BiolinkBlock::TYPES,
                'catColors'   => BiolinkBlock::CATEGORY_COLORS,
                'pollTallies' => [],
            ];

            $payload = [
                'success'      => true,
                'block'        => $block,
                'insert_after' => $request->input('insert_after'),
            ];

            if ($parentId) {
                $payload['parent_id']  = $parentId;
                $payload['child_html'] = view('user.links.partials.block-child-card', array_merge($viewData, ['child' => $block]))->render();
            } else {
                $payload['html'] = view('user.links.partials.block-card', array_merge($viewData, ['block' => $block]))->render();
            }

            return response()->json($payload);
        }

        return redirect()->route('user.links.blocks.editor', $link)->with('success', 'Block added.');
    }

    /**
     * Emit a follower feed event + notifications for a newly-added biolink
     * block. Debounced once-per-day per creator so editing sprees don't spam.
     */
    protected function emitBlockAddedFeedEvent(\App\Modules\User\Models\Link $link, BiolinkBlock $block): void
    {
        try {
            $creatorId = $link->user_id;
            $today     = now()->toDateString();

            $alreadyEmittedToday = \App\Modules\User\Models\FeedEvent::where('user_id', $creatorId)
                ->where('type', 'block_added')
                ->whereDate('occurred_at', $today)
                ->exists();
            if ($alreadyEmittedToday) return;

            $creator = $link->user;
            \App\Modules\User\Models\FeedEvent::create([
                'user_id'     => $creatorId,
                'type'        => 'block_added',
                'occurred_at' => now(),
                'data'        => [
                    'creator_name'   => $creator?->name,
                    'creator_avatar' => \App\Support\PublicStorageUrl::resolve($creator?->creatorAvatarRaw()),
                    'link_alias'     => $link->alias,
                    'block_type'     => $block->type,
                    'block_label'    => BiolinkBlock::TYPES[$block->type] ?? $block->type,
                ],
            ]);

            // Per-follower in-app notifications, only if creator opted in.
            if ($creator && $creator->notify_follower_updates) {
                $followerIds = \App\Modules\User\Models\Follow::where('creator_id', $creatorId)->pluck('follower_id');
                foreach ($followerIds as $fid) {
                    \App\Modules\User\Models\UserNotification::create([
                        'user_id'    => $fid,
                        'type'       => 'creator_update',
                        'data'       => [
                            'creator_id'   => $creatorId,
                            'creator_name' => $creator->name,
                            'message'      => "{$creator->name} added something new to their biolink.",
                            'link_alias'   => $link->alias,
                        ],
                        'created_at' => now(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('block_added feed event failed: ' . $e->getMessage());
        }
    }

    /**
     * Return a human-readable error if placing a block of $type in the given
     * placement (container-child vs page-root) would render blank, or null if
     * the placement is fine. Coverage is derived from the actual blade
     * renderers via {@see BlockRenderCoverage}, so this can never drift from
     * what really renders. Only known types reach the callers (validation
     * rejects unknown ones first), so a false here always means a real gap.
     */
    protected function placementRenderError(string $type, bool $isChild): ?string
    {
        $label = BiolinkBlock::TYPES[$type]['label'] ?? $type;

        if ($isChild) {
            if (! BlockRenderCoverage::rendersAsChild($type)) {
                return "The \"{$label}\" block can't go inside a container — there it would render "
                    . 'as a generic placeholder. Place it directly on the page instead.';
            }
            return null;
        }

        if (! BlockRenderCoverage::rendersTopLevel($type)) {
            return "The \"{$label}\" block can only go inside a container — at the page root it "
                . 'would render blank. Add it inside a card or grid block.';
        }

        return null;
    }

    protected function recordBlockActivity(string $action, Link $link, BiolinkBlock $block): void
    {
        $label = (string) ($block->settings['title'] ?? $block->settings['heading'] ?? $block->settings['label'] ?? $block->type);
        WorkspaceActivityRecorder::record(
            null, $action, 'biolink', $block->id,
            ($link->title ?: $link->alias) . ' — ' . $label,
            route('user.links.blocks.editor', $link),
            ['link_id' => $link->id, 'block_type' => $block->type],
        );
    }

    public function update(Request $request, Link $link, BiolinkBlock $block)
    {
        abort_if($link->user_id !== workspace_owner_id() || $block->link_id !== $link->id, 403);

        $validated = $request->validate([
            'settings' => 'nullable|array',
            'style' => 'nullable|array',
            'is_active' => 'boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'visibility' => 'nullable|array',
            // Task #1094 — global click cap (null/0 = unlimited). The
            // running tally lives in `click_count` and is bumped by the
            // tracking service; the editor never writes to it.
            'max_clicks' => 'nullable|integer|min:0|max:10000000',
        ]);

        $settings = $validated['settings'] ?? $block->settings;
        $settings = $this->sanitizeSettings($block->type, $settings);

        // Clear the `_placeholder` flag only when the caller actually
        // edited a seeded field. Loose equality so nested arrays
        // compare element-wise.
        $existingSeed = $block->settings['_placeholder_seed'] ?? null;
        $wasPlaceholder = !empty($block->settings['_placeholder']);
        if ($wasPlaceholder && is_array($existingSeed)) {
            $touched = false;
            foreach ($existingSeed as $k => $seedVal) {
                if (($settings[$k] ?? null) != $seedVal) { $touched = true; break; }
            }
            if ($touched) {
                unset($settings['_placeholder'], $settings['_placeholder_seed']);
            } else {
                $settings['_placeholder'] = true;
                $settings['_placeholder_seed'] = $existingSeed;
            }
        } else {
            unset($settings['_placeholder'], $settings['_placeholder_seed']);
        }

        if (in_array($block->type, ['verified_heading', 'verified_avatar'])) {
            $existing = $block->settings;
            if ($block->type === 'verified_heading') {
                $settings['text'] = $existing['text'] ?? '';
                $settings['verified'] = 1;
                $settings['locked_text'] = 1;
            }
            if ($block->type === 'verified_avatar') {
                $settings['image_url'] = $existing['image_url'] ?? '';
                $settings['verified'] = 1;
                $settings['locked_image'] = 1;
            }
        }

        $settings['_visibility'] = $this->sanitizeVisibility($validated['visibility'] ?? ($block->settings['_visibility'] ?? []));
        $existingStyle = $block->settings['_style'] ?? [];
        $incomingStyle = $validated['style'] ?? [];

        // Design lock: styling is read-only while the page is bound to a
        // design-locked template — silently ignore any submitted style so
        // content edits still save normally.
        if ($link->isDesignLocked()) {
            $incomingStyle = [];
        }

        // Task #4025: an explicitly-submitted empty value is a "clear this
        // key" instruction (e.g. wiping the Background Color text field back
        // to Transparent). sanitizeBlockStyle() skips empty values, so
        // without this the merge below would silently keep the old value
        // and users could never return a block to transparent/inherit.
        if (is_array($incomingStyle)) {
            foreach ($incomingStyle as $k => $v) {
                if ($v === '' || $v === null) {
                    unset($existingStyle[$k]);
                }
            }
        }

        $sanitized = $this->sanitizeBlockStyle(array_merge($existingStyle, $incomingStyle));

        // Variant application now flows through the dedicated
        // applyVariant endpoint (full _style replace, snapshot-aware).
        // The standard form-based update() merges incoming style into the
        // existing _style — that's the right behaviour for granular
        // tweaks but would leak prior-variant residue if used to swap
        // skins. We only need to preserve any existing custom snapshot
        // here so visibility/dates/etc. saves don't drop it.
        if (array_key_exists('_style_custom_snapshot', $block->settings ?? [])) {
            $settings['_style_custom_snapshot'] = $block->settings['_style_custom_snapshot'];
        }
        $settings['_style'] = $sanitized;

        // Task #1094 — extracting `max_clicks` here so the field is only
        // touched when the editor actually sent it (avoids zeroing out
        // the cap on partial saves from older form versions). 0/null
        // both collapse to "unlimited".
        $maxClicksUpdate = [];
        if (array_key_exists('max_clicks', $validated)) {
            $mc = $validated['max_clicks'];
            $maxClicksUpdate['max_clicks'] = ($mc === null || (int) $mc <= 0) ? null : (int) $mc;
        }

        $block->update(array_merge([
            'settings' => $settings,
            'is_active' => $validated['is_active'] ?? $block->is_active,
            'start_date' => $validated['start_date'] ?? $block->start_date,
            'end_date' => $validated['end_date'] ?? $block->end_date,
        ], $maxClicksUpdate));

        $this->recordBlockActivity('biolink.block.update', $link, $block);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'block' => $block->fresh()]);
        }

        return redirect()->route('user.links.blocks.editor', $link)->with('success', 'Block updated.');
    }

    /**
     * Apply a curated design variant to every block of the same type on a
     * page. Used by the "Apply this design to all blocks of this type"
     * shortcut in the Designs tab. Children of card containers are included.
     */
    public function applyVariantToAll(Request $request, Link $link, BiolinkBlock $block)
    {
        abort_if($link->user_id !== workspace_owner_id() || $block->link_id !== $link->id, 403);
        if ($resp = $this->designLockedResponse($link)) return $resp;

        $validated = $request->validate([
            'variant' => 'required|string|max:60',
        ]);

        $variant = BlockVariantCatalog::find($block->type, $validated['variant']);
        if (!$variant) {
            return response()->json(['success' => false, 'error' => 'Unknown variant'], 422);
        }

        // Sanitize the variant payload through the same pipeline as the
        // editor form so we don't trust the catalog blindly. We also force
        // the variant key + catalog VERSION to be persisted so the gallery
        // can show the selected state next time the editor opens and the
        // renderer can migrate older skins later.
        $variantStyle = $this->sanitizeBlockStyle(array_merge(
            $variant['style'],
            [
                '_variant' => $validated['variant'],
                '_variant_version' => BlockVariantCatalog::VERSION,
            ]
        ));

        $count = 0;
        $siblings = $link->biolinkBlocks()->where('type', $block->type)->get();
        foreach ($siblings as $b) {
            $settings = $b->settings ?? [];
            $existing = $settings['_style'] ?? [];
            // Mirror the per-block snapshot logic from update() so even
            // bulk applies preserve each sibling's original handcrafted
            // look once.
            $oldVariant = $existing['_variant'] ?? '';
            if ($oldVariant === '' && !empty($existing)
                && empty($settings['_style_custom_snapshot'])) {
                $settings['_style_custom_snapshot'] = $existing;
            }
            // Full replace (not merge) so prior variant residue — keys
            // present in the old skin but not in the new one — is wiped.
            // Sanitize through STYLE_DEFAULTS first so every key in
            // `_style` is at a known baseline before the variant overlays
            // its overrides on top.
            $settings['_style'] = $this->sanitizeBlockStyle(array_merge(
                BiolinkBlock::STYLE_DEFAULTS,
                $variantStyle
            ));
            $b->update(['settings' => $settings]);
            $count++;
        }

        return response()->json(['success' => true, 'updated' => $count]);
    }

    /**
     * Apply a curated design variant to a single block. This is the
     * preferred path used by the editor's Designs gallery: it replaces the
     * block's `_style` payload wholesale with the variant's payload (plus
     * STYLE_DEFAULTS as the baseline) so swapping from variant A to
     * variant B never leaves residual keys from A behind. The first time
     * a variant replaces handcrafted styling we snapshot the original
     * `_style` into `settings._style_custom_snapshot` so the creator can
     * always restore it via "Custom (your tweaks)".
     */
    public function applyVariant(Request $request, Link $link, BiolinkBlock $block)
    {
        abort_if($link->user_id !== workspace_owner_id() || $block->link_id !== $link->id, 403);
        if ($resp = $this->designLockedResponse($link)) return $resp;

        $validated = $request->validate(['variant' => 'required|string|max:60']);
        $variant = BlockVariantCatalog::find($block->type, $validated['variant']);
        if (!$variant) {
            return response()->json(['success' => false, 'error' => 'Unknown variant'], 422);
        }

        $settings = $block->settings ?? [];
        $existing = $settings['_style'] ?? [];

        // Snapshot original handcrafted style on first variant application.
        $oldVariant = $existing['_variant'] ?? '';
        if ($oldVariant === '' && !empty($existing) && empty($settings['_style_custom_snapshot'])) {
            // Strip the variant bookkeeping keys before storing — the
            // snapshot is meant to capture handcrafted tweaks only.
            $snapshot = $existing;
            unset($snapshot['_variant'], $snapshot['_variant_version']);
            $settings['_style_custom_snapshot'] = $snapshot;
        }

        $settings['_style'] = $this->sanitizeBlockStyle(array_merge(
            BiolinkBlock::STYLE_DEFAULTS,
            $variant['style'],
            [
                '_variant' => $validated['variant'],
                '_variant_version' => BlockVariantCatalog::VERSION,
            ]
        ));

        $block->update(['settings' => $settings]);
        return response()->json(['success' => true, 'block' => $block->fresh()]);
    }

    /**
     * Restore the pre-variant handcrafted style snapshot captured on the
     * first variant apply. Performs a full `_style` replacement (not a
     * merge) so any keys introduced by the curated variant are dropped
     * cleanly. The snapshot itself is kept on disk so creators can
     * roundtrip between variants and their custom look.
     */
    public function restoreCustomStyle(Link $link, BiolinkBlock $block)
    {
        abort_if($link->user_id !== workspace_owner_id() || $block->link_id !== $link->id, 403);
        if ($resp = $this->designLockedResponse($link)) return $resp;

        $settings = $block->settings ?? [];
        $snapshot = $settings['_style_custom_snapshot'] ?? null;
        if (!is_array($snapshot)) {
            return response()->json(['success' => false, 'error' => 'No custom style snapshot to restore'], 404);
        }

        $settings['_style'] = $this->sanitizeBlockStyle(array_merge(
            BiolinkBlock::STYLE_DEFAULTS,
            $snapshot,
            ['_variant' => '', '_variant_version' => 0]
        ));

        $block->update(['settings' => $settings]);
        return response()->json(['success' => true, 'block' => $block->fresh()]);
    }

    /**
     * Reset a single block's `_style` payload to STYLE_DEFAULTS, dropping
     * both any curated variant and any handcrafted overrides. Also clears
     * `_style_custom_snapshot` so the user gets a truly clean slate. Used
     * by the "Reset to default" button in the Designs gallery.
     */
    public function resetStyle(Request $request, Link $link, BiolinkBlock $block)
    {
        abort_if($link->user_id !== workspace_owner_id() || $block->link_id !== $link->id, 403);
        if ($resp = $this->designLockedResponse($link)) return $resp;

        $applyToAll = (bool) $request->boolean('apply_to_all');
        $defaults = $this->sanitizeBlockStyle(BiolinkBlock::STYLE_DEFAULTS);

        $reset = function (BiolinkBlock $b) use ($defaults) {
            $settings = $b->settings ?? [];
            $settings['_style'] = $defaults;
            unset($settings['_style_custom_snapshot']);
            $b->update(['settings' => $settings]);
        };

        if ($applyToAll) {
            $count = 0;
            foreach ($link->biolinkBlocks()->where('type', $block->type)->get() as $b) {
                $reset($b);
                $count++;
            }
            return response()->json(['success' => true, 'updated' => $count]);
        }

        $reset($block);
        return response()->json(['success' => true, 'updated' => 1, 'block' => $block->fresh()]);
    }

    /**
     * Returns rendered HTML thumbnails for every variant offered for a
     * block's type, with the actual block content (label, image, etc.)
     * styled by each variant's payload. Used by the Designs gallery so
     * the previews match what the creator will actually see — not just
     * an abstract color/shape sketch.
     */
    public function variantPreviews(Link $link, BiolinkBlock $block)
    {
        abort_if($link->user_id !== workspace_owner_id() || $block->link_id !== $link->id, 403);

        $variants = BlockVariantCatalog::forType($block->type);
        $globalTheme = $link->settings ?? [];
        $previews = [];
        foreach ($variants as $v) {
            $style = array_merge(BiolinkBlock::STYLE_DEFAULTS, $v['style']);
            $resolved = BiolinkBlock::getBlockStyle($style, is_array($globalTheme) ? $globalTheme : []);
            $previews[] = [
                'key' => $v['key'],
                'name' => $v['name'],
                'tags' => $v['tags'] ?? [],
                'inline_style' => BiolinkBlock::buildInlineStyle($resolved),
                'text_color' => $resolved['text_color'] ?? '',
                // Shape kind so the JS gallery knows whether to render a
                // button, heading, image, avatar, divider, plain link or
                // generic text sketch — without this, image / avatar /
                // heading / divider blocks all rendered as a tiny text
                // chip and looked broken on the dark modal.
                'shape_kind' => BlockVariantCatalog::shapeKindFor($block->type, $v['shape'] ?? null),
            ];
        }

        return response()->json(['previews' => $previews]);
    }

    public function editForm(Link $link, BiolinkBlock $block)
    {
        abort_if($link->user_id !== workspace_owner_id() || $block->link_id !== $link->id, 403);
        $blockTypes = BiolinkBlock::TYPES;
        $html = view('user.links.partials.block-edit-form-ajax', compact('link', 'block', 'blockTypes'))->render();
        return response()->json(['html' => $html]);
    }

    public function destroy(Link $link, BiolinkBlock $block)
    {
        abort_if($link->user_id !== workspace_owner_id() || $block->link_id !== $link->id, 403);
        if (in_array($block->type, ['verified_heading', 'verified_avatar'])) {
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Verified blocks cannot be deleted.'], 403);
            }
            return redirect()->back()->with('error', 'Verified blocks cannot be deleted.');
        }
        $this->recordBlockActivity('biolink.block.delete', $link, $block);
        $block->delete();

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('user.links.blocks.editor', $link)->with('success', 'Block deleted.');
    }

    public function bulkDestroy(Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id() || !$link->isBiolinkFamily(), 403);

        // Verified blocks (verified_heading / verified_avatar) are protected and
        // must survive a "delete all" just like they survive a single delete.
        // Because the parent_id FK is ON DELETE CASCADE, we must also spare any
        // card that contains a verified block — otherwise deleting the card would
        // cascade-delete the verified child, contradicting the UI promise that
        // verified blocks are kept. Cards cannot nest in cards, so sparing the
        // direct parent (one level) is sufficient.
        $verified = $link->biolinkBlocks()
            ->whereIn('type', ['verified_heading', 'verified_avatar'])
            ->get(['id', 'parent_id']);
        $protectedIds = $verified->pluck('id')
            ->merge($verified->pluck('parent_id')->filter())
            ->unique()
            ->all();

        $deletable = $link->biolinkBlocks()
            ->when($protectedIds, fn ($q) => $q->whereNotIn('id', $protectedIds))
            ->get();

        $deleted = 0;
        foreach ($deletable as $block) {
            $this->recordBlockActivity('biolink.block.delete', $link, $block);
            $block->delete();
            $deleted++;
        }

        $remaining = $link->biolinkBlocks()->count();

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['success' => true, 'deleted' => $deleted, 'remaining' => $remaining]);
        }

        return redirect()->route('user.links.blocks.editor', $link)
            ->with('success', $deleted ? "Deleted {$deleted} block(s)." : 'No blocks to delete.');
    }

    public function reorder(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id() || !$link->isBiolinkFamily(), 403);

        $validated = $request->validate([
            'blocks' => 'required|array',
            'blocks.*' => 'integer|exists:biolink_blocks,id',
        ]);

        foreach ($validated['blocks'] as $index => $blockId) {
            BiolinkBlock::where('id', $blockId)
                ->where('link_id', $link->id)
                ->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }

    public function moveBlock(Request $request, Link $link, BiolinkBlock $block)
    {
        abort_if($link->user_id !== workspace_owner_id() || $block->link_id !== $link->id, 403);

        $validated = $request->validate([
            'parent_id' => 'nullable|integer|exists:biolink_blocks,id',
        ]);

        $newParentId = $validated['parent_id'] ?? null;

        if ($block->type === 'card' && $newParentId) {
            return response()->json(['success' => false, 'error' => 'Cannot move a card container inside another card.'], 422);
        }

        // Placement render-gap guard: a top-level-only block dragged into a
        // container (or a child-only block dragged back out to the root) would
        // render blank — refuse it instead of silently degrading the page.
        $placementError = $this->placementRenderError($block->type, $newParentId !== null);
        if ($placementError !== null) {
            return response()->json(['success' => false, 'error' => $placementError], 422);
        }

        $oldParentId = $block->parent_id;

        if ($newParentId) {
            $parent = BiolinkBlock::where('id', $newParentId)
                ->where('link_id', $link->id)
                ->where('type', 'card')
                ->firstOrFail();
            $maxSort = $parent->children()->max('sort_order') ?? -1;
        } else {
            $maxSort = $link->biolinkBlocks()->whereNull('parent_id')->max('sort_order') ?? -1;
        }

        $block->update([
            'parent_id' => $newParentId,
            'sort_order' => $maxSort + 1,
        ]);

        if ($oldParentId) {
            $siblings = BiolinkBlock::where('parent_id', $oldParentId)
                ->where('link_id', $link->id)
                ->orderBy('sort_order')
                ->get();
            foreach ($siblings as $i => $sib) {
                $sib->update(['sort_order' => $i]);
            }
        } else {
            $siblings = $link->biolinkBlocks()
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->get();
            foreach ($siblings as $i => $sib) {
                $sib->update(['sort_order' => $i]);
            }
        }

        $fresh = $block->fresh();
        $fresh->load('children');
        $viewData = [
            'link'        => $link,
            'blockTypes'  => BiolinkBlock::TYPES,
            'catColors'   => BiolinkBlock::CATEGORY_COLORS,
            'pollTallies' => [],
        ];

        $payload = ['success' => true, 'block' => $fresh, 'parent_id' => $newParentId];
        if ($newParentId) {
            $payload['child_html'] = view('user.links.partials.block-child-card', array_merge($viewData, ['child' => $fresh]))->render();
        } else {
            $payload['html'] = view('user.links.partials.block-card', array_merge($viewData, ['block' => $fresh]))->render();
        }

        return response()->json($payload);
    }

    public function toggleActive(Link $link, BiolinkBlock $block)
    {
        abort_if($link->user_id !== workspace_owner_id() || $block->link_id !== $link->id, 403);
        $block->update(['is_active' => !$block->is_active]);

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['success' => true, 'block' => $block->fresh()]);
        }

        return redirect()->route('user.links.blocks.editor', $link)->with('success', 'Block visibility toggled.');
    }

    /**
     * Cache an in-progress page-settings form snapshot so the device-preview
     * iframe can render the unsaved edits without the owner having to click
     * "Save Settings" first. The cached overrides are scoped to the link and
     * expire after 10 minutes (cheap to refresh on every keystroke).
     *
     * Files (background images, slideshow images, video uploads, etc.) are
     * intentionally skipped — they only become previewable once persisted by
     * the regular save flow. Everything else (colours, gradients, fonts,
     * theme, layout, meta, etc.) flows straight through into the preview.
     */
    public function previewDraft(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id() || !$link->isBiolinkFamily(), 403);

        $input = $request->except(['_token', '_method', 'remove_slideshow_images']);
        // Drop any uploaded file fields — they aren't persisted yet, so we
        // can't hand them to the renderer. Saved values are kept untouched.
        foreach (array_keys($request->allFiles()) as $key) {
            unset($input[$key]);
        }
        // Scalar booleans coming from checkboxes are sent as "1" — leave
        // them as-is; merge into existing settings so unrelated keys keep
        // their saved values.
        \Illuminate\Support\Facades\Cache::put(
            "biolink_draft:{$link->id}",
            ['biolink' => $input],
            now()->addMinutes(10)
        );

        return response()->json(['success' => true]);
    }

    /**
     * 403 JSON response for style-mutation endpoints when the page is bound
     * to a design-locked template; null when styling edits are allowed.
     */
    private function designLockedResponse(Link $link)
    {
        if (!$link->isDesignLocked()) {
            return null;
        }
        return response()->json([
            'success' => false,
            'error' => 'This page uses a design-locked template. Detach from the template to customize styling.',
        ], 403);
    }

    /**
     * Page-settings keys that are design surfaces and therefore stripped
     * from saves while the link is design-locked. Content, SEO, sharing,
     * privacy and behaviour keys stay editable.
     */
    public const DESIGN_LOCKED_PAGE_KEYS = [
        'background_type', 'bg_preset_key', 'background_color', 'background_gradient',
        'gradient_colors', 'gradient_angle', 'gradient_type', 'gradient_preset_id',
        'slideshow_interval', 'video_url', 'bg_template_id', 'bg_attachment',
        'bg_fallback_color', 'bg_blur', 'bg_overlay_color', 'bg_overlay_opacity',
        'font_family', 'font_color', 'button_style', 'button_color', 'button_text_color',
        'custom_branding_text', 'custom_branding_url', 'custom_branding_logo',
        'custom_css', 'custom_js_head', 'custom_js_body',
    ];

    public function updatePageSettings(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id() || !$link->isBiolinkFamily(), 403);

        $validated = $request->validate([
            'biolink_title' => 'nullable|string|max:100',
            'biolink_description' => 'nullable|string|max:500',
            'background_type' => 'nullable|string|in:color,gradient,image,slideshow,video,template,preset',
            // CSS background preset key from BgPresetCatalog. Only stored when
            // background_type === 'preset'; the public renderer resolves CSS from the
            // catalog server-side, so raw CSS is never accepted from the client.
            'bg_preset_key' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/',
                function ($attribute, $value, $fail) {
                    if ($value && !\App\Modules\User\Support\BgPresetCatalog::findByKey($value)) {
                        $fail('The selected background preset is not valid.');
                    }
                }
            ],
            'background_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'background_gradient' => 'nullable|string|max:500',
            'background_image' => \App\Services\UploadPolicy::rule('link.background_image', $request->user()),
            'gradient_colors' => 'nullable|string|max:2000',
            'gradient_angle' => 'nullable|integer|min:0|max:360',
            'gradient_type' => 'nullable|string|in:linear,radial,conic',
            // Preset id from the GradientCatalog grid. Empty = custom (the
            // user manually edited stops). Stored alongside gradient_colors
            // so the picker can re-highlight the chosen preset on edit.
            'gradient_preset_id' => 'nullable|string|max:60|regex:/^[a-z0-9\-]+$/',
            'slideshow_images' => 'nullable|array|max:10',
            'slideshow_images.*' => \App\Services\UploadPolicy::rule('link.slideshow_image', $request->user(), true),
            'slideshow_interval' => 'nullable|integer|min:1|max:30',
            'video_url' => 'nullable|string|max:500',
            'video_file' => \App\Services\UploadPolicy::rule('link.video_file', $request->user()),
            'bg_template_id' => 'nullable|integer|exists:bg_templates,id',
            'bg_attachment' => 'nullable|string|in:fixed,scroll',
            'bg_fallback_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'bg_fallback_image' => \App\Services\UploadPolicy::rule('link.bg_fallback_image', $request->user()),
            'bg_blur' => 'nullable|integer|min:0|max:100',
            'bg_overlay_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'bg_overlay_opacity' => 'nullable|integer|min:0|max:100',
            'font_family' => 'nullable|string|max:100',
            'font_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'button_style' => 'nullable|string|in:rounded,pill,square,outline,shadow',
            'button_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'button_text_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'verified_badge' => 'boolean',
            'branding_hidden' => 'boolean',
            'block_theme' => 'nullable|array',
            'custom_branding_text' => 'nullable|string|max:100',
            'custom_branding_url' => 'nullable|string|max:500',
            'custom_branding_logo' => 'nullable|string|max:500',
            'favicon_url' => 'nullable|string|max:500',
            'favicon_upload' => \App\Services\UploadPolicy::rule('link.favicon_upload', $request->user()),
            'visibility' => 'nullable|string|in:public,registered,followers,subscribers',
            'custom_css' => 'nullable|string|max:10000',
            'custom_js_head' => 'nullable|string|max:10000',
            'custom_js_body' => 'nullable|string|max:10000',
            'layout' => 'nullable|array',
            'meta' => 'nullable|array',
            'meta.seo_title' => 'nullable|string|max:70',
            'meta.seo_description' => 'nullable|string|max:320',
            'meta.keywords' => 'nullable|string|max:500',
            'meta.author' => 'nullable|string|max:100',
            'meta.language' => 'nullable|string|max:5',
            'meta.canonical_url' => 'nullable|url|max:500',
            'meta.robots' => ['nullable', 'string', Rule::in(['index,follow', 'index,nofollow', 'noindex,follow', 'noindex,nofollow'])],
            'meta.rating' => 'nullable|string|in:general,mature,restricted',
            'og' => 'nullable|array',
            'og.title' => 'nullable|string|max:100',
            'og.description' => 'nullable|string|max:300',
            'og.type' => 'nullable|string|in:website,profile,article,product',
            'og.site_name' => 'nullable|string|max:100',
            'og.image_url' => 'nullable|url|max:500',
            'og_image_upload' => \App\Services\UploadPolicy::rule('link.og_image_upload', $request->user()),
            'twitter' => 'nullable|array',
            'twitter.card' => 'nullable|string|in:summary_large_image,summary,app,player',
            'twitter.site' => 'nullable|string|max:50',
            'twitter.title' => 'nullable|string|max:100',
            'twitter.description' => 'nullable|string|max:200',
            'favicons' => 'nullable|array',
            'favicons.apple_touch_icon' => 'nullable|url|max:500',
            'favicons.icon_512' => 'nullable|url|max:500',
            'apple_touch_upload' => \App\Services\UploadPolicy::rule('link.apple_touch_upload', $request->user()),
            'icon_512_upload' => \App\Services\UploadPolicy::rule('link.icon_512_upload', $request->user()),
            'manifest' => 'nullable|array',
            'manifest.enabled' => 'boolean',
            'manifest.name' => 'nullable|string|max:100',
            'manifest.short_name' => 'nullable|string|max:25',
            'manifest.description' => 'nullable|string|max:300',
            'manifest.display' => 'nullable|string|in:standalone,fullscreen,minimal-ui,browser',
            'manifest.orientation' => 'nullable|string|in:any,portrait,landscape',
            'manifest.theme_color' => 'nullable|string|max:20',
            'manifest.background_color' => 'nullable|string|max:20',
            'manifest.start_url' => 'nullable|string|max:200',
            'manifest.categories' => 'nullable|string|max:200',

            'share_button' => 'nullable|array',
            'share_button.enabled' => 'boolean',
            'share_button.show_qr' => 'boolean',
            'share_button.style' => 'nullable|string|in:fab,bar,icon',
            'share_button.position' => 'nullable|string|in:bottom-right,bottom-left,bottom-center,top-right,top-left',
            'share_button.color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'share_button.text_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'share_button.size' => 'nullable|string|in:sm,md,lg',
            'share_button.qr_size' => 'nullable|integer|min:100|max:400',
            'share_button.qr_fg_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'share_button.qr_bg_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'share_button.label' => 'nullable|string|max:30',

            'menu_bar' => 'nullable|array',
            'menu_bar.enabled' => 'boolean',
            'menu_bar.position' => 'nullable|string|in:top,bottom,floating-top-right,floating-top-left,floating-bottom-right,floating-bottom-left',
            'menu_bar.style' => 'nullable|string|in:pills,underline,flat',
            'menu_bar.bg_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'menu_bar.text_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'menu_bar.active_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'menu_bar.icon_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'menu_bar.overlay_bg' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'menu_bar.items' => 'nullable|string|max:5000',

            'auto_translate' => 'nullable|array',
            'auto_translate.enabled' => 'boolean',
            'auto_translate.position' => 'nullable|string|in:top-right,top-left,bottom-right,bottom-left',
            'auto_translate.default_lang' => 'nullable|string|max:5',
            'auto_translate.languages' => 'nullable|string|max:500',
            'auto_translate.style' => 'nullable|string|in:dropdown,flags,minimal',
            'auto_translate.bg_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'auto_translate.text_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],

            // Auto-UTM defaults applied to every outbound biolink block click.
            // Defaults are templates supporting {slug} and {block} tokens;
            // a block can override any individual key from the editor.
            'auto_utm' => 'nullable|array',
            'auto_utm.enabled' => 'boolean',
            'auto_utm.defaults' => 'nullable|array',
            'auto_utm.defaults.utm_source'   => 'nullable|string|max:120',
            'auto_utm.defaults.utm_medium'   => 'nullable|string|max:120',
            'auto_utm.defaults.utm_campaign' => 'nullable|string|max:160',
            'auto_utm.defaults.utm_term'     => 'nullable|string|max:160',
            'auto_utm.defaults.utm_content'  => 'nullable|string|max:160',

            // Per-biolink privacy controls (task #1114). Defaults are
            // privacy-respecting (visitor counts hidden, referrers not
            // logged) so a brand-new biolink is GDPR-safe out of the box.
            'privacy' => 'nullable|array',
            'privacy.hide_public_visitor_counts' => 'boolean',
            'privacy.disable_referrer_logging'   => 'boolean',
            'privacy.consent_banner_enabled'     => 'boolean',
            'privacy.consent_banner_text'        => 'nullable|string|max:500',
            'privacy.consent_accept_label'       => 'nullable|string|max:40',
            'privacy.consent_decline_label'      => 'nullable|string|max:40',
        ]);

        $user = auth()->user();
        $settings = $link->settings ?? [];
        $blockTheme = $validated['block_theme'] ?? null;
        $layoutInput = $validated['layout'] ?? null;
        $metaInput = $validated['meta'] ?? null;
        $ogInput = $validated['og'] ?? null;
        $twitterInput = $validated['twitter'] ?? null;
        $faviconsInput = $validated['favicons'] ?? null;
        $manifestInput = $validated['manifest'] ?? null;
        $shareButtonInput = $validated['share_button'] ?? null;
        $menuBarInput = $validated['menu_bar'] ?? null;
        $autoTranslateInput = $validated['auto_translate'] ?? null;
        $privacyInput = $validated['privacy'] ?? null;
        unset($validated['privacy']);
        $slideshowFiles = $request->file('slideshow_images');
        $videoFile = $request->file('video_file');
        $fallbackImageFile = $request->file('bg_fallback_image');
        unset($validated['block_theme'], $validated['layout'], $validated['meta'], $validated['og'], $validated['twitter'], $validated['favicons'], $validated['manifest'], $validated['share_button'], $validated['menu_bar'], $validated['auto_translate'], $validated['og_image_upload'], $validated['apple_touch_upload'], $validated['icon_512_upload'], $validated['slideshow_images'], $validated['video_file'], $validated['bg_fallback_image']);

        // Design lock: strip every design surface from the save — background,
        // typography, buttons, block theme, layout, custom branding/CSS/JS and
        // design-related uploads. Non-design settings (SEO, sharing, privacy,
        // visibility, etc.) continue to save normally.
        if ($link->isDesignLocked()) {
            foreach (self::DESIGN_LOCKED_PAGE_KEYS as $k) {
                unset($validated[$k]);
            }
            $blockTheme = null;
            $layoutInput = null;
            $slideshowFiles = null;
            $videoFile = null;
            $fallbackImageFile = null;
            $request->files->remove('background_image');
            $request->files->remove('slideshow_images');
            $request->files->remove('video_file');
            $request->files->remove('bg_fallback_image');
        }

        if ($link->is_verified) {
            unset($validated['biolink_title']);
        }

        if (!$user->getPlanFeature('custom_branding', false)) {
            unset($validated['custom_branding_text'], $validated['custom_branding_url'], $validated['custom_branding_logo']);
        }
        if (!$user->getPlanFeature('custom_favicon', false)) {
            unset($validated['favicon_url']);
            $request->files->remove('favicon_upload');
            $faviconsInput = null;
            $request->files->remove('apple_touch_upload');
            $request->files->remove('icon_512_upload');
        }
        if (!$user->getPlanFeature('custom_code', false)) {
            unset($validated['custom_css'], $validated['custom_js_head'], $validated['custom_js_body']);
        }

        unset($validated['favicon_upload']);

        // Auto-UTM: handle the toggle/defaults block explicitly so an
        // unchecked "Enable" checkbox actually disables it (HTML forms
        // omit unchecked checkboxes from the payload entirely). When the
        // form did include any auto_utm key we replace the whole block
        // so removed defaults don't linger.
        $autoUtmInput = $validated['auto_utm'] ?? null;
        unset($validated['auto_utm']);
        if ($request->has('auto_utm')) {
            $defaults = is_array($autoUtmInput['defaults'] ?? null) ? $autoUtmInput['defaults'] : [];
            $cleanDefaults = [];
            foreach (\App\Modules\Common\Services\AutoUtmBuilder::UTM_KEYS as $k) {
                $v = trim((string) ($defaults[$k] ?? ''));
                if ($v !== '') $cleanDefaults[$k] = $v;
            }
            $settings['biolink']['auto_utm'] = [
                'enabled'  => !empty($autoUtmInput['enabled']),
                'defaults' => $cleanDefaults,
            ];
        }

        $settings['biolink'] = array_merge($settings['biolink'] ?? [], $validated);

        // video_url is echoed into the public page as a <video><source src>
        // (common/biolink.blade.php), so it must pass the same sanitizer as
        // the other page-level URL fields: https?:// or a /f/ vault path only.
        if (array_key_exists('video_url', $validated)) {
            $settings['biolink']['video_url'] = $this->sanitizeUrl(trim((string) ($validated['video_url'] ?? '')));
        }

        if ($blockTheme !== null) {
            $settings['biolink']['block_theme'] = $this->sanitizeBlockStyle($blockTheme);
            $settings['biolink']['block_theme']['apply_to_all'] = !empty($blockTheme['apply_to_all']);
        }

        if ($layoutInput !== null) {
            $settings['biolink']['layout'] = $this->sanitizeLayout($layoutInput);
        }

        $nullifyEmpty = fn(array $arr) => array_map(fn($v) => is_string($v) && trim($v) === '' ? null : (is_string($v) ? trim($v) : $v), $arr);

        if ($metaInput !== null) {
            $settings['biolink']['meta'] = $nullifyEmpty($metaInput);
        }

        if ($ogInput !== null) {
            $settings['biolink']['og'] = $nullifyEmpty($ogInput);
        }

        if ($twitterInput !== null) {
            $settings['biolink']['twitter'] = $nullifyEmpty($twitterInput);
        }

        if ($manifestInput !== null) {
            $settings['biolink']['manifest'] = $nullifyEmpty($manifestInput);
            $settings['biolink']['manifest']['enabled'] = !empty($manifestInput['enabled']);
            // start_url is emitted verbatim into the public PWA manifest JSON
            // (RedirectController::manifest). Allow https?:// absolute URLs or
            // a same-origin relative path (single leading slash, no "//" host
            // escape, no backslashes/control chars); anything else is dropped
            // so the manifest falls back to the biolink's own URL.
            $rawStart = trim((string) ($settings['biolink']['manifest']['start_url'] ?? ''));
            $settings['biolink']['manifest']['start_url'] = $this->sanitizeStartUrl($rawStart);
        }

        if ($shareButtonInput !== null) {
            $settings['biolink']['share_button'] = $nullifyEmpty($shareButtonInput);
            $settings['biolink']['share_button']['enabled'] = !empty($shareButtonInput['enabled']);
            $settings['biolink']['share_button']['show_qr'] = !empty($shareButtonInput['show_qr']);
        }

        if ($menuBarInput !== null) {
            $settings['biolink']['menu_bar'] = $nullifyEmpty($menuBarInput);
            $settings['biolink']['menu_bar']['enabled'] = !empty($menuBarInput['enabled']);
            if (!empty($menuBarInput['items'])) {
                $decoded = json_decode($menuBarInput['items'], true);
                if (is_array($decoded)) {
                    $sanitizedItems = [];
                    foreach ($decoded as $item) {
                        if (!is_array($item)) continue;
                        $label = trim(strip_tags(substr($item['label'] ?? '', 0, 30)));
                        if (empty($label)) continue;
                        $rawTarget = $item['target'] ?? '_self';
                        $target = in_array($rawTarget, ['_self', '_blank', 'tab'], true) ? $rawTarget : '_self';

                        if ($target === 'tab') {
                            $rawId = trim((string)($item['id'] ?? ''));
                            if (!preg_match('/^[a-z0-9\-]{1,50}$/i', $rawId)) {
                                $rawId = \Illuminate\Support\Str::slug($label);
                                if (empty($rawId)) {
                                    $rawId = 'tab-' . substr(md5($label . microtime(true) . count($sanitizedItems)), 0, 6);
                                }
                            }
                            $existingIds = array_column(
                                array_filter($sanitizedItems, fn($i) => ($i['target'] ?? '') === 'tab'),
                                'id'
                            );
                            $baseId = $rawId; $n = 1;
                            while (in_array($rawId, $existingIds, true)) {
                                $n++;
                                $rawId = $baseId . '-' . $n;
                            }
                            $sanitizedItems[] = [
                                'label' => $label,
                                'url' => '#' . $rawId,
                                'target' => 'tab',
                                'id' => $rawId,
                                'is_active' => !empty($item['is_active']),
                            ];
                            continue;
                        }

                        $url = trim($item['url'] ?? '');
                        if (empty($url)) continue;
                        if (!preg_match('#^(https?://|/)#i', $url)) continue;
                        $sanitizedItems[] = [
                            'label' => $label,
                            'url' => htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
                            'target' => $target,
                            'is_active' => !empty($item['is_active']),
                        ];
                    }
                    $settings['biolink']['menu_bar']['items'] = array_slice($sanitizedItems, 0, 20);
                }
            }
            unset($settings['biolink']['menu_bar']['items_raw']);
        }

        // Per-biolink privacy controls. We always replace the whole block
        // when the form posted any privacy.* key so unchecked switches
        // (HTML omits unchecked checkboxes) actually flip back to false.
        if ($request->has('privacy') || $privacyInput !== null) {
            $p = is_array($privacyInput) ? $privacyInput : [];
            $settings['biolink']['privacy'] = [
                'hide_public_visitor_counts' => !empty($p['hide_public_visitor_counts']),
                'disable_referrer_logging'   => !empty($p['disable_referrer_logging']),
                'consent_banner_enabled'     => !empty($p['consent_banner_enabled']),
                'consent_banner_text'        => trim((string) ($p['consent_banner_text'] ?? '')) ?: null,
                'consent_accept_label'       => trim((string) ($p['consent_accept_label'] ?? '')) ?: null,
                'consent_decline_label'      => trim((string) ($p['consent_decline_label'] ?? '')) ?: null,
            ];
        }

        if ($autoTranslateInput !== null) {
            $settings['biolink']['auto_translate'] = $nullifyEmpty($autoTranslateInput);
            $settings['biolink']['auto_translate']['enabled'] = !empty($autoTranslateInput['enabled']);
            if (!empty($autoTranslateInput['languages'])) {
                $codes = array_filter(array_map('trim', explode(',', $autoTranslateInput['languages'])));
                $validCodes = array_filter($codes, fn($c) => preg_match('/^[a-z]{2}(-[A-Z]{2,3})?$/', $c));
                $settings['biolink']['auto_translate']['languages'] = implode(',', array_slice($validCodes, 0, 30));
            }
        }

        if ($faviconsInput !== null) {
            $existingFavicons = $settings['biolink']['favicons'] ?? [];
            foreach ($faviconsInput as $k => $v) {
                if (!empty(trim($v))) {
                    $existingFavicons[$k] = $this->sanitizeUrl(trim($v));
                }
            }
            $settings['biolink']['favicons'] = $existingFavicons;
        }

        // Vault helper: store an UploadedFile and return its public vault URL.
        // Quota / size failures bubble up as RuntimeException; we catch them
        // once around the whole upload block below. The optional $compress
        // arg downscales+re-encodes raster photos so backgrounds / OG images
        // / carousel slides don't bloat the vault with full-res camera dumps.
        // Logos, favicons, and videos omit $compress so they're stored as-is.
        $vault = function ($file, array $compress = []) use ($user) {
            $opts = [];
            if (!empty($compress)) {
                $opts['compress_image'] = true;
                $opts['max_width']  = (int) ($compress['max_width']  ?? 1600);
                $opts['max_height'] = (int) ($compress['max_height'] ?? 1600);
                $opts['quality']    = (int) ($compress['quality']    ?? 85);
            }
            return UserFile::createFromUpload($file, $user, $opts)->url;
        };

        try {

        if ($request->hasFile('background_image')) {
            $settings['biolink']['background_image'] = $vault($request->file('background_image'), ['max_width' => 1920, 'max_height' => 1920]);
        }

        if (!empty($validated['gradient_colors'])) {
            $decoded = json_decode($validated['gradient_colors'], true);
            if (is_array($decoded)) {
                $settings['biolink']['gradient_colors'] = $decoded;
            }
        }

        // Track which preset (if any) the user picked. Stored alongside
        // the resolved stops so the picker can re-highlight on edit, even
        // if the catalog itself is later expanded with new entries.
        if (array_key_exists('gradient_preset_id', $validated)) {
            $settings['biolink']['gradient_preset_id'] = (string) ($validated['gradient_preset_id'] ?? '');
        }

        if ($slideshowFiles && is_array($slideshowFiles)) {
            $existingSlides = $settings['biolink']['slideshow_images'] ?? [];
            foreach ($slideshowFiles as $file) {
                $existingSlides[] = $vault($file, ['max_width' => 1600, 'max_height' => 1600]);
            }
            $settings['biolink']['slideshow_images'] = array_slice($existingSlides, 0, 10);
        }

        if ($videoFile) {
            $settings['biolink']['video_file'] = $vault($videoFile);
        }

        if ($fallbackImageFile) {
            $settings['biolink']['bg_fallback_image'] = $vault($fallbackImageFile, ['max_width' => 1920, 'max_height' => 1920]);
        }

        if ($request->has('remove_slideshow_images')) {
            $removeIndexes = array_map('intval', (array) $request->input('remove_slideshow_images', []));
            $existing = $settings['biolink']['slideshow_images'] ?? [];
            $settings['biolink']['slideshow_images'] = array_values(array_diff_key($existing, array_flip($removeIndexes)));
        }

        // Favicon single source of truth: the primary favicon lives on the
        // Link.favicon column (shared with short links). We intentionally no
        // longer mirror it into settings.biolink.favicon_url. Touch-icon sizes
        // (apple_touch_icon, icon_512) have no column and stay under
        // settings.biolink.favicons.
        $faviconValue = null;
        if ($request->hasFile('favicon_upload') && $user->getPlanFeature('custom_favicon', false)) {
            $faviconValue = $vault($request->file('favicon_upload'));
        } elseif (!empty($validated['favicon_url']) && $user->getPlanFeature('custom_favicon', false)) {
            $faviconValue = $this->sanitizeUrl($validated['favicon_url']);
        }
        // Drop any legacy mirror so it can never diverge from the column.
        unset($settings['biolink']['favicon_url']);

        if ($request->hasFile('apple_touch_upload') && $user->getPlanFeature('custom_favicon', false)) {
            $settings['biolink']['favicons']['apple_touch_icon'] = $vault($request->file('apple_touch_upload'));
        }

        if ($request->hasFile('icon_512_upload') && $user->getPlanFeature('custom_favicon', false)) {
            $settings['biolink']['favicons']['icon_512'] = $vault($request->file('icon_512_upload'));
        }

        if ($request->hasFile('og_image_upload')) {
            $settings['biolink']['og']['image_url'] = $vault($request->file('og_image_upload'), ['max_width' => 1200, 'max_height' => 1200]);
        }

        } catch (\RuntimeException $e) {
            return $this->uploadError($request, $e->getMessage());
        }

        $updateData = ['settings' => $settings];
        if ($faviconValue !== null) {
            $updateData['favicon'] = $faviconValue;
        }

        // SEO/meta single source of truth: the title/description/image trio
        // lives on the Link columns (shared with short links + the REST API).
        // When the form posted the meta/og block we mirror the posted value
        // (including an explicit clear) onto the column, then strip the trio
        // from the JSON so the two can never diverge.
        if ($metaInput !== null) {
            $updateData['seo_title'] = trim((string) ($metaInput['seo_title'] ?? '')) ?: null;
            $updateData['seo_description'] = trim((string) ($metaInput['seo_description'] ?? '')) ?: null;
        }
        unset($settings['biolink']['meta']['seo_title'], $settings['biolink']['meta']['seo_description']);

        $ogImageFinal = $settings['biolink']['og']['image_url'] ?? null;
        if ($ogImageFinal) {
            $updateData['seo_image'] = $ogImageFinal;
        } elseif ($ogInput !== null && array_key_exists('image_url', $ogInput)) {
            // OG image URL field was posted but cleared → clear the column too.
            $updateData['seo_image'] = null;
        }
        unset($settings['biolink']['og']['image_url']);

        // Visibility tier lives on the Link.visibility column (single source of
        // truth, shared with the REST API). Surfaced in the biolink editor so
        // creators can set it from the UI; enforcement is in
        // RedirectController::enforceVisibility (biolink family persists here; gate covers all types).
        if (!empty($validated['visibility'])) {
            $updateData['visibility'] = $validated['visibility'];
        }

        if (!empty($settings['biolink']['custom_branding_url'])) {
            $settings['biolink']['custom_branding_url'] = $this->sanitizeUrl($settings['biolink']['custom_branding_url']);
        }
        if (!empty($settings['biolink']['custom_branding_logo'])) {
            $settings['biolink']['custom_branding_logo'] = $this->sanitizeUrl($settings['biolink']['custom_branding_logo']);
        }
        $updateData['settings'] = $settings;

        $link->update($updateData);

        // JSON response for the slides + conversational editors which post the
        // background card via fetch and want to refresh their device-preview
        // iframes inline rather than navigate away.
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok'       => true,
                'message'  => 'Page settings updated.',
                'settings' => $settings['biolink'] ?? [],
            ]);
        }

        $referer = $request->headers->get('referer', '');
        if (str_contains($referer, '/settings/layout')) {
            return redirect()->route('user.links.settings.layout', $link)->with('success', 'Page settings updated.');
        } elseif (str_contains($referer, '/settings/block-theme')) {
            return redirect()->route('user.links.settings.block-theme', $link)->with('success', 'Page settings updated.');
        } elseif (str_contains($referer, '/settings/advanced')) {
            return redirect()->route('user.links.settings.advanced', $link)->with('success', 'Page settings updated.');
        }
        return redirect()->route('user.links.settings.appearance', $link)->with('success', 'Page settings updated.');
    }

    private function sanitizeLayout(array $input): array
    {
        $bounds = [
            'max_width_phone' => [280, 600],
            'max_width_tablet' => [320, 900],
            'max_width_desktop' => [400, 1200],
            'page_padding_top' => [0, 200],
            'page_padding_bottom' => [0, 200],
            'page_padding_x' => [0, 100],
            'block_gap' => [0, 100],
            'block_padding' => [0, 60],
        ];
        $result = [];
        foreach ($bounds as $key => [$min, $max]) {
            if (isset($input[$key]) && $input[$key] !== '' && is_numeric($input[$key])) {
                $result[$key] = max($min, min($max, (int) $input[$key]));
            }
        }
        return $result;
    }

    // Public + static so sibling save paths that persist user URLs rendered
    // on public pages (e.g. SlideDeckController slide backgrounds) reuse the
    // exact same rules instead of drifting with their own copy.
    public static function sanitizeUrl(?string $url): string
    {
        if (empty($url)) return '';
        if (preg_match('/^https?:\/\//i', $url)) return $url;
        // Relative vault media paths (/f/{id}/{filename}) are safe: a single
        // leading slash, no scheme, no protocol-relative "//" host escape,
        // and no control characters/backslashes that could smuggle one in.
        if (preg_match('/^\/f\/[^\/\\\\\s][^\\\\\s]*$/', $url) && ! str_contains($url, '//')) {
            return $url;
        }
        return '';
    }

    /**
     * PWA manifest start_url sanitizer: https?:// absolute URLs pass through;
     * same-origin relative paths need a single leading slash (no "//" host
     * escape) and no backslashes or whitespace/control characters. Anything
     * else returns null so RedirectController::manifest falls back to the
     * biolink's own URL.
     */
    private function sanitizeStartUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') return null;
        if (preg_match('/^https?:\/\//i', $url)) return $url;
        if (preg_match('/^\/(?!\/)[^\\\\\s]*$/', $url) && ! str_contains($url, '//')) {
            return $url;
        }
        return null;
    }

    private function sanitizeHtml(string $html): string
    {
        $html = strip_tags(
            $html,
            '<p><br><a><strong><em><u><ul><ol><li><h1><h2><h3><h4><h5><h6><span><div><img><table><tr><td><th><thead><tbody><hr><blockquote><pre><code>'
        );
        $html = preg_replace('/\s+on\w+\s*=/i', ' data-removed=', $html);
        $html = preg_replace('/javascript\s*:/i', '', $html);
        return $html;
    }

    public function sanitizeSettings(string $type, array $settings): array
    {
        if ($type === 'roadmap') {
            $settings['title']             = trim((string) ($settings['title'] ?? 'Roadmap'));
            $settings['subtitle']          = trim((string) ($settings['subtitle'] ?? ''));
            $settings['allow_submissions'] = (bool) ($settings['allow_submissions'] ?? true);
            $settings['require_email']     = (bool) ($settings['require_email'] ?? true);
            $settings['require_login']     = (bool) ($settings['require_login'] ?? false);
            $settings['auto_approve']      = (bool) ($settings['auto_approve'] ?? false);
            $bid = $settings['kanban_board_id'] ?? null;
            $settings['kanban_board_id']   = ($bid !== null && $bid !== '') ? (int) $bid : null;
            $cols = (array) ($settings['show_columns'] ?? \App\Modules\User\Models\RoadmapItem::PUBLIC_STATUSES);
            $settings['show_columns']      = array_values(array_intersect(\App\Modules\User\Models\RoadmapItem::PUBLIC_STATUSES, $cols))
                ?: \App\Modules\User\Models\RoadmapItem::PUBLIC_STATUSES;
            // Per-block submitter blocklist. Creators paste a CSV of
            // emails (case-insensitive) and/or fingerprints (the same
            // SHA-256 hash we store on roadmap_votes.fingerprint). Both
            // submit + vote + comment endpoints check these lists, so a
            // banned fan can neither propose new ideas nor influence
            // existing ones from the same browser/email.
            foreach (['blocked_emails_csv' => 'blocked_emails',
                      'blocked_fingerprints_csv' => 'blocked_fingerprints'] as $csvKey => $arrKey) {
                if (array_key_exists($csvKey, $settings)) {
                    $raw = (string) $settings[$csvKey];
                    $parsed = array_values(array_unique(array_filter(array_map(
                        fn($v) => strtolower(trim($v)),
                        preg_split('/[,\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: []
                    ))));
                    $settings[$arrKey] = array_slice($parsed, 0, 500);
                    unset($settings[$csvKey]);
                } else {
                    $settings[$arrKey] = array_values(array_filter(array_map(
                        fn($v) => strtolower(trim((string) $v)),
                        (array) ($settings[$arrKey] ?? [])
                    )));
                }
            }
        }

        // Product blocks (Task #1761): when native checkout is enabled we
        // need an authoritative numeric price + a constrained product type
        // and currency. The display `price` string is kept for rendering.
        if ($type === 'product') {
            $settings['native_checkout'] = (bool) ($settings['native_checkout'] ?? false);
            $amount = (float) ($settings['amount'] ?? 0);
            $settings['amount']      = $amount > 0 ? (string) $amount : '';
            $settings['price_cents'] = max(0, (int) round($amount * 100));
            $cur = strtoupper(trim((string) ($settings['currency'] ?? 'USD')));
            $settings['currency'] = in_array($cur, ['USD','EUR','GBP','CAD','AUD','INR','BRL','JPY'], true) ? $cur : 'USD';
            $settings['product_type'] = in_array(($settings['product_type'] ?? 'digital'), ['digital','physical'], true)
                ? $settings['product_type'] : 'digital';
            $settings['thank_you_message'] = mb_substr(trim((string) ($settings['thank_you_message'] ?? '')), 0, 500);
        }

        // Tip-jar blocks: convert "amounts_csv" form input into a numeric
        // array, dropping non-positive values and capping at 6 entries.
        if (in_array($type, ['buy_me_coffee', 'ko_fi', 'tip_jar'], true) && array_key_exists('amounts_csv', $settings)) {
            $raw = (string) $settings['amounts_csv'];
            $parsed = array_values(array_filter(array_map('intval', preg_split('/[,\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY)), fn ($n) => $n > 0));
            $settings['amounts'] = array_slice($parsed, 0, 6);
            unset($settings['amounts_csv']);
        }

        $urlFields = ['url', 'link', 'thumbnail', 'image', 'image_url', 'video_url',
                       'audio_url', 'file_url', 'embed_url', 'logo_url', 'cover',
                       'website', 'avatar', 'post_url', 'buy_url',
                       'destination_url', 'href', 'digital_file'];
        foreach ($urlFields as $field) {
            if (isset($settings[$field]) && $settings[$field] !== '') {
                $settings[$field] = $this->sanitizeUrl($settings[$field]);
            }
        }

        // Pre-load this user's connection IDs once, keyed by platform, so we can
        // validate that any referenced connection_id (a) belongs to the current
        // user and (b) matches the entry's platform. Anything that fails either
        // check is silently dropped rather than persisted.
        $userId = optional(auth()->user())->id;
        $userConnByPlatform = [];
        if ($userId) {
            $userConnByPlatform = \App\Modules\User\Models\SocialAccountConnection::query()
                ->where('user_id', $userId)
                ->get(['id', 'platform'])
                ->groupBy('platform')
                ->map(fn ($g) => $g->pluck('id')->all())
                ->all();
        }

        $sanitizeConnRef = function (array $entry) use ($userConnByPlatform) {
            if (! array_key_exists('connection_id', $entry)) return $entry;
            $raw = $entry['connection_id'];
            if ($raw === '' || $raw === null) {
                $entry['connection_id'] = null;
                return $entry;
            }
            $cid = (int) $raw;
            $name = $entry['name'] ?? null;
            $allowed = $name && isset($userConnByPlatform[$name])
                ? $userConnByPlatform[$name] : [];
            $entry['connection_id'] = in_array($cid, $allowed, true) ? $cid : null;
            return $entry;
        };

        if (isset($settings['platforms']) && is_array($settings['platforms'])) {
            foreach ($settings['platforms'] as &$platform) {
                if (isset($platform['url'])) {
                    $platform['url'] = $this->sanitizeUrl($platform['url']);
                }
                // New per-entry follow-button settings (Task #48).
                if (isset($platform['display'])) {
                    $platform['display'] = in_array($platform['display'], ['icon','follow','follow_count'], true)
                        ? $platform['display'] : 'icon';
                }
                $platform = $sanitizeConnRef($platform);
            }
            unset($platform);
        }

        if (isset($settings['groups']) && is_array($settings['groups'])) {
            foreach ($settings['groups'] as &$grp) {
                if (isset($grp['platforms']) && is_array($grp['platforms'])) {
                    foreach ($grp['platforms'] as &$gp) {
                        if (isset($gp['display'])) {
                            $gp['display'] = in_array($gp['display'], ['icon','follow','follow_count'], true)
                                ? $gp['display'] : 'icon';
                        }
                        $gp = $sanitizeConnRef($gp);
                    }
                    unset($gp);
                }
            }
            unset($grp);
        }

        if (isset($settings['images']) && is_array($settings['images'])) {
            $settings['images'] = array_values(array_filter($settings['images'], function ($img) {
                $url = is_array($img) ? ($img['url'] ?? '') : $img;
                return empty($url) || preg_match('/^https?:\/\//i', $url);
            }));
        }

        if (isset($settings['items']) && is_array($settings['items'])) {
            foreach ($settings['items'] as &$item) {
                if (!is_array($item)) continue;
                if (isset($item['url'])) $item['url'] = $this->sanitizeUrl($item['url']);
                if (isset($item['image'])) $item['image'] = $this->sanitizeUrl($item['image']);
                if (isset($item['avatar'])) $item['avatar'] = $this->sanitizeUrl($item['avatar']);
                if (isset($item['thumbnail'])) $item['thumbnail'] = $this->sanitizeUrl($item['thumbnail']);
            }
            unset($item);
        }

        if (isset($settings['cards']) && is_array($settings['cards'])) {
            foreach ($settings['cards'] as &$card) {
                if (isset($card['url'])) $card['url'] = $this->sanitizeUrl($card['url']);
                if (isset($card['image'])) $card['image'] = $this->sanitizeUrl($card['image']);
            }
        }

        if (isset($settings['groups']) && is_array($settings['groups'])) {
            foreach ($settings['groups'] as &$group) {
                if (isset($group['platforms']) && is_array($group['platforms'])) {
                    foreach ($group['platforms'] as &$platform) {
                        if (isset($platform['url'])) $platform['url'] = $this->sanitizeUrl($platform['url']);
                    }
                }
            }
        }

        if (isset($settings['socials']) && is_array($settings['socials'])) {
            foreach ($settings['socials'] as &$social) {
                if (isset($social['url'])) $social['url'] = $this->sanitizeUrl($social['url']);
            }
            unset($social);
        }

        // Profile-card stat counters (profile_card_v3): keep label/value pairs
        // as trimmed strings, drop empties, cap the row.
        if (isset($settings['stats']) && is_array($settings['stats'])) {
            $stats = [];
            foreach ($settings['stats'] as $stat) {
                if (!is_array($stat)) continue;
                $label = trim((string) ($stat['label'] ?? ''));
                $value = trim((string) ($stat['value'] ?? ''));
                if ($label === '' && $value === '') continue;
                $stats[] = ['label' => $label, 'value' => $value];
            }
            $settings['stats'] = array_slice($stats, 0, 6);
        }

        // Profile-card badge pills (profile_card_v4): accept either {label}
        // entries or bare strings, normalise to {label}, drop empties, cap.
        if (isset($settings['badges']) && is_array($settings['badges'])) {
            $badges = [];
            foreach ($settings['badges'] as $badge) {
                $label = is_array($badge) ? trim((string) ($badge['label'] ?? '')) : trim((string) $badge);
                if ($label === '') continue;
                $badges[] = ['label' => $label];
            }
            $settings['badges'] = array_slice($badges, 0, 12);
        }

        if (in_array($type, ['custom_html', 'paragraph_rich']) && isset($settings['html'])) {
            $settings['html'] = $this->sanitizeHtml($settings['html']);
        }

        if (isset($settings['_image_style']) && is_array($settings['_image_style'])) {
            $settings['_image_style'] = $this->sanitizeImageStyle($settings['_image_style']);
        }

        if (isset($settings['_link']) && is_array($settings['_link'])) {
            $settings['_link'] = $this->sanitizeLinkSettings($settings['_link']);
        }

        if (isset($settings['_tab_id'])) {
            $tid = trim((string)$settings['_tab_id']);
            $settings['_tab_id'] = preg_match('/^[a-z0-9\-]{1,50}$/i', $tid) ? $tid : '';
        }

        return $settings;
    }


    private function sanitizeVisibility(array $input): array
    {
        $allowed = [
            'continents' => ['Africa', 'Antarctica', 'Asia', 'Europe', 'North America', 'South America', 'Oceania'],
            'countries' => null,
            'countries_exclude' => null,
            'cities' => null,
            'devices' => ['desktop', 'tablet', 'mobile'],
            'devices_exclude' => ['desktop', 'tablet', 'mobile'],
            'os' => ['iOS', 'Android', 'Windows', 'OS X', 'Linux', 'Chrome OS'],
            'browsers' => ['Chrome', 'Firefox', 'Safari', 'Edge', 'Opera', 'Brave', 'Vivaldi', 'Internet Explorer'],
            'languages' => null,
            'visitor_types' => ['student', 'professional', 'business', 'creator', 'other'],
        ];

        // CSV-style fields (free-form text, comma-separated): accept scalar string too.
        $csvFields = ['countries', 'countries_exclude', 'cities', 'languages'];

        $result = [];
        foreach ($allowed as $key => $validValues) {
            $raw = $input[$key] ?? null;
            if (is_string($raw) && in_array($key, $csvFields, true)) {
                $raw = array_map('trim', explode(',', $raw));
            }
            if (!is_array($raw)) {
                $result[$key] = [];
                continue;
            }
            $values = array_filter(array_map('trim', $raw), fn($v) => $v !== '');
            if ($validValues !== null) {
                $values = array_values(array_intersect($values, $validValues));
            } else {
                $values = array_values(array_map(fn($v) => substr(strip_tags($v), 0, 100), $values));
            }
            $result[$key] = $values;
        }

        // Time slots: list of { days: [mon..sun], start: HH:MM, end: HH:MM }.
        // Empty list = no time-of-day restriction.
        $validDays = ['mon','tue','wed','thu','fri','sat','sun'];
        $slots = [];
        $rawSlots = $input['time_slots'] ?? [];
        if (is_array($rawSlots)) {
            foreach ($rawSlots as $slot) {
                if (!is_array($slot)) continue;
                $days = is_array($slot['days'] ?? null)
                    ? array_values(array_intersect(array_map('strtolower', $slot['days']), $validDays))
                    : [];
                $start = (string)($slot['start'] ?? '');
                $end   = (string)($slot['end'] ?? '');
                if (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) continue;
                if (empty($days)) continue;
                $slots[] = ['days' => $days, 'start' => $start, 'end' => $end];
                if (count($slots) >= 20) break; // hard cap
            }
        }
        $result['time_slots'] = $slots;

        return $result;
    }

    private function sanitizeBlockStyle(array $input): array
    {
        $enums = [
            'font_style' => ['normal', 'italic'],
            'border_style' => ['none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge'],
            'shadow_type' => ['none', 'soft', 'hard', 'neon', 'glow', 'neumorphic', 'inset'],
            'shadow_preset' => ['none', 'soft', 'medium', 'strong'],
            'glass_preset' => ['off', 'light', 'heavy'],
            'display_mode' => ['card', 'content'],
            'effect' => ['none', 'glass', 'gradient_border'],
            // Per-block layout switch for link-family blocks. Empty
            // string is the default (existing button render); since the
            // foreach skips empty values, only non-default picks
            // ('plain_text' / 'image_cover') will ever be persisted —
            // which is exactly what we want.
            'link_layout' => [
                'plain_text', 'image_cover',
                'icon_left', 'icon_right', 'icon_both', 'icon_only',
                'icon_circle_left', 'icon_circle_right', 'icon_box',
                'image_left', 'image_right', 'image_top',
                'image_icon_rounded', 'image_icon_square', 'image_icon_circle',
            ],
        ];
        $numericBounds = [
            'font_size' => [8, 72],
            'bg_opacity' => [0, 100],
            'border_width' => [0, 10],
            'border_radius' => [0, 999],
            'shadow_x' => [-50, 50],
            'shadow_y' => [-50, 50],
            'shadow_blur' => [0, 100],
            'shadow_spread' => [-20, 50],
            'glass_blur' => [0, 100],
            'glass_opacity' => [0, 100],
            'padding' => [0, 60],
            'padding_top' => [0, 200],
            'padding_bottom' => [0, 200],
            'padding_left' => [0, 200],
            'padding_right' => [0, 200],
            'margin_top' => [-100, 200],
            'margin_bottom' => [-100, 200],
            'margin_left' => [-100, 200],
            'margin_right' => [-100, 200],
            'grid_span' => [1, 12],
        ];
        $colorKeys = ['text_color', 'bg_color', 'border_color', 'shadow_color'];
        $fontWeightKeys = ['font_weight'];
        $fontFamilyKeys = ['font_family'];
        $urlKeys = ['bg_image'];

        $allowed = array_keys(BiolinkBlock::STYLE_DEFAULTS);
        $result = [];
        foreach ($allowed as $key) {
            if (!isset($input[$key]) || $input[$key] === '') continue;
            $val = is_string($input[$key]) ? trim($input[$key]) : $input[$key];

            if (isset($enums[$key])) {
                if (in_array($val, $enums[$key], true)) $result[$key] = $val;
            } elseif (isset($numericBounds[$key])) {
                if (is_numeric($val)) {
                    $result[$key] = max($numericBounds[$key][0], min($numericBounds[$key][1], (float) $val));
                }
            } elseif (in_array($key, $colorKeys, true)) {
                if (preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*[\d.]+\s*)?\)|transparent)$/', $val)) {
                    $result[$key] = $val;
                } elseif ($key === 'bg_color' && is_string($val) && strlen($val) <= 240
                    && preg_match('/^(linear|radial|conic)-gradient\([^;{}<>"\'`]+\)$/i', $val)
                ) {
                    // Task #1041: allow CSS gradients on `bg_color` so curated
                    // cover/profile variants (e.g. cover_aurora) round-trip.
                    // We forbid `;{}<>"\'\`` so the value can never break out
                    // of the inline style attribute it ends up in.
                    $result[$key] = $val;
                }
            } elseif (in_array($key, $fontWeightKeys, true)) {
                if (preg_match('/^(300|400|500|600|700|800|900)$/', (string) $val)) {
                    $result[$key] = (string) $val;
                }
            } elseif (in_array($key, $fontFamilyKeys, true)) {
                // Allow Google Font names plus a "custom:<family>" prefix for
                // user-uploaded fonts. The colon is the only structural
                // delimiter we accept; anything else (quotes, parens, semis)
                // would be unsafe inside a CSS font-family declaration.
                $safe = preg_replace('/[^a-zA-Z0-9 :_\-]/', '', substr((string) $val, 0, 80));
                if ($safe !== '') $result[$key] = trim($safe);
            } elseif (in_array($key, $urlKeys, true)) {
                if (filter_var($val, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//', $val)) {
                    $result[$key] = substr($val, 0, 500);
                }
            } elseif ($key === '_template') {
                $validTemplates = array_keys(BiolinkBlock::BLOCK_TEMPLATES);
                if (in_array($val, $validTemplates, true)) {
                    $result[$key] = $val;
                }
            } elseif ($key === '_variant') {
                // Variant key is opaque; we accept any short slug-shaped
                // string. If the catalog later drops it, the renderer just
                // falls back to whatever's in _style. This keeps old pages
                // visually stable across catalog versions.
                $safe = preg_replace('/[^a-z0-9_\-]/i', '', substr((string) $val, 0, 60));
                if ($safe !== '') $result[$key] = $safe;
            } elseif ($key === '_variant_version') {
                $n = (int) $val;
                if ($n >= 0 && $n < 100000) $result[$key] = $n;
            } elseif (in_array($key, ['_animation', '_gallery_layout', '_social_set', '_profile_layout'], true)) {
                // Opaque slug-shaped variant metadata hooks (Task #1041).
                // The renderer is free to ignore unknown values; we only
                // bound the character set + length so they're safe to
                // emit as CSS class suffixes / data attributes later.
                $safe = preg_replace('/[^a-z0-9_\-]/i', '', substr((string) $val, 0, 40));
                if ($safe !== '') $result[$key] = $safe;
            }
        }
        return $result;
    }

    private function sanitizeImageStyle(array $input): array
    {
        $enums = [
            'mask_shape' => ['none', 'rounded', 'circle', 'square', 'diamond', 'hexagon', 'octagon', 'star', 'blob', 'arch', 'heart', 'torn'],
            'object_fit' => ['cover', 'contain', 'fill', 'none'],
            'border_style' => ['none', 'solid', 'dashed', 'dotted', 'double'],
            'shadow_type' => ['none', 'soft', 'hard', 'glow', 'neon', 'drop'],
        ];
        $numericBounds = [
            'border_radius' => [0, 999],
            'border_width' => [0, 10],
            'shadow_x' => [-40, 40],
            'shadow_y' => [-40, 40],
            'shadow_blur' => [0, 80],
            'shadow_spread' => [-20, 40],
        ];
        $colorKeys = ['border_color', 'shadow_color'];

        $result = [];
        foreach (array_keys(BiolinkBlock::IMAGE_STYLE_DEFAULTS) as $key) {
            if (!isset($input[$key]) || $input[$key] === '') continue;
            $val = is_string($input[$key]) ? trim($input[$key]) : $input[$key];

            if (isset($enums[$key])) {
                if (in_array($val, $enums[$key], true)) $result[$key] = $val;
            } elseif (isset($numericBounds[$key])) {
                if (is_numeric($val)) {
                    $result[$key] = max($numericBounds[$key][0], min($numericBounds[$key][1], (float) $val));
                }
            } elseif (in_array($key, $colorKeys, true)) {
                if (preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*[\d.]+\s*)?\)|transparent)$/', $val)) {
                    $result[$key] = $val;
                }
            }
        }
        return $result;
    }

    private function sanitizeLinkSettings(array $input): array
    {
        $result = [];

        if (!empty($input['url'])) {
            $url = trim($input['url']);
            if (filter_var($url, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//', $url)) {
                $result['url'] = substr($url, 0, 2048);
            }
        }

        $allowedTargets = ['_blank', '_self'];
        if (isset($input['target']) && in_array($input['target'], $allowedTargets, true)) {
            $result['target'] = $input['target'];
        }

        $allowedRels = ['noopener', 'noopener nofollow', 'noopener noreferrer', 'noopener noreferrer nofollow', 'sponsored', 'ugc'];
        if (isset($input['rel']) && in_array($input['rel'], $allowedRels, true)) {
            $result['rel'] = $input['rel'];
        }

        if (!empty($input['title'])) {
            $result['title'] = substr(strip_tags(trim($input['title'])), 0, 200);
        }

        // Preserve the legacy flat utm_* fields. Older saved blocks may
        // still write here, and the AutoUtmBuilder treats them as
        // overrides for backward compatibility.
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $utm) {
            if (!empty($input[$utm])) {
                $result[$utm] = preg_replace('/[^a-zA-Z0-9_\-.{} ]/', '', substr(trim($input[$utm]), 0, 160));
            }
        }

        // New structured Auto-UTM block: per-block toggle (inherit/on/off)
        // plus per-key overrides. Tokens like {slug} and {block} are
        // resolved at click time, so we keep `{` and `}` in the
        // sanitization allow-list.
        if (isset($input['auto_utm']) && is_array($input['auto_utm'])) {
            $au = $input['auto_utm'];
            $enabled = isset($au['enabled']) ? (string) $au['enabled'] : 'inherit';
            if (!in_array($enabled, ['inherit', 'on', 'off'], true)) {
                $enabled = 'inherit';
            }
            $cleanOverrides = [];
            $rawOverrides = is_array($au['overrides'] ?? null) ? $au['overrides'] : [];
            foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $utm) {
                if (!isset($rawOverrides[$utm])) continue;
                $val = trim((string) $rawOverrides[$utm]);
                if ($val === '') continue;
                $cleanOverrides[$utm] = preg_replace('/[^a-zA-Z0-9_\-.{} ]/', '', substr($val, 0, 160));
            }
            $result['auto_utm'] = [
                'enabled'   => $enabled,
                'overrides' => $cleanOverrides,
            ];
        }

        return $result;
    }

    /**
     * Fetch Open Graph metadata (title, description, og:image / favicon
     * fallback) for a URL submitted from the biolink block editor.
     *
     * Rate-limited to 10 fetches per user per minute so the SSRF-guarded
     * HTTP client can't be turned into a user-controlled DoS probe.
     */
    public function ogMeta(Request $request, Link $link): \Illuminate\Http\JsonResponse
    {
        abort_if($link->user_id !== workspace_owner_id() || !$link->isBiolinkFamily(), 403);

        $url = trim((string) $request->input('url', ''));
        if ($url === '') {
            return response()->json(['error' => 'Please enter a URL first.'], 422);
        }

        $rateKey = 'og-meta:' . auth()->id();
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            $seconds = RateLimiter::availableIn($rateKey);
            return response()->json(['error' => "Too many requests. Try again in {$seconds}s."], 429);
        }
        RateLimiter::hit($rateKey, 60);

        try {
            $meta = app(OgMetadataService::class)->extractFromUrl($url);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'meta' => $meta]);
    }

    /**
     * Return the owner's links as a searchable list for the block-editor
     * link picker. Results are workspace-owner-scoped (the same principal
     * that owns the biolink being edited) and exclude the biolink itself.
     *
     * Query param: `q` — optional title/alias search string.
     */
    public function linkPicker(Request $request, Link $link): \Illuminate\Http\JsonResponse
    {
        abort_if($link->user_id !== workspace_owner_id() || !$link->isBiolinkFamily(), 403);

        $q = trim((string) $request->input('q', ''));

        $query = Link::where('user_id', workspace_owner_id())
            ->where('id', '!=', $link->id)
            ->whereNotNull('alias')
            ->orderByDesc('id')
            ->select(['id', 'type', 'title', 'alias', 'seo_title']);

        if ($q !== '') {
            $query->where(function ($qb) use ($q) {
                $qb->where('title', 'ilike', "%{$q}%")
                   ->orWhere('alias', 'ilike', "%{$q}%")
                   ->orWhere('seo_title', 'ilike', "%{$q}%");
            });
        }

        $links = $query->limit(25)->get()->map(fn ($l) => [
            'id'    => $l->id,
            'type'  => $l->type,
            'title' => $l->title ?: $l->seo_title ?: $l->alias,
            'alias' => $l->alias,
            'url'   => url('/' . $l->alias),
        ]);

        return response()->json(['links' => $links]);
    }
}
