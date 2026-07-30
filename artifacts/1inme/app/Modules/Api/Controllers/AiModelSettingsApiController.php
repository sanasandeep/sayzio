<?php

namespace App\Modules\Api\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AI\AiEngineSettings;
use Illuminate\Http\Request;

/**
 * Task #6143 — REST parity for the web Settings → AI Models tab.
 * Paid users read/set their per-feature chat-model overrides; free
 * users can read (to render the upgrade prompt) but not write.
 */
class AiModelSettingsApiController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json(['data' => [
            'is_paid'         => ! $user->isOnFreePlan(),
            'features'        => AiEngineSettings::FEATURES,
            'models'          => AiEngineSettings::selectableChatModels(),
            'choices'         => (object) AiEngineSettings::userFeatureModels($user),
            'platform_models' => (object) AiEngineSettings::featureModels(),
            'default_model'   => AiEngineSettings::DEFAULT_FEATURE_MODEL,
        ]]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        if ($user->isOnFreePlan()) {
            return response()->json(['error' => [
                'message' => 'Choosing AI models per feature is available on paid plans.',
                'code'    => 'paid_plan_required',
            ]], 403);
        }

        $data = $request->validate([
            'feature_models'   => ['required', 'array'],
            'feature_models.*' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            // Partial update: only the submitted features change; null/''
            // clears a feature back to the platform default.
            AiEngineSettings::setUserFeatureModels($user, $data['feature_models']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => [
                'message' => $e->getMessage(),
                'code'    => 'invalid_model',
            ]], 422);
        }

        return response()->json(['data' => [
            'choices' => (object) AiEngineSettings::userFeatureModels($user->fresh()),
        ]]);
    }

    public function reset(Request $request)
    {
        $user = $request->user();
        if ($user->isOnFreePlan()) {
            return response()->json(['error' => [
                'message' => 'Choosing AI models per feature is available on paid plans.',
                'code'    => 'paid_plan_required',
            ]], 403);
        }

        AiEngineSettings::setUserFeatureModels($user, [], true);

        return response()->json(['data' => ['choices' => (object) []]]);
    }
}
