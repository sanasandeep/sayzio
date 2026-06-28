<?php

namespace Tests\Feature;

use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the mobile "rename + retire an existing agent" endpoint
 * (Task #2686):
 *
 *   PATCH /api/v1/ai-companions/personas/{persona}
 *
 * The web persona editor only exposes name / enabled-state inside a full
 * form save; mobile sends a focused PATCH of just the field(s) it changed,
 * reusing the same pattern added for the `use_brand_kit` toggle. The list
 * endpoint (GET /api/v1/ai-companions/personas) now also returns disabled
 * agents (with an `is_disabled` flag) so they can be shown and re-enabled.
 */
class MobileAiPersonaUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AiEngineSettings::setEnabled(true);
    }

    private function makeUser(): User
    {
        $user = User::create([
            'name'     => 'AP ' . Str::random(4),
            'email'    => 'ap-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
        $user->ensureDefaultWorkspace();
        return $user->fresh();
    }

    private function asUser(User $user): self
    {
        $this->withToken($user->createToken('mobile-test')->plainTextToken);
        return $this;
    }

    private function persona(User $user, array $overrides = []): AiPersonaAgent
    {
        return AiPersonaAgent::create(array_merge([
            'user_id'           => $user->id,
            'slug'              => 'p-' . Str::random(6),
            'name'              => 'Helper',
            'system_prompt'     => 'You help visitors.',
            'use_brand_kit'     => true,
            'model'             => 'gpt-4o-mini',
            'temperature_x100'  => 50,
            'max_tokens'        => 300,
            'languages'         => [],
            'allowed_actions'   => [],
            'fallback_behavior' => 'clarify',
            'use_default_mind'  => false,
            'is_disabled'       => false,
        ], $overrides));
    }

    public function test_user_can_rename_their_agent(): void
    {
        $user    = $this->makeUser();
        $persona = $this->persona($user);

        $resp = $this->asUser($user)->patchJson(
            "/api/v1/ai-companions/personas/{$persona->id}",
            ['name' => '  Support Bot  '],
        );

        $resp->assertOk();
        $resp->assertJsonPath('data.persona.name', 'Support Bot');
        $resp->assertJsonPath('data.persona.is_disabled', false);
        $this->assertSame('Support Bot', $persona->fresh()->name);
    }

    public function test_user_can_retire_and_re_enable_their_agent(): void
    {
        $user    = $this->makeUser();
        $persona = $this->persona($user);

        $this->asUser($user)
            ->patchJson("/api/v1/ai-companions/personas/{$persona->id}", ['is_disabled' => true])
            ->assertOk()
            ->assertJsonPath('data.persona.is_disabled', true);
        $this->assertTrue($persona->fresh()->is_disabled);

        $this->asUser($user)
            ->patchJson("/api/v1/ai-companions/personas/{$persona->id}", ['is_disabled' => false])
            ->assertOk()
            ->assertJsonPath('data.persona.is_disabled', false);
        $this->assertFalse($persona->fresh()->is_disabled);
    }

    public function test_persona_list_includes_disabled_agents_with_flag(): void
    {
        $user = $this->makeUser();
        $this->persona($user, ['name' => 'Active One', 'is_disabled' => false]);
        $this->persona($user, ['name' => 'Retired One', 'is_disabled' => true]);

        $resp = $this->asUser($user)->getJson('/api/v1/ai-companions/personas');

        $resp->assertOk();
        $items = collect($resp->json('data.items'));
        $this->assertCount(2, $items);
        // Enabled agents sort first so the picker's auto-select lands on a usable one.
        $this->assertSame('Active One', $items->first()['name']);
        $this->assertFalse($items->first()['is_disabled']);
        $retired = $items->firstWhere('name', 'Retired One');
        $this->assertTrue($retired['is_disabled']);
    }

    public function test_empty_patch_is_rejected(): void
    {
        $user    = $this->makeUser();
        $persona = $this->persona($user);

        $this->asUser($user)
            ->patchJson("/api/v1/ai-companions/personas/{$persona->id}", [])
            ->assertStatus(422);
    }

    public function test_user_cannot_update_another_users_agent(): void
    {
        $owner   = $this->makeUser();
        $other   = $this->makeUser();
        $persona = $this->persona($owner);

        $this->asUser($other)
            ->patchJson("/api/v1/ai-companions/personas/{$persona->id}", ['name' => 'Hijacked'])
            ->assertStatus(404);

        $this->assertSame('Helper', $persona->fresh()->name);
    }
}
