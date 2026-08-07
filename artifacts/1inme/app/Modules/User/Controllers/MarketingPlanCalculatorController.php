<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\MarketingPlanCalc;
use App\Modules\User\Models\MarketingStrategy;
use App\Services\MarketingPlanAiSeed;
use App\Services\MarketingPlanDefaults;
use App\Services\MarketingPlanIndustryPresets;
use Illuminate\Http\Request;

/**
 * Task #6737 — Marketing Plan Calculator.
 *
 * Interactive in-app replacement for the "Sayzio-Powered Digital
 * Marketing Plan — 12 Month" spreadsheet. The browser (Alpine) does all
 * the math live; this controller lists, stores and reloads named plans
 * (a JSON payload of assumptions) per user/workspace.
 */
class MarketingPlanCalculatorController extends Controller
{
    /**
     * Plan feature key that switches the calculator on (Task #6766) and the
     * quantity key capping how many named plans can be kept (-1 = unlimited).
     */
    public const FEATURE_KEY = 'marketing_plan_calculator';
    public const LIMIT_KEY   = 'max_marketing_plans';

    /**
     * Advisory-lock "class" for the saved-plan cap critical section in
     * store(): pg_advisory_xact_lock(CAP_LOCK_CLASS, user_id) serialises
     * concurrent creates per owner. Arbitrary but must be unique among
     * advisory-lock users of this database.
     */
    public const CAP_LOCK_CLASS = 67660;

    /** Saved-plan list. */
    public function index(Request $request)
    {
        if ($gate = $this->gateView($request)) return $gate;

        $plans = MarketingPlanCalc::listForOwner($request->user()->id, $this->workspaceId());

        return view('user.marketing-plan.index', [
            'plans'          => $plans,
            'latestStrategy' => $this->ownedStrategies($request)->orderByDesc('id')->first(['id', 'title']),
            'canCreate'      => $request->user()->planUnderLimit(self::LIMIT_KEY, $plans->count()),
            'planCap'        => (int) $request->user()->getPlanFeature(self::LIMIT_KEY, 0),
        ]);
    }

    /**
     * New-plan editor seeded from the spreadsheet's default benchmarks —
     * or, with `?from_strategy={id}` (Task #6739), pre-filled from one of
     * the owner's AI Marketing Strategist plans. Everything stays editable
     * before saving.
     */
    public function create(Request $request)
    {
        if ($gate = $this->gateView($request)) return $gate;
        if (!$this->underCap($request)) {
            return redirect()
                ->route('user.marketing-plan.index')
                ->with('limit_reached', true);
        }

        $payload  = MarketingPlanDefaults::defaults($request->user());
        $seedName = null;
        $aiSeed   = null;

        // Task #6767 — `?preset={key}` seeds the channel table from an
        // industry benchmark preset. Unknown keys fall back to generic.
        if (($presetKey = (string) $request->query('preset')) !== ''
            && MarketingPlanIndustryPresets::exists($presetKey)) {
            $payload = MarketingPlanIndustryPresets::apply($payload, $presetKey);
        }

        if (($strategyId = (int) $request->query('from_strategy')) > 0) {
            $strategy = $this->ownedStrategies($request)->whereKey($strategyId)->first();
            if (!$strategy) abort(404);

            $seed     = MarketingPlanAiSeed::fromStrategy($strategy, $request->user());
            $payload  = $seed['payload'];
            $seedName = $seed['name'];
            $aiSeed   = [
                'strategy_id'    => $strategy->id,
                'strategy_title' => (string) $strategy->title,
                'matched'        => $seed['matched'],
            ];
        }

        return view('user.marketing-plan.editor', [
            'plan'        => null,
            'payload'     => $payload,
            'planOptions' => MarketingPlanDefaults::planOptions(),
            'presets'     => MarketingPlanIndustryPresets::forClient(),
            'seedName'    => $seedName,
            'aiSeed'      => $aiSeed,
        ]);
    }

    /** Save a new named plan (AJAX). */
    public function store(Request $request)
    {
        $this->ensureEnabled($request);

        $data = $this->validatePlan($request);

        // Count-and-create must be atomic or two concurrent requests at the
        // last free slot both pass the cap check and both insert. A per-owner
        // Postgres advisory lock (transaction-scoped, auto-released on
        // commit/rollback) serialises the critical section; the cap is
        // (re)checked only after the lock is held.
        $plan = \DB::transaction(function () use ($request, $data) {
            \DB::select('select pg_advisory_xact_lock(?, ?)', [self::CAP_LOCK_CLASS, $request->user()->id]);

            if (!$this->underCap($request)) {
                return null;
            }

            return MarketingPlanCalc::create([
                'user_id'      => $request->user()->id,
                'workspace_id' => $this->workspaceId(),
                'name'         => $data['name'],
                'payload'      => $data['payload'],
            ]);
        });

        if (!$plan) {
            return response()->json([
                'ok'      => false,
                'message' => 'You have reached your plan\'s saved-plan limit. Upgrade to save more plans.',
            ], 403);
        }

        return response()->json([
            'ok'       => true,
            'id'       => $plan->id,
            'redirect' => route('user.marketing-plan.edit', $plan->id),
        ]);
    }

    /** Reopen a saved plan in the editor. */
    public function edit(Request $request, int $plan)
    {
        if ($gate = $this->gateView($request)) return $gate;

        $model = $this->findOwned($request, $plan);

        // Merge over the defaults so payloads saved before new fields were
        // added still open with sane values for the newer inputs.
        $payload = array_replace(MarketingPlanDefaults::defaults($request->user()), (array) $model->payload);

        // Task #6767 — plans saved before presets existed must read "Custom",
        // not inherit the defaults' 'generic' stamp from the merge above.
        if (!array_key_exists(MarketingPlanIndustryPresets::PAYLOAD_KEY, (array) $model->payload)) {
            unset($payload[MarketingPlanIndustryPresets::PAYLOAD_KEY]);
        }

        return view('user.marketing-plan.editor', [
            'plan'        => $model,
            'payload'     => $payload,
            'planOptions' => MarketingPlanDefaults::planOptions(),
            'presets'     => MarketingPlanIndustryPresets::forClient(),
        ]);
    }

    /** Update a saved plan (AJAX). */
    public function update(Request $request, int $plan)
    {
        $this->ensureEnabled($request);

        $model = $this->findOwned($request, $plan);
        $data  = $this->validatePlan($request);

        $model->update(['name' => $data['name'], 'payload' => $data['payload']]);

        return response()->json(['ok' => true, 'id' => $model->id]);
    }

    /** Delete a saved plan. */
    public function destroy(Request $request, int $plan)
    {
        $this->ensureEnabled($request);

        $this->findOwned($request, $plan)->delete();

        return redirect()
            ->route('user.marketing-plan.index')
            ->with('status', 'Plan deleted.');
    }

    /**
     * Validate a save/update. The payload is the owner's own free-form
     * assumption set, so validation focuses on shape + size, plus bounds
     * on the numbers the calculation engine consumes (Task #6742) so a
     * stored plan can never reload with nonsense inputs (0/negative FX
     * rate, negative costs, >100% conversion rates).
     *
     * @return array{name:string,payload:array<string,mixed>}
     */
    protected function validatePlan(Request $request): array
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:160',
            'payload' => 'required|array',

            // Task #6767 — the originating industry preset key (badge only,
            // free-form so a removed preset can't block re-saving old plans).
            'payload.industry_preset'  => 'nullable|string|max:64',

            // Engine-critical numbers — bounded, but nullable so older /
            // partial payloads still save.
            'payload.usd_inr_rate'     => 'nullable|numeric|min:1|max:100000',
            'payload.annual_budget'    => 'nullable|numeric|min:0|max:1000000000000',
            'payload.ai_credits'       => 'nullable|numeric|min:0|max:1000000000000',
            'payload.organic_visitors' => 'nullable|numeric|min:0|max:1000000000000',
            'payload.hours_per_tool'   => 'nullable|numeric|min:0|max:1000000000000',
            'payload.time_value'       => 'nullable|numeric|min:0|max:1000000000000',
            'payload.weights.*'        => 'nullable|numeric|min:0|max:100',
            // Task #6768 — finance assumptions for CAC/ROAS/LTV metrics.
            'payload.gross_margin'     => 'nullable|numeric|min:0|max:100',
            'payload.ltv_multiplier'   => 'nullable|numeric|min:0|max:1000',
            'payload.uplifts.chat'     => 'nullable|numeric|min:0|max:100',
            'payload.uplifts.crm'      => 'nullable|numeric|min:0|max:100',
            'payload.channels.*.alloc' => 'nullable|numeric|min:0|max:100',
            'payload.channels.*.cpv'   => 'nullable|numeric|min:0|max:1000000000000',
            'payload.channels.*.vl'    => 'nullable|numeric|min:0|max:100',
            'payload.channels.*.lc'    => 'nullable|numeric|min:0|max:100',
            'payload.channels.*.acv'   => 'nullable|numeric|min:0|max:1000000000000',
            'payload.tools.*.cost'     => 'nullable|numeric|min:0|max:1000000000000',
        ]);

        // Hard cap the stored blob so a hostile client can't bloat the row.
        if (strlen((string) json_encode($request->input('payload'))) > 120_000) {
            abort(422, 'Plan payload too large.');
        }

        // NOTE: once nested `payload.*` rules exist, validated()['payload']
        // would contain ONLY the ruled keys — return the full raw payload
        // (it passed the bounds checks above) so unruled keys survive.
        return ['name' => trim($validated['name']), 'payload' => (array) $request->input('payload')];
    }

    /**
     * Locked-out upgrade page when the plan doesn't include the calculator
     * (Task #6766), or null when the feature is enabled. GET actions render
     * this instead of the tool.
     */
    protected function gateView(Request $request)
    {
        if ($request->user()->planFeatureEnabled(self::FEATURE_KEY)) {
            return null;
        }

        return view('user.marketing-plan.locked', [
            'upgradePlan' => $request->user()->planThatUnlocks(self::FEATURE_KEY),
        ]);
    }

    /** Hard gate for write actions (AJAX/non-GET). */
    protected function ensureEnabled(Request $request): void
    {
        if (!$request->user()->planFeatureEnabled(self::FEATURE_KEY)) {
            abort(403, 'The Marketing Plan Calculator is not available on your current plan.');
        }
    }

    /**
     * True while the owner is below the saved-plan cap in the active
     * workspace. Existing plans stay viewable/editable/deletable at or over
     * the cap — only creating NEW plans is blocked.
     */
    protected function underCap(Request $request): bool
    {
        $count = MarketingPlanCalc::query()
            ->where('user_id', $request->user()->id)
            ->where('workspace_id', $this->workspaceId())
            ->count();

        return $request->user()->planUnderLimit(self::LIMIT_KEY, $count);
    }

    /** Owner-scoped plan lookup or 404. */
    protected function findOwned(Request $request, int $plan): MarketingPlanCalc
    {
        $model = MarketingPlanCalc::query()
            ->whereKey($plan)
            ->where('user_id', $request->user()->id)
            ->where('workspace_id', $this->workspaceId())
            ->first();

        if (!$model) abort(404);
        return $model;
    }

    /** The owner's AI Marketing Strategist plans in the active workspace. */
    protected function ownedStrategies(Request $request)
    {
        $wsId = $this->workspaceId();

        return MarketingStrategy::query()
            ->where('user_id', $request->user()->id)
            ->where(fn ($q) => $wsId === null
                ? $q->whereNull('workspace_id')
                : $q->where('workspace_id', $wsId));
    }

    /** Active workspace id (null when personal). */
    protected function workspaceId(): ?int
    {
        return app()->bound('current_workspace') ? (int) app('current_workspace')->id : null;
    }
}
