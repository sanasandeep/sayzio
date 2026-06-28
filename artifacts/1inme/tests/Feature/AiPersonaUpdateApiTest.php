<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\AiPersonaAgentVersion;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sanctum (bearer-token) coverage for the mobile On-Brand AI toggle endpoint
 * (PATCH /api/v1/ai-companions/personas/{persona}).
 *
 * That endpoint flips a Persona's `use_brand_kit` and writes a new version row
 * mirroring the web persona save. With no automated coverage a regression could
 * silently change or fail to change an agent's voice with no signal, so these
 * tests pin:
 *
 *   1. The owner can flip use_brand_kit OFF (true → false): the response
 *      reflects the new value and a new persona version row is written.
 *   2. The owner can flip use_brand_kit back ON (false → true): again the
 *      response reflects it and the version history advances.
 *   3. The boolean validation rejects a non-boolean value (422).
 *   4. A non-owner gets 404 and the target persona is left untouched.
 *
 * Authenticated requests use a real personal access token, NOT
 * Sanctum::actingAs — that injects a Mockery mock the TouchSessionToken
 * middleware can't ->save() (see the sanctum-api-tests convention).
 *
 * The endpoint never calls OpenAI (it only persists a boolean + version row),
 * so these tests need the AI engine enabled but no chat double.
 */
class AiPersonaUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AiEngineSettings::setEnabled(true);
    }

    private function plan(): Plan
    {
        return Plan::create([
            'name'          => 'AI Plan',
            'slug'          => 'ai-' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 0,
            'features'      => [
                'max_links'    => 100,
                'max_biolinks' => 100,
            ],
        ]);
    }

    private function makeUser(?Plan $plan = null): User
    {
        $user = User::create([
            'name'     => 'Persona ' . Str::random(4),
            'email'    => 'persona-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'role'     => 'user',
            'plan_id'  => ($plan ?? $this->plan())->id,
        ]);
        $user->ensureDefaultWorkspace();
        return $user->fresh();
    }

    private function persona(User $user, bool $useBrandKit): AiPersonaAgent
    {
        return AiPersonaAgent::create([
            'user_id'           => $user->id,
            'slug'              => 'p-' . Str::random(6),
            'name'              => 'Helper',
            'system_prompt'     => 'You help visitors.',
            'use_brand_kit'     => $useBrandKit,
            'model'             => 'gpt-4o-mini',
            'temperature_x100'  => 50,
            'max_tokens'        => 300,
            'languages'         => [],
            'allowed_actions'   => [],
            'fallback_behavior' => 'clarify',
            'use_default_mind'  => false,
            'is_disabled'       => false,
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function url(AiPersonaAgent $persona): string
    {
        return "/api/v1/ai-companions/personas/{$persona->id}";
    }

    public function test_owner_can_flip_use_brand_kit_off_and_a_version_is_written(): void
    {
        $user = $this->makeUser();
        $persona = $this->persona($user, true);

        $before = AiPersonaAgentVersion::where('persona_id', $persona->id)->count();

        $res = $this->withToken($this->token($user))
            ->patchJson($this->url($persona), ['use_brand_kit' => false]);

        $res->assertOk()
            ->assertJsonPath('data.persona.id', $persona->id)
            ->assertJsonPath('data.persona.use_brand_kit', false);

        $this->assertFalse((bool) $persona->fresh()->use_brand_kit);

        $after = AiPersonaAgentVersion::where('persona_id', $persona->id)->count();
        $this->assertSame($before + 1, $after, 'A new persona version row should be written.');
        $this->assertNotNull($persona->fresh()->active_version_id);
    }

    public function test_owner_can_flip_use_brand_kit_back_on_and_history_advances(): void
    {
        $user = $this->makeUser();
        $persona = $this->persona($user, false);

        $before = AiPersonaAgentVersion::where('persona_id', $persona->id)->count();

        $res = $this->withToken($this->token($user))
            ->patchJson($this->url($persona), ['use_brand_kit' => true]);

        $res->assertOk()
            ->assertJsonPath('data.persona.use_brand_kit', true);

        $this->assertTrue((bool) $persona->fresh()->use_brand_kit);

        $after = AiPersonaAgentVersion::where('persona_id', $persona->id)->count();
        $this->assertSame($before + 1, $after, 'The version history should advance.');
    }

    public function test_non_boolean_use_brand_kit_is_rejected(): void
    {
        $user = $this->makeUser();
        $persona = $this->persona($user, true);

        $res = $this->withToken($this->token($user))
            ->patchJson($this->url($persona), ['use_brand_kit' => 'maybe']);

        $res->assertStatus(422);

        // The persona must be left untouched on a validation failure.
        $this->assertTrue((bool) $persona->fresh()->use_brand_kit);
    }

    public function test_non_owner_gets_404_and_persona_is_untouched(): void
    {
        $owner = $this->makeUser();
        $persona = $this->persona($owner, true);

        $intruder = $this->makeUser();

        $before = AiPersonaAgentVersion::where('persona_id', $persona->id)->count();

        $res = $this->withToken($this->token($intruder))
            ->patchJson($this->url($persona), ['use_brand_kit' => false]);

        $res->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');

        // Nothing changed: value preserved and no version row added.
        $this->assertTrue((bool) $persona->fresh()->use_brand_kit);
        $this->assertSame(
            $before,
            AiPersonaAgentVersion::where('persona_id', $persona->id)->count(),
            'A non-owner request must not write a version row.'
        );
    }
}
