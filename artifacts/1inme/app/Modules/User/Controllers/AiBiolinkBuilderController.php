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
        ]);
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
            );
        } catch (InsufficientCoinsForAiException $e) {
            return response()->json([
                'message'  => 'Not enough AI credits to build this page.',
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
     * @return array{description:string,links:list<string>,images:list<string>,files:list<string>,use_brand_kit:bool}
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
        ]);

        return [
            'description'   => $data['description'],
            'links'         => array_values($data['links'] ?? []),
            'images'        => array_values($data['images'] ?? []),
            'files'         => array_values($data['files'] ?? []),
            // On-brand by default; the intake form sends an explicit opt-out.
            'use_brand_kit' => $request->has('use_brand_kit') ? $request->boolean('use_brand_kit') : true,
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
