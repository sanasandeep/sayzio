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
use App\Services\Marketing\CampaignWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        $campaign = app(CampaignWorkspace::class)->requestCampaign($request);
        $owner = workspace_owner();
        $allowed = AiPlanAccess::featureAllowed($owner, AiBrandStudioService::FEATURE);

        $kits = BrandStudioKit::where('user_id', $owner->id)->latest()->limit(50)->get();

        return view($campaign ? 'user.marketing-studio.build' : 'user.brand-studio.index', [
            'campaign' => $campaign,
            'campaignBrief' => $campaign ? mb_substr(implode("\n\n", array_filter([$campaign->brief['goal'] ?? '', $campaign->brief['offer'] ?? '', $campaign->brief['content'] ?? ''])), 0, 4000) : '',
            'allowed' => $allowed,
            'aiEnabled' => AiEngineSettings::isEnabled(),
            'balance' => AiEngineSettings::isEnabled() ? $this->credits->getBalance($owner) : 0,
            'kits' => $kits,
            'brandKits' => BrandKit::where('user_id', $owner->id)->latest()->get(['id', 'name']),
            'bulkCap' => AiBrandStudioService::bulkCap($owner),
            'assetKinds' => AiBrandStudioService::ASSET_KINDS,
            'kitCaps' => AiBrandStudioService::KIT_CAPS,
            'savedPresets' => BrandStudioPreset::where('user_id', $owner->id)->latest()->get()
                ->map(fn (BrandStudioPreset $p) => [
                    'id' => $p->id,
                    'label' => $p->name,
                    'rows' => array_values((array) $p->composition),
                ])->all(),
        ]);
    }

    public function storePreset(Request $request): JsonResponse
    {
        $owner = workspace_owner();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'composition' => ['required', 'array', 'min:1', 'max:20'],
            'composition.*.kind' => ['required', 'string', 'in:'.implode(',', AiBrandStudioService::ASSET_KINDS)],
            'composition.*.count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'composition.*.purpose' => ['nullable', 'string', 'max:'.AiBrandStudioService::MAX_PURPOSE_LEN],
        ]);

        try {
            $composition = AiBrandStudioService::sanitizeComposition($data['composition']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (BrandStudioPreset::where('user_id', $owner->id)->count() >= BrandStudioPreset::MAX_PER_USER) {
            return response()->json(['message' => 'You can save up to '.BrandStudioPreset::MAX_PER_USER.' combos. Delete one to save a new one.'], 422);
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
        if (! AiEngineSettings::isEnabled()) {
            return response()->json(['message' => 'AI Engine is disabled.'], 404);
        }
        if (! AiPlanAccess::featureAllowed($owner, AiBrandStudioService::FEATURE)) {
            return response()->json(['message' => 'Marketing Studio material creation is not available on your plan.'], 403);
        }

        $data = $this->validatePayload($request);

        try {
            $brand = $this->studio->resolveBrand($owner, $data['brand_kit_id'], $data['inline']);
            $cost = $this->studio->estimateCredits($owner, $data['request'], $brand['directives'].($data['campaign'] ? '\n'.app(CampaignWorkspace::class)->brief($data['campaign'], $data['strategy_id']) : ''), $data['mode'], $data['bulk_kind'], $data['bulk_count'], $data['composition']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'quote' => $data['campaign'] ? app(CampaignWorkspace::class)->quote($request, $data['campaign'], 'create', $data) : null,
            'estimated_credits' => $cost,
            'balance' => $this->credits->getBalance($owner),
        ]);
    }

    public function plan(Request $request): JsonResponse
    {
        $owner = workspace_owner();
        if (! AiEngineSettings::isEnabled()) {
            return response()->json(['message' => 'AI Engine is disabled.'], 404);
        }
        if (! AiPlanAccess::featureAllowed($owner, AiBrandStudioService::FEATURE)) {
            return response()->json(['message' => 'Marketing Studio material creation is not available on your plan.'], 403);
        }

        $data = $this->validatePayload($request);

        if ($data['campaign']) {
            app(CampaignWorkspace::class)->quote($request, $data['campaign'], 'create', $data, true);
        }
        try {
            $brand = $this->studio->resolveBrand($owner, $data['brand_kit_id'], $data['inline']);
            $result = $this->studio->plan($owner, $data['request'], $brand['directives'].($data['campaign'] ? '\n'.app(CampaignWorkspace::class)->brief($data['campaign'], $data['strategy_id']) : ''), $brand['brand'], $data['mode'], $data['bulk_kind'], $data['bulk_count'], $data['composition']);
        } catch (InsufficientCoinsForAiException $e) {
            return response()->json([
                'message' => 'Not enough coins for this Brand Studio run.',
                'required' => $e->required ?? null,
                'balance' => $e->balance ?? null,
            ], 402);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($data['campaign']) {
            app(CampaignWorkspace::class)->connect($data['campaign'], 'studio', $result['kit']->id);
        }

        return response()->json([
            'credits_spent' => $result['credits_spent'],
            'balance' => $this->credits->getBalance($owner),
            'kit_id' => $result['kit']->id,
            'redirect' => route('user.brand-studio.show', $result['kit']),
        ]);
    }

    public function show(Request $request, BrandStudioKit $kit)
    {
        $this->authorizeKit($kit);

        $aiEnabled = AiEngineSettings::isEnabled();

        return view('user.brand-studio.show', [
            'campaign' => app(CampaignWorkspace::class)->forItem('studio', $kit->id, $request),
            'kit' => $kit,
            'aiEnabled' => $aiEnabled,
            'balance' => $aiEnabled ? $this->credits->getBalance(workspace_owner()) : 0,
        ]);
    }

    public function confirm(Request $request, BrandStudioKit $kit)
    {
        $this->authorizeKit($kit);

        $data = $request->validate([
            'retry' => 'nullable|boolean',
            'selection_submitted' => 'nullable|boolean',
            'keep' => ['nullable', 'required_if:selection_submitted,1', 'array', 'min:1'],
            'keep.*' => ['integer', 'min:0'],
            'edits' => 'nullable|array|max:50',
            'edits.*.title' => 'nullable|string|max:160',
            'edits.*.url' => 'nullable|url:http,https|max:2048',
            'edits.*.description' => 'nullable|string|max:500',
            'edits.*.blocks' => 'nullable|array|max:30',
            'edits.*.blocks.*.text' => 'nullable|string|max:2000',
        ]);

        try {
            $result = DB::transaction(function () use ($request, $kit, $data) {
                $locked = BrandStudioKit::whereKey($kit->id)->lockForUpdate()->firstOrFail();
                $campaign = app(CampaignWorkspace::class)->forItem('studio', $kit->id, $request);
                if ($locked->isCreated() && ! ($campaign && $request->boolean('retry'))) {
                    return ['created' => 0, 'skipped' => []];
                }
                $proposal = $locked->proposal;
                foreach ($data['edits'] ?? [] as $index => $edit) {
                    if (! isset($proposal['assets'][$index])) {
                        continue;
                    }
                    $asset = &$proposal['assets'][$index];
                    if (! empty($edit['title'])) {
                        $asset[$asset['kind'] === 'qr_code' ? 'name' : 'title'] = $edit['title'];
                    }
                    if (isset($edit['url']) && in_array($asset['kind'], ['short_link', 'qr_code'])) {
                        $asset['url'] = $edit['url'];
                    }
                    if (isset($edit['description']) && $asset['kind'] === 'form') {
                        $asset['description'] = $edit['description'];
                    }
                    foreach ($edit['blocks'] ?? [] as $j => $blockEdit) {
                        if (isset($asset['blocks'][$j]) && in_array($asset['blocks'][$j]['type'], ['heading', 'paragraph', 'link', 'cta_button'])) {
                            $asset['blocks'][$j]['settings']['text'] = $blockEdit['text'] ?? '';
                        }
                    }
                    unset($asset);
                }
                $locked->update(['proposal' => $proposal]);

                if ($campaign) {
                    foreach ($locked->proposedAssets() as $i => $asset) {
                        if (isset($data['keep']) && ! in_array($i, $data['keep'])) {
                            continue;
                        }$permission = match ($asset['kind']) {
                            'form' => 'forms.create','qr_code' => 'qr.create',default => 'links.create'
                        };
                        abort_unless($request->user()->canInWorkspace(app('current_workspace'), $permission), 403);
                    }
                }
                $existing = $campaign ? collect(app(CampaignWorkspace::class)->materials($campaign))->map(fn ($item) => $item['kind'].':'.$item['id'])->all() : [];
                $result = $this->studio->materialize(workspace_owner(), $locked, $data['keep'] ?? null, $campaign && $request->boolean('retry'));
                if ($campaign) {
                    $workspace = app(CampaignWorkspace::class);
                    $workspace->finalizeDrafts($campaign, $kit->id, $request, $existing);
                }

                return $result;
            });
        } catch (\RuntimeException $e) {
            if ($request->ajax()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->ajax()) {
            return response()->json([
                'ok' => true,
                'created' => $result['created'],
                'skipped' => $result['skipped'],
                'redirect' => route('user.brand-studio.show', $kit),
            ]);
        }

        return redirect()->route('user.brand-studio.show', $kit)
            ->with('status', $result['created'].' asset(s) created.');
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
            'request' => ['nullable', 'required_without:composition', 'string', 'max:4000'],
            'mode' => ['nullable', 'in:kit,bulk'],
            'bulk_kind' => ['nullable', 'in:'.implode(',', AiBrandStudioService::ASSET_KINDS)],
            'bulk_count' => ['nullable', 'integer', 'min:1', 'max:'.AiBrandStudioService::HARD_BULK_CAP],
            'composition' => ['nullable', 'array', 'max:20'],
            'composition.*.kind' => ['required_with:composition', 'string', 'in:'.implode(',', AiBrandStudioService::ASSET_KINDS)],
            'composition.*.count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'composition.*.purpose' => ['nullable', 'string', 'max:'.AiBrandStudioService::MAX_PURPOSE_LEN],
            'strategy_id' => ['nullable', 'integer'],
            'brand_kit_id' => ['nullable', 'integer'],
            'brand_name' => ['nullable', 'string', 'max:160'],
            'brand_colors' => ['nullable', 'string', 'max:300'],
            'brand_voice' => ['nullable', 'string', 'max:500'],
            'brand_description' => ['nullable', 'string', 'max:1000'],
        ]);

        $mode = (string) ($data['mode'] ?? 'kit');
        try {
            $composition = $mode === 'kit'
                ? AiBrandStudioService::sanitizeComposition($data['composition'] ?? [])
                : [];
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['composition' => $e->getMessage()]);
        }

        return [
            'campaign' => app(CampaignWorkspace::class)->requestCampaign($request),
            'strategy_id' => $request->filled('strategy_id') ? (int) $request->input('strategy_id') : null,
            'request' => (string) ($data['request'] ?? ''),
            'mode' => $mode,
            'composition' => $composition,
            'bulk_kind' => $data['bulk_kind'] ?? null,
            'bulk_count' => (int) ($data['bulk_count'] ?? 5),
            'brand_kit_id' => isset($data['brand_kit_id']) ? (int) $data['brand_kit_id'] : null,
            'inline' => [
                'name' => (string) ($data['brand_name'] ?? ''),
                'colors' => (string) ($data['brand_colors'] ?? ''),
                'voice' => (string) ($data['brand_voice'] ?? ''),
                'description' => (string) ($data['brand_description'] ?? ''),
            ],
        ];
    }

    private function authorizeKit(BrandStudioKit $kit): void
    {
        abort_if($kit->user_id !== workspace_owner_id(), 403);
    }
}
