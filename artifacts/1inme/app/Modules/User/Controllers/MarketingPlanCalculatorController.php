<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\MarketingPlanCalc;
use App\Modules\User\Models\MarketingStrategy;
use App\Services\MarketingPlanActuals;
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

        $user     = $request->user();
        $payload  = MarketingPlanDefaults::defaults($user);
        $seedName = null;
        $aiSeed   = null;

        // Task #6767 — `?preset={key}` seeds the channel table from an
        // industry benchmark preset. Unknown keys fall back to generic.
        if (($presetKey = (string) $request->query('preset')) !== ''
            && MarketingPlanIndustryPresets::exists($presetKey)) {
            $payload = MarketingPlanIndustryPresets::apply($payload, $presetKey);
        }

        // Task #6772 — live Sayzio actuals for "Use my Sayzio data" and the
        // Plan vs Actual view. Only computed for users authorized to see the
        // workspace's business analytics; other members get no actuals data.
        $canActuals = $this->canViewActuals($request);
        $prefill = $canActuals
            ? MarketingPlanActuals::prefill($this->actualsSubject($request), $this->workspaceId())
            : null;
        $actuals = $prefill['summary'] ?? null;
        $actualsSeed = null;

        if (($strategyId = (int) $request->query('from_strategy')) > 0) {
            $strategy = $this->ownedStrategies($request)->whereKey($strategyId)->first();
            if (!$strategy) abort(404);

            $seed     = MarketingPlanAiSeed::fromStrategy($strategy, $user);
            $payload  = $seed['payload'];
            $seedName = $seed['name'];
            $aiSeed   = [
                'strategy_id'    => $strategy->id,
                'strategy_title' => (string) $strategy->title,
                'matched'        => $seed['matched'],
            ];
        } elseif ($request->boolean('use_actuals') && $prefill) {
            // Task #6772 — "Use my Sayzio data": start the plan from the
            // workspace's real analytics/leads/revenue instead of benchmarks.
            $payload     = $prefill['payload'];
            $actualsSeed = ['filled' => $prefill['filled'], 'sufficient' => $prefill['sufficient']];
        }

        // The fixed Sayzio row's AI-credit spend defaults to last month's
        // real coin spend (still editable) whenever the user hasn't typed one.
        if ($actuals && (float) ($payload['ai_credits'] ?? 0) <= 0 && (float) $actuals['ai_spend_last_month_inr'] > 0) {
            $payload['ai_credits'] = $actuals['ai_spend_last_month_inr'];
        }

        return view('user.marketing-plan.editor', [
            'plan'           => null,
            'payload'        => $payload,
            'planOptions'    => MarketingPlanDefaults::planOptions(),
            'presets'        => MarketingPlanIndustryPresets::forClient(),
            'seedName'       => $seedName,
            'aiSeed'         => $aiSeed,
            'toolsLocked'    => MarketingPlanDefaults::toolCostsLocked(),
            'actuals'        => $actuals,
            'actualsPrefill' => $prefill
                ? ['values' => $prefill['values'], 'filled' => $prefill['filled'], 'sufficient' => $prefill['sufficient']]
                : null,
            'actualsSeed'    => $actualsSeed,
        ]);
    }

    /**
     * Task #6772 — the workspace's live Sayzio actuals + the derived
     * prefill values, as JSON (used by tests and refresh-minded clients;
     * the editor also gets the same data embedded server-side).
     */
    public function actuals(Request $request)
    {
        // Sensitive owner analytics — membership alone is not enough.
        abort_unless($this->canViewActuals($request), 403);

        $prefill = MarketingPlanActuals::prefill($this->actualsSubject($request), $this->workspaceId());

        return response()->json([
            'ok'      => true,
            'actuals' => $prefill['summary'],
            'prefill' => [
                'values'     => $prefill['values'],
                'filled'     => $prefill['filled'],
                'sufficient' => $prefill['sufficient'],
            ],
        ]);
    }

    /** Save a new named plan (AJAX). */
    public function store(Request $request)
    {
        $this->ensureEnabled($request);

        $data = $this->validatePlan($request);

        // Admin-locked tool costs can never be overridden by the client.
        if (MarketingPlanDefaults::toolCostsLocked()) {
            $data['payload'] = MarketingPlanDefaults::enforceLockedToolCosts($data['payload']);
        }

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

        // Admin-locked tool costs always display the admin's numbers, even
        // on plans saved before the lock (or with older costs).
        if (MarketingPlanDefaults::toolCostsLocked()) {
            $payload = MarketingPlanDefaults::enforceLockedToolCosts($payload);
        }

        // Task #6772 — live actuals for the Plan vs Actual view and the
        // in-editor "Use my Sayzio data" action; withheld from members who
        // aren't authorized to see the workspace's business analytics.
        $prefill = $this->canViewActuals($request)
            ? MarketingPlanActuals::prefill($this->actualsSubject($request), $this->workspaceId())
            : null;

        return view('user.marketing-plan.editor', [
            'plan'           => $model,
            'payload'        => $payload,
            'planOptions'    => MarketingPlanDefaults::planOptions(),
            'presets'        => MarketingPlanIndustryPresets::forClient(),
            'toolsLocked'    => MarketingPlanDefaults::toolCostsLocked(),
            'actuals'        => $prefill['summary'] ?? null,
            'actualsPrefill' => $prefill
                ? ['values' => $prefill['values'], 'filled' => $prefill['filled'], 'sufficient' => $prefill['sufficient']]
                : null,
        ]);
    }

    /** Update a saved plan (AJAX). */
    public function update(Request $request, int $plan)
    {
        $this->ensureEnabled($request);

        $model = $this->findOwned($request, $plan);
        $data  = $this->validatePlan($request);

        // Admin-locked tool costs can never be overridden by the client.
        if (MarketingPlanDefaults::toolCostsLocked()) {
            $data['payload'] = MarketingPlanDefaults::enforceLockedToolCosts($data['payload']);
        }

        // Task #6771 — logged actuals must survive clients that resubmit a
        // payload without the actuals_log key (e.g. an older cached editor
        // re-saving assumptions): merge the stored log back in rather than
        // silently dropping months of tracked history.
        if (!array_key_exists('actuals_log', $data['payload'])
            && array_key_exists('actuals_log', (array) $model->payload)) {
            $data['payload']['actuals_log'] = ((array) $model->payload)['actuals_log'];
        }

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
            // Task #6769 — scenario multipliers (% of the Expected base;
            // 100 = unchanged). CPV must stay > 0 — it divides spend.
            'payload.scenarios.conservative.cpv'    => 'nullable|numeric|min:1|max:1000',
            'payload.scenarios.conservative.vl'     => 'nullable|numeric|min:0|max:1000',
            'payload.scenarios.conservative.lc'     => 'nullable|numeric|min:0|max:1000',
            'payload.scenarios.conservative.budget' => 'nullable|numeric|min:0|max:1000',
            'payload.scenarios.aggressive.cpv'      => 'nullable|numeric|min:1|max:1000',
            'payload.scenarios.aggressive.vl'       => 'nullable|numeric|min:0|max:1000',
            'payload.scenarios.aggressive.lc'       => 'nullable|numeric|min:0|max:1000',
            'payload.scenarios.aggressive.budget'   => 'nullable|numeric|min:0|max:1000',
            // Task #6771 — manually logged monthly actuals (overall totals,
            // not per channel). 12 slots; every field is optional so partial
            // entry ("only spend so far this month") is valid.
            'payload.actuals_log'             => 'nullable|array|max:12',
            'payload.actuals_log.*'           => 'nullable|array',
            'payload.actuals_log.*.spend'     => 'nullable|numeric|min:0|max:1000000000000',
            'payload.actuals_log.*.visitors'  => 'nullable|numeric|min:0|max:1000000000000',
            'payload.actuals_log.*.leads'     => 'nullable|numeric|min:0|max:1000000000000',
            'payload.actuals_log.*.customers' => 'nullable|numeric|min:0|max:1000000000000',
            'payload.actuals_log.*.revenue'   => 'nullable|numeric|min:0|max:1000000000000',
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
    /**
     * The user whose Sayzio data grounds the actuals: inside a workspace the
     * data belongs to the workspace OWNER (a team member browsing an owner's
     * workspace must see the owner's workspace actuals, never their own
     * personal numbers misattributed to the team). Falls back to the
     * authenticated user when no workspace context is bound.
     */
    protected function actualsSubject(Request $request): \App\Modules\User\Models\User
    {
        return app()->bound('workspace_owner')
            ? app('workspace_owner')
            : $request->user();
    }

    /**
     * Task #6772 — may the authenticated user see the active workspace's
     * live business actuals (traffic, leads, revenue, AI spend, feature
     * signals)? Membership alone is NOT enough: this is sensitive owner
     * analytics, so inside a team workspace it is restricted to the owner
     * and to members whose role is analytics-capable (admin / analyst).
     * Personal scope (no workspace bound) is always the user's own data.
     */
    protected function canViewActuals(Request $request): bool
    {
        if (!app()->bound('current_workspace')) {
            return true; // personal scope: the user's own data
        }

        $ws   = app('current_workspace');
        $user = $request->user();
        if (!$user || !$ws) return false;
        if ((int) $ws->owner_user_id === $user->id) return true;
        if ($user->hasPermission('user.workspaces.access_any')) return true;

        $membership = $user->membershipFor($ws);
        return $membership !== null
            && !$membership->isSuspended()
            && in_array($membership->role, ['admin', 'analyst'], true);
    }

    protected function workspaceId(): ?int
    {
        return app()->bound('current_workspace') ? (int) app('current_workspace')->id : null;
    }
}
