<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Services\AI\AiActionCooldown;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\Builder\AbstractAiTypeBuilderService;
use App\Services\AI\Builder\AiResumeBuilderService;
use App\Services\AI\Builder\AiRestaurantMenuBuilderService;
use App\Services\AI\Builder\AiServiceBookingBuilderService;
use App\Services\AI\Builder\AiSlidesBuilderService;
use App\Services\AI\Builder\AiStoreMenuBuilderService;
use App\Services\AI\InsufficientCoinsForAiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Build with AI" endpoints for the non-biolink link types (Task #5727):
 * Slides, Restaurant Menu, Store, Service Booking, Resume.
 *
 *   GET  links/{link}/ai-type-builder           → intake screen
 *   POST links/{link}/ai-type-builder/estimate  → upfront credit cost
 *   POST links/{link}/ai-type-builder/generate  → run the build, then open the editor
 *
 * Mirrors AiBiolinkBuilderController's contract: the AI charge happens
 * inside the service's generate() call via OpenAiService against the
 * per-type feature key — no new currency/coin path — with cooldown-based
 * double-charge protection and auto-refund on failed builds.
 */
class AiTypeBuilderController extends Controller
{
    /** links.type → builder service class. */
    public const SERVICES = [
        Link::TYPE_SLIDES          => AiSlidesBuilderService::class,
        Link::TYPE_RESTAURANT_MENU => AiRestaurantMenuBuilderService::class,
        Link::TYPE_STORE_MENU      => AiStoreMenuBuilderService::class,
        Link::TYPE_SERVICE_BOOKING => AiServiceBookingBuilderService::class,
        Link::TYPE_RESUME          => AiResumeBuilderService::class,
    ];

    public function __construct(protected AiUsageCharger $credits) {}

    public function intake(Request $request, Link $link)
    {
        $service = $this->serviceFor($request, $link);

        return view('user.links.ai-type-builder', [
            'link'           => $link,
            'service'        => $service,
            'aiEnabled'      => AiEngineSettings::isEnabled(),
            'balance'        => AiEngineSettings::isEnabled() ? $this->credits->getBalance($request->user()) : 0,
            'maxLinks'       => AbstractAiTypeBuilderService::MAX_LINKS,
            'maxImages'      => AbstractAiTypeBuilderService::MAX_IMAGES,
            'supportsLinks'  => $service->supportsLinks(),
            'supportsImages' => $service->supportsImages(),
            'editorUrl'      => $this->editorUrl($link),
            'typeLabel'      => $this->typeLabel($link->type),
        ]);
    }

    public function estimate(Request $request, Link $link): JsonResponse
    {
        $service = $this->serviceFor($request, $link);
        $data    = $this->validatePayload($request);

        try {
            $cost = $service->estimateCredits($request->user(), $data['description'], $data['links'], $data['images']);
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
        $service = $this->serviceFor($request, $link);
        $data    = $this->validatePayload($request);

        // Double-charge guard: identical re-runs within the cooldown window
        // are served from cache without charging; a concurrent identical
        // request is rejected instead of starting a second paid build.
        $cooldownKey = AiActionCooldown::key($service->feature(), $request->user()->id, ['link' => $link->id] + $data);
        if ($hit = AiActionCooldown::fresh($cooldownKey)) {
            return response()->json([
                'summary'       => $hit['result']['summary'] ?? [],
                'credits_spent' => 0,
                'balance'       => $this->credits->getBalance($request->user()),
                'redirect'      => $this->editorUrl($link),
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
            $result = $service->generate($request->user(), $link, $data['description'], $data['links'], $data['images']);
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

        AiActionCooldown::remember($cooldownKey, ['summary' => $result['summary']]);

        return response()->json([
            'summary'       => $result['summary'],
            'credits_spent' => $result['credits_spent'],
            'balance'       => $this->credits->getBalance($request->user()),
            'redirect'      => $this->editorUrl($link),
        ]);
    }

    /** Resolve + authorize the builder for this link, or abort. */
    private function serviceFor(Request $request, Link $link): AbstractAiTypeBuilderService
    {
        if ($link->user_id !== workspace_owner_id()) {
            abort(404);
        }

        $class = self::SERVICES[$link->type] ?? null;
        if ($class === null) {
            abort(404);
        }

        if (!AiEngineSettings::isEnabled()) {
            abort(404, 'AI Engine is disabled.');
        }

        /** @var AbstractAiTypeBuilderService $service */
        $service = app($class);

        if (!AiPlanAccess::featureAllowed($request->user(), $service->feature())) {
            abort(403, 'Your plan does not include this AI builder.');
        }

        return $service;
    }

    /** @return array{description:string,links:array,images:array} */
    private function validatePayload(Request $request): array
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'min:10', 'max:' . AbstractAiTypeBuilderService::MAX_DESCRIPTION],
            'links'       => ['sometimes', 'array', 'max:' . AbstractAiTypeBuilderService::MAX_LINKS],
            'links.*'     => ['string', 'max:2048'],
            'images'      => ['sometimes', 'array', 'max:' . AbstractAiTypeBuilderService::MAX_IMAGES],
            'images.*'    => ['string', 'max:2048'],
        ]);

        return [
            'description' => $data['description'],
            'links'       => array_values($data['links'] ?? []),
            'images'      => array_values($data['images'] ?? []),
        ];
    }

    private function editorUrl(Link $link): string
    {
        return match ($link->type) {
            Link::TYPE_SLIDES          => route('user.links.slides.editor', $link),
            Link::TYPE_RESTAURANT_MENU => route('user.links.restaurant.editor', $link),
            Link::TYPE_STORE_MENU      => route('user.links.store.editor', $link),
            Link::TYPE_SERVICE_BOOKING => route('user.links.service-booking.editor', $link),
            Link::TYPE_RESUME          => route('user.resume.editor'),
            default                    => route('user.links.index'),
        };
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            Link::TYPE_SLIDES          => __('Slides'),
            Link::TYPE_RESTAURANT_MENU => __('Restaurant Menu'),
            Link::TYPE_STORE_MENU      => __('Store'),
            Link::TYPE_SERVICE_BOOKING => __('Service Booking'),
            Link::TYPE_RESUME          => __('Resume'),
            default                    => __('Page'),
        };
    }
}
