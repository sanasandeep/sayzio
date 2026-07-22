<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiMindDefault;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\QrCode;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiMindProvisioner;
use App\Services\AI\AiMindQueryService;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\InsufficientCoinsForAiException;
use App\Services\Brand\AiBrandKitService;
use App\Services\Brand\BrandConsistencyService;
use App\Services\Brand\BrandKitAssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * AI Brand Kit endpoints (Task #2662).
 *
 *   GET    brand-kits                              → list + generate UI
 *   POST   brand-kits/estimate                     → upfront credit cost
 *   POST   brand-kits/generate                     → run generation, save a kit
 *   DELETE brand-kits/{brandKit}                   → delete a kit
 *   POST   brand-kits/{brandKit}/apply/biolink/{link} → apply to a biolink
 *   POST   brand-kits/{brandKit}/apply/qr/{qrCode}    → apply to a QR code
 *
 * The AI charge happens inside the generate() call via OpenAiService against
 * the `brand_kit` feature — no new currency/coin path. Generation is gated
 * by the per-plan `max_brand_kits` quantity cap; plan-less users (cap 0) see
 * an upgrade prompt instead of the generate form.
 */
class BrandKitController extends Controller
{
    public function __construct(
        protected AiBrandKitService $kits,
        protected AiUsageCharger $credits,
        protected AiMindQueryService $minds,
        protected BrandKitAssetService $assets,
    ) {}

    public function index(Request $request)
    {
        $user  = $request->user();
        $list  = BrandKit::where('user_id', $user->id)->latest()->get();
        $count = $list->count();

        $cap        = AiPlanAccess::quantityCap($user, 'brand_kits');
        $canCreate  = AiPlanAccess::underQuantityCap($user, 'brand_kits', $count);
        $upgrade    = $canCreate ? null : AiPlanAccess::quantityUpgradePlan($user, 'brand_kits', $count);
        $aiEnabled  = AiEngineSettings::isEnabled();

        // Ensure the user's own Minds exist, then pre-populate the KB
        // picker from their saved Brand Kit default so they don't have
        // to re-pick every visit (mirrors Coach / Persona).
        AiMindProvisioner::ensureForUser($user);
        $input   = [];
        $default = AiMindDefault::forUserFeature($user->id, AiBrandKitService::FEATURE);
        if ($default) {
            $input['mind_ids']         = $default->mind_ids ?? [];
            $input['include_platform'] = $default->include_platform;
        }

        // Targets the user can apply a kit to. `settings`/`type` are needed by
        // the Brand Consistency audit below (it reads settings['biolink']).
        $biolinks = Link::where('user_id', workspace_owner_id())
            ->where('type', 'biolink')
            ->orderByDesc('id')
            ->get(['id', 'title', 'alias', 'type', 'settings']);
        $qrCodes = QrCode::where('user_id', workspace_owner_id())
            ->orderByDesc('id')
            ->get(['id', 'name']);

        // Brand Consistency Score (Task #2664): audit the creator's biolinks
        // against their default Brand Kit. Plan-gated behind the legacy-safe
        // `brand_consistency` feature; null when ungated or no kit exists.
        $consistency  = null;
        $onBrandKit   = AiPlanAccess::featureAllowed($user, 'brand_consistency')
            ? BrandKit::defaultFor($user->id)
            : null;
        if ($onBrandKit) {
            $consistency = app(BrandConsistencyService::class)->audit($onBrandKit, $biolinks);
        }

        return view('user.brand-kits.index', [
            'kits'         => $list,
            'count'        => $count,
            'cap'          => $cap,
            'canCreate'    => $canCreate,
            'upgradePlan'  => $upgrade,
            'aiEnabled'    => $aiEnabled,
            'balance'      => $aiEnabled ? $this->credits->getBalance($user) : 0,
            'biolinks'     => $biolinks,
            'qrCodes'      => $qrCodes,
            'blockThemes'  => $this->kits->allowedBlockThemes(),
            'consistency'  => $consistency,
            'consistencyKit' => $onBrandKit,
            'input'        => $input,
            'mineMinds'    => $this->userMinds($user),
            'platformMind' => $this->platformMind(),
            'hasDefault'   => (bool) $default,
            'defaultFeature' => AiBrandKitService::FEATURE,
            'assetTypes'    => $this->assetTypeOptions($user),
        ]);
    }

    /**
     * Save the current Mind selection (from the picker form) as this
     * user's default for Brand Kit. Subsequent visits pre-populate it.
     */
    public function saveDefaults(Request $request)
    {
        $data = $request->validate([
            'mind_ids'         => 'nullable|array',
            'mind_ids.*'       => 'integer',
            'include_platform' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $mindIds = array_values(array_unique(array_map('intval', $data['mind_ids'] ?? [])));
        // Constrain to the user's own active Minds so we don't store
        // stale or cross-user ids in defaults.
        if ($mindIds) {
            $mindIds = AiMind::where('user_id', $user->id)
                ->where('is_disabled', false)
                ->whereIn('id', $mindIds)
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->all();
        }

        AiMindDefault::updateOrCreate(
            ['user_id' => $user->id, 'feature' => AiBrandKitService::FEATURE],
            [
                'mind_ids'         => $mindIds,
                'include_platform' => (bool) ($data['include_platform'] ?? false),
            ],
        );

        return redirect()->route('user.brand-kits.index')
            ->with('status', 'Saved as your default Mind selection for Brand Kit.');
    }

    /**
     * Forget this user's default Mind selection for Brand Kit.
     */
    public function clearDefaults(Request $request)
    {
        AiMindDefault::where('user_id', $request->user()->id)
            ->where('feature', AiBrandKitService::FEATURE)
            ->delete();

        return redirect()->route('user.brand-kits.index')
            ->with('status', 'Cleared your default Mind selection for Brand Kit.');
    }

    public function estimate(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!AiEngineSettings::isEnabled()) {
            return response()->json(['message' => 'AI Engine is disabled.'], 404);
        }

        $data = $this->validatePayload($request);

        try {
            $cost = $this->kits->estimateCredits($user, $data['prompt'], $data['website_url'], $data['logo_url'], '', $data['components']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Each requested image asset is a separate flat per-generation charge.
        $assetCost = 0;
        foreach ($data['asset_types'] as $type) {
            $assetCost += $this->assets->coinCost($user, $type);
        }

        return response()->json([
            'estimated_credits' => $cost + $assetCost,
            'balance'           => $this->credits->getBalance($user),
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!AiEngineSettings::isEnabled()) {
            return response()->json(['message' => 'AI Engine is disabled.'], 404);
        }

        $count = BrandKit::where('user_id', $user->id)->count();
        if (!AiPlanAccess::underQuantityCap($user, 'brand_kits', $count)) {
            return response()->json([
                'message' => AiPlanAccess::quantityLimitMessage($user, 'brand_kits', 'brand kit', $count),
            ], 403);
        }

        $data = $this->validatePayload($request);

        // Resolve the picked Knowledge Bases (own minds + optional
        // platform default), validate ownership server-side, and pull
        // the most relevant chunks to ground the generation. Selecting
        // none leaves $kbContext empty → identical to today's behavior.
        $selectedMinds  = $this->minds->resolveMindsForUser($user, $data['mind_ids'], $data['include_platform']);
        $kbContext      = '';
        $kbCreditsSpent = 0;
        $citations      = [];
        $mindStats      = [];
        if ($selectedMinds) {
            $retrievalQuery = trim($data['prompt'] . ' ' . (string) $data['website_url'] . ' ' . (string) $data['logo_url']);
            if ($retrievalQuery === '') {
                $retrievalQuery = 'brand identity palette voice tagline';
            }
            try {
                $retrieved      = $this->minds->retrieveContext($user, $selectedMinds, $retrievalQuery);
                $kbContext      = $retrieved['context'];
                $citations      = $retrieved['citations'];
                $kbCreditsSpent = (int) $retrieved['credits_spent'];
                $mindStats      = $retrieved['mind_stats'] ?? [];
            } catch (InsufficientCoinsForAiException $e) {
                throw $e;
            } catch (\Throwable $e) {
                Log::warning('Brand Kit Mind retrieval failed: ' . $e->getMessage());
            }
        }

        try {
            $result = $this->kits->generate($user, $data['prompt'], $data['website_url'], $data['logo_url'], $kbContext, $data['components']);
        } catch (InsufficientCoinsForAiException $e) {
            return response()->json([
                'message'  => 'Not enough coins to generate this brand kit.',
                'required' => $e->required ?? null,
                'balance'  => $e->balance ?? null,
            ], 402);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Generate any requested image assets right after the kit. Each is a
        // separate charge with its own refund-on-failure inside the service;
        // one failed image never rolls back the kit or the other images.
        $assetsSpent  = 0;
        $assetsDone   = [];
        $assetErrors  = [];
        foreach ($data['asset_types'] as $type) {
            try {
                $asset = $this->assets->generate($user, $result['kit'], $type);
                $assetsSpent += (int) $asset->credits_spent;
                $assetsDone[] = $type;
            } catch (\Throwable $e) {
                $label = BrandKitAssetService::TYPES[$type]['label'] ?? $type;
                $assetErrors[] = $label . ': ' . $e->getMessage();
                Log::warning('Brand kit inline asset generation failed', ['type' => $type, 'error' => $e->getMessage()]);
            }
        }
        if ($assetsDone) {
            session()->flash('status', count($assetsDone) . ' brand image' . (count($assetsDone) === 1 ? '' : 's') . ' generated: open the kit\'s Visual assets panel to view them.');
        }
        if ($assetErrors) {
            session()->flash('error', 'Some images could not be generated: ' . implode(' · ', $assetErrors));
        }

        return response()->json([
            'credits_spent' => (int) $result['credits_spent'] + $kbCreditsSpent + $assetsSpent,
            'balance'       => $this->credits->getBalance($user),
            'kit'           => [
                'id'     => $result['kit']->id,
                'name'   => $result['kit']->name,
                'config' => $result['kit']->config,
            ],
            'citations'     => $citations,
            'minds_used'    => array_map(
                fn(AiMind $m) => [
                    'id'          => (int) $m->id,
                    'name'        => (string) $m->name,
                    'is_platform' => $m->isPlatform(),
                    'chunks_used' => (int) ($mindStats[(int) $m->id]['chunks_used'] ?? 0),
                    'top_score'   => (float) ($mindStats[(int) $m->id]['top_score'] ?? 0.0),
                ],
                $selectedMinds,
            ),
            'redirect'      => route('user.brand-kits.index'),
        ]);
    }

    public function destroy(Request $request, BrandKit $brandKit)
    {
        $this->authorizeKit($request, $brandKit);
        $brandKit->delete();

        if ($request->ajax()) {
            return response()->json(['ok' => true]);
        }
        return redirect()->route('user.brand-kits.index')->with('status', 'Brand kit deleted.');
    }

    public function applyToBiolink(Request $request, BrandKit $brandKit, Link $link)
    {
        $this->authorizeKit($request, $brandKit);
        abort_if($link->user_id !== workspace_owner_id() || $link->type !== 'biolink', 403);

        $this->kits->applyToBiolink($brandKit, $link);

        if ($request->ajax()) {
            return response()->json([
                'ok'       => true,
                'redirect' => route('user.links.blocks.editor', $link),
            ]);
        }
        return redirect()
            ->route('user.links.blocks.editor', $link)
            ->with('status', 'Brand kit applied to this Link in Bio.');
    }

    public function applyToQr(Request $request, BrandKit $brandKit, QrCode $qrCode)
    {
        $this->authorizeKit($request, $brandKit);
        abort_if($qrCode->user_id !== workspace_owner_id(), 403);

        $this->kits->applyToQr($brandKit, $qrCode);

        if ($request->ajax()) {
            return response()->json([
                'ok'       => true,
                'redirect' => route('user.qr-codes.edit', $qrCode),
            ]);
        }
        return redirect()
            ->route('user.qr-codes.edit', $qrCode)
            ->with('status', 'Brand kit applied to this QR code.');
    }

    /**
     * @return array{prompt:string,website_url:?string,logo_url:?string,mind_ids:int[],include_platform:bool,components:string[]}
     */
    private function validatePayload(Request $request): array
    {
        $data = $request->validate([
            'prompt'           => ['nullable', 'string', 'max:2000'],
            'website_url'      => ['nullable', 'string', 'max:2048'],
            'logo_url'         => ['nullable', 'string', 'max:2048'],
            'mind_ids'         => ['nullable', 'array'],
            'mind_ids.*'       => ['integer'],
            'include_platform' => ['nullable', 'boolean'],
            'components'       => ['nullable', 'array'],
            'components.*'     => ['string', 'in:' . implode(',', AiBrandKitService::COMPONENTS)],
            'asset_types'      => ['nullable', 'array'],
            'asset_types.*'    => ['string', 'in:' . implode(',', array_keys(BrandKitAssetService::TYPES))],
        ]);

        return [
            'prompt'           => (string) ($data['prompt'] ?? ''),
            'website_url'      => $data['website_url'] ?? null,
            'logo_url'         => $data['logo_url'] ?? null,
            'mind_ids'         => array_map('intval', $data['mind_ids'] ?? []),
            'include_platform' => (bool) ($data['include_platform'] ?? false),
            // Empty selection = generate everything (back-compat for callers
            // that never send the field, e.g. the mobile API path).
            'components'       => array_values(array_map('strval', $data['components'] ?? [])),
            // Optional image assets to generate right after the kit itself
            // (each is a separate flat coin charge; empty = none).
            'asset_types'      => array_values(array_unique(array_map('strval', $data['asset_types'] ?? []))),
        ];
    }

    /**
     * Image-asset choices for the generate form's "What to include" section.
     * Empty when image generation is unavailable (engine off / no key) or the
     * user's plan doesn't include brand asset generation.
     *
     * @return list<array{type:string,label:string,cost:int}>
     */
    private function assetTypeOptions($user): array
    {
        if (!$this->assets->enabled() || !AiPlanAccess::featureAllowed($user, 'brand_kit_assets')) {
            return [];
        }
        $out = [];
        foreach (BrandKitAssetService::TYPES as $type => $meta) {
            $out[] = [
                'type'  => $type,
                'label' => $meta['label'],
                'cost'  => $this->assets->coinCost($user, $type),
            ];
        }
        return $out;
    }

    /** @return \Illuminate\Support\Collection<int,AiMind> */
    protected function userMinds($user)
    {
        return AiMind::where('user_id', $user->id)
            ->where('is_disabled', false)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    protected function platformMind(): ?AiMind
    {
        return AiMind::whereNull('user_id')
            ->where('is_default', true)
            ->where('is_disabled', false)
            ->first(['id', 'name']);
    }

    private function authorizeKit(Request $request, BrandKit $brandKit): void
    {
        abort_if($brandKit->user_id !== $request->user()->id, 403);
    }

    // ── AI-generated visual assets (Task #5612) ───────────────────────

    /** Catalog + current assets for one kit (drives the Assets panel). */
    public function assets(Request $request, BrandKit $brandKit, \App\Services\Brand\BrandKitAssetService $assets): JsonResponse
    {
        $this->authorizeKit($request, $brandKit);
        $user = $request->user();

        return response()->json([
            'enabled'   => $assets->enabled(),
            'allowed'   => AiPlanAccess::featureAllowed($user, 'brand_kit_assets'),
            'balance'   => $this->credits->getBalance($user),
            'types'     => $assets->catalogFor($user, $brandKit),
        ]);
    }

    /** Generate or regenerate one asset (optional tweak instructions). */
    public function generateAsset(Request $request, BrandKit $brandKit, string $type, \App\Services\Brand\BrandKitAssetService $assets): JsonResponse
    {
        $this->authorizeKit($request, $brandKit);
        $user = $request->user();

        $data = $request->validate([
            'instructions' => 'nullable|string|max:1000',
            'mode'         => 'nullable|string|in:new,variation,alteration',
        ]);

        try {
            $asset = $assets->generate($user, $brandKit, $type, $data['instructions'] ?? null, $data['mode'] ?? 'new');
        } catch (InsufficientCoinsForAiException $e) {
            return response()->json([
                'message'  => 'Not enough coins to generate this asset.',
                'required' => $e->required ?? null,
                'balance'  => $e->balance ?? null,
            ], 402);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'asset'   => $assets->present($asset),
            'balance' => $this->credits->getBalance($user),
        ]);
    }

    /** Delete one asset and its stored image. */
    public function destroyAsset(Request $request, BrandKit $brandKit, string $type, \App\Services\Brand\BrandKitAssetService $assets): JsonResponse
    {
        $this->authorizeKit($request, $brandKit);

        $asset = \App\Modules\User\Models\BrandKitAsset::where('brand_kit_id', $brandKit->id)
            ->where('type', $type)->first();
        abort_if(!$asset, 404);

        $assets->delete($asset);

        return response()->json(['ok' => true]);
    }

    /**
     * One-click apply. Targets:
     *   kit_logo            — set the kit's config logo_url (logo/avatar/watermark)
     *   biolink_favicon     — set a biolink's favicon (requires link_id)
     *   biolink_og          — set a biolink's SEO share image (requires link_id)
     *   company_letterhead  — set a BillingCompany's letterhead (requires company_id)
     */
    public function applyAsset(Request $request, BrandKit $brandKit, string $type, \App\Services\Brand\BrandKitAssetService $assets): JsonResponse
    {
        $this->authorizeKit($request, $brandKit);
        $user = $request->user();

        $data = $request->validate([
            'target'     => 'required|string|in:kit_logo,biolink_favicon,biolink_og,company_letterhead',
            'link_id'    => 'nullable|integer',
            'company_id' => 'nullable|integer',
        ]);

        $asset = \App\Modules\User\Models\BrandKitAsset::where('brand_kit_id', $brandKit->id)
            ->where('type', $type)->first();
        abort_if(!$asset || $asset->status !== \App\Modules\User\Models\BrandKitAsset::STATUS_READY, 404);

        try {
            switch ($data['target']) {
                case 'kit_logo':
                    $assets->applyLogoToKit($asset, $brandKit);
                    break;

                case 'biolink_favicon':
                case 'biolink_og':
                    $link = Link::where('user_id', workspace_owner_id())
                        ->where('type', 'biolink')
                        ->find((int) ($data['link_id'] ?? 0));
                    abort_if(!$link, 404, 'Link in Bio page not found.');
                    $data['target'] === 'biolink_favicon'
                        ? $assets->applyFaviconToLink($asset, $link)
                        : $assets->applyOgToLink($asset, $link);
                    break;

                case 'company_letterhead':
                    $company = \App\Modules\User\Models\BillingCompany::where('user_id', $user->id)
                        ->find((int) ($data['company_id'] ?? 0));
                    abort_if(!$company, 404, 'Billing company not found.');
                    $assets->applyLetterheadToCompany($asset, $company);
                    break;
            }
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }
}
