<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for the profile_theme_color field on the creator profile
 * settings page and its API / web mini-summary endpoint.
 *
 * Web save: validates hex format, strips invalid values.
 * Mini endpoint (web): returns theme_color, 404 on unpublished profiles.
 * API mini endpoint:   mirrors the web mini in the Sanctum context.
 *
 * Uses real personal access tokens (NOT Sanctum::actingAs) per the
 * documented gotcha in .agents/memory/sanctum-api-tests.md.
 */
class CreatorProfileThemeColorTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    // ── Web settings save ──────────────────────────────────────────────

    public function test_valid_hex_color_is_saved(): void
    {
        $user = User::factory()->create([
            'handle'            => 'themetest',
            'profile_published' => true,
        ]);

        $this->actingAs($user, 'web')
            ->post(route('user.creator-profile.update'), [
                '_token'              => csrf_token(),
                'profile_theme_color' => '#e11d48',
                'tagline'             => 'test',
                'sections'            => [],
                'socials'             => [],
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertSame('#e11d48', $user->profile_theme_color);
    }

    public function test_invalid_hex_color_is_rejected(): void
    {
        $user = User::factory()->create([
            'handle'            => 'badcolor',
            'profile_published' => true,
        ]);

        $this->actingAs($user, 'web')
            ->post(route('user.creator-profile.update'), [
                '_token'              => csrf_token(),
                'profile_theme_color' => 'not-a-color',
                'tagline'             => 'test',
                'sections'            => [],
                'socials'             => [],
            ])
            ->assertSessionHasErrors('profile_theme_color');
    }

    public function test_empty_color_clears_the_field(): void
    {
        $user = User::factory()->create([
            'handle'              => 'clearcolor',
            'profile_theme_color' => '#3d6bff',
            'profile_published'   => true,
        ]);

        $this->actingAs($user, 'web')
            ->post(route('user.creator-profile.update'), [
                '_token'              => csrf_token(),
                'profile_theme_color' => '',
                'tagline'             => '',
                'sections'            => [],
                'socials'             => [],
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertNull($user->profile_theme_color);
    }

    // ── Web mini summary endpoint ──────────────────────────────────────

    public function test_mini_endpoint_returns_theme_color(): void
    {
        $user = User::factory()->create([
            'handle'              => 'minitest',
            'profile_published'   => true,
            'profile_theme_color' => '#7c3aed',
            'tagline'             => 'Mini popover test',
        ]);

        $this->getJson('/@minitest/mini')
            ->assertOk()
            ->assertJson([
                'data' => [
                    'handle'      => 'minitest',
                    'theme_color' => '#7c3aed',
                ],
            ]);
    }

    public function test_mini_endpoint_returns_null_theme_color_when_not_set(): void
    {
        $user = User::factory()->create([
            'handle'              => 'nominitheme',
            'profile_published'   => true,
            'profile_theme_color' => null,
        ]);

        $this->getJson('/@nominitheme/mini')
            ->assertOk()
            ->assertJson([
                'data' => [
                    'handle'      => 'nominitheme',
                    'theme_color' => null,
                ],
            ]);
    }

    public function test_mini_endpoint_returns_404_for_unpublished_profile(): void
    {
        User::factory()->create([
            'handle'            => 'unpubmini',
            'profile_published' => false,
        ]);

        $this->getJson('/@unpubmini/mini')->assertStatus(404);
    }

    public function test_mini_endpoint_returns_404_for_unknown_handle(): void
    {
        $this->getJson('/@doesnotexist99/mini')->assertStatus(404);
    }

    public function test_mini_endpoint_returns_expected_shape(): void
    {
        $user = User::factory()->create([
            'handle'            => 'shapetest',
            'profile_published' => true,
        ]);

        $this->getJson('/@shapetest/mini')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'handle',
                    'name',
                    'avatar',
                    'tagline',
                    'followers_count',
                    'is_verified',
                    'theme_color',
                    'profile_url',
                    'profile_published',
                ],
            ]);
    }

    // ── API mini endpoint ──────────────────────────────────────────────

    public function test_api_mini_endpoint_returns_theme_color(): void
    {
        $creator = User::factory()->create([
            'handle'              => 'apimini',
            'profile_published'   => true,
            'profile_theme_color' => '#e11d48',
        ]);

        $viewer = User::factory()->create();

        $this->withToken($this->token($viewer))
            ->getJson('/api/v1/creator-profile/apimini/mini')
            ->assertOk()
            ->assertJson([
                'data' => [
                    'handle'      => 'apimini',
                    'theme_color' => '#e11d48',
                ],
            ]);
    }

    public function test_api_mini_endpoint_is_publicly_accessible(): void
    {
        User::factory()->create([
            'handle'            => 'apiminiopen',
            'profile_published' => true,
        ]);

        $this->getJson('/api/v1/creator-profile/apiminiopen/mini')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['handle', 'name', 'theme_color', 'profile_url'],
            ]);
    }

    public function test_api_mini_endpoint_404_for_unpublished(): void
    {
        User::factory()->create([
            'handle'            => 'apiminipriv',
            'profile_published' => false,
        ]);

        $this->getJson('/api/v1/creator-profile/apiminipriv/mini')
            ->assertStatus(404);
    }

    // ── Full API show includes theme_color ─────────────────────────────

    public function test_api_show_includes_theme_color_in_profile(): void
    {
        $creator = User::factory()->create([
            'handle'              => 'showtheme',
            'profile_published'   => true,
            'profile_theme_color' => '#0ea5e9',
        ]);

        $viewer = User::factory()->create();

        $this->withToken($this->token($viewer))
            ->getJson('/api/v1/creator-profile/showtheme')
            ->assertOk()
            ->assertJson([
                'data' => [
                    'profile' => [
                        'theme_color' => '#0ea5e9',
                    ],
                ],
            ]);
    }
}
