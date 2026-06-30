<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiMindSource;
use App\Modules\User\Models\AiResourceShare;
use App\Services\AI\AiMindFeatureAdapter;
use App\Services\AI\AiMindProvisioner;
use App\Services\AI\AiMindSettings;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiResourceShareService;
use App\Services\AI\MindCreditUsageService;
use Illuminate\Http\Request;

/**
 * Customer-facing AI Mind dashboard. Lists every Mind the user can
 * see (their own + the platform default), and lets them create / edit
 * / delete their own. Source management lives on `MindSourceController`
 * and the test-chat panel lives on `MindChatController`.
 */
class MindController extends Controller
{
    public function __construct(protected AiResourceShareService $shares) {}

    public function index(Request $request)
    {
        if (!AiEngineSettings::isEnabled()) {
            return view('user.ai.disabled', ['title' => 'Minds']);
        }
        $this->ensureEnabled();
        $user = $request->user();
        // Lazily seed "My Mind" + ensure the platform mind exists so the
        // dashboard never opens to an empty state.
        AiMindProvisioner::ensureForUser($user);

        $mine = AiMind::where('user_id', $user->id)
            ->withCount(['sources', 'chunks'])
            ->latest()
            ->get();
        $platform = AiMind::whereNull('user_id')
            ->where('is_default', true)
            ->withCount(['sources', 'chunks'])
            ->get();

        // Surface the user's effective per-plan cap (falls back to the global
        // admin cap when the plan predates the key) in the dashboard counter.
        $caps = AiMindSettings::caps();
        $caps['max_minds_per_user'] = \App\Services\AI\AiPlanAccess::quantityCap($user, 'minds');

        return view('user.minds.index', [
            'mine'     => $mine,
            'platform' => $platform,
            'caps'     => $caps,
            'usedMinds'=> $mine->count(),
            'shared'   => $this->shares->sharedMindsForUser($user)->loadCount(['sources', 'chunks']),
        ]);
    }

    public function create()
    {
        $this->ensureEnabled();
        return view('user.minds.create');
    }

    public function store(Request $request)
    {
        $this->ensureEnabled();
        $user = $request->user();
        $current = AiMind::where('user_id', $user->id)->count();
        if (!\App\Services\AI\AiPlanAccess::underQuantityCap($user, 'minds', $current)) {
            return back()->with('error',
                \App\Services\AI\AiPlanAccess::quantityLimitMessage($user, 'minds', 'AI Mind', $current));
        }

        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'description' => 'nullable|string|max:2000',
        ]);
        $mind = AiMind::create([
            'user_id'     => $user->id,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
        ]);
        return redirect()->route('user.minds.edit', $mind)->with('status', 'Mind created.');
    }

    public function edit(Request $request, AiMind $mind, MindCreditUsageService $usage)
    {
        $this->ensureEnabled();
        $this->authorize_($mind, $request->user());
        $user = $request->user();
        $isOwner = !$mind->isPlatform() && (int) $mind->user_id === (int) $user->id;
        $shareAccess = $this->shares->accessForMind($user, $mind);
        $canEdit = $isOwner || $shareAccess === AiResourceShare::ACCESS_EDIT;
        $sources = $mind->sources()->withCount('chunks')->latest('id')->get();
        $creditUsage      = $usage->usageForMind((int) $mind->id);
        $sourceCreditSpend = $usage->ingestionBySource((int) $mind->id);
        $dailyCreditSpend  = $usage->dailySpendForMind((int) $mind->id);
        return view('user.minds.edit', [
            'mind'              => $mind,
            'sources'           => $sources,
            'features'          => AiMindFeatureAdapter::FEATURES,
            'caps'              => AiMindSettings::caps(),
            'isPlatform'        => $mind->isPlatform(),
            'isOwner'           => $isOwner,
            'canEdit'           => $canEdit,
            'shareAccess'       => $shareAccess,
            'shareWorkspaces'   => $isOwner ? $this->shares->shareableWorkspacesFor($user) : collect(),
            'shareBadges'       => $isOwner ? $this->shares->shareableBadgesFor($user) : collect(),
            'currentShares'     => $isOwner ? $this->shares->sharesForResource(AiResourceShare::RESOURCE_MIND, (int) $mind->id) : collect(),
            'sourceCounts'      => $sources->groupBy('type')->map->count(),
            'creditUsage'       => $creditUsage,
            'sourceCreditSpend' => $sourceCreditSpend,
            'dailyCreditSpend'  => $dailyCreditSpend,
        ]);
    }

    public function update(Request $request, AiMind $mind)
    {
        $this->ensureEnabled();
        $this->authorize_($mind, $request->user(), needEdit: true);
        if ($mind->isPlatform()) abort(403, 'The default Mind is platform-managed.');
        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'description' => 'nullable|string|max:2000',
        ]);
        $mind->update($data);
        return back()->with('status', 'Mind updated.');
    }

    public function destroy(Request $request, AiMind $mind)
    {
        $this->ensureEnabled();
        // Deleting is owner-only — shared editors can change content but
        // not remove the resource out from under the owner.
        if ($mind->isPlatform()) abort(403, 'The default Mind is platform-managed.');
        if ((int) $mind->user_id !== (int) $request->user()->id) abort(403);
        $mind->delete();
        return redirect()->route('user.minds.index')->with('status', 'Mind deleted.');
    }

    /** Re-ingest every source in the mind. */
    public function refresh(Request $request, AiMind $mind)
    {
        $this->ensureEnabled();
        $this->authorize_($mind, $request->user(), needEdit: true);
        if ($mind->is_disabled) abort(403, 'This Mind is disabled.');
        foreach ($mind->sources as $s) {
            $s->forceFill(['status' => AiMindSource::STATUS_QUEUED])->save();
            \App\Jobs\IngestAiMindSourceJob::dispatch($s->id);
        }
        return back()->with('status', 'Refresh queued for ' . $mind->sources->count() . ' source(s).');
    }

    /**
     * Throws 403 unless the user may reach the mind. The owner always
     * passes; the platform mind is read-only for everyone; otherwise
     * access comes from a workspace/badge share (Task #2909). Pass
     * $needEdit=true to require USE+EDIT (write) rather than USE.
     */
    protected function authorize_(AiMind $mind, $user, bool $needEdit = false): void
    {
        if ($mind->isPlatform()) {
            if ($needEdit) abort(403, 'The default Mind is platform-managed.');
            return; // read access for everyone
        }
        if ((int) $mind->user_id === (int) $user->id) return; // owner: full access
        $access = $this->shares->accessForMind($user, $mind);
        if ($access === null) abort(403);
        if ($needEdit && $access !== AiResourceShare::ACCESS_EDIT) abort(403);
    }

    protected function ensureEnabled(): void
    {
        if (!AiEngineSettings::isEnabled()) abort(404);
    }
}
