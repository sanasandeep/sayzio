<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\MarketingPlanCalc;
use App\Services\MarketingPlanDefaults;
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
    /** Saved-plan list. */
    public function index(Request $request)
    {
        return view('user.marketing-plan.index', [
            'plans' => MarketingPlanCalc::listForOwner($request->user()->id, $this->workspaceId()),
        ]);
    }

    /** New-plan editor seeded from the spreadsheet's default benchmarks. */
    public function create(Request $request)
    {
        return view('user.marketing-plan.editor', [
            'plan'        => null,
            'payload'     => MarketingPlanDefaults::defaults($request->user()),
            'planOptions' => MarketingPlanDefaults::planOptions(),
        ]);
    }

    /** Save a new named plan (AJAX). */
    public function store(Request $request)
    {
        $data = $this->validatePlan($request);

        $plan = MarketingPlanCalc::create([
            'user_id'      => $request->user()->id,
            'workspace_id' => $this->workspaceId(),
            'name'         => $data['name'],
            'payload'      => $data['payload'],
        ]);

        return response()->json([
            'ok'       => true,
            'id'       => $plan->id,
            'redirect' => route('user.marketing-plan.edit', $plan->id),
        ]);
    }

    /** Reopen a saved plan in the editor. */
    public function edit(Request $request, int $plan)
    {
        $model = $this->findOwned($request, $plan);

        // Merge over the defaults so payloads saved before new fields were
        // added still open with sane values for the newer inputs.
        $payload = array_replace(MarketingPlanDefaults::defaults($request->user()), (array) $model->payload);

        return view('user.marketing-plan.editor', [
            'plan'        => $model,
            'payload'     => $payload,
            'planOptions' => MarketingPlanDefaults::planOptions(),
        ]);
    }

    /** Update a saved plan (AJAX). */
    public function update(Request $request, int $plan)
    {
        $model = $this->findOwned($request, $plan);
        $data  = $this->validatePlan($request);

        $model->update(['name' => $data['name'], 'payload' => $data['payload']]);

        return response()->json(['ok' => true, 'id' => $model->id]);
    }

    /** Delete a saved plan. */
    public function destroy(Request $request, int $plan)
    {
        $this->findOwned($request, $plan)->delete();

        return redirect()
            ->route('user.marketing-plan.index')
            ->with('status', 'Plan deleted.');
    }

    /**
     * Validate a save/update. The payload is the owner's own free-form
     * assumption set, so validation focuses on shape + size, not values.
     *
     * @return array{name:string,payload:array<string,mixed>}
     */
    protected function validatePlan(Request $request): array
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:160',
            'payload' => 'required|array',
        ]);

        // Hard cap the stored blob so a hostile client can't bloat the row.
        if (strlen((string) json_encode($validated['payload'])) > 120_000) {
            abort(422, 'Plan payload too large.');
        }

        return ['name' => trim($validated['name']), 'payload' => $validated['payload']];
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

    /** Active workspace id (null when personal). */
    protected function workspaceId(): ?int
    {
        return app()->bound('current_workspace') ? (int) app('current_workspace')->id : null;
    }
}
