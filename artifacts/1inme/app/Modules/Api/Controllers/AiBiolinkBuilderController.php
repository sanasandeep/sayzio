<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Api\Resources\LinkResource;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\Link;
use App\Services\AI\AiActionCooldown;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\InsufficientCoinsForAiException;
use App\Services\Biolink\AiBiolinkBuilderService;
use App\Services\Integrations\GoogleCseUsage;
use App\Services\Integrations\GoogleImageSearchService;
use App\Services\OgMetadataService;
use App\Modules\User\Models\UserFile;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Mobile (Sanctum) parity for the web "Build my Link in Bio with AI" flow
 * (App\Modules\User\Controllers\AiBiolinkBuilderController).
 *
 *   GET  links/{link}/ai-builder            → intake context (engine on?, balance, limits, On-Brand)
 *   POST links/{link}/ai-builder/estimate   → upfront credit cost
 *   POST links/{link}/ai-builder/generate   → run the build, replace the page's blocks
 *
 * Both surfaces delegate to the same {@see AiBiolinkBuilderService}, so the
 * plan/AI-credit gating, the safe block subset, and the auto-refund-on-parse-
 * failure all live in one place and never drift. Like the web flow this
 * operates on an existing biolink and replaces its blocks; the mobile client
 * then opens the standard block editor on the result.
 *
 * On-Brand AI (Task #2664): `use_brand_kit` (default on) injects the owner's
 * default Brand Kit voice/palette directives into the build, gated behind the
 * `brand_consistency` feature — exact parity with the web intake form's
 * "Use my Brand Kit voice" opt-out.
 */
class AiBiolinkBuilderController extends Controller
{
    use ApiResponses;

    public function __construct(
        private AiBiolinkBuilderService $builder,
        private AiUsageCharger $credits,
    ) {}

    /**
     * Intake context for the mobile builder screen: whether the AI engine is
     * on, the caller's credit balance, the input caps, the curated block types
     * this user may use, and the On-Brand AI availability + a light Brand Kit
     * summary. Mirrors the web intake() view payload.
     */
    public function intake(Request $request, int $linkId)
    {
        $link = $this->ownedBiolink($request, $linkId);
        if (!$link) {
            return $this->notFound('Link in Bio not found');
        }

        $user = $request->user();
        $aiEnabled = AiEngineSettings::isEnabled();
        $onBrandAllowed = AiPlanAccess::featureAllowed($user, 'brand_consistency');
        $kit = $onBrandAllowed ? BrandKit::defaultFor($user->id) : null;

        // Baseline worst-case build cost (empty prompt, no attachments) so
        // the mobile screen can render the shared "Uses up to N coins ·
        // Balance: X" affordability hint before the creator even types —
        // the input-specific /estimate endpoint stays the accurate quote.
        // (estimateCredits rejects an empty description, so quote a short
        // representative prompt; token math is local — no model call.)
        $baselineCost = 0;
        if ($aiEnabled) {
            try {
                $baselineCost = (int) $this->builder->estimateCredits(
                    $user,
                    'A simple page with a short bio and a few of my links.',
                    [],
                    [],
                    [],
                );
            } catch (\Throwable $e) {
                $baselineCost = 0;
            }
        }

        return $this->ok([
            'ai_enabled'      => $aiEnabled,
            'balance'         => $aiEnabled ? $this->credits->getBalance($user) : 0,
            'estimated_cost'  => $baselineCost,
            'allowed_types'   => $this->builder->allowedTypesFor($user),
            'max_links'       => 25,
            'max_images'      => 25,
            'max_files'       => 15,
            'on_brand_allowed' => $onBrandAllowed,
            'image_search_enabled' => app(GoogleImageSearchService::class)->enabled(),
            'brand_kit'       => $kit ? [
                'id'   => (int) $kit->id,
                'name' => (string) $kit->name,
            ] : null,
        ]);
    }

    /**
     * Free image-preview step (Task #5722) — mobile parity with the web
     * source-preview endpoint. Runs the extraction pass on the supplied
     * links so the creator can review/deselect candidate images before the
     * paid build, and reports the generation fallback slots + per-image cost.
     */
    public function sourcePreview(Request $request, int $linkId)
    {
        $link = $this->ownedBiolink($request, $linkId);
        if (!$link) {
            return $this->notFound('Link in Bio not found');
        }

        if (!AiEngineSettings::isEnabled()) {
            return $this->fail('AI generation is currently unavailable.', 503, 'ai_unavailable');
        }

        $data = $request->validate([
            'links'   => ['nullable', 'array', 'max:25'],
            'links.*' => ['string', 'max:2048'],
        ]);

        $preview = app(\App\Services\Biolink\BuilderImageSourcer::class)
            ->preview($request->user(), array_values($data['links'] ?? []));

        return $this->ok($preview);
    }

    public function estimate(Request $request, int $linkId)
    {
        $link = $this->ownedBiolink($request, $linkId);
        if (!$link) {
            return $this->notFound('Link in Bio not found');
        }

        if (!AiEngineSettings::isEnabled()) {
            return $this->fail('AI generation is currently unavailable.', 503, 'ai_unavailable');
        }

        $data = $this->validatePayload($request);

        try {
            $cost = $this->builder->estimateCredits(
                $request->user(),
                $data['description'],
                $data['links'],
                $data['images'],
                $data['files'],
                '',
                $this->brandDirectives($request, $data['use_brand_kit']),
                $data['image_choices'],
            );
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422, 'invalid_request');
        }

        return $this->ok([
            'estimated_credits' => $cost,
            'balance'           => $this->credits->getBalance($request->user()),
        ]);
    }

    public function generate(Request $request, int $linkId)
    {
        $link = $this->ownedBiolink($request, $linkId);
        if (!$link) {
            return $this->notFound('Link in Bio not found');
        }

        if (!AiEngineSettings::isEnabled()) {
            return $this->fail('AI generation is currently unavailable.', 503, 'ai_unavailable');
        }

        $data = $this->validatePayload($request);

        // Double-charge guard (shared key with the web surface): identical
        // re-runs inside the cooldown are served from cache without charging;
        // a concurrent identical request 429s instead of double-charging.
        $cooldownKey = AiActionCooldown::key('biolink_builder', $request->user()->id, ['link' => $link->id] + $data);
        if ($hit = AiActionCooldown::fresh($cooldownKey)) {
            return $this->ok([
                'blocks'        => $hit['result']['blocks'],
                'credits_spent' => 0,
                'balance'       => $this->credits->getBalance($request->user()),
                'link'          => LinkResource::toArray($link->fresh()),
                'cached'        => true,
                'generated_at'  => $hit['generated_at'],
            ]);
        }
        if (!AiActionCooldown::begin($cooldownKey)) {
            return $this->fail('This build is already running — give it a moment.', 429, 'in_progress');
        }

        try {
            $result = $this->builder->generate(
                $request->user(),
                $link,
                $data['description'],
                $data['links'],
                $data['images'],
                $data['files'],
                '',
                true,
                $this->brandDirectives($request, $data['use_brand_kit']),
                $data['image_choices'],
            );
        } catch (InsufficientCoinsForAiException $e) {
            return $this->fail('Not enough coins to build this page.', 402, 'insufficient_credits', [
                'required' => $e->required ?? null,
                'balance'  => $e->balance ?? null,
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422, 'generation_failed');
        } finally {
            AiActionCooldown::end($cooldownKey);
        }

        AiActionCooldown::remember($cooldownKey, ['blocks' => $result['blocks']]);

        return $this->ok([
            'blocks'        => $result['blocks'],
            'credits_spent' => $result['credits_spent'],
            'balance'       => $this->credits->getBalance($request->user()),
            'link'          => LinkResource::toArray($link->fresh()),
        ]);
    }

    /**
     * Google image search (mobile parity with the web builder's picker).
     * Suggestions only — the creator explicitly picks, nothing is ever
     * auto-placed. Free of AI credits; 404s in preview mode so the mobile
     * client hides the feature when no keys are configured.
     */
    public function imageSearch(Request $request, int $linkId, GoogleImageSearchService $search)
    {
        $link = $this->ownedBiolink($request, $linkId);
        if (!$link) {
            return $this->notFound('Link in Bio not found');
        }

        if (!$search->enabled()) {
            return $this->fail('Image search is not available.', 404, 'image_search_unavailable');
        }

        if (GoogleCseUsage::capReached($request->user()?->id)) {
            return $this->fail("You've reached today's image search limit — try again tomorrow.", 429, 'image_search_daily_cap');
        }

        $data = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:200'],
        ]);

        return $this->ok([
            'results'    => $search->search($data['query'], 8, $request->user()?->id),
            'disclaimer' => 'Make sure you have the rights to use any image you pick — search results may be copyrighted.',
        ]);
    }

    /**
     * Import chosen candidate images into the caller's vault (SSRF-safe
     * download via OgMetadataService, context `ai_builder`) and return the
     * relative vault URLs the mobile intake appends to images[].
     */
    public function importImages(Request $request, int $linkId, OgMetadataService $og)
    {
        $link = $this->ownedBiolink($request, $linkId);
        if (!$link) {
            return $this->notFound('Link in Bio not found');
        }

        $data = $request->validate([
            'urls'   => ['required', 'array', 'min:1', 'max:6'],
            'urls.*' => ['string', 'url', 'max:2048', 'starts_with:http://,https://'],
        ]);

        $stored = [];
        $seenHashes = [];
        foreach (array_values(array_unique($data['urls'])) as $url) {
            $img = $og->downloadImage($url);
            if ($img === null) {
                continue;
            }
            $hash = md5($img['bytes']);
            if (isset($seenHashes[$hash])) {
                continue;
            }
            $seenHashes[$hash] = true;

            try {
                $file = UserFile::createFromBytes(
                    $img['bytes'],
                    'ai-builder-' . substr($hash, 0, 8) . '.' . $this->extensionFor($img['mime']),
                    $img['mime'],
                    $request->user(),
                    ['skip_scan' => true, 'context' => 'ai_builder'],
                );
            } catch (\RuntimeException $e) {
                return $this->fail($e->getMessage(), 422, 'storage_quota');
            }

            $stored[] = ['url' => $file->url_path, 'source_url' => $url];
        }

        if ($stored === []) {
            return $this->fail(
                'None of the selected images could be downloaded. They may be blocked or too large.',
                422,
                'import_failed',
            );
        }

        return $this->ok(['images' => $stored]);
    }

    private function extensionFor(string $mime): string
    {
        return match ($mime) {
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            'image/bmp'  => 'bmp',
            'image/x-icon', 'image/vnd.microsoft.icon' => 'ico',
            default      => 'jpg',
        };
    }

    /**
     * @return array{description:string,links:list<string>,images:list<string>,files:list<string>,use_brand_kit:bool,image_choices:array{kept?:list<string>,skip_slots?:list<string>}}
     */
    private function validatePayload(Request $request): array
    {
        $data = $request->validate([
            'description'   => ['required', 'string', 'min:10', 'max:4000'],
            'links'         => ['nullable', 'array', 'max:25'],
            'links.*'       => ['string', 'max:2048'],
            'images'        => ['nullable', 'array', 'max:25'],
            'images.*'      => ['string', 'max:2048'],
            'files'         => ['nullable', 'array', 'max:15'],
            'files.*'       => ['string', 'max:2048'],
            'use_brand_kit' => ['nullable', 'boolean'],
            // Image preview confirmation (Task #5722): the exact extracted
            // images the creator kept, and any generation slots they skipped.
            // Sending `kept_images` (even empty) means "I reviewed the
            // candidates — don't re-extract, use my list".
            'kept_images'            => ['nullable', 'array', 'max:25'],
            'kept_images.*'          => ['string', 'max:2048'],
            'skip_generated_slots'   => ['nullable', 'array'],
            'skip_generated_slots.*' => ['string', 'in:avatar,cover'],
        ]);

        $imageChoices = [];
        if ($request->has('kept_images')) {
            $imageChoices['kept'] = array_values($data['kept_images'] ?? []);
        }
        if (!empty($data['skip_generated_slots'])) {
            $imageChoices['skip_slots'] = array_values($data['skip_generated_slots']);
        }

        return [
            'description'   => $data['description'],
            'links'         => array_values($data['links'] ?? []),
            'images'        => array_values($data['images'] ?? []),
            'files'         => array_values($data['files'] ?? []),
            // On-brand by default; the mobile form sends an explicit opt-out.
            'use_brand_kit' => $request->has('use_brand_kit') ? $request->boolean('use_brand_kit') : true,
            'image_choices' => $imageChoices,
        ];
    }

    /**
     * Resolve the creator's saved Brand Kit voice/palette directives to feed
     * the builder, honoring the per-request opt-out and the plan gate. Returns
     * '' when off, ungated, or the creator has no kit — so generation is
     * unaffected for everyone who hasn't opted into On-Brand AI. Mirrors the
     * web controller, but resolves the kit by the caller id (the Sanctum API
     * path never binds an active workspace) like the rest of the API module.
     */
    private function brandDirectives(Request $request, bool $useBrandKit): string
    {
        if (!$useBrandKit) {
            return '';
        }
        if (!AiPlanAccess::featureAllowed($request->user(), 'brand_consistency')) {
            return '';
        }
        $kit = BrandKit::defaultFor($request->user()->id);

        return $kit ? $kit->promptDirectives(true) : '';
    }

    /**
     * The caller's own biolink (strictly `type = biolink`, matching the web
     * authorizeLink gate). Returns null when missing/not-owned/not-a-biolink so
     * the caller surfaces a 404.
     */
    private function ownedBiolink(Request $request, int $id): ?Link
    {
        return Link::where('user_id', $request->user()->id)
            ->where('type', 'biolink')
            ->find($id);
    }
}
