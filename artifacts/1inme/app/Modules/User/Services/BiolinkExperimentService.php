<?php

namespace App\Modules\User\Services;

use App\Modules\Common\Services\AdaptiveSegmentResolver;
use App\Modules\User\Models\BiolinkAdaptiveArm;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\BiolinkExperiment;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

/**
 * Coordinates everything to do with biolink layout optimization, in two
 * modes that share one `biolink_experiments` row per link (never both
 * running at once — `activeFor()` enforces a single active experiment):
 *
 *  - 'ab' (original): manual two-variant test.
 *     - taking the variant A snapshot when an experiment starts
 *     - mirroring live block edits into the variant B snapshot
 *     - sticky 50/50 assignment per visitor
 *     - per-variant exposure / click / conversion accounting
 *     - stop-condition evaluation + winner promotion
 *
 *  - 'adaptive' (Task #3531): continuous per-segment optimization.
 *     - visitors are bucketed into a coarse segment (device/OS/geo/
 *       referrer/time-of-day/new-vs-returning) via AdaptiveSegmentResolver
 *     - a multi-armed bandit (UCB1) picks which block to feature (or the
 *       creator's default order) for that segment, from a small candidate
 *       set of the link's clickable blocks
 *     - clicks/conversions feed back into `biolink_adaptive_arms` so each
 *       segment's ordering keeps improving over time; there's no
 *       stop/winner step, it just keeps adapting while turned on
 *
 * "Live" biolink_blocks rows are treated as Variant B for the duration
 * of an 'ab' experiment, so the existing editor keeps working unchanged —
 * Variant A is read from the snapshot, Variant B is read from / mirrored
 * into the live table. Adaptive mode never rewrites live blocks at all;
 * it only reorders them at render time.
 */
class BiolinkExperimentService
{
    public const COOKIE_PREFIX = '_blab_'; // biolink layout A/B
    public const COOKIE_TTL_DAYS = 60;

    /**
     * Block types eligible to be "featured" (moved to the top of the
     * page) by the adaptive optimizer. Kept to conversion-oriented,
     * link-like blocks — reordering a heading or divider isn't a
     * meaningful lever, and container/media blocks are excluded so the
     * optimizer never hoists something like an embedded video above
     * the creator's actual call to action.
     */
    public const ADAPTIVE_FEATURABLE_TYPES = [
        'link', 'link_big', 'cta_button', 'product', 'service', 'price',
        'form', 'email_subscribe', 'email_collector', 'whatsapp_number_subscribe',
        'whatsapp_channel_subscribe', 'buy_me_coffee', 'patreon', 'ko_fi',
    ];

    /** Max candidate "feature this block" arms per segment (+1 baseline). */
    public const ADAPTIVE_MAX_CANDIDATES = 4;

    /**
     * Active experiment for the link, if any. Returns null when no
     * experiment is running so callers can short-circuit cheaply.
     */
    public function activeFor(Link $link): ?BiolinkExperiment
    {
        return BiolinkExperiment::where('link_id', $link->id)
            ->where('status', 'running')
            ->first();
    }

    /**
     * Start a new experiment. Snapshots the current biolink blocks tree
     * into both variants (Variant B is editable from the moment the test
     * starts, but starts identical to A so creators don't ship a half-
     * finished page to half their visitors).
     */
    public function start(Link $link, array $opts = []): BiolinkExperiment
    {
        // Defensive: prevent two concurrent experiments on the same link.
        // The editor UI also guards this, but a stale tab could race.
        if ($existing = $this->activeFor($link)) {
            return $existing;
        }

        $snapshot = $this->snapshotLiveBlocks($link);

        $stopCondition = in_array($opts['stop_condition'] ?? 'manual', ['manual','sample_size','end_date'], true)
            ? $opts['stop_condition']
            : 'manual';

        return BiolinkExperiment::create([
            'link_id'            => $link->id,
            'variant_a_snapshot' => $snapshot,
            'variant_b_snapshot' => $snapshot,
            'status'             => 'running',
            'stop_condition'     => $stopCondition,
            'stop_sample_size'   => $stopCondition === 'sample_size'
                ? max(50, (int) ($opts['stop_sample_size'] ?? 200))
                : null,
            'stop_end_date'      => $stopCondition === 'end_date' && !empty($opts['stop_end_date'])
                ? $opts['stop_end_date']
                : null,
            'started_at'         => now(),
        ]);
    }

    /**
     * Refresh the variant B snapshot from the current live blocks. Called
     * after every edit in the biolink editor so the public renderer can
     * serve variant B without re-querying biolink_blocks at request time.
     * No-op when no experiment is running.
     */
    public function syncVariantBFromLive(Link $link): void
    {
        $exp = $this->activeFor($link);
        if (!$exp) return;

        $exp->update(['variant_b_snapshot' => $this->snapshotLiveBlocks($link)]);
    }

    /**
     * Stop the experiment and (optionally) promote a winner. When a winner
     * is provided we copy that variant's snapshot back into biolink_blocks
     * — replacing whatever lives there now — so the link's "permanent"
     * state matches what the winning visitors saw.
     *
     * - winner='a' → live blocks are replaced with the frozen variant A snapshot.
     * - winner='b' → no rewrite needed (live blocks ARE variant B already).
     * - winner=null → just stop without promoting.
     */
    public function stop(BiolinkExperiment $exp, ?string $winner = null): BiolinkExperiment
    {
        $winner = in_array($winner, ['a', 'b'], true) ? $winner : null;

        DB::transaction(function () use ($exp, $winner) {
            if ($winner === 'a') {
                $this->restoreLiveBlocksFromSnapshot($exp->link, $exp->variant_a_snapshot ?? []);
            }
            $exp->update([
                'status'       => 'completed',
                'winner'       => $winner,
                'stopped_at'   => now(),
                'promoted_at'  => $winner ? now() : null,
            ]);
        });

        return $exp->fresh();
    }

    /**
     * Decide which variant this visitor should see and remember it (cookie
     * for web, deterministic hash fallback for clients without cookies
     * such as the mobile app, server-side render of a curl probe, etc.).
     *
     * @return string 'a' or 'b'
     */
    public function assignVariant(Request $request, BiolinkExperiment $exp): string
    {
        if ($exp->isAdaptive()) {
            return $this->assignAdaptiveArm($request, $exp);
        }

        $cookieName = self::COOKIE_PREFIX . $exp->link_id;

        $existing = $request->cookie($cookieName);
        if (in_array($existing, ['a', 'b'], true)) {
            return $existing;
        }

        // Header-based override lets the mobile client send its own stable
        // visitor id without depending on HTTP cookies.
        $visitorId = $request->header('X-1INME-Visitor-Id');
        $hashSeed = $visitorId
            ?: ($request->ip() . '|' . ($request->userAgent() ?? '') . '|' . $exp->link_id);

        $bucket = hexdec(substr(hash('sha256', $hashSeed), 0, 8)) % 2;
        $variant = $bucket === 0 ? 'a' : 'b';

        // Queue the cookie on the response so subsequent visits stick.
        Cookie::queue(
            $cookieName,
            $variant,
            self::COOKIE_TTL_DAYS * 24 * 60,
            null, null, true, false, false, 'lax'
        );

        return $variant;
    }

    /**
     * Increment the per-variant visit counter and check stop conditions.
     * Returns the (possibly updated) experiment so the caller can read
     * the new status.
     */
    public function recordVisit(BiolinkExperiment $exp, string $variant): BiolinkExperiment
    {
        if ($exp->isAdaptive()) {
            $this->bumpAdaptiveArm($variant, ['impressions']);
            return $exp;
        }

        $variant = $variant === 'a' ? 'a' : 'b';
        $exp->increment("variant_{$variant}_visits");
        $exp->refresh();
        return $this->maybeAutoPromote($exp);
    }

    /**
     * Increment the per-variant click counter. A click is also counted
     * as a conversion (outbound clicks are one of the two primary
     * conversion surfaces; the other is form submissions, recorded via
     * `recordConversion()`).
     */
    public function recordClick(BiolinkExperiment $exp, string $variant): void
    {
        if ($exp->isAdaptive()) {
            $this->bumpAdaptiveArm($variant, ['clicks', 'conversions']);
            return;
        }

        $variant = $variant === 'a' ? 'a' : 'b';
        $exp->increment("variant_{$variant}_clicks");
        $exp->increment("variant_{$variant}_conversions");
    }

    /**
     * Increment ONLY the per-variant conversions counter (no click).
     * Used by non-click conversions like contact-form submissions,
     * email subscribes, and RSVPs so creator-attributed conversions
     * include both outbound clicks and form submits.
     */
    public function recordConversion(BiolinkExperiment $exp, string $variant): void
    {
        if ($exp->isAdaptive()) {
            $this->bumpAdaptiveArm($variant, ['conversions']);
            return;
        }

        $variant = $variant === 'a' ? 'a' : 'b';
        $exp->increment("variant_{$variant}_conversions");
    }

    /**
     * Resolve the active experiment + the visitor's assigned variant for
     * a link in one call. Returns [null, null] when no experiment is
     * running. Convenience wrapper used by callers that just want to
     * attribute an arbitrary event (form submit, RSVP, etc.) without
     * caring about the snapshot tree.
     *
     * @return array{0: ?BiolinkExperiment, 1: ?string}
     */
    public function resolveAssignment(\Illuminate\Http\Request $request, \App\Modules\User\Models\Link $link): array
    {
        $exp = $this->activeFor($link);
        if (!$exp) return [null, null];
        return [$exp, $this->assignVariant($request, $exp)];
    }

    /**
     * Evaluate the configured stop condition and, if met, promote the
     * leader as winner. "Leader" = higher CTR; we tie-break to A so the
     * frozen snapshot wins ties (no surprise rewrites of the live blocks).
     * Used both inline (after every visit) and from the scheduled command.
     */
    public function maybeAutoPromote(BiolinkExperiment $exp): BiolinkExperiment
    {
        // Adaptive experiments never "stop" on their own — they keep
        // learning per segment for as long as the creator leaves them on.
        if (!$exp->isRunning() || $exp->isAdaptive()) return $exp;

        $shouldStop = false;
        if ($exp->stop_condition === 'sample_size'
            && $exp->stop_sample_size
            && $exp->totalVisits() >= $exp->stop_sample_size) {
            $shouldStop = true;
        } elseif ($exp->stop_condition === 'end_date'
            && $exp->stop_end_date
            && $exp->stop_end_date->isPast()) {
            $shouldStop = true;
        }

        if (!$shouldStop) return $exp;

        $ctrA = $exp->ctrFor('a');
        $ctrB = $exp->ctrFor('b');
        $winner = $ctrB > $ctrA ? 'b' : 'a';

        return $this->stop($exp, $winner);
    }

    /**
     * Hydrate the renderable block collection for a given variant. Returns
     * an Eloquent-shaped Collection of BiolinkBlock instances (with their
     * `children` relation pre-set) so the existing blade template can
     * iterate it without caring whether the data came from the database
     * or a snapshot row.
     */
    public function renderableBlocks(BiolinkExperiment $exp, string $variant): Collection
    {
        if ($exp->isAdaptive()) {
            return $this->renderableAdaptiveBlocks($exp, $variant);
        }

        $variant = $variant === 'a' ? 'a' : 'b';
        $tree = $exp->{"variant_{$variant}_snapshot"} ?? [];
        return collect($tree)->map(fn ($node) => $this->hydrateNode($exp->link_id, $node));
    }

    /**
     * Look up a block from the active experiment's snapshots when the
     * live row is gone (typical for variant A blocks once the creator
     * starts editing the live page). Returns null when the id isn't
     * found in either snapshot.
     */
    public function findSnapshotBlock(BiolinkExperiment $exp, int $blockId, string $variant): ?BiolinkBlock
    {
        $variant = $variant === 'a' ? 'a' : 'b';
        $tree = $exp->{"variant_{$variant}_snapshot"} ?? [];

        foreach ($this->flattenSnapshot($tree) as $node) {
            if ((int) ($node['id'] ?? 0) === $blockId) {
                return $this->hydrateNode($exp->link_id, $node, false);
            }
        }
        return null;
    }

    /**
     * Turn on adaptive optimization for a link. Mutually exclusive with
     * the manual A/B flow — `activeFor()` only ever returns one running
     * experiment per link, so the caller (controller) is expected to
     * refuse this when a manual test is already running, same as
     * `start()` refuses a second manual test.
     */
    public function startAdaptive(Link $link): BiolinkExperiment
    {
        if ($existing = $this->activeFor($link)) {
            return $existing;
        }

        return BiolinkExperiment::create([
            'link_id'            => $link->id,
            'mode'               => 'adaptive',
            // Unused in adaptive mode, but the columns are NOT NULL —
            // an empty tree is the correct "no snapshot" value.
            'variant_a_snapshot' => [],
            'variant_b_snapshot' => [],
            'status'             => 'running',
            'stop_condition'     => 'manual',
            'started_at'         => now(),
        ]);
    }

    /**
     * Housekeeping for a long-running adaptive experiment: arm rows for
     * segments that haven't been seen in a while (rare device/geo/time
     * combos, or a featured block the creator has since deleted) just
     * accumulate without ever contributing useful signal. Called from
     * the scheduled command instead of `maybeAutoPromote()`.
     */
    public function pruneIdleAdaptiveArms(BiolinkExperiment $exp, int $idleDays = 90): int
    {
        return BiolinkAdaptiveArm::where('biolink_experiment_id', $exp->id)
            ->where('updated_at', '<', now()->subDays($idleDays))
            ->delete();
    }

    /**
     * Adaptive counterpart to `assignVariant()`. Resolves the visitor's
     * segment, then uses a UCB1 multi-armed bandit to pick which arm
     * (baseline order, or "feature block X") to serve. Sticky per
     * (link, segment) for a short window so a click a few seconds after
     * page load attributes to the same arm the visitor actually saw,
     * even though the bandit is free to pick differently on their next
     * visit — segments are coarse but not static (time-of-day changes,
     * a "new" visitor becomes "returning").
     *
     * @return string "arm:{id}"
     */
    protected function assignAdaptiveArm(Request $request, BiolinkExperiment $exp): string
    {
        $segment = app(AdaptiveSegmentResolver::class)->resolve($request);
        $cookieName = 'adap_arm_' . $exp->link_id;

        $existing = (string) $request->cookie($cookieName);
        if ($existing !== '' && str_starts_with($existing, $segment . ':')) {
            $armId = (int) substr($existing, strlen($segment) + 1);
            if ($armId > 0
                && BiolinkAdaptiveArm::where('id', $armId)->where('biolink_experiment_id', $exp->id)->exists()) {
                return "arm:{$armId}";
            }
        }

        $arm = $this->pickArmViaBandit($exp, $segment);

        // Short TTL relative to the A/B sticky cookie: this only needs to
        // survive one page-view-to-click round trip, not weeks, so a
        // returning visitor's segment/arm gets re-evaluated against the
        // latest stats far more often than a manual A/B assignment would.
        Cookie::queue(
            $cookieName,
            $segment . ':' . $arm->id,
            30,
            null, null, true, false, false, 'lax'
        );

        return "arm:{$arm->id}";
    }

    /**
     * UCB1 bandit: try every candidate arm at least once, then favor
     * whichever arm maximizes (observed conversion rate + an exploration
     * bonus that shrinks as an arm accumulates impressions). This keeps
     * testing all arms forever (never fully "locks in"), which suits a
     * segment whose visitor mix can drift over time.
     */
    protected function pickArmViaBandit(BiolinkExperiment $exp, string $segment): BiolinkAdaptiveArm
    {
        $candidateBlockIds = $this->candidateFeaturedBlocks($exp->link);

        // null = baseline arm (creator's own block order, nothing featured).
        $arms = collect([null])
            ->concat($candidateBlockIds)
            ->map(fn ($blockId) => $this->ensureAdaptiveArm($exp, $segment, $blockId));

        $unshown = $arms->first(fn (BiolinkAdaptiveArm $arm) => $arm->impressions === 0);
        if ($unshown) return $unshown;

        $totalImpressions = max(1, (int) $arms->sum('impressions'));

        return $arms->sortByDesc(function (BiolinkAdaptiveArm $arm) use ($totalImpressions) {
            $mean = $arm->impressions > 0 ? $arm->conversions / $arm->impressions : 0.0;
            $explorationBonus = sqrt(2 * log($totalImpressions) / max(1, $arm->impressions));
            return $mean + $explorationBonus;
        })->first();
    }

    /**
     * The live top-level blocks eligible to be "featured" (moved to the
     * top of the page), capped to a handful so the arm set per segment
     * stays small enough to converge on real traffic volumes.
     *
     * @return Collection<int, int> block ids
     */
    protected function candidateFeaturedBlocks(Link $link): Collection
    {
        return BiolinkBlock::where('link_id', $link->id)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->whereIn('type', self::ADAPTIVE_FEATURABLE_TYPES)
            ->orderBy('sort_order')
            ->limit(self::ADAPTIVE_MAX_CANDIDATES)
            ->pluck('id');
    }

    protected function ensureAdaptiveArm(BiolinkExperiment $exp, string $segment, ?int $featuredBlockId): BiolinkAdaptiveArm
    {
        return BiolinkAdaptiveArm::firstOrCreate([
            'biolink_experiment_id' => $exp->id,
            'segment'               => $segment,
            'featured_block_id'     => $featuredBlockId,
        ]);
    }

    /**
     * Increment one or more counter columns on the arm encoded in an
     * adaptive `"arm:{id}"` variant string. Silently no-ops for
     * malformed input or an arm that's since been deleted (e.g. by
     * `pruneIdleAdaptiveArms()`) — bookkeeping should never break the
     * click/conversion flow it's attached to.
     */
    protected function bumpAdaptiveArm(string $variant, array $columns): void
    {
        $armId = $this->parseArmId($variant);
        if (!$armId) return;

        $arm = BiolinkAdaptiveArm::find($armId);
        if (!$arm) return;

        foreach ($columns as $column) {
            $arm->increment($column);
        }
    }

    protected function parseArmId(string $variant): ?int
    {
        return preg_match('/^arm:(\d+)$/', $variant, $m) ? (int) $m[1] : null;
    }

    /**
     * Adaptive counterpart to `renderableBlocks()`. Unlike the A/B
     * flow — which reads a frozen snapshot — adaptive mode always
     * reads the LIVE blocks (the creator's normal editor keeps working
     * exactly as it does with no experiment running at all) and simply
     * reorders them: the arm's featured block (if any, and if it still
     * exists / is still active) is moved to the front.
     */
    public function renderableAdaptiveBlocks(BiolinkExperiment $exp, string $variant): Collection
    {
        $blocks = $exp->link->biolinkBlocks()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        $armId = $this->parseArmId($variant);
        $featuredBlockId = $armId ? BiolinkAdaptiveArm::find($armId)?->featured_block_id : null;
        if (!$featuredBlockId) {
            return $blocks;
        }

        $featured = $blocks->firstWhere('id', $featuredBlockId);
        if (!$featured || !$featured->is_active) {
            return $blocks;
        }

        return $blocks->reject(fn (BiolinkBlock $b) => (int) $b->id === (int) $featured->id)
            ->prepend($featured)
            ->values();
    }

    // ─────────────────────────────────────────────────────────────────
    // Internals
    // ─────────────────────────────────────────────────────────────────

    /**
     * Walk the live biolink_blocks tree (top-level + children) and turn
     * it into the snapshot array we persist on the experiment.
     */
    protected function snapshotLiveBlocks(Link $link): array
    {
        $top = $link->biolinkBlocks()->whereNull('parent_id')->orderBy('sort_order')->get();
        $children = BiolinkBlock::where('link_id', $link->id)
            ->whereNotNull('parent_id')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('parent_id');

        return $top->map(fn ($b) => $this->serializeBlock($b, $children))->all();
    }

    protected function serializeBlock(BiolinkBlock $b, Collection $childrenByParent): array
    {
        $kids = $childrenByParent->get($b->id, collect());
        return [
            'id'         => (int) $b->id,
            'type'       => (string) $b->type,
            'settings'   => $b->settings ?? [],
            'sort_order' => (int) $b->sort_order,
            'is_active'  => (bool) $b->is_active,
            'parent_id'  => $b->parent_id,
            'children'   => $kids->map(fn ($c) => $this->serializeBlock($c, $childrenByParent))->all(),
        ];
    }

    protected function hydrateNode(int $linkId, array $node, bool $withChildren = true): BiolinkBlock
    {
        $block = new BiolinkBlock();
        $block->forceFill([
            'id'         => $node['id'] ?? null,
            'link_id'    => $linkId,
            'type'       => $node['type'] ?? '',
            'settings'   => $node['settings'] ?? [],
            'sort_order' => $node['sort_order'] ?? 0,
            'is_active'  => $node['is_active'] ?? true,
            'parent_id'  => $node['parent_id'] ?? null,
        ]);
        // Pretend this row came from the database so blade `$block->id`
        // checks behave normally and Eloquent doesn't try to lazy-load
        // the children relation off a non-existent row.
        $block->exists = true;
        $block->setRawAttributes($block->getAttributes(), true);

        if ($withChildren) {
            $kids = collect($node['children'] ?? [])
                ->map(fn ($c) => $this->hydrateNode($linkId, $c, true));
            $block->setRelation('children', $kids);
            $block->setRelation('activeChildren', $kids->filter(fn ($c) => $c->is_active)->values());
        }
        return $block;
    }

    /**
     * Flatten a snapshot tree to a flat list (used by findSnapshotBlock).
     */
    protected function flattenSnapshot(array $tree): array
    {
        $out = [];
        foreach ($tree as $node) {
            $out[] = $node;
            foreach ($this->flattenSnapshot($node['children'] ?? []) as $child) {
                $out[] = $child;
            }
        }
        return $out;
    }

    /**
     * Wipe the link's live blocks and recreate them from a snapshot.
     * Used when promoting variant A as the winner so the link's
     * permanent state matches what the winning visitors saw.
     */
    protected function restoreLiveBlocksFromSnapshot(Link $link, array $snapshot): void
    {
        DB::transaction(function () use ($link, $snapshot) {
            BiolinkBlock::where('link_id', $link->id)->delete();
            foreach ($snapshot as $node) {
                $this->insertSnapshotNode($link->id, $node, null);
            }
        });
    }

    protected function insertSnapshotNode(int $linkId, array $node, ?int $parentId): void
    {
        $row = BiolinkBlock::create([
            'link_id'    => $linkId,
            'type'       => $node['type'] ?? 'text',
            'settings'   => $node['settings'] ?? [],
            'sort_order' => $node['sort_order'] ?? 0,
            'is_active'  => $node['is_active'] ?? true,
            'parent_id'  => $parentId,
        ]);
        foreach (($node['children'] ?? []) as $child) {
            $this->insertSnapshotNode($linkId, $child, $row->id);
        }
    }
}
