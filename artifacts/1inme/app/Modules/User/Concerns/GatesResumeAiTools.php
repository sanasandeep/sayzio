<?php

namespace App\Modules\User\Concerns;

use App\Services\AI\AiPlanAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared per-plan gate for the AI Resume Tools feature (tailor / import /
 * cover-letter). Returns a 403 JSON response when the user's plan does not
 * unlock `ai_resume_tools`, otherwise null so the caller continues.
 *
 * Falls back to "allowed" when the plan row predates the key, so nothing
 * regresses mid-rollout (handled inside AiPlanAccess::featureAllowed).
 */
trait GatesResumeAiTools
{
    protected function resumeToolsGate(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (AiPlanAccess::featureAllowed($user, 'ai_resume_tools')) {
            return null;
        }

        $plan = AiPlanAccess::featureUpgradePlan($user, 'ai_resume_tools');
        $message = 'AI Resume Tools are not available on your current plan.';
        if ($plan) {
            $message .= ' Upgrade to the ' . $plan->name . ' plan to use them.';
        }

        return response()->json([
            'message'      => $message,
            'code'         => 'plan_upgrade_required',
            'upgrade_plan' => $plan?->slug,
        ], 403);
    }
}
