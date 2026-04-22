<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiMindDefault;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiMindProvisioner;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Mobile parity for the Persona/Coach Mind picker defaults.
 *
 *   GET    /api/v1/ai/minds                         user's minds + platform mind
 *   GET    /api/v1/ai/{feature}/defaults            current saved default selection
 *   PUT    /api/v1/ai/{feature}/defaults            save a new default selection
 *   DELETE /api/v1/ai/{feature}/defaults            forget the default
 *
 * `{feature}` is one of: persona, coach. Web equivalents live on
 * PersonaController / CoachController. Logic mirrors those (constrain
 * mind_ids to the user's own active Minds, store boolean platform opt-in).
 */
class AiMindPickerController extends Controller
{
    use ApiResponses;

    private const FEATURES = ['persona', 'coach'];

    public function minds(Request $request)
    {
        if (!AiEngineSettings::isEnabled()) return $this->fail('AI Engine is disabled.', 404);
        $user = $request->user();
        AiMindProvisioner::ensureForUser($user);

        $mine = AiMind::where('user_id', $user->id)
            ->where('is_disabled', false)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn(AiMind $m) => [
                'id'   => (int) $m->id,
                'name' => (string) $m->name,
            ])->all();

        $platform = AiMind::whereNull('user_id')
            ->where('is_default', true)
            ->where('is_disabled', false)
            ->first(['id', 'name']);

        return $this->ok([
            'mine'     => $mine,
            'platform' => $platform ? [
                'id'   => (int) $platform->id,
                'name' => (string) $platform->name,
            ] : null,
        ]);
    }

    public function getDefaults(Request $request, string $feature)
    {
        if (!AiEngineSettings::isEnabled()) return $this->fail('AI Engine is disabled.', 404);
        $feature = $this->normalizeFeature($feature);
        if ($feature === null) return $this->fail('Unknown feature.', 404);

        $user = $request->user();
        $default = AiMindDefault::forUserFeature($user->id, $feature);

        return $this->ok([
            'feature'          => $feature,
            'has_default'      => (bool) $default,
            'mind_ids'         => $default ? array_map('intval', $default->mind_ids ?? []) : [],
            'include_platform' => $default ? (bool) $default->include_platform : false,
        ]);
    }

    public function saveDefaults(Request $request, string $feature)
    {
        if (!AiEngineSettings::isEnabled()) return $this->fail('AI Engine is disabled.', 404);
        $feature = $this->normalizeFeature($feature);
        if ($feature === null) return $this->fail('Unknown feature.', 404);

        $data = $request->validate([
            'mind_ids'         => 'nullable|array',
            'mind_ids.*'       => 'integer',
            'include_platform' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $mindIds = array_values(array_unique(array_map('intval', $data['mind_ids'] ?? [])));
        // Constrain to the user's own active Minds so we don't store
        // stale or cross-user ids in defaults.
        if ($mindIds) {
            $mindIds = AiMind::where('user_id', $user->id)
                ->where('is_disabled', false)
                ->whereIn('id', $mindIds)
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->all();
        }

        AiMindDefault::updateOrCreate(
            ['user_id' => $user->id, 'feature' => $feature],
            [
                'mind_ids'         => $mindIds,
                'include_platform' => (bool) ($data['include_platform'] ?? false),
            ],
        );

        return $this->ok([
            'feature'          => $feature,
            'has_default'      => true,
            'mind_ids'         => $mindIds,
            'include_platform' => (bool) ($data['include_platform'] ?? false),
        ]);
    }

    public function clearDefaults(Request $request, string $feature)
    {
        if (!AiEngineSettings::isEnabled()) return $this->fail('AI Engine is disabled.', 404);
        $feature = $this->normalizeFeature($feature);
        if ($feature === null) return $this->fail('Unknown feature.', 404);

        AiMindDefault::where('user_id', $request->user()->id)
            ->where('feature', $feature)
            ->delete();

        return $this->ok([
            'feature'          => $feature,
            'has_default'      => false,
            'mind_ids'         => [],
            'include_platform' => false,
        ]);
    }

    private function normalizeFeature(string $feature): ?string
    {
        $feature = strtolower($feature);
        return in_array($feature, self::FEATURES, true) ? $feature : null;
    }
}
