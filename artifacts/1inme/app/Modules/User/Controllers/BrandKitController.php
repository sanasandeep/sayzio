<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\QrCode;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\InsufficientCoinsForAiException;
use App\Services\Brand\AiBrandKitService;
use App\Services\Brand\BrandConsistencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        ]);
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

        try {
            $result = $this->kits->generate($user, $data['prompt'], $data['website_url'], $data['logo_url']);
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
            'credits_spent' => $result['credits_spent'],
            'balance'       => $this->credits->getBalance($user),
            'kit'           => [
                'id'     => $result['kit']->id,
                'name'   => $result['kit']->name,
                'config' => $result['kit']->config,
            ],
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
     * @return array{prompt:string,website_url:?string,logo_url:?string}
     */
    private function validatePayload(Request $request): array
    {
        $data = $request->validate([
            'prompt'      => ['nullable', 'string', 'max:2000'],
            'website_url' => ['nullable', 'string', 'max:2048'],
            'logo_url'    => ['nullable', 'string', 'max:2048'],
        ]);

        return [
            'prompt'      => (string) ($data['prompt'] ?? ''),
            'website_url' => $data['website_url'] ?? null,
            'logo_url'    => $data['logo_url'] ?? null,
        ];
    }

    private function authorizeKit(Request $request, BrandKit $brandKit): void
    {
        abort_if($brandKit->user_id !== $request->user()->id, 403);
    }
}
