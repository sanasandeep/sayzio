<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiMindSource;
use App\Services\AI\AiMindFeatureAdapter;
use App\Services\AI\AiMindProvisioner;
use App\Services\AI\AiMindSettings;
use App\Services\AI\AiEngineSettings;
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
    public function index(Request $request)
    {
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

        return view('user.minds.index', [
            'mine'     => $mine,
            'platform' => $platform,
            'caps'     => AiMindSettings::caps(),
            'usedMinds'=> $mine->count(),
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
        $caps = AiMindSettings::caps();
        $current = AiMind::where('user_id', $user->id)->count();
        if ($current >= $caps['max_minds_per_user']) {
            return back()->with('error', "You have reached the {$caps['max_minds_per_user']}-mind limit. Delete an existing mind or contact support to raise the cap.");
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
        $sources = $mind->sources()->withCount('chunks')->latest('id')->get();
        $creditUsage      = $usage->usageForMind((int) $mind->id);
        $sourceCreditSpend = $usage->ingestionBySource((int) $mind->id);
        return view('user.minds.edit', [
            'mind'              => $mind,
            'sources'           => $sources,
            'features'          => AiMindFeatureAdapter::FEATURES,
            'caps'              => AiMindSettings::caps(),
            'isPlatform'        => $mind->isPlatform(),
            'sourceCounts'      => $sources->groupBy('type')->map->count(),
            'creditUsage'       => $creditUsage,
            'sourceCreditSpend' => $sourceCreditSpend,
        ]);
    }

    public function update(Request $request, AiMind $mind)
    {
        $this->ensureEnabled();
        $this->authorize_($mind, $request->user());
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
        $this->authorize_($mind, $request->user());
        if ($mind->isPlatform()) abort(403, 'The default Mind is platform-managed.');
        $mind->delete();
        return redirect()->route('user.minds.index')->with('status', 'Mind deleted.');
    }

    /** Re-ingest every source in the mind. */
    public function refresh(Request $request, AiMind $mind)
    {
        $this->ensureEnabled();
        $this->authorize_($mind, $request->user());
        if ($mind->is_disabled) abort(403, 'This Mind is disabled.');
        foreach ($mind->sources as $s) {
            $s->forceFill(['status' => AiMindSource::STATUS_QUEUED])->save();
            \App\Jobs\IngestAiMindSourceJob::dispatch($s->id);
        }
        return back()->with('status', 'Refresh queued for ' . $mind->sources->count() . ' source(s).');
    }

    /** Throws 403 unless the user owns the mind. Platform mind is read-only for everyone. */
    protected function authorize_(AiMind $mind, $user): void
    {
        if ($mind->isPlatform()) return; // read access for everyone
        if ((int) $mind->user_id !== (int) $user->id) abort(403);
    }

    protected function ensureEnabled(): void
    {
        if (!AiEngineSettings::isEnabled()) abort(404);
    }
}
