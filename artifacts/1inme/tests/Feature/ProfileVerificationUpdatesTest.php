<?php

namespace Tests\Feature;

use App\Modules\User\Models\ProfileVerificationRequest;
use App\Modules\User\Models\User;
use App\Modules\User\Models\VerificationTickType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task #5479: users can send follow-up messages/attachments ("updates") to a
 * pending profile verification request, admins see them, and the verification
 * pages render the real @handle (regression for the `@{{ ... }}` Blade
 * escaping bug that printed the literal template string).
 */
class ProfileVerificationUpdatesTest extends TestCase
{
    use RefreshDatabase;

    private function tickType(): VerificationTickType
    {
        return VerificationTickType::publicRequestable()->first()
            ?? VerificationTickType::create(['name' => 'Blue', 'color' => '#3d6bff', 'is_active' => true, 'admin_assigned_only' => false, 'sort_order' => 1]);
    }

    private function makePending(User $user): ProfileVerificationRequest
    {
        return ProfileVerificationRequest::create([
            'user_id'       => $user->id,
            'tick_type_id'  => $this->tickType()->id,
            'official_name' => 'Alex Rivera',
            'purpose'       => 'Testing',
            'status'        => 'pending',
            'kind'          => 'new',
        ]);
    }

    public function test_user_can_add_a_message_update_to_pending_request(): void
    {
        $user = User::factory()->create(['profile_verification_status' => 'pending']);
        $req  = $this->makePending($user);

        $this->actingAs($user)
            ->post('/user/settings/profile-verification/updates', [
                'message' => 'Here is more context about my identity.',
            ])
            ->assertRedirect(route('user.profile-verification.index'))
            ->assertSessionHas('success');

        $req->refresh();
        $this->assertCount(1, $req->updates);
        $this->assertSame('Here is more context about my identity.', $req->updates[0]['message']);
        $this->assertSame([], $req->updates[0]['files']);
        $this->assertNotEmpty($req->updates[0]['created_at']);
    }

    public function test_update_requires_message_or_attachment(): void
    {
        $user = User::factory()->create(['profile_verification_status' => 'pending']);
        $req  = $this->makePending($user);

        $this->actingAs($user)
            ->from(route('user.profile-verification.index'))
            ->post('/user/settings/profile-verification/updates', ['message' => ''])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertEmpty($req->refresh()->updates ?? []);
    }

    public function test_update_rejected_without_pending_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/user/settings/profile-verification/updates', ['message' => 'hello'])
            ->assertRedirect(route('user.profile-verification.index'))
            ->assertSessionHas('error');
    }

    public function test_update_cap_enforced(): void
    {
        $user = User::factory()->create(['profile_verification_status' => 'pending']);
        $req  = $this->makePending($user);
        $req->update(['updates' => array_fill(0, ProfileVerificationRequest::MAX_UPDATES, [
            'message' => 'x', 'files' => [], 'created_at' => now()->toIso8601String(),
        ])]);

        $this->actingAs($user)
            ->post('/user/settings/profile-verification/updates', ['message' => 'one more'])
            ->assertSessionHas('error');

        $this->assertCount(ProfileVerificationRequest::MAX_UPDATES, $req->refresh()->updates);
    }

    public function test_apply_form_optional_message_seeds_first_update(): void
    {
        $user = User::factory()->create();
        $tick = $this->tickType();

        $this->actingAs($user)
            ->post('/user/settings/profile-verification/request', [
                'tick_type_id'  => $tick->id,
                'official_name' => 'Alex Rivera',
                'purpose'       => 'I am a public creator.',
                'message'       => 'Please see my press coverage.',
            ])
            ->assertRedirect(route('user.profile-verification.index'));

        $req = ProfileVerificationRequest::where('user_id', $user->id)->firstOrFail();
        $this->assertCount(1, $req->updates);
        $this->assertSame('Please see my press coverage.', $req->updates[0]['message']);
    }

    public function test_api_add_update_and_show_includes_updates(): void
    {
        $user = User::factory()->create(['profile_verification_status' => 'pending']);
        $req  = $this->makePending($user);
        $token = $user->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/profile-verification/updates', ['message' => 'API follow-up'])
            ->assertStatus(201)
            ->assertJsonPath('data.update.message', 'API follow-up');

        $this->flushHeaders();

        $this->assertSame('API follow-up', $req->refresh()->updates[0]['message']);
    }

    public function test_api_update_without_pending_request_fails(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/profile-verification/updates', ['message' => 'hello'])
            ->assertStatus(400);

        $this->flushHeaders();
    }

    public function test_verification_page_renders_real_handle_not_template_literal(): void
    {
        $user = User::factory()->create();
        $user->update(['handle' => 'realhandle' . $user->id]);

        $res = $this->actingAs($user)->get('/user/settings/profile-verification');
        $res->assertOk();
        $res->assertSee('@realhandle' . $user->id);
        $res->assertDontSee('$user->handle', false);
    }

    public function test_admin_review_shows_user_updates(): void
    {
        $user = User::factory()->create(['profile_verification_status' => 'pending']);
        $req  = $this->makePending($user);
        $req->appendUpdate('Extra proof attached.', []);

        $reviewer = User::factory()->create()->fresh();
        $roleId = \Illuminate\Support\Facades\DB::table('roles')->where('slug', 'user-admin')->where('guard', 'web')->value('id');
        $this->assertNotNull($roleId);
        $reviewer->roles()->syncWithoutDetaching([(int) $roleId]);
        $reviewer->flushPermissionCache();

        $this->actingAs($reviewer->fresh())
            ->get("/user/profile-verification-admin/{$req->id}")
            ->assertOk()
            ->assertSee('Updates from the User')
            ->assertSee('Extra proof attached.');
    }
}
