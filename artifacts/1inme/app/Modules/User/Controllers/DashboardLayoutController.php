<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Support\DashboardPresets;
use App\Modules\User\Support\DashboardWidgetCatalog;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\DashboardAiDesignerService;
use App\Services\AI\InsufficientCoinsForAiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Task #3525 — "Customize dashboard" endpoints for `/user/dashboard`.
 *
 *   POST dashboard/layout/preset  → apply one of the 5 curated presets
 *   POST dashboard/ai/estimate    → upfront credit cost for an AI layout
 *   POST dashboard/ai/generate    → run the designer, persist the custom layout
 *
 * The AI charge happens inside DashboardAiDesignerService::generate() via
 * OpenAiService against the `dashboard_designer` feature — no new currency
 * path, auto-refunded on any parse/validation failure.
 */
class DashboardLayoutController extends Controller
{
    public function __construct(
        protected DashboardAiDesignerService $designer,
        protected AiUsageCharger $credits,
    ) {}

    public function applyPreset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'preset' => ['required', 'string'],
        ]);

        if (!DashboardPresets::isValidPreset($data['preset'])) {
            return response()->json(['message' => 'Unknown dashboard preset.'], 422);
        }

        DashboardPresets::applyPreset($request->user(), $data['preset']);

        return response()->json([
            'preset'  => $data['preset'],
            'widgets' => DashboardPresets::widgetsForPreset($data['preset']),
        ]);
    }

    public function estimate(Request $request): JsonResponse
    {
        if (!AiEngineSettings::isEnabled()) {
            return response()->json(['message' => 'AI Engine is disabled.'], 404);
        }
        if (!AiPlanAccess::featureAllowed($request->user(), DashboardAiDesignerService::FEATURE)) {
            return response()->json(['message' => 'AI dashboard designer is not available on your plan.'], 403);
        }

        $data = $this->validateAnswers($request);

        try {
            $cost = $this->designer->estimateCredits($request->user(), $data);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'estimated_credits' => $cost,
            'balance'           => $this->credits->getBalance($request->user()),
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        if (!AiEngineSettings::isEnabled()) {
            return response()->json(['message' => 'AI Engine is disabled.'], 404);
        }
        if (!AiPlanAccess::featureAllowed($request->user(), DashboardAiDesignerService::FEATURE)) {
            return response()->json(['message' => 'AI dashboard designer is not available on your plan.'], 403);
        }

        $data = $this->validateAnswers($request);

        try {
            $result = $this->designer->generate($request->user(), $data);
        } catch (InsufficientCoinsForAiException $e) {
            return response()->json([
                'message'  => 'Not enough AI credits to design a dashboard.',
                'required' => $e->required ?? null,
                'balance'  => $e->balance ?? null,
            ], 402);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'widgets'       => $result['widgets'],
            'credits_spent' => $result['credits_spent'],
            'balance'       => $this->credits->getBalance($request->user()),
            'redirect'      => route('user.dashboard'),
        ]);
    }

    /**
     * @return array{goal:string,priorities:list<string>,density:string,notes:string,selected_widgets:list<string>}
     */
    private function validateAnswers(Request $request): array
    {
        $data = $request->validate([
            'goal'                => ['required', 'string', 'min:5', 'max:800'],
            'priorities'          => ['nullable', 'array', 'max:10'],
            'priorities.*'        => ['string', 'max:120'],
            'density'             => ['nullable', 'string', 'in:minimal,balanced,detailed'],
            'notes'               => ['nullable', 'string', 'max:800'],
            'selected_widgets'    => ['nullable', 'array', 'max:' . count(DashboardWidgetCatalog::WIDGETS)],
            'selected_widgets.*'  => ['string'],
        ]);

        return [
            'goal'             => $data['goal'],
            'priorities'       => array_values($data['priorities'] ?? []),
            'density'          => $data['density'] ?? 'balanced',
            'notes'            => $data['notes'] ?? '',
            // Sanitized here (not just inside the service) so the estimate
            // step prices the exact same widget set the generate step later
            // enforces — never trust the raw client array beyond this line.
            'selected_widgets' => DashboardWidgetCatalog::sanitize($data['selected_widgets'] ?? []),
        ];
    }
}
