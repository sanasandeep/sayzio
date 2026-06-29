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
            $cost = $this->kits->estimateCredits($user, $data['prompt'], $data['website_url'], $data['logo_url']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'estimated_credits' => $cost,
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
            $result = $this->kits->generate($user, $data['prompt'], $data['website_url'], $data['logo_url'], $kbContext);
        } catch (InsufficientCoinsForAiException $e) {
            return response()->json([
                'message'  => 'Not enough AI credits to generate this brand kit.',
                'required' => $e->required ?? null,
                'balance'  => $e->balance ?? null,
            ], 402);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'credits_spent' => (int) $result['credits_spent'] + $kbCreditsSpent,
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
     * @return array{prompt:string,website_url:?string,logo_url:?string,mind_ids:int[],include_platform:bool}
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
        ]);

        return [
            'prompt'           => (string) ($data['prompt'] ?? ''),
            'website_url'      => $data['website_url'] ?? null,
            'logo_url'         => $data['logo_url'] ?? null,
            'mind_ids'         => array_map('intval', $data['mind_ids'] ?? []),
            'include_platform' => (bool) ($data['include_platform'] ?? false),
        ];
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
}
