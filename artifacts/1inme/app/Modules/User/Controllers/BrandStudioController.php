<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\BrandStudioKit;
use App\Modules\User\Models\BrandStudioPreset;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\InsufficientCoinsForAiException;
use App\Services\Brand\AiBrandStudioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI Brand Studio (Task #5551) — bulk on-brand asset creator.
 *
 *   GET    brand-studio                → studio home: brief form + past kits
 *   POST   brand-studio/estimate       → upfront credit cost (JSON)
 *   POST   brand-studio/plan           → run the AI planning step (JSON)
 *   GET    brand-studio/{kit}          → review a proposal / view results
 *   POST   brand-studio/{kit}/confirm  → materialize the kept assets
 *   DELETE brand-studio/{kit}          → delete a kit record
 *   POST   brand-studio/presets        → save the current composition as a reusable combo (JSON)
 *   PATCH  brand-studio/presets/{preset} → rename a saved combo (JSON)
 *   DELETE brand-studio/presets/{preset} → delete a saved combo (JSON)
 *
 * The AI charge happens inside plan() via OpenAiService against the
 * `brand_studio` feature with auto-refund on parse failure. Confirming a
 * proposal is deterministic and free; per-type plan caps are enforced in
 * the service. Availability is plan-gated (`brand_studio`); bulk-variation
 * counts are capped per plan via `max_brand_studio_bulk`.
 */
class BrandStudioController extends Controller
{
    public function __construct(
        protected AiBrandStudioService $studio,
        protected AiUsageCharger $credits,
    ) {}

    public function index(Request $request)
    {
        $owner   = workspace_owner();
        $allowed = AiPlanAccess::featureAllowed($owner, AiBrandStudioService::FEATURE);

        $kits = BrandStudioKit::where('user_id', $owner->id)->latest()->limit(50)->get();

        return view('user.brand-studio.index', [
            'allowed'    => $allowed,
            'aiEnabled'  => AiEngineSettings::isEnabled(),
            'balance'    => AiEngineSettings::isEnabled() ? $this->credits->getBalance($owner) : 0,
            'kits'       => $kits,
            'brandKits'  => BrandKit::where('user_id', $owner->id)->latest()->get(['id', 'name']),
            'bulkCap'    => AiBrandStudioService::bulkCap($owner),
            'assetKinds' => AiBrandStudioService::ASSET_KINDS,
            'kitCaps'    => AiBrandStudioService::KIT_CAPS,
            'savedPresets' => BrandStudioPreset::where('user_id', $owner->id)->latest()->get()
                ->map(fn (BrandStudioPreset $p) => [
                    'id'    => $p->id,
                    'label' => $p->name,
                    'rows'  => array_values((array) $p->composition),
                ])->all(),
        ]);
    }

    public function storePreset(Request $request): JsonResponse
    {
        $owner = workspace_owner();

        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:60'],
            'composition'           => ['required', 'array', 'min:1', 'max:20'],
            'composition.*.kind'    => ['required', 'string', 'in:' . implode(',', AiBrandStudioService::ASSET_KINDS)],
            'composition.*.count'   => ['nullable', 'integer', 'min:1', 'max:10'],
            'composition.*.purpose' => ['nullable', 'string', 'max:' . AiBrandStudioService::MAX_PURPOSE_LEN],
        ]);

        try {
            $composition = AiBrandStudioService::sanitizeComposition($data['composition']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (BrandStudioPreset::where('user_id', $owner->id)->count() >= BrandStudioPreset::MAX_PER_USER) {
            return response()->json(['message' => 'You can save up to ' . BrandStudioPreset::MAX_PER_USER . ' combos. Delete one to save a new one.'], 422);
        }

        $name = trim($data['name']);
        $preset = BrandStudioPreset::updateOrCreate(
            ['user_id' => $owner->id, 'name' => $name],
            ['composition' => $composition],
        );

        return response()->json([
            'preset' => ['id' => $preset->id, 'label' => $preset->name, 'rows' => $composition],
        ]);
    }

    public function renamePreset(Request $request, BrandStudioPreset $preset): JsonResponse
    {
        abort_if($preset->user_id !== workspace_owner_id(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        $name = trim($data['name']);
        if ($name === '') {
            return response()->json(['message' => 'Please enter a combo name.'], 422);
        }

        // Names stay unique per user: block renaming onto another saved combo
        // instead of silently overwriting it.
        $taken = BrandStudioPreset::where('user_id', $preset->user_id)
            ->where('id', '!=', $preset->id)
            ->where('name', $name)
            ->exists();
        if ($taken) {
            return response()->json(['message' => 'You already have a saved combo with that name.'], 422);
        }

        $preset->update(['name' => $name]);

        return response()->json([
            'preset' => ['id' => $preset->id, 'label' => $preset->name, 'rows' => array_values((array) $preset->composition)],
        ]);
    }

    public function destroyPreset(Request $request, BrandStudioPreset $preset): JsonResponse
    {
        abort_if($preset->user_id !== workspace_owner_id(), 403);
        $preset->delete();
        return response()->json(['ok' => true]);
    }

    public function estimate(Request $request): JsonResponse
    {
        $owner = workspace_owner();
        if (!AiEngineSettings::isEnabled()) {
            return response()->json(['message' => 'AI Engine is disabled.'], 404);
        }
        if (!AiPlanAccess::featureAllowed($owner, AiBrandStudioService::FEATURE)) {
            return response()->json(['message' => 'AI Brand Studio is not available on your plan.'], 403);
        }

        $data = $this->validatePayload($request);

        try {
            $brand = $this->studio->resolveBrand($owner, $data['brand_kit_id'], $data['inline']);
            $cost  = $this->studio->estimateCredits($owner, $data['request'], $brand['directives'], $data['mode'], $data['bulk_kind'], $data['bulk_count'], $data['composition']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'estimated_credits' => $cost,
            'balance'           => $this->credits->getBalance($owner),
        ]);
    }

    public function plan(Request $request): JsonResponse
    {
        $owner = workspace_owner();
        if (!AiEngineSettings::isEnabled()) {
            return response()->json(['message' => 'AI Engine is disabled.'], 404);
        }
        if (!AiPlanAccess::featureAllowed($owner, AiBrandStudioService::FEATURE)) {
            return response()->json(['message' => 'AI Brand Studio is not available on your plan.'], 403);
        }

        $data = $this->validatePayload($request);

        try {
            $brand  = $this->studio->resolveBrand($owner, $data['brand_kit_id'], $data['inline']);
            $result = $this->studio->plan($owner, $data['request'], $brand['directives'], $brand['brand'], $data['mode'], $data['bulk_kind'], $data['bulk_count'], $data['composition']);
        } catch (InsufficientCoinsForAiException $e) {
            return response()->json([
                'message'  => 'Not enough coins for this Brand Studio run.',
                'required' => $e->required ?? null,
                'balance'  => $e->balance ?? null,
            ], 402);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'credits_spent' => $result['credits_spent'],
            'balance'       => $this->credits->getBalance($owner),
            'kit_id'        => $result['kit']->id,
            'redirect'      => route('user.brand-studio.show', $result['kit']),
        ]);
    }

    public function show(Request $request, BrandStudioKit $kit)
    {
        $this->authorizeKit($kit);

        $aiEnabled = AiEngineSettings::isEnabled();

        return view('user.brand-studio.show', [
            'kit'       => $kit,
            'aiEnabled' => $aiEnabled,
            'balance'   => $aiEnabled ? $this->credits->getBalance(workspace_owner()) : 0,
        ]);
    }

    public function confirm(Request $request, BrandStudioKit $kit)
    {
        $this->authorizeKit($kit);

        $data = $request->validate([
            'keep'   => ['nullable', 'array'],
            'keep.*' => ['integer', 'min:0'],
        ]);

        try {
            $result = $this->studio->materialize(workspace_owner(), $kit, $data['keep'] ?? null);
        } catch (\RuntimeException $e) {
            if ($request->ajax()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        }

        if ($request->ajax()) {
            return response()->json([
                'ok'       => true,
                'created'  => $result['created'],
                'skipped'  => $result['skipped'],
                'redirect' => route('user.brand-studio.show', $kit),
            ]);
        }
        return redirect()->route('user.brand-studio.show', $kit)
            ->with('status', $result['created'] . ' asset(s) created.');
    }

    public function destroy(Request $request, BrandStudioKit $kit)
    {
        $this->authorizeKit($kit);
        $refunded = $this->studio->discard($kit);

        if ($request->ajax()) {
            return response()->json(['ok' => true, 'refunded' => $refunded]);
        }
        return redirect()->route('user.brand-studio.index')->with(
            'status',
            $refunded > 0 ? "Plan discarded — {$refunded} credits refunded." : 'Kit deleted.'
        );
    }

    /**
     * @return array{request:string,mode:string,bulk_kind:?string,bulk_count:int,brand_kit_id:?int,inline:array<string,string>}
     */
    private function validatePayload(Request $request): array
    {
        $data = $request->validate([
            'request'               => ['nullable', 'required_without:composition', 'string', 'max:4000'],
            'mode'                  => ['nullable', 'in:kit,bulk'],
            'bulk_kind'             => ['nullable', 'in:' . implode(',', AiBrandStudioService::ASSET_KINDS)],
            'bulk_count'            => ['nullable', 'integer', 'min:1', 'max:' . AiBrandStudioService::HARD_BULK_CAP],
            'composition'           => ['nullable', 'array', 'max:20'],
            'composition.*.kind'    => ['required_with:composition', 'string', 'in:' . implode(',', AiBrandStudioService::ASSET_KINDS)],
            'composition.*.count'   => ['nullable', 'integer', 'min:1', 'max:10'],
            'composition.*.purpose' => ['nullable', 'string', 'max:' . AiBrandStudioService::MAX_PURPOSE_LEN],
            'brand_kit_id'          => ['nullable', 'integer'],
            'brand_name'            => ['nullable', 'string', 'max:160'],
            'brand_colors'          => ['nullable', 'string', 'max:300'],
            'brand_voice'           => ['nullable', 'string', 'max:500'],
            'brand_description'     => ['nullable', 'string', 'max:1000'],
        ]);

        $mode = (string) ($data['mode'] ?? 'kit');
        try {
            $composition = $mode === 'kit'
                ? AiBrandStudioService::sanitizeComposition($data['composition'] ?? [])
                : [];
        } catch (\RuntimeException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(['composition' => $e->getMessage()]);
        }
        return [
            'request'      => (string) ($data['request'] ?? ''),
            'mode'         => $mode,
            'composition'  => $composition,
            'bulk_kind'    => $data['bulk_kind'] ?? null,
            'bulk_count'   => (int) ($data['bulk_count'] ?? 5),
            'brand_kit_id' => isset($data['brand_kit_id']) ? (int) $data['brand_kit_id'] : null,
            'inline'       => [
                'name'        => (string) ($data['brand_name'] ?? ''),
                'colors'      => (string) ($data['brand_colors'] ?? ''),
                'voice'       => (string) ($data['brand_voice'] ?? ''),
                'description' => (string) ($data['brand_description'] ?? ''),
            ],
        ];
    }

    private function authorizeKit(BrandStudioKit $kit): void
    {
        abort_if($kit->user_id !== workspace_owner_id(), 403);
    }
}
