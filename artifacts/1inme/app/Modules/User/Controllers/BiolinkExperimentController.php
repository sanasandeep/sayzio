<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BiolinkExperiment;
use App\Modules\User\Models\Link;
use App\Modules\User\Services\BiolinkExperimentService;
use Illuminate\Http\Request;

/**
 * Owner-side endpoints for managing a biolink layout A/B test:
 * start, stop, pick winner, fetch live results JSON.
 */
class BiolinkExperimentController extends Controller
{
    public function __construct(protected BiolinkExperimentService $service) {}

    /**
     * Start a new experiment for this link. Snapshots the current
     * blocks tree as both Variant A (frozen baseline) and the initial
     * Variant B. The creator then edits the live blocks to evolve
     * Variant B; Variant A stays untouched.
     */
    public function start(Request $request, Link $link)
    {
        $this->authorizeLink($request, $link);

        $data = $request->validate([
            'stop_condition'   => ['nullable', 'in:manual,sample_size,end_date'],
            'stop_sample_size' => ['nullable', 'integer', 'min:50', 'max:1000000'],
            'stop_end_date'    => ['nullable', 'date', 'after:now'],
        ]);

        if (!$link->biolinkBlocks()->exists()) {
            return back()->with('error', 'Add at least one block before starting an A/B test.');
        }

        $this->service->start($link, $data);

        return back()->with('status', 'A/B test started. Variant B is now editable from this page.');
    }

    /**
     * Stop the running experiment. The optional `winner` param ('a'|'b')
     * promotes that variant — when 'a' wins we rewrite the live blocks
     * back to the frozen Variant A snapshot.
     */
    public function stop(Request $request, Link $link)
    {
        $this->authorizeLink($request, $link);

        $exp = $this->service->activeFor($link);
        if (!$exp) {
            return back()->with('error', 'No A/B test is running on this link.');
        }

        $winner = $request->input('winner');
        $winner = in_array($winner, ['a', 'b'], true) ? $winner : null;

        $this->service->stop($exp, $winner);

        $msg = match (true) {
            $winner === 'a' => 'Variant A promoted. Your live page now matches the original layout.',
            $winner === 'b' => 'Variant B promoted. Your live page keeps the new layout.',
            default => 'A/B test stopped without picking a winner.',
        };

        return back()->with('status', $msg);
    }

    /**
     * JSON results endpoint for the editor's results panel. Surfaces
     * per-variant visits, clicks, CTR, conversions and stop-condition
     * progress so the panel can poll this without a full page reload.
     */
    public function results(Request $request, Link $link)
    {
        $this->authorizeLink($request, $link);

        $exp = BiolinkExperiment::where('link_id', $link->id)
            ->orderByDesc('id')
            ->first();

        if (!$exp) {
            return response()->json(['experiment' => null]);
        }

        return response()->json([
            'experiment' => [
                'id'           => $exp->id,
                'status'       => $exp->status,
                'winner'       => $exp->winner,
                'started_at'   => $exp->started_at?->toIso8601String(),
                'stopped_at'   => $exp->stopped_at?->toIso8601String(),
                'promoted_at'  => $exp->promoted_at?->toIso8601String(),
                'stop_condition' => [
                    'type'        => $exp->stop_condition,
                    'sample_size' => $exp->stop_sample_size,
                    'end_date'    => $exp->stop_end_date?->toIso8601String(),
                    'progress'    => $exp->stop_condition === 'sample_size' && $exp->stop_sample_size
                        ? min(1.0, $exp->totalVisits() / max(1, $exp->stop_sample_size))
                        : null,
                ],
                'variant_a' => [
                    'visits'      => $exp->variant_a_visits,
                    'clicks'      => $exp->variant_a_clicks,
                    'conversions' => $exp->variant_a_conversions,
                    'ctr'         => $exp->ctrFor('a'),
                ],
                'variant_b' => [
                    'visits'      => $exp->variant_b_visits,
                    'clicks'      => $exp->variant_b_clicks,
                    'conversions' => $exp->variant_b_conversions,
                    'ctr'         => $exp->ctrFor('b'),
                ],
            ],
        ]);
    }

    /**
     * Turn on the adaptive optimizer (Task #3531). Mutually exclusive
     * with the manual A/B flow — refuses when either is already running,
     * mirroring `start()`'s guard against two concurrent experiments.
     */
    public function enableAdaptive(Request $request, Link $link)
    {
        $this->authorizeLink($request, $link);

        if ($existing = $this->service->activeFor($link)) {
            $msg = $existing->isAdaptive()
                ? 'Adaptive optimization is already on for this link.'
                : 'Stop the running A/B test before turning on adaptive optimization.';
            return back()->with('error', $msg);
        }

        if (!$link->biolinkBlocks()->exists()) {
            return back()->with('error', 'Add at least one block before turning on adaptive optimization.');
        }

        $this->service->startAdaptive($link);

        return back()->with('status', 'Adaptive optimization is on. Sayzio will keep testing block order per visitor segment.');
    }

    /**
     * Turn off adaptive optimization. Just stops the experiment — there's
     * no "winner" to promote since the live blocks were never rewritten.
     */
    public function disableAdaptive(Request $request, Link $link)
    {
        $this->authorizeLink($request, $link);

        $exp = $this->service->activeFor($link);
        if (!$exp || !$exp->isAdaptive()) {
            return back()->with('error', 'Adaptive optimization is not running on this link.');
        }

        $this->service->stop($exp);

        return back()->with('status', 'Adaptive optimization turned off. Your block order stays as-is.');
    }

    /**
     * JSON results for the adaptive dashboard: per-segment arm stats
     * (baseline vs the leading featured-block arm) plus the lift the
     * leader shows over baseline, so creators can see the optimizer is
     * actually doing something without wading through raw arm rows.
     */
    public function adaptiveResults(Request $request, Link $link)
    {
        $this->authorizeLink($request, $link);

        $exp = BiolinkExperiment::where('link_id', $link->id)
            ->where('mode', 'adaptive')
            ->orderByDesc('id')
            ->first();

        if (!$exp) {
            return response()->json(['experiment' => null]);
        }

        $blockLabels = $link->biolinkBlocks()
            ->get(['id', 'type'])
            ->mapWithKeys(fn ($b) => [
                (int) $b->id => \App\Modules\User\Models\BiolinkBlock::TYPES[$b->type]['label'] ?? ucfirst($b->type),
            ]);

        $segments = $exp->adaptiveArms()->get()->groupBy('segment')->map(function ($arms, $segment) use ($blockLabels) {
            $baseline = $arms->firstWhere('featured_block_id', null);
            $leader = $arms->filter(fn ($a) => $a->featured_block_id !== null)
                ->sortByDesc(fn ($a) => $a->conversionRate())
                ->first();

            $baselineRate = $baseline ? $baseline->conversionRate() : 0.0;
            $leaderRate = $leader ? $leader->conversionRate() : 0.0;
            $lift = $baselineRate > 0 ? round((($leaderRate - $baselineRate) / $baselineRate) * 100, 1) : null;

            return [
                'segment'          => $segment,
                'impressions'      => (int) $arms->sum('impressions'),
                'baseline'         => $baseline ? [
                    'impressions' => $baseline->impressions,
                    'conversions' => $baseline->conversions,
                    'rate'        => $baselineRate,
                ] : null,
                'leader'           => $leader ? [
                    'featured_block_id' => $leader->featured_block_id,
                    'featured_label'    => $blockLabels[$leader->featured_block_id] ?? 'Block #' . $leader->featured_block_id,
                    'impressions'       => $leader->impressions,
                    'conversions'       => $leader->conversions,
                    'rate'              => $leaderRate,
                ] : null,
                'lift_pct'         => $lift,
            ];
        })->sortByDesc('impressions')->values();

        return response()->json([
            'experiment' => [
                'id'         => $exp->id,
                'status'     => $exp->status,
                'started_at' => $exp->started_at?->toIso8601String(),
                'segments'   => $segments,
            ],
        ]);
    }

    /**
     * Mirror the link-edit guard used elsewhere in this module. We don't
     * need a full policy for the v1 since experiment management is
     * scoped to the link's owner / workspace just like block editing.
     */
    protected function authorizeLink(Request $request, Link $link): void
    {
        $user = $request->user();
        if (!$user || (int) $user->id !== (int) $link->user_id) {
            // Workspace permission check is handled by the route middleware
            // (`workspace.can:links.edit`) — this is a defence-in-depth fallback.
            abort(403);
        }
    }
}
