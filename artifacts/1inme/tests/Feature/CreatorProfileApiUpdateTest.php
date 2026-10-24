<?php

namespace Tests\Feature;

use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\CreatorProfile;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PATCH /api/v1/me/creator-profile — owner editor parity for the mobile app.
 * Delegates to the web controller's shared saveCoreProfileFields /
 * saveShowcaseFields helpers, so these tests assert the delegation-visible
 * behaviour: present-keys-only core updates, whole-block showcase replace,
 * foreign link IDs silently dropped, publish blocked without a handle.
 */
class CreatorProfileApiUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        $user->refresh();
        // Current schema stores creator fields on the personal workspace profile.
        $profile = CreatorProfile::forWorkspace($user->ensureDefaultWorkspace());
        $profile->forceFill(collect(CreatorProfile::FIELDS)->mapWithKeys(fn ($key) => [$key => $user->getAttribute($key)])->all())->save();

        return $user->createToken('t')->plainTextToken;
    }

    public function test_requires_auth(): void
    {
        $this->patchJson('/api/v1/me/creator-profile', ['tagline' => 'x'])
            ->assertStatus(401);
    }

    public function test_updates_core_fields_without_touching_absent_ones(): void
    {
        $user = User::factory()->create();
        $user->update(['handle' => 'creator'.$user->id, 'bio' => 'Original bio', 'profile_theme_color' => '#112233']);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->patchJson('/api/v1/me/creator-profile', [
                'tagline' => 'Hello from the API',
                'profile_theme_color' => '#aabbcc',
            ])
            ->assertOk()
            ->assertJsonPath('data.profile.tagline', 'Hello from the API')
            ->assertJsonPath('data.profile.profile_theme_color', '#aabbcc');

        $this->flushHeaders();

        $user->refresh();
        $this->assertSame('Hello from the API', $user->tagline);
        $this->assertSame('Original bio', $user->bio, 'absent keys must not be touched');
        // No showcase key in the request → showcase untouched.
        $this->assertNull($user->profile_showcase);
    }

    public function test_publish_without_handle_is_rejected(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['handle' => null])->save();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->patchJson('/api/v1/me/creator-profile', ['profile_published' => 1])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'no_handle');

        $this->flushHeaders();
        $this->assertFalse((bool) $user->refresh()->profile_published);
    }

    public function test_showcase_replace_drops_foreign_link_ids(): void
    {
        $user = User::factory()->create();
        $user->update(['handle' => 'owner'.$user->id]);
        $other = User::factory()->create();

        $mine = Link::create(['user_id' => $user->id,  'type' => 'link', 'alias' => 'mine'.uniqid(), 'url' => 'https://example.com', 'is_active' => true]);
        $his = Link::create(['user_id' => $other->id, 'type' => 'link', 'alias' => 'his'.uniqid(),  'url' => 'https://example.com', 'is_active' => true]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->patchJson('/api/v1/me/creator-profile', [
                'featured_links' => [
                    ['id' => $mine->id, 'enabled' => 1],
                    ['id' => $his->id,  'enabled' => 1],
                ],
                'featured_links_style' => 'pill',
            ])
            ->assertOk();

        $this->flushHeaders();

        $showcase = $user->refresh()->profile_showcase;
        $ids = array_column($showcase['featured_links'] ?? [], 'id');
        $this->assertSame([$mine->id], $ids, 'foreign link IDs must be silently dropped');
        $this->assertSame('pill', $showcase['featured_links_style']);
    }

    public function test_invalid_theme_color_is_a_422(): void
    {
        $user = User::factory()->create();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->patchJson('/api/v1/me/creator-profile', ['profile_theme_color' => 'red'])
            ->assertStatus(422);

        $this->flushHeaders();
    }

    public function test_presentation_patch_preserves_existing_showcase_content(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['handle' => 'presentation'.$user->id, 'profile_showcase' => [
            'featured_links_style' => 'pill',
            'cta' => ['primary' => ['kind' => 'link', 'label' => 'My site', 'value' => 'https://example.com'], 'secondary' => []],
        ]])->save();
        $this->withToken($this->tokenFor($user))->patchJson('/api/v1/me/creator-profile', [
            'presentation' => ['layout' => 'editorial', 'theme' => 'mint', 'mode' => 'dark', 'avatar_size' => 128],
        ])->assertOk()->assertJsonPath('data.profile.presentation.layout', 'editorial')
            ->assertJsonPath('data.profile.presentation.mode', 'dark')
            ->assertJsonPath('data.profile.showcase.featured_links_style', 'pill')
            ->assertJsonPath('data.profile.showcase.cta.primary.label', 'My site');
    }

    public function test_stale_workspace_form_is_rejected_without_writing(): void
    {
        $user = User::factory()->create();
        $this->withToken($this->tokenFor($user))->patchJson('/api/v1/me/creator-profile', [
            'creator_profile_id' => 99999999, 'bio' => 'Wrong workspace',
        ])->assertStatus(409)->assertJsonPath('error.code', 'workspace_changed');
        $this->assertNotSame('Wrong workspace', $user->refresh()->bio);
    }

    public function test_invalid_image_scheme_and_presentation_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->withToken($this->tokenFor($user))->patchJson('/api/v1/me/creator-profile', [
            'creator_avatar_url' => 'javascript:alert(1)',
            'presentation' => ['layout' => 'invented'],
        ])->assertUnprocessable();
    }

    public function test_hidden_posts_are_not_returned_by_the_public_feed(): void
    {
        $user = User::factory()->create()->fresh();
        $user->forceFill(['handle' => 'hidden_'.$user->id, 'profile_published' => true, 'profile_section_visibility' => ['posts' => false]])->save();
        $this->tokenFor($user);
        CreatorPost::create(['user_id' => $user->id, 'body' => 'Do not render hidden feed content', 'published_at' => now(), 'post_type' => 'text', 'visibility' => 'free']);
        $this->getJson('/api/v1/creator-profile/'.$user->handle.'/posts')->assertOk()->assertJsonCount(0, 'data.items')->assertJsonPath('data.meta.total', 0);
    }
}
