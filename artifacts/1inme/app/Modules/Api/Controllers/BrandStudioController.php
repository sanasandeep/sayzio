<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\BrandStudioKit;
use App\Modules\User\Models\BrandStudioPreset;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\InsufficientCoinsForAiException;
use App\Services\Brand\AiBrandStudioService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Mobile (Sanctum) parity for the web "AI Brand Studio" feature (Task #5551,
 * see App\Modules\User\Controllers\BrandStudioController for the web flow).
 *
 * Routes (all under /api/v1, auth:sanctum):
 *   GET    /brand-studio                gating + saved brand kits + past runs
 *   POST   /brand-studio/estimate       upfront credit cost
 *   POST   /brand-studio/plan           run the AI planning step
 *   GET    /brand-studio/{kit}          proposal / results detail
 *   POST   /brand-studio/{kit}/confirm  materialize the kept assets
 *   DELETE /brand-studio/{kit}          delete a kit record
 *   POST   /brand-studio/presets        save the current composition as a reusable combo
 *   PATCH  /brand-studio/presets/{preset} rename a saved combo
 *   DELETE /brand-studio/presets/{preset} delete a saved combo
 *
 * All heavy lifting (AI call, credit charge + auto-refund, proposal
 * sanitization, per-type plan caps at materialize time) is delegated to the
 * shared {@see AiBrandStudioService} exactly like the web controller, so the
 * two surfaces never drift. Kits are owned by `user_id`; the Sanctum path
 * never binds `current_workspace`, matching BrandKitController.
 */
class BrandStudioController extends Controller
{
    use ApiResponses;

    public function __construct(
        protected AiBrandStudioService $studio,
        protected AiUsageCharger $credits,
    ) {}

    public function index(Request $request)
    {
        $user      = $request->user();
        $aiEnabled = AiEngineSettings::isEnabled();
        $allowed   = AiPlanAccess::featureAllowed($user, AiBrandStudioService::FEATURE);

        return $this->ok([
            'available'   => $allowed,
            'ai_enabled'  => $aiEnabled,
            'balance'     => $aiEnabled ? $this->credits->getBalance($user) : 0,
            'bulk_cap'    => AiBrandStudioService::bulkCap($user),
            'asset_kinds' => AiBrandStudioService::ASSET_KINDS,
            'kit_caps'    => AiBrandStudioService::KIT_CAPS,
            'brand_kits'  => BrandKit::where('user_id', $user->id)->latest()->get(['id', 'name'])
                ->map(fn ($k) => ['id' => $k->id, 'name' => $k->name])->all(),
            'kits'        => BrandStudioKit::where('user_id', $user->id)->latest()->limit(50)->get()
                ->map(fn (BrandStudioKit $k) => $this->presentKit($k, false))->all(),
            'saved_presets' => BrandStudioPreset::where('user_id', $user->id)->latest()->get()
                ->map(fn (BrandStudioPreset $p) => $this->presentPreset($p))->all(),
        ]);
    }

    public function storePreset(Request $request)
    {
        $user = $request->user();

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
            return $this->fail($e->getMessage(), 422, 'invalid_composition');
        }

        if (BrandStudioPreset::where('user_id', $user->id)->count() >= BrandStudioPreset::MAX_PER_USER) {
            return $this->fail('You can save up to ' . BrandStudioPreset::MAX_PER_USER . ' combos. Delete one to save a new one.', 422, 'preset_limit_reached');
        }

        $preset = BrandStudioPreset::updateOrCreate(
            ['user_id' => $user->id, 'name' => trim($data['name'])],
            ['composition' => $composition],
        );

        return $this->ok(['preset' => $this->presentPreset($preset->refresh())]);
    }

    public function renamePreset(Request $request, BrandStudioPreset $preset)
    {
        abort_if($preset->user_id !== $request->user()->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        $name = trim($data['name']);
        if ($name === '') {
            return $this->fail('Please enter a combo name.', 422, 'invalid_name');
        }

        // Names stay unique per user: block renaming onto another saved combo
        // instead of silently overwriting it.
        $taken = BrandStudioPreset::where('user_id', $preset->user_id)
            ->where('id', '!=', $preset->id)
            ->where('name', $name)
            ->exists();
        if ($taken) {
            return $this->fail('You already have a saved combo with that name.', 422, 'name_taken');
        }

        $preset->update(['name' => $name]);

        return $this->ok(['preset' => $this->presentPreset($preset->refresh())]);
    }

    public function destroyPreset(Request $request, BrandStudioPreset $preset)
    {
        abort_if($preset->user_id !== $request->user()->id, 404);
        $preset->delete();
        return $this->ok(['deleted' => true]);
    }

    /** @return array{id:int,label:string,rows:list<array<string,mixed>>} */
    private function presentPreset(BrandStudioPreset $preset): array
    {
        return [
            'id'    => $preset->id,
            'label' => $preset->name,
            'rows'  => array_values((array) $preset->composition),
        ];
    }

    public function estimate(Request $request)
    {
        $user = $request->user();
        if (!AiEngineSettings::isEnabled()) {
            return $this->fail('AI Engine is disabled.', 404, 'ai_disabled');
        }
        if (!AiPlanAccess::featureAllowed($user, AiBrandStudioService::FEATURE)) {
            return $this->planGate('AI Brand Studio is not available on your plan.', AiBrandStudioService::FEATURE, $user);
        }

        $data = $this->validatePayload($request);

        try {
            $brand = $this->studio->resolveBrand($user, $data['brand_kit_id'], $data['inline']);
            $cost  = $this->studio->estimateCredits($user, $data['request'], $brand['directives'], $data['mode'], $data['bulk_kind'], $data['bulk_count'], $data['composition']);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422, 'invalid_request');
        }

        return $this->ok([
            'estimated_credits' => $cost,
            'balance'           => $this->credits->getBalance($user),
        ]);
    }

    public function plan(Request $request)
    {
        $user = $request->user();
        if (!AiEngineSettings::isEnabled()) {
            return $this->fail('AI Engine is disabled.', 404, 'ai_disabled');
        }
        if (!AiPlanAccess::featureAllowed($user, AiBrandStudioService::FEATURE)) {
            return $this->planGate('AI Brand Studio is not available on your plan.', AiBrandStudioService::FEATURE, $user);
        }

        $data = $this->validatePayload($request);

        try {
            $brand  = $this->studio->resolveBrand($user, $data['brand_kit_id'], $data['inline']);
            $result = $this->studio->plan($user, $data['request'], $brand['directives'], $brand['brand'], $data['mode'], $data['bulk_kind'], $data['bulk_count'], $data['composition']);
        } catch (InsufficientCoinsForAiException $e) {
            return $this->fail('Not enough coins for this Brand Studio run.', 402, 'insufficient_credits', [
                'required' => $e->required ?? null,
                'balance'  => $e->balance ?? null,
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422, 'plan_failed');
        }

        return $this->ok([
            'credits_spent' => $result['credits_spent'],
            'balance'       => $this->credits->getBalance($user),
            'kit'           => $this->presentKit($result['kit'], true),
        ]);
    }

    public function show(Request $request, BrandStudioKit $kit)
    {
        $this->authorizeKit($request, $kit);

        $aiEnabled = AiEngineSettings::isEnabled();

        return $this->ok([
            'kit'                   => $this->presentKit($kit, true),
            'balance'               => $aiEnabled ? $this->credits->getBalance($request->user()) : 0,
            'low_balance_threshold' => AiUsageCharger::lowBalanceThreshold(),
        ]);
    }

    public function confirm(Request $request, BrandStudioKit $kit)
    {
        $this->authorizeKit($request, $kit);

        $data = $request->validate([
            'keep'   => ['nullable', 'array'],
            'keep.*' => ['integer', 'min:0'],
        ]);

        try {
            $result = $this->studio->materialize($request->user(), $kit, $data['keep'] ?? null);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422, 'confirm_failed');
        }

        return $this->ok([
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'kit'     => $this->presentKit($result['kit'], true),
        ]);
    }

    public function destroy(Request $request, BrandStudioKit $kit)
    {
        $this->authorizeKit($request, $kit);
        $refunded = $this->studio->discard($kit);
        return $this->ok(['deleted' => true, 'refunded_credits' => $refunded]);
    }

    /** @return array<string,mixed> */
    private function presentKit(BrandStudioKit $kit, bool $detail): array
    {
        $base = [
            'id'            => $kit->id,
            'name'          => $kit->name,
            'mode'          => $kit->mode,
            'status'        => $kit->status,
            'asset_count'   => count($kit->isCreated() ? $kit->createdAssets() : $kit->proposedAssets()),
            'credits_spent' => (int) $kit->credits_spent,
            'created_at'    => $kit->created_at?->toIso8601String(),
        ];

        if ($detail) {
            $base['request']  = $kit->request;
            $base['proposal'] = ['assets' => $kit->proposedAssets()];
            $base['results']  = [
                'assets'  => $kit->createdAssets(),
                'skipped' => array_values((array) ($kit->results['skipped'] ?? [])),
            ];
        }

        return $base;
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

    private function authorizeKit(Request $request, BrandStudioKit $kit): void
    {
        abort_if($kit->user_id !== $request->user()->id, 404);
    }
}
