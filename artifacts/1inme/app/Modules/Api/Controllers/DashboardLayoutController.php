<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Support\DashboardPresets;
use App\Modules\User\Support\DashboardWidgetCatalog;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\DashboardAiDesignerService;
use App\Services\AI\InsufficientCoinsForAiException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Mobile (Sanctum) parity for the web "Customize dashboard" flow
 * (App\Modules\User\Controllers\DashboardLayoutController). Same widget
 * catalog, presets, and AI designer service — no drift possible since both
 * surfaces delegate to the shared Support/Service classes.
 *
 *   GET  /dashboard/layout          → catalog + presets + the caller's current layout
 *   POST /dashboard/layout/preset   → apply one of the 5 curated presets
 *   POST /dashboard/ai/estimate     → upfront credit cost for an AI layout
 *   POST /dashboard/ai/generate     → run the designer, persist the custom layout
 */
class DashboardLayoutController extends Controller
{
    use ApiResponses;

    public function __construct(
        protected DashboardAiDesignerService $designer,
        protected AiUsageCharger $credits,
    ) {}

    public function show(Request $request)
    {
        $user = $request->user();
        $layout = DashboardPresets::resolveFor($user);

        return $this->ok([
            'catalog'          => DashboardWidgetCatalog::forFrontend(),
            'grouped_catalog'  => DashboardWidgetCatalog::groupedForFrontend(),
            'presets'          => DashboardPresets::forFrontend(),
            'current'  => [
                'preset'    => $layout['preset'],
                'is_custom' => $layout['is_custom'],
                'widgets'   => $layout['widgets'],
                'source'    => $layout['source'],
            ],
            'ai_designer_allowed' => AiPlanAccess::featureAllowed($user, DashboardAiDesignerService::FEATURE),
            'ai_enabled'          => AiEngineSettings::isEnabled(),
        ]);
    }

    public function applyPreset(Request $request)
    {
        $data = $request->validate([
            'preset' => ['required', 'string'],
        ]);

        if (!DashboardPresets::isValidPreset($data['preset'])) {
            return $this->fail('Unknown dashboard preset.', 422, 'invalid_preset');
        }

        DashboardPresets::applyPreset($request->user(), $data['preset']);

        return $this->ok([
            'preset'  => $data['preset'],
            'widgets' => DashboardPresets::widgetsForPreset($data['preset']),
        ]);
    }

    public function estimate(Request $request)
    {
        if (!AiEngineSettings::isEnabled()) {
            return $this->fail('AI generation is currently unavailable.', 503, 'ai_unavailable');
        }
        if (!AiPlanAccess::featureAllowed($request->user(), DashboardAiDesignerService::FEATURE)) {
            return $this->fail('AI dashboard designer is not available on your plan.', 403, 'plan_gated');
        }

        $data = $this->validateAnswers($request);

        try {
            $cost = $this->designer->estimateCredits($request->user(), $data);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422, 'invalid_request');
        }

        return $this->ok([
            'estimated_credits' => $cost,
            'balance'           => $this->credits->getBalance($request->user()),
        ]);
    }

    public function generate(Request $request)
    {
        if (!AiEngineSettings::isEnabled()) {
            return $this->fail('AI generation is currently unavailable.', 503, 'ai_unavailable');
        }
        if (!AiPlanAccess::featureAllowed($request->user(), DashboardAiDesignerService::FEATURE)) {
            return $this->fail('AI dashboard designer is not available on your plan.', 403, 'plan_gated');
        }

        $data = $this->validateAnswers($request);

        try {
            $result = $this->designer->generate($request->user(), $data);
        } catch (InsufficientCoinsForAiException $e) {
            return $this->fail('Not enough coins to design a dashboard.', 402, 'insufficient_credits', [
                'required' => $e->required ?? null,
                'balance'  => $e->balance ?? null,
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422, 'generation_failed');
        }

        return $this->ok([
            'widgets'       => $result['widgets'],
            'credits_spent' => $result['credits_spent'],
            'balance'       => $this->credits->getBalance($request->user()),
        ]);
    }

    /**
     * @return array{goal:string,priorities:list<string>,density:string,notes:string,selected_widgets:list<string>}
     */
    private function validateAnswers(Request $request): array
    {
        $data = $request->validate([
            'goal'               => ['required', 'string', 'min:5', 'max:800'],
            'priorities'         => ['nullable', 'array', 'max:10'],
            'priorities.*'       => ['string', 'max:120'],
            'density'            => ['nullable', 'string', 'in:minimal,balanced,detailed'],
            'notes'              => ['nullable', 'string', 'max:800'],
            'selected_widgets'   => ['nullable', 'array', 'max:' . count(DashboardWidgetCatalog::WIDGETS)],
            'selected_widgets.*' => ['string'],
        ]);

        return [
            'goal'       => $data['goal'],
            'priorities' => array_values($data['priorities'] ?? []),
            'density'    => $data['density'] ?? 'balanced',
            'notes'      => $data['notes'] ?? '',
            // Sanitized here (mirrors web DashboardLayoutController) so the
            // estimate step prices the exact widget set generate enforces —
            // never trust the raw client array beyond this line.
            'selected_widgets' => DashboardWidgetCatalog::sanitize($data['selected_widgets'] ?? []),
        ];
    }
}
