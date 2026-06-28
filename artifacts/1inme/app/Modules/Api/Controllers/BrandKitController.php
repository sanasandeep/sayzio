<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\QrCode;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\InsufficientCoinsForAiException;
use App\Services\Brand\AiBrandKitService;
use App\Services\Brand\BrandConsistencyService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

/**
 * Mobile (Sanctum) parity for the web "AI Brand Kit" feature
 * (see App\Modules\User\Controllers\BrandKitController for the web flow).
 *
 * Routes (all under /api/v1, auth:sanctum):
 *   GET    /brand-kits                              list + apply targets + gating
 *   POST   /brand-kits/estimate                     upfront credit cost
 *   POST   /brand-kits/generate                     run generation, save a kit
 *   DELETE /brand-kits/{brandKit}                   delete a kit
 *   POST   /brand-kits/{brandKit}/apply/biolink/{link} apply to a biolink
 *   POST   /brand-kits/{brandKit}/apply/qr/{qrCode}    apply to a QR code
 *
 * The heavy lifting — the OpenAI call, credit metering and the auto-refund on
 * a failed generation — is delegated to the shared {@see AiBrandKitService}
 * exactly like the web controller, so the two surfaces never drift. Generation
 * is gated by the per-plan `max_brand_kits` quantity cap; plan-less users
 * (cap 0) get a plan-gated rejection the mobile upgrade prompt understands.
 *
 * Ownership: brand kits are owned by `user_id`, and apply targets (biolinks /
 * QR codes) are resolved against the authenticated user's own rows. The Sanctum
 * path never binds `current_workspace`, so the BelongsToWorkspace read scope is
 * skipped and `where('user_id', …)` returns the caller's rows across workspaces,
 * matching the rest of the API surface (QrCodeController / CardScanController).
 */
class BrandKitController extends Controller
{
    use ApiResponses;

    public function __construct(
        protected AiBrandKitService $kits,
        protected AiUsageCharger $credits,
    ) {}

    /** List the user's kits plus the targets a kit can be applied to. */
    public function index(Request $request)
    {
        $user  = $request->user();
        $list  = BrandKit::where('user_id', $user->id)->latest()->get();
        $count = $list->count();

        $aiEnabled = AiEngineSettings::isEnabled();
        $cap       = AiPlanAccess::quantityCap($user, 'brand_kits');
        $canCreate = AiPlanAccess::underQuantityCap($user, 'brand_kits', $count);
        $upgrade   = $canCreate ? null : AiPlanAccess::quantityUpgradePlan($user, 'brand_kits', $count);

        $biolinks = Link::where('user_id', $user->id)
            ->where('type', 'biolink')
            ->orderByDesc('id')
            ->get(['id', 'title', 'alias'])
            ->map(fn ($l) => [
                'id'    => $l->id,
                'title' => $l->title,
                'alias' => $l->alias,
            ])
            ->all();

        $qrCodes = QrCode::where('user_id', $user->id)
            ->orderByDesc('id')
            ->get(['id', 'name'])
            ->map(fn ($q) => [
                'id'   => $q->id,
                'name' => $q->name,
            ])
            ->all();

        return $this->ok([
            'kits'         => $list->map(fn (BrandKit $k) => $this->presentKit($k))->all(),
            'count'        => $count,
            'cap'          => $cap,
            'can_create'   => $canCreate,
            'upgrade_plan' => $upgrade ? ['slug' => $upgrade->slug, 'name' => $upgrade->name] : null,
            'ai_enabled'   => $aiEnabled,
            'balance'      => $aiEnabled ? $this->credits->getBalance($user) : 0,
            'biolinks'     => $biolinks,
            'qr_codes'     => $qrCodes,
            'block_themes' => $this->kits->allowedBlockThemes(),
        ]);
    }

    /**
     * Brand Consistency Score (Task #2664 web parity).
     *
     * Audits the caller's biolinks against their default Brand Kit and returns
     * the 0-100 on-brand score plus per-link findings with plain-English
     * reasons. Plan-gated behind the legacy-safe `brand_consistency` feature.
     * The mobile client turns each finding into a one-tap "Apply fix" by
     * calling the existing apply-to-biolink endpoint with the returned
     * `kit_id` + `link_id`, so there is no new apply path. Mirrors the audit
     * the web BrandKitController@index embeds in the page.
     */
    public function consistency(Request $request)
    {
        $user = $request->user();

        if (!AiPlanAccess::featureAllowed($user, 'brand_consistency')) {
            return $this->ok(['available' => false, 'has_kit' => false, 'audit' => null]);
        }

        $kit = BrandKit::defaultFor($user->id);
        if (!$kit) {
            return $this->ok(['available' => true, 'has_kit' => false, 'audit' => null]);
        }

        $biolinks = Link::where('user_id', $user->id)
            ->where('type', 'biolink')
            ->orderByDesc('id')
            ->get(['id', 'title', 'alias', 'type', 'settings']);

        $audit = app(BrandConsistencyService::class)->audit($kit, $biolinks);

        return $this->ok([
            'available' => true,
            'has_kit'   => true,
            'audit'     => [
                'score'          => $audit['score'],
                'grade'          => $audit['grade'],
                'label'          => $audit['label'],
                'kit_id'         => $audit['kit_id'],
                'kit_name'       => $audit['kit_name'],
                'links_total'    => $audit['links_total'],
                'links_on_brand' => $audit['links_on_brand'],
                // Off-brand pages only (worst-first), each with the kit-vs-page
                // mismatches a one-tap "Apply fix" resolves. apply_url (a web
                // route) is dropped — mobile applies via kit_id + link_id.
                'findings'       => array_map(fn ($f) => [
                    'link_id'    => $f['link_id'],
                    'title'      => $f['title'],
                    'alias'      => $f['alias'],
                    'score'      => $f['score'],
                    'severity'   => $f['severity'],
                    'headline'   => $f['headline'],
                    'reason'     => $f['reason'],
                    'mismatches' => $f['mismatches'],
                ], $audit['findings']),
            ],
        ]);
    }

    /** Upfront, worst-case credit cost before the user taps Generate. */
    public function estimate(Request $request)
    {
        $user = $request->user();

        if (!AiEngineSettings::isEnabled()) {
            return $this->fail('AI features are currently unavailable.', 503, 'ai_unavailable');
        }

        $data = $this->validatePayload($request);

        try {
            $cost = $this->kits->estimateCredits($user, $data['prompt'], $data['website_url'], $data['logo_url']);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422, 'invalid_input');
        }

        return $this->ok([
            'estimated_credits' => $cost,
            'balance'           => $this->credits->getBalance($user),
        ]);
    }

    /** Run the generation and persist a new kit. Mirrors the web generate(). */
    public function generate(Request $request)
    {
        $user = $request->user();

        if (!AiEngineSettings::isEnabled()) {
            return $this->fail('AI features are currently unavailable.', 503, 'ai_unavailable');
        }

        $count = BrandKit::where('user_id', $user->id)->count();
        if (!AiPlanAccess::underQuantityCap($user, 'brand_kits', $count)) {
            return $this->planGate(
                AiPlanAccess::quantityLimitMessage($user, 'brand_kits', 'brand kit', $count),
                'brand_kits',
                $user,
                403,
                'plan_limit',
                $count,
            );
        }

        $data = $this->validatePayload($request);

        try {
            $result = $this->kits->generate($user, $data['prompt'], $data['website_url'], $data['logo_url']);
        } catch (InsufficientCoinsForAiException $e) {
            return $this->fail(
                'Not enough AI credits to generate this brand kit.',
                402,
                'insufficient_credits',
                ['required' => $e->required ?? null, 'balance' => $e->balance ?? null],
            );
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422, 'generation_failed');
        }

        return $this->created([
            'credits_spent' => $result['credits_spent'],
            'balance'       => $this->credits->getBalance($user),
            'kit'           => $this->presentKit($result['kit']),
        ]);
    }

    /** Delete one of the user's kits. */
    public function destroy(Request $request, int $brandKit)
    {
        $kit = $this->resolveKit($request, $brandKit);
        if (!$kit) {
            return $this->notFound('Brand kit not found.');
        }
        $kit->delete();
        return $this->ok(['ok' => true]);
    }

    /** Apply a kit to one of the user's biolinks. */
    public function applyToBiolink(Request $request, int $brandKit, int $link)
    {
        $kit = $this->resolveKit($request, $brandKit);
        if (!$kit) {
            return $this->notFound('Brand kit not found.');
        }

        $target = Link::where('user_id', $request->user()->id)
            ->where('type', 'biolink')
            ->find($link);
        if (!$target) {
            return $this->notFound('Link in Bio not found.');
        }

        $this->kits->applyToBiolink($kit, $target);

        return $this->ok([
            'ok'   => true,
            'link' => ['id' => $target->id, 'title' => $target->title, 'alias' => $target->alias],
        ]);
    }

    /** Apply a kit's palette to one of the user's QR codes. */
    public function applyToQr(Request $request, int $brandKit, int $qrCode)
    {
        $kit = $this->resolveKit($request, $brandKit);
        if (!$kit) {
            return $this->notFound('Brand kit not found.');
        }

        $target = QrCode::where('user_id', $request->user()->id)->find($qrCode);
        if (!$target) {
            return $this->notFound('QR code not found.');
        }

        $this->kits->applyToQr($kit, $target);

        return $this->ok([
            'ok'      => true,
            'qr_code' => ['id' => $target->id, 'name' => $target->name],
        ]);
    }

    // ── helpers ──────────────────────────────────────────────────────

    /** Resolve a kit owned by the signed-in user (workspace scope is skipped). */
    protected function resolveKit(Request $request, int $id): ?BrandKit
    {
        $kit = BrandKit::find($id);
        if (!$kit || $kit->user_id !== $request->user()->id) {
            return null;
        }
        return $kit;
    }

    protected function presentKit(BrandKit $kit): array
    {
        return [
            'id'         => $kit->id,
            'name'       => $kit->name,
            'slug'       => $kit->slug,
            'is_default' => (bool) $kit->is_default,
            'config'     => is_array($kit->config) ? $kit->config : [],
            'created_at' => optional($kit->created_at)->toIso8601String(),
        ];
    }

    /**
     * @return array{prompt:string,website_url:?string,logo_url:?string}
     */
    private function validatePayload(Request $request): array
    {
        try {
            $data = $request->validate([
                'prompt'      => ['nullable', 'string', 'max:2000'],
                'website_url' => ['nullable', 'string', 'max:2048'],
                'logo_url'    => ['nullable', 'string', 'max:2048'],
            ]);
        } catch (ValidationException $e) {
            // Surface as the unified envelope rather than Laravel's default.
            abort(response()->json(['error' => [
                'message' => 'Validation failed',
                'code'    => 'validation_error',
                'details' => $e->errors(),
            ]], 422));
        }

        return [
            'prompt'      => (string) ($data['prompt'] ?? ''),
            'website_url' => $data['website_url'] ?? null,
            'logo_url'    => $data['logo_url'] ?? null,
        ];
    }
}
