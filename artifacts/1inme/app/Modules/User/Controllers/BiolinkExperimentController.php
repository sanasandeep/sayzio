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
