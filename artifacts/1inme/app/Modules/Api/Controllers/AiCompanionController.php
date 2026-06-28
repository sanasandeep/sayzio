<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\AiCompanion;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\AiPersonaAgentVersion;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\CompanionSettings;
use App\Services\AI\PersonaSettings;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

        $current = AiCompanion::where('user_id', $user->id)->count();
        if (!\App\Services\AI\AiPlanAccess::underQuantityCap($user, 'companions', $current)) {
            return $this->fail(
                \App\Services\AI\AiPlanAccess::quantityLimitMessage($user, 'companions', 'Companion', $current),
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

    /**
     * Create a minimal AI Persona on the spot from the mobile block
     * editor, so a Companion can be built fully self-serve without
     * hopping to the web persona builder. Only name + base instructions
     * (system_prompt) are collected — every other knob falls back to the
     * same sensible defaults the web "blank" template uses. Mirrors
     * PersonasController@createPersonaFromConfig (caps, default model,
     * initial version row) but trimmed to the two fields mobile asks for.
     */
    public function storePersona(Request $request)
    {
        if (!AiEngineSettings::isEnabled()) {
            return $this->fail('AI Personas are not available right now.', 422, 'ai_disabled');
        }

        $user = $request->user();
        $caps = PersonaSettings::caps();

        $current = AiPersonaAgent::where('user_id', $user->id)->count();
        if (!\App\Services\AI\AiPlanAccess::underQuantityCap($user, 'personas', $current)) {
            return $this->fail(
                \App\Services\AI\AiPlanAccess::quantityLimitMessage($user, 'personas', 'Persona', $current),
                422,
                'persona_limit',
            );
        }

        $data = $request->validate([
            'name'           => 'required|string|max:120',
            'system_prompt'  => "nullable|string|max:{$caps['max_system_prompt_chars']}",
            // On-Brand AI (Task #2664) opt-out — when on (default), the Companion
            // runtime injects the owner's default Brand Kit voice into replies.
            'use_brand_kit'  => 'nullable|boolean',
        ]);

        // Default on; mobile sends an explicit opt-out, mirroring the web
        // persona edit form's "Use my Brand Kit voice" checkbox.
        $useBrandKit = $request->has('use_brand_kit') ? $request->boolean('use_brand_kit') : true;

        $persona = DB::transaction(function () use ($user, $data, $useBrandKit) {
            $allowed = collect(AiPersonaAgent::ACTIONS)->keys()
                ->mapWithKeys(fn ($k) => [$k => false])
                ->all();

            $persona = AiPersonaAgent::create([
                'user_id'           => $user->id,
                'slug'              => null,
                'name'              => $data['name'],
                'system_prompt'     => trim((string) ($data['system_prompt'] ?? '')) !== ''
                    ? $data['system_prompt']
                    : 'You are a helpful assistant.',
                'tone_preset'       => 'friendly',
                'model'             => $this->pickDefaultModel(),
                'temperature_x100'  => 50,
                'max_tokens'        => 600,
                'languages'         => [],
                'allowed_actions'   => $allowed,
                'fallback_behavior' => 'clarify',
                'starter_questions' => [],
                'use_default_mind'  => true,
                'use_brand_kit'     => $useBrandKit,
            ]);
            $persona->slug = Str::slug($persona->name) . '-' . $persona->id;
            $persona->save();

            $this->writeInitialVersion($persona);

            return $persona;
        });

        return $this->created(['persona' => [
            'id'   => (int) $persona->id,
            'name' => $persona->name,
        ]]);
    }

    /** Write the initial version row and point the persona at it. */
    protected function writeInitialVersion(AiPersonaAgent $persona): void
    {
        $version = AiPersonaAgentVersion::create([
            'persona_id'         => $persona->id,
            'revision'           => 1,
            'config'             => $persona->snapshotConfig(),
            'summary'            => 'Initial version',
            'created_by_user_id' => $persona->user_id,
            'created_at'         => now(),
        ]);
        $persona->forceFill(['active_version_id' => $version->id])->save();
    }

    /** First admin-enabled chat model, falling back to gpt-4o-mini. */
    protected function pickDefaultModel(): string
    {
        foreach (AiEngineSettings::models() as $m) {
            if (!empty($m['enabled']) && ($m['kind'] ?? '') === 'chat') {
                return $m['name'];
            }
        }
        return 'gpt-4o-mini';
    }
}
