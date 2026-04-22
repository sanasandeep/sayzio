<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiMindChunk;
use App\Modules\User\Models\AiMindSource;
use App\Services\AI\AiMindProvisioner;
use App\Services\AI\AiMindSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin "AI Minds" page:
 *   - Aggregate stats (mind count, source count, embedding storage).
 *   - Per-plan caps (max minds, max sources, max link refreshes/day).
 *   - Disable / re-enable a user's mind for abuse.
 *   - Re-seed the platform default mind from product docs.
 */
class AiMindAdminController extends Controller
{
    public function index(Request $request)
    {
        $totals = [
            'minds'     => (int) AiMind::count(),
            'sources'   => (int) AiMindSource::count(),
            'chunks'    => (int) AiMindChunk::count(),
            'failed'    => (int) AiMindSource::where('status', AiMindSource::STATUS_FAILED)->count(),
            'disabled'  => (int) AiMind::where('is_disabled', true)->count(),
        ];

        $topUsers = AiMind::query()
            ->whereNotNull('user_id')
            ->select('user_id', DB::raw('COUNT(*) as minds_count'),
                DB::raw('SUM(sources_count) as sources_total'),
                DB::raw('SUM(chunks_count) as chunks_total'))
            ->groupBy('user_id')
            ->orderByDesc('chunks_total')
            ->limit(20)
            ->get();

        $minds = AiMind::query()
            ->with('user:id,name,email')
            ->withCount(['sources','chunks'])
            ->latest('updated_at')
            ->paginate(25);

        return view('admin.ai-minds.index', [
            'totals'   => $totals,
            'topUsers' => $topUsers,
            'minds'    => $minds,
            'caps'     => AiMindSettings::caps(),
        ]);
    }

    public function updateCaps(Request $request)
    {
        $defaults = AiMindSettings::capsDefault();
        $rules = [];
        foreach ($defaults as $k => $_) {
            $rules["caps.{$k}"] = 'nullable|integer|min:0|max:1000000';
        }
        $data = $request->validate($rules);
        AiMindSettings::setCaps($data['caps'] ?? []);
        return back()->with('success', 'AI Mind caps updated.');
    }

    public function disable(Request $request, AiMind $mind)
    {
        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);
        $mind->forceFill([
            'is_disabled'     => true,
            'disabled_reason' => $data['reason'],
        ])->save();
        return back()->with('success', 'Mind disabled.');
    }

    public function enable(Request $request, AiMind $mind)
    {
        $mind->forceFill([
            'is_disabled'     => false,
            'disabled_reason' => null,
        ])->save();
        return back()->with('success', 'Mind re-enabled.');
    }

    public function reseedDefault()
    {
        $mind = AiMindProvisioner::ensurePlatformDefault();
        // Re-queue ingestion of every source on the platform mind so
        // any new product knowledge picks up immediately.
        foreach ($mind->sources as $s) {
            $s->forceFill(['status' => AiMindSource::STATUS_QUEUED])->save();
            \App\Jobs\IngestAiMindSourceJob::dispatch($s->id);
        }
        return back()->with('success', 'Platform default Mind re-seeded.');
    }
}
