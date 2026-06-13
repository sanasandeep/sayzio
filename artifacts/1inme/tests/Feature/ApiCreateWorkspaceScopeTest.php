<?php

namespace Tests\Feature;

use App\Modules\User\Models\Form;
use App\Modules\User\Models\SocialProof;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The sanctum API path does not run SetActiveWorkspace, so models using the
 * BelongsToWorkspace trait used to be created with workspace_id = null —
 * making mobile-created forms / "Buzz" proofs invisible in the
 * workspace-scoped web lists. These endpoints must now stamp the user's
 * active workspace on create so the items show up on both surfaces.
 */
class ApiCreateWorkspaceScopeTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $user = User::create([
            'name'     => 'WS ' . Str::random(4),
            'email'    => 'ws-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
        $user->ensureDefaultWorkspace();
        return $user->fresh();
    }

    public function test_form_created_via_api_is_assigned_the_active_workspace(): void
    {
        $user = $this->makeUser();
        $ws   = $user->ownedWorkspaces()->first();

        $this->withToken($user->createToken('test')->plainTextToken);
        $resp = $this->postJson('/api/v1/forms', ['title' => 'Mobile Form']);
        $resp->assertStatus(201);

        $id = $resp->json('data.form.id');
        $form = Form::withoutGlobalScope('workspace')->find($id);
        $this->assertNotNull($form);
        $this->assertSame($ws->id, $form->workspace_id);
    }

    public function test_api_form_slug_uniqueness_stays_global_across_workspaces(): void
    {
        // Regression: deriving the workspace must NOT bind current_workspace,
        // otherwise Form::uniqueSlug() becomes workspace-scoped and would hand
        // out a slug that already exists in another workspace — breaking the
        // public /f/{slug} route which resolves slugs globally.
        $other = $this->makeUser();
        $otherWs = $other->ownedWorkspaces()->first();
        // user_id / workspace_id aren't fillable, so set them directly.
        $existing = $other->forms()->make([
            'slug'          => 'shared-title',
            'title'         => 'Shared Title',
            'fields'        => [],
            'design'        => Form::defaultDesign(),
            'settings'      => Form::defaultSettings(),
            'notifications' => Form::defaultNotifications(),
            'is_active'     => true,
        ]);
        $existing->workspace_id = $otherWs->id;
        $existing->save();
        $this->assertSame('shared-title', $existing->slug);

        $user = $this->makeUser();
        $this->withToken($user->createToken('test')->plainTextToken);
        $resp = $this->postJson('/api/v1/forms', ['title' => 'Shared Title']);
        $resp->assertStatus(201);

        $newSlug = $resp->json('data.form.slug');
        $this->assertNotSame('shared-title', $newSlug, 'slug must not collide globally');
        $this->assertStringStartsWith('shared-title-', $newSlug);
    }

    public function test_social_proof_created_via_api_is_assigned_the_active_workspace(): void
    {
        $user = $this->makeUser();
        $ws   = $user->ownedWorkspaces()->first();

        $type = array_key_first(SocialProof::TYPES);

        $this->withToken($user->createToken('test')->plainTextToken);
        $resp = $this->postJson('/api/v1/social/proofs', [
            'name' => 'Mobile Buzz',
            'type' => $type,
        ]);
        $resp->assertStatus(201);

        $id = $resp->json('data.proof.id');
        $proof = SocialProof::withoutGlobalScope('workspace')->find($id);
        $this->assertNotNull($proof);
        $this->assertSame($ws->id, $proof->workspace_id);
    }
}
