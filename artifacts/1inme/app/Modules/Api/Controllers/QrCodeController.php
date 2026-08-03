<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\QrCode;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Project;
use App\Modules\User\Support\QrCodeCatalog;
use App\Modules\User\Support\QrCodeDesignSanitizer;
use App\Modules\User\Support\QrCodeTypeRegistry;
use App\Services\AI\AiActionCooldown;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\InsufficientCoinsForAiException;
use App\Services\AI\QrArtAllowanceExceededException;
use App\Services\AI\QrArtGenerationException;
use App\Services\AI\QrArtService;
use App\Services\AI\QrArtUnavailableException;
use App\Support\PlanLimit;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QrCodeController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $items = QrCode::where('user_id', $request->user()->id)->orderByDesc('id')->get();
        return $this->ok(['items' => $items->map(fn ($q) => $this->transform($q))->all()]);
    }

    public function show(Request $request, int $id)
    {
        $q = QrCode::where('user_id', $request->user()->id)->find($id);
        if (!$q) return $this->notFound('QR code not found');
        return $this->ok(['qr_code' => $this->transform($q)]);
    }

    /** Catalog of shapes / eyes / frames / fonts / presets shared with the web builder. */
    public function catalog(Request $request)
    {
        return $this->ok([
            'dots'      => QrCodeCatalog::dotShapes(),
            'outer_eyes' => QrCodeCatalog::outerEyeShapes(),
            'inner_eyes' => QrCodeCatalog::innerEyeShapes(),
            'frames'    => QrCodeCatalog::frames(),
            'fonts'     => QrCodeCatalog::fonts(),
            'types'     => QrCodeTypeRegistry::types(),
            'presets'   => QrCodeCatalog::presets(),
            'default_design' => QrCodeDesignSanitizer::defaultDesign(),
        ]);
    }

    public function store(Request $request)
    {
        if ($limited = $this->savedQrLimitResponse($request->user(), 1)) {
            return $limited;
        }
        try {
            $attrs = $this->validatePayload($request, $request->all());
        } catch (ValidationException $e) {
            return $this->fail('Validation failed', 422, 'validation_error', $e->errors());
        }
        $q = new QrCode(array_merge($attrs, ['user_id' => $request->user()->id]));
        $q->workspace_id = $this->activeWorkspaceId($request->user());
        $q->save();
        return $this->created(['qr_code' => $this->transform($q)]);
    }

    public function update(Request $request, int $id)
    {
        $q = QrCode::where('user_id', $request->user()->id)->find($id);
        if (!$q) return $this->notFound('QR code not found');
        try {
            $attrs = $this->validatePayload($request, $request->all(), partial: true, existing: $q);
        } catch (ValidationException $e) {
            return $this->fail('Validation failed', 422, 'validation_error', $e->errors());
        }
        $q->fill($attrs)->save();
        return $this->ok(['qr_code' => $this->transform($q->fresh())]);
    }

    public function destroy(Request $request, int $id)
    {
        $q = QrCode::where('user_id', $request->user()->id)->find($id);
        if (!$q) return $this->notFound('QR code not found');
        $q->delete();
        return $this->noContent();
    }

    /**
     * Bulk-create QR codes in one call. Each item is validated and sanitized
     * independently; the whole batch is rejected if any item is invalid.
     */
    public function bulk(Request $request)
    {
        $items = $request->input('items');
        if (!is_array($items) || !count($items)) {
            return $this->fail('Provide a non-empty "items" array', 422, 'validation_error');
        }
        if (count($items) > 500) {
            return $this->fail('Bulk limit is 500 items per request', 422, 'too_many_items');
        }
        if ($limited = $this->savedQrLimitResponse($request->user(), count($items))) {
            return $limited;
        }

        $prepared = [];
        $errors = [];
        foreach ($items as $i => $item) {
            if (!is_array($item)) { $errors[$i] = ['item' => ['Must be an object']]; continue; }
            try {
                $prepared[$i] = $this->validatePayload($request, $item);
            } catch (ValidationException $e) {
                $errors[$i] = $e->errors();
            }
        }
        if ($errors) {
            return $this->fail('One or more items are invalid', 422, 'validation_error', $errors);
        }

        $wsId = $this->activeWorkspaceId($request->user());
        $created = [];
        foreach ($prepared as $attrs) {
            $q = new QrCode(array_merge($attrs, ['user_id' => $request->user()->id]));
            $q->workspace_id = $wsId;
            $q->save();
            $created[] = $this->transform($q);
        }
        return $this->created(['items' => $created, 'count' => count($created)]);
    }

    /**
     * AI Artistic QR availability + cost, mirroring the web builder's gating
     * variables so the mobile builder can render preview / plan-locked / live
     * states and show the coin balance + per-generation cost up front.
     *
     * Unlike the cached design catalog, this is intentionally NOT cached: the
     * coin balance must stay fresh between generations. Style presets come from
     * the same QrCodeCatalog source the web builder uses.
     */
    public function artAvailability(Request $request)
    {
        $user = $request->user();
        $art  = app(QrArtService::class);

        $allowed = AiPlanAccess::featureAllowed($user, 'qr_art');
        $plan    = $allowed ? null : $user->planThatUnlocks('qr_art');

        return $this->ok([
            'enabled' => $art->enabled(),
            'allowed' => $allowed,
            'cost'    => $art->coinCost($user),
            'balance' => app(AiUsageCharger::class)->getBalance($user),
            'recommended_plan' => $plan ? ['slug' => $plan->slug, 'name' => $plan->name] : null,
            'presets' => QrCodeCatalog::aiArtStylePresets(),
            // Monthly allowance (max_qr_art_monthly): -1 = unlimited, values
            // normalized so the bypass sentinel never leaks into a payload.
            'monthly_allowance' => $art->monthlyAllowance($user),
            'monthly_used'      => $art->monthlyUsed($user),
            'monthly_remaining' => $art->monthlyRemaining($user),
        ]);
    }

    /**
     * Generate an AI Artistic QR for the mobile builder, mirroring the web
     * generateArt controller: gated on feature availability + plan access +
     * coin balance, with a coin charge and auto-refund on failure handled by
     * QrArtService. Returns the stored artwork URL the client drops into
     * design.ai_art.
     *
     * Sanctum has no workspace binding, so the authenticated user IS the owner
     * and is charged directly (matching the brand-kit / other AI API surfaces).
     *
     * Accepts either a pre-resolved `data` string (matching the web flow) or a
     * `link_id` / `type` + `payload` combination which it resolves server-side
     * via the same QrCodeTypeRegistry the saved QR will encode with, so the
     * artwork's woven control image matches the QR the user persists. The
     * resolved string is returned as `encoded` so the client can persist it in
     * design.ai_art.data.
     */
    public function generateArt(Request $request, QrArtService $art)
    {
        $user = $request->user();

        if (!$art->enabled()) {
            return $this->fail(
                "AI Artistic QR isn't available yet — an administrator needs to add a Replicate API key.",
                422,
                'disabled'
            );
        }

        if (!AiPlanAccess::featureAllowed($user, 'qr_art')) {
            return $this->planGate(
                "Your plan doesn't include AI Artistic QR.",
                'qr_art',
                $user,
                403,
                'plan_upgrade_required'
            );
        }

        $typeKeys = array_keys(QrCodeTypeRegistry::types());
        try {
            $validated = validator($request->all(), [
                'data'            => ['nullable', 'string', 'max:2048'],
                'link_id'         => ['nullable', Rule::exists('links', 'id')->where('user_id', $user->id)],
                'type'            => ['nullable', Rule::in($typeKeys)],
                'payload'         => ['nullable', 'array'],
                'prompt'          => ['required', 'string', 'max:600'],
                'style'           => ['nullable', 'string', 'max:60'],
                'negative_prompt' => ['nullable', 'string', 'max:600'],
            ])->validate();
        } catch (ValidationException $e) {
            return $this->fail('Validation failed', 422, 'validation_error', $e->errors());
        }

        // Resolve the scanner-visible string the artwork is woven around. Prefer
        // an explicit `data` (web parity); otherwise derive it from the link or
        // the type+payload exactly like the saved QR will encode.
        $data = trim((string) ($validated['data'] ?? ''));
        if ($data === '') {
            if (!empty($validated['link_id'])) {
                $link = Link::where('user_id', $user->id)->find($validated['link_id']);
                $data = $link ? (string) $link->getShortUrl() : '';
            } else {
                try {
                    $data = QrCodeTypeRegistry::buildPayloadString(
                        $validated['type'] ?? 'url',
                        (array) ($validated['payload'] ?? [])
                    );
                } catch (\Throwable $e) {
                    $data = '';
                }
            }
        }
        if ($data === '') {
            return $this->fail('Add your QR content first.', 422, 'missing_data');
        }

        // Double-charge guard (shared key with the web surface): an identical
        // regeneration inside the cooldown re-serves the stored artwork
        // without a new coin charge; a concurrent identical request 429s.
        $cooldownKey = AiActionCooldown::key('qr_art', $user->id, [
            'data'            => $data,
            'prompt'          => $validated['prompt'],
            'negative_prompt' => $validated['negative_prompt'] ?? null,
            'strength'        => null,
        ]);
        if ($hit = AiActionCooldown::fresh($cooldownKey)) {
            return $this->ok([
                'image_url'    => $hit['result']['image_url'] ?? null,
                'file_id'      => $hit['result']['file_id'] ?? null,
                'cost'         => 0,
                'balance'      => app(AiUsageCharger::class)->getBalance($user),
                'style'        => $validated['style'] ?? null,
                'encoded'      => $data,
                'cached'       => true,
                'generated_at' => $hit['generated_at'],
            ]);
        }
        if (!AiActionCooldown::begin($cooldownKey)) {
            return $this->fail('This artwork is already generating — give it a moment.', 429, 'in_progress');
        }

        try {
            $result = $art->generate($user, $data, $validated['prompt'], [
                'negative_prompt' => $validated['negative_prompt'] ?? null,
            ]);
        } catch (QrArtAllowanceExceededException $e) {
            return $this->fail(
                $e->getMessage(),
                403,
                'allowance_exhausted',
                ['allowance' => $e->allowance, 'used' => $e->used]
            );
        } catch (InsufficientCoinsForAiException $e) {
            return $this->fail(
                "Not enough coins — this needs {$e->required}, your balance is {$e->balance}.",
                402,
                'insufficient_credits',
                ['required' => $e->required, 'balance' => $e->balance]
            );
        } catch (QrArtUnavailableException $e) {
            return $this->fail($e->getMessage(), 422, 'disabled');
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422, 'invalid');
        } catch (QrArtGenerationException $e) {
            return $this->fail($e->getMessage(), 422, 'failed');
        } finally {
            AiActionCooldown::end($cooldownKey);
        }

        AiActionCooldown::remember($cooldownKey, [
            'image_url' => $result['image_url'] ?? null,
            'file_id'   => $result['file_id'] ?? null,
        ]);

        return $this->ok([
            'image_url' => $result['image_url'] ?? null,
            'file_id'   => $result['file_id'] ?? null,
            'cost'      => $result['cost'] ?? null,
            'balance'   => $result['balance'] ?? null,
            'style'     => $validated['style'] ?? null,
            'encoded'   => $data,
        ]);
    }

    /**
     * 403 "saved QR limit reached" response when creating $adding more QR
     * codes would exceed the user's `max_qr_codes` plan cap, or null when
     * allowed. -1 = unlimited; bypass-permission holders always pass and the
     * cap in the payload is normalized so the sentinel never leaks.
     */
    protected function savedQrLimitResponse(\App\Modules\User\Models\User $user, int $adding)
    {
        // Account-wide count: the plan cap spans every workspace.
        $current = QrCode::withoutGlobalScope('workspace')
            ->where('user_id', $user->id)->count();
        // planUnderLimit checks "one more"; for bulk verify the whole batch fits.
        if ($user->planUnderLimit('max_qr_codes', $current + $adding - 1, -1)) {
            return null;
        }
        $cap = PlanLimit::normalize((int) $user->getPlanFeature('max_qr_codes', -1));
        $msg = "You've reached your plan's saved QR code limit ({$cap}).";
        if ($plan = $user->planThatUnlocks('max_qr_codes', $current)) {
            $msg .= " Upgrade to the {$plan->name} plan to save more.";
        }
        return $this->fail($msg, 403, 'plan_limit_reached', [
            'limit' => $cap,
            'used'  => $current,
        ]);
    }

    /**
     * Validate + sanitize a single QR payload (used by store, update, bulk).
     * Mirrors the web builder pipeline exactly via QrCodeDesignSanitizer and
     * QrCodeTypeRegistry so the API and UI never diverge.
     */
    protected function validatePayload(Request $request, array $input, bool $partial = false, ?QrCode $existing = null): array
    {
        $userId = $request->user()->id;
        $typeKeys = array_keys(QrCodeTypeRegistry::types());

        $rules = [
            'name'       => [$partial ? 'sometimes' : 'required', 'string', 'max:160'],
            'type'       => [$partial ? 'sometimes' : 'required', Rule::in($typeKeys)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('user_id', $userId)],
            'link_id'    => ['nullable', Rule::exists('links', 'id')->where('user_id', $userId)],
            'payload'    => ['nullable', 'array'],
            'design'     => ['nullable', 'array'],
        ];
        $base = validator($input, $rules)->validate();

        $type = $base['type'] ?? $existing?->type ?? 'url';
        $hasLink = array_key_exists('link_id', $base) ? !empty($base['link_id']) : (bool) $existing?->link_id;

        // Type-specific payload rules — skipped when the QR is link-backed.
        if (!$hasLink && (array_key_exists('payload', $base) || !$partial)) {
            $payloadRules = collect(QrCodeTypeRegistry::rulesFor($type))
                ->mapWithKeys(fn ($rule, $key) => ["payload.$key" => $rule])
                ->toArray();
            if ($payloadRules) {
                validator($input, $payloadRules)->validate();
            }
        }

        $attrs = [];
        if (array_key_exists('name', $base))       $attrs['name'] = $base['name'];
        if (array_key_exists('type', $base))       $attrs['type'] = $base['type'];
        if (array_key_exists('project_id', $base)) $attrs['project_id'] = $base['project_id'] ?: null;
        if (array_key_exists('link_id', $base))    $attrs['link_id'] = $base['link_id'] ?: null;
        if (array_key_exists('payload', $base))    $attrs['payload'] = (array) ($base['payload'] ?? []);
        if (array_key_exists('design', $input))    $attrs['design'] = QrCodeDesignSanitizer::sanitize((array) ($input['design'] ?? []));

        // On create, ensure design + payload always have a sane baseline.
        if (!$partial) {
            $attrs['payload'] = $attrs['payload'] ?? [];
            $attrs['design']  = $attrs['design'] ?? QrCodeDesignSanitizer::defaultDesign();
        }

        return $attrs;
    }

    protected function transform(QrCode $q): array
    {
        return [
            'id'          => $q->id,
            'name'        => $q->name,
            'type'        => $q->type,
            'link_id'     => $q->link_id,
            'project_id'  => $q->project_id,
            'payload'     => $q->payload,
            'design'      => $q->design,
            'encoded'     => $this->encodedFor($q),
            'preview_url' => $q->preview_url,
            'created_at'  => optional($q->created_at)->toIso8601String(),
        ];
    }

    /** Resolve the string a scanner will see (short link or built payload). */
    protected function encodedFor(QrCode $q): string
    {
        if ($q->link_id && $q->relationLoaded('link') === false) {
            $q->loadMissing('link');
        }
        if ($q->link) {
            return (string) $q->link->getShortUrl();
        }
        try {
            return QrCodeTypeRegistry::buildPayloadString($q->type, (array) $q->payload);
        } catch (\Throwable $e) {
            return '';
        }
    }
}
