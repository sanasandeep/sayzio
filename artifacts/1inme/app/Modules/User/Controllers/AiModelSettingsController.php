<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AI\AiEngineSettings;
use Illuminate\Http\Request;

/**
 * Task #6143 — paid users can pick which enabled chat model each AI
 * feature uses for *their* calls, overriding the admin's platform
 * default. Free users see an upgrade prompt; their stored choices (if
 * any, e.g. after a downgrade) are ignored server-side by
 * AiEngineSettings::userFeatureModelOverride().
 */
class AiModelSettingsController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return view('user.settings.ai-models', [
            'isPaid'        => ! $user->isOnFreePlan(),
            'models'        => AiEngineSettings::selectableChatModels(),
            'choices'       => AiEngineSettings::userFeatureModels($user),
            'features'      => AiEngineSettings::FEATURES,
            'platformModels' => AiEngineSettings::featureModels(),
            'defaultModel'  => AiEngineSettings::DEFAULT_FEATURE_MODEL,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        if ($user->isOnFreePlan()) {
            abort(403, 'Choosing AI models per feature is available on paid plans.');
        }

        $data = $request->validate([
            'feature_models'   => ['array'],
            'feature_models.*' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            AiEngineSettings::setUserFeatureModels($user, $data['feature_models'] ?? [], true);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['feature_models' => $e->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => ['feature_models' => AiEngineSettings::userFeatureModels($user->fresh())]]);
        }

        return back()->with('success', 'AI model preferences saved.');
    }

    public function reset(Request $request)
    {
        $user = $request->user();
        if ($user->isOnFreePlan()) {
            abort(403, 'Choosing AI models per feature is available on paid plans.');
        }

        AiEngineSettings::setUserFeatureModels($user, [], true);

        if ($request->wantsJson()) {
            return response()->json(['data' => ['feature_models' => []]]);
        }

        return back()->with('success', 'All features reset to the platform default models.');
    }
}
