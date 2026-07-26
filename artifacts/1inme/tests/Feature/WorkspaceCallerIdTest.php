<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Support\DialerIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Per-workspace Caller ID: the owner can present as themselves (personal,
 * default) or as the workspace's brand. Owner-only on both web and API;
 * free feature (no plan gate). The receiver's saved contact name always
 * wins, so the brand override only applies to unsaved numbers.
 */
class WorkspaceCallerIdTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function makeTeam(User $owner, string $name = 'Acme Co'): Workspace
    {
        return Workspace::create([
            'owner_user_id' => $owner->id,
            'name'          => $name,
            'is_personal'   => false,
        ]);
    }

    public function test_default_caller_id_is_personal(): void
    {
        $owner = User::factory()->create()->fresh();
        $team  = $this->makeTeam($owner);

        $this->assertSame('personal', $team->callerIdConfig()['type']);
        $this->assertSame('personal', $team->resolvedCallerId()['type']);
    }

    public function test_web_owner_can_set_brand_caller_id(): void
    {
        $owner = User::factory()->create()->fresh();
        $team  = $this->makeTeam($owner);

        $resp = $this->actingAs($owner)->put('/user/workspaces/' . $team->id . '/caller-id', [
            'type'            => 'brand',
            'brand_name'      => 'Acme Support',
            'brand_tagline'   => 'We fix things',
            'brand_auto_sync' => 0,
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHas('success');

        $team->refresh();
        $cfg = $team->callerIdConfig();
        $this->assertSame('brand', $cfg['type']);
        $this->assertSame('Acme Support', $cfg['brand']['name']);
        $this->assertSame('We fix things', $cfg['brand']['tagline']);
        $this->assertFalse($cfg['brand']['auto_sync']);

        $resolved = $team->resolvedCallerId();
        $this->assertSame('brand', $resolved['type']);
        $this->assertSame('Acme Support', $resolved['name']);
    }

    public function test_web_non_owner_cannot_set_caller_id(): void
    {
        $owner  = User::factory()->create()->fresh();
        $member = User::factory()->create()->fresh();
        $team   = $this->makeTeam($owner);
        $team->members()->create(['user_id' => $member->id, 'role' => 'admin']);

        $this->actingAs($member)->put('/user/workspaces/' . $team->id . '/caller-id', [
            'type' => 'brand',
        ])->assertStatus(403);

        $this->assertSame('personal', $team->fresh()->callerIdConfig()['type']);
    }

    public function test_web_rejects_invalid_type(): void
    {
        $owner = User::factory()->create()->fresh();
        $team  = $this->makeTeam($owner);

        $this->actingAs($owner)
            ->from('/user/workspaces/' . $team->id . '/settings')
            ->put('/user/workspaces/' . $team->id . '/caller-id', ['type' => 'corporate'])
            ->assertSessionHasErrors('type');
    }

    public function test_brand_with_no_name_falls_back_to_workspace_name(): void
    {
        $owner = User::factory()->create()->fresh();
        $team  = $this->makeTeam($owner, 'Fallback Studio');

        $team->setCallerIdConfig('brand', [
            'name' => null, 'logo_url' => null, 'tagline' => null, 'auto_sync' => false,
        ]);

        $resolved = $team->fresh()->resolvedCallerId();
        $this->assertSame('brand', $resolved['type']);
        $this->assertSame('Fallback Studio', $resolved['name']);
    }

    public function test_api_get_and_put_caller_id_owner_only(): void
    {
        $owner  = User::factory()->create()->fresh();
        $member = User::factory()->create()->fresh();
        $team   = $this->makeTeam($owner);
        $team->members()->create(['user_id' => $member->id, 'role' => 'editor']);

        // Non-owner is refused on both verbs.
        $this->withToken($this->bearer($member))
            ->getJson('/api/v1/workspaces/' . $team->id . '/caller-id')
            ->assertStatus(403);
        $this->withToken($this->bearer($member))
            ->putJson('/api/v1/workspaces/' . $team->id . '/caller-id', ['type' => 'brand'])
            ->assertStatus(403);

        // Owner reads the default…
        $get = $this->withToken($this->bearer($owner))
            ->getJson('/api/v1/workspaces/' . $team->id . '/caller-id');
        $get->assertStatus(200);
        $this->assertSame('personal', $get->json('data.item.config.type'));

        // …and updates it, getting the refreshed config + resolved identity back.
        $put = $this->withToken($this->bearer($owner))
            ->putJson('/api/v1/workspaces/' . $team->id . '/caller-id', [
                'type'            => 'brand',
                'brand_name'      => 'API Brand',
                'brand_auto_sync' => false,
            ]);
        $put->assertStatus(200);
        $this->assertSame('brand', $put->json('data.item.config.type'));
        $this->assertSame('API Brand', $put->json('data.item.resolved.name'));

        $this->assertSame('brand', $team->fresh()->callerIdConfig()['type']);
    }

    public function test_api_put_brand_with_omitted_auto_sync_defaults_to_on(): void
    {
        $owner = User::factory()->create()->fresh();
        $team  = $this->makeTeam($owner);

        $put = $this->withToken($this->bearer($owner))
            ->putJson('/api/v1/workspaces/' . $team->id . '/caller-id', [
                'type'       => 'brand',
                'brand_name' => 'Sync Default Co',
            ]);
        $put->assertStatus(200);

        // Spec: brand auto-syncs from Brand Kit by default; omitting the
        // flag must NOT silently disable it.
        $this->assertTrue($team->fresh()->callerIdConfig()['brand']['auto_sync']);
    }

    public function test_dialer_payload_brand_override_for_unsaved_number(): void
    {
        $creator = User::factory()->create()->fresh();
        $team    = $this->makeTeam($creator, 'Brandline');
        $team->setCallerIdConfig('brand', [
            'name' => 'Brandline HQ', 'logo_url' => null, 'tagline' => 'Always on', 'auto_sync' => false,
        ]);
        $creator->forceFill(['active_workspace_id' => $team->id])->save();

        $callerId = DialerIdentity::callerIdFor($creator->fresh());
        $this->assertSame('brand', $callerId['type']);
        $this->assertSame('Brandline HQ', $callerId['name']);
        $this->assertSame('Always on', $callerId['tagline']);
    }
}
