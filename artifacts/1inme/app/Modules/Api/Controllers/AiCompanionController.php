<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\AiCompanion;
use App\Modules\User\Models\AiPersonaAgent;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\CompanionSettings;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AiCompanionController extends Controller
{
    use ApiResponses;

    /**
     * List the signed-in user's biolink-placement AI Companions so the
     * mobile block editor's "AI" picker can offer them — mirroring the
     * web editor's special-panel $userCompanions list. Restricted to the
     * `biolink` placement so embed/inbox-only companions don't leak in.
     */
    public function index(Request $request)
    {
        $items = AiCompanion::where('user_id', $request->user()->id)
            ->where('placement', 'biolink')
            ->orderByDesc('id')
            ->get(['id', 'public_id', 'name', 'is_disabled'])
            ->map(fn ($c) => [
                'id'          => (int) $c->id,
                'public_id'   => $c->public_id,
                'name'        => $c->name,
                'is_disabled' => (bool) $c->is_disabled,
            ])->values()->all();

        return $this->ok(['items' => $items]);
    }

    /**
     * List the user's enabled AI Personas so the mobile "AI" picker can
     * offer a persona when creating a Companion on the spot. A Companion
     * is intentionally thin — all "brain" config lives on the Persona —
     * so a persona must exist before one can be created.
     */
    public function personas(Request $request)
    {
        if (!AiEngineSettings::isEnabled()) {
            return $this->ok(['items' => []]);
        }

        $items = AiPersonaAgent::where('user_id', $request->user()->id)
            ->where('is_disabled', false)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($p) => ['id' => (int) $p->id, 'name' => $p->name])
            ->values()->all();

        return $this->ok(['items' => $items]);
    }

    /**
     * Create a biolink-placement AI Companion on the spot from the mobile
     * block editor's special panel. Mirrors web CompanionsController@store:
     * name + persona_id, placement forced to `biolink`, respecting the
     * per-user companion cap and the global AI engine toggle.
     */
    public function store(Request $request)
    {
        if (!AiEngineSettings::isEnabled()) {
            return $this->fail('AI Companions are not available right now.', 422, 'ai_disabled');
        }

        $user = $request->user();
        $caps = CompanionSettings::caps();

        if (AiCompanion::where('user_id', $user->id)->count() >= $caps['max_companions_per_user']) {
            return $this->fail(
                "You have reached the {$caps['max_companions_per_user']}-Companion limit.",
                422,
                'companion_limit',
            );
        }

        $data = $request->validate([
            'name'       => 'required|string|max:120',
            'persona_id' => 'required|integer',
        ]);

        $persona = AiPersonaAgent::where('id', $data['persona_id'])
            ->where('user_id', $user->id)
            ->where('is_disabled', false)
            ->first();
        if (!$persona) {
            return $this->fail('Pick one of your Personas.', 422, 'invalid_persona', [
                'persona_id' => ['Pick one of your Personas.'],
            ]);
        }

        $companion = AiCompanion::create([
            'user_id'              => $user->id,
            'persona_id'           => $persona->id,
            'public_id'            => AiCompanion::newPublicId(),
            'name'                 => $data['name'],
            'placement'            => 'biolink',
            'config'               => AiCompanion::defaultConfig(),
            'allowed_domains'      => [],
            'free_turns_per_month' => $caps['default_free_turns_per_month'],
            'hard_cap_per_month'   => 2000,
        ]);

        return $this->created(['companion' => [
            'id'          => (int) $companion->id,
            'public_id'   => $companion->public_id,
            'name'        => $companion->name,
            'is_disabled' => (bool) $companion->is_disabled,
        ]]);
    }
}
