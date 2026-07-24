<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\Link;
use App\Services\AI\AiActionCooldown;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\InsufficientCoinsForAiException;
use App\Services\Biolink\AiBiolinkBuilderService;
use App\Services\Integrations\GoogleCseUsage;
use App\Services\Integrations\GoogleImageSearchService;
use App\Services\OgMetadataService;
use App\Modules\User\Models\UserFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Build my Link in Bio with AI" endpoints.
 *
 *   GET  links/{link}/ai-builder           → intake screen
 *   POST links/{link}/ai-builder/estimate  → upfront credit cost
 *   POST links/{link}/ai-builder/generate  → run the build, then open the editor
 *
 * The AI charge happens inside the generate() call via OpenAiService
 * against the `biolink_builder` feature — no new currency/coin path.
 */
class AiBiolinkBuilderController extends Controller
{
    public function __construct(
        protected AiBiolinkBuilderService $builder,
        protected AiUsageCharger $credits,
    ) {}

    public function intake(Request $request, Link $link)
    {
        $this->authorizeLink($link);

        $onBrandAllowed = AiPlanAccess::featureAllowed($request->user(), 'brand_consistency');

        return view('user.links.ai-builder', [
            'link'           => $link,
            'aiEnabled'      => AiEngineSettings::isEnabled(),
            'balance'        => AiEngineSettings::isEnabled() ? $this->credits->getBalance($request->user()) : 0,
            'allowedTypes'   => $this->builder->allowedTypesFor($request->user()),
            'maxLinks'       => 25,
            'maxImages'      => 25,
            'maxFiles'       => 15,
            'onBrandAllowed' => $onBrandAllowed,
            'brandKit'       => $onBrandAllowed ? BrandKit::defaultFor(workspace_owner_id()) : null,
            'imageSearchEnabled' => app(GoogleImageSearchService::class)->enabled(),
        ]);
    }

    /**
     * Free image-preview step (Task #5722): run the extraction pass on the
     * supplied links now so the creator can see the candidate images and
     * deselect the ones they don't want before the paid build runs. Also
     * reports the generation fallback (slots + per-image coin cost) so the
     * UI can offer per-slot skip toggles.
     */
    public function sourcePreview(Request $request, Link $link): JsonResponse
    {
        $this->authorizeLink($link);

        if (!AiEngineSettings::isEnabled()) {
            return response()->json(['message' => 'AI Engine is disabled.'], 404);
        }

        $data = $request->validate([
            'links'   => ['nullable', 'array', 'max:25'],
            'links.*' => ['string', 'max:2048'],
        ]);

        $preview = app(\App\Services\Biolink\BuilderImageSourcer::class)
            ->preview($request->user(), array_values($data['links'] ?? []));

        return response()->json($preview);
    }

    public function estimate(Request $request, Link $link): JsonResponse
    {
        $this->authorizeLink($link);

        if (!AiEngineSettings::isEnabled()) {
            return response()->json(['message' => 'AI Engine is disabled.'], 404);
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
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'estimated_credits' => $cost,
            'balance'           => $this->credits->getBalance($request->user()),
        ]);
    }

    public function generate(Request $request, Link $link): JsonResponse
    {
        $this->authorizeLink($link);

        if (!AiEngineSettings::isEnabled()) {
            return response()->json(['message' => 'AI Engine is disabled.'], 404);
        }

        $data = $this->validatePayload($request);

        // Double-charge guard: identical re-runs within the cooldown window
        // are served from cache without charging; a concurrent identical
        // request is rejected instead of starting a second paid build.
        $cooldownKey = AiActionCooldown::key('biolink_builder', $request->user()->id, ['link' => $link->id] + $data);
        if ($hit = AiActionCooldown::fresh($cooldownKey)) {
            return response()->json([
                'blocks'        => $hit['result']['blocks'],
                'credits_spent' => 0,
                'balance'       => $this->credits->getBalance($request->user()),
                'redirect'      => route('user.links.blocks.editor', $link),
                'cached'        => true,
                'generated_at'  => $hit['generated_at'],
            ]);
        }
        if (!AiActionCooldown::begin($cooldownKey)) {
            return response()->json([
                'message' => 'This build is already running — give it a moment.',
                'code'    => 'in_progress',
            ], 429);
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
            return response()->json([
                'message'  => 'Not enough coins to build this page.',
                'required' => $e->required ?? null,
                'balance'  => $e->balance ?? null,
            ], 402);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } finally {
            AiActionCooldown::end($cooldownKey);
        }

        AiActionCooldown::remember($cooldownKey, ['blocks' => $result['blocks']]);

        return response()->json([
            'blocks'        => $result['blocks'],
            'credits_spent' => $result['credits_spent'],
            'balance'       => $this->credits->getBalance($request->user()),
            'redirect'      => route('user.links.blocks.editor', $link),
        ]);
    }

    /**
     * Lightweight availability recheck for the image-search picker: the
     * intake page polls this on window focus while the picker is collapsed
     * (admin removed the CSE keys mid-session) so it can reappear without a
     * full page reload once the keys are re-added.
     */
    public function imageSearchAvailability(Request $request, Link $link, GoogleImageSearchService $search): JsonResponse
    {
        $this->authorizeLink($link);

        return response()->json(['enabled' => $search->enabled()]);
    }

    /**
     * Google image search: candidate suggestions the creator explicitly
     * picks from (rights disclaimer shown client-side; nothing is ever
     * auto-placed). Free of AI credits; the route carries a throttle and
     * the service itself is bounded. 404s in preview mode so the feature
     * hides gracefully when no admin/env keys exist.
     */
    public function imageSearch(Request $request, Link $link, GoogleImageSearchService $search): JsonResponse
    {
        $this->authorizeLink($link);

        if (!$search->enabled()) {
            // `code` lets a mid-session client (admin removed the CSE keys
            // while the intake was open) collapse the picker instead of
            // leaving it retryable forever.
            return response()->json([
                'message' => 'Image search is not available.',
                'code'    => 'image_search_unavailable',
            ], 404);
        }

        if (GoogleCseUsage::capReached($request->user()?->id)) {
            return response()->json([
                'message' => __("You've reached today's image search limit — try again tomorrow."),
            ], 429);
        }

        $data = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:200'],
        ]);

        return response()->json([
            'results'    => $search->search($data['query'], 8, $request->user()?->id),
            'disclaimer' => __('Make sure you have the rights to use any image you pick — search results may be copyrighted.'),
        ]);
    }

    /**
     * Download the creator's chosen candidate images (SSRF-safe via
     * OgMetadataService::downloadImage), store them in the vault under the
     * `ai_builder` context, and return relative vault URLs the intake form
     * appends to its images[] list — the same shape uploads use.
     */
    public function importImages(Request $request, Link $link, OgMetadataService $og): JsonResponse
    {
        $this->authorizeLink($link);

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
                // Storage quota — surface loudly instead of silently dropping.
                return response()->json(['message' => $e->getMessage()], 422);
            }

            $stored[] = ['url' => $file->url_path, 'source_url' => $url];
        }

        if ($stored === []) {
            return response()->json([
                'message' => 'None of the selected images could be downloaded. They may be blocked or too large.',
            ], 422);
        }

        return response()->json(['images' => $stored]);
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
            'kept_images'           => ['nullable', 'array', 'max:25'],
            'kept_images.*'         => ['string', 'max:2048'],
            'skip_generated_slots'  => ['nullable', 'array'],
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
            // On-brand by default; the intake form sends an explicit opt-out.
            'use_brand_kit' => $request->has('use_brand_kit') ? $request->boolean('use_brand_kit') : true,
            'image_choices' => $imageChoices,
        ];
    }

    /**
     * Resolve the creator's saved Brand Kit voice/palette directives to feed
     * the builder, honoring the per-request opt-out and the plan gate. Returns
     * '' when off, ungated, or the creator has no kit — so generation is
     * unaffected for everyone who hasn't opted into On-Brand AI.
     */
    private function brandDirectives(Request $request, bool $useBrandKit): string
    {
        if (!$useBrandKit) {
            return '';
        }
        if (!AiPlanAccess::featureAllowed($request->user(), 'brand_consistency')) {
            return '';
        }
        $kit = BrandKit::defaultFor(workspace_owner_id());

        return $kit ? $kit->promptDirectives(true) : '';
    }

    private function authorizeLink(Link $link): void
    {
        abort_if($link->user_id !== workspace_owner_id() || $link->type !== 'biolink', 403);
    }
}
