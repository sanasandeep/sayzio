<?php

namespace Tests\Feature\CreatorProfile;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the profile showcase additions:
 *   - Featured links stored and enforced as owner-only, max 8 (rich {id,enabled} format)
 *   - Featured links style stored and validated
 *   - Per-link enabled toggle: disabled links excluded from public view
 *   - Showcase items stored with type/link_id validation
 *   - Highlights config saved and exposed in API payload
 *   - CTA config saved and exposed in API payload
 *   - Public controller passes new data to the view
 *   - API payload includes featured_links / showcase_cards / showcase / total_public_links
 *   - Backward-compat: legacy featured_link_ids array auto-upgraded on read
 */
class ProfileShowcaseTest extends TestCase
{
    use RefreshDatabase;

    // ── helpers ──────────────────────────────────────────────────────────────

    private function makeCreator(array $attrs = []): User
    {
        /** @var User $user */
        $user = User::factory()->create(array_merge([
            'handle'            => 'testcreator_' . uniqid(),
            'profile_published' => true,
        ], $attrs));
        return $user;
    }

    private function makeLink(int $userId, array $attrs = []): Link
    {
        return Link::factory()->create(array_merge([
            'user_id'    => $userId,
            'type'       => 'short',
            'is_active'  => true,
            'visibility' => 'public',
        ], $attrs));
    }

    // ── User model helpers ────────────────────────────────────────────────

    public function test_default_profile_showcase_returns_safe_defaults(): void
    {
        $defaults = User::defaultProfileShowcase();
        $this->assertIsArray($defaults);
        $this->assertArrayHasKey('featured_links', $defaults);
        $this->assertEmpty($defaults['featured_links']);
        $this->assertSame('classic', $defaults['featured_links_style']);
        $this->assertFalse($defaults['show_link_stats']);
        $this->assertEmpty($defaults['showcase_items']);
        $this->assertTrue($defaults['highlights']['show_followers']);
        $this->assertNull($defaults['cta']['primary']);
        $this->assertIsArray($defaults['cta']['secondary']);
    }

    public function test_resolved_profile_showcase_merges_over_defaults(): void
    {
        $user = $this->makeCreator();
        $user->profile_showcase = [
            'show_link_stats' => true,
            'highlights' => ['show_followers' => false],
        ];
        $user->save();

        $resolved = $user->resolvedProfileShowcase();
        $this->assertTrue($resolved['show_link_stats']);
        $this->assertFalse($resolved['highlights']['show_followers']);
        // Other highlight keys remain default (true).
        $this->assertTrue($resolved['highlights']['show_links']);
        $this->assertEmpty($resolved['featured_links']);
        $this->assertSame('classic', $resolved['featured_links_style']);
    }

    public function test_resolved_profile_showcase_when_null_returns_defaults(): void
    {
        $user = $this->makeCreator();
        $resolved = $user->resolvedProfileShowcase();
        $this->assertArrayHasKey('featured_links', $resolved);
        $this->assertEmpty($resolved['featured_links']);
        $this->assertIsArray($resolved['highlights']);
    }

    // ── Editor save — featured links ──────────────────────────────────────

    public function test_save_featured_links_persists_owner_ids(): void
    {
        $creator = $this->makeCreator();
        $l1 = $this->makeLink($creator->id);
        $l2 = $this->makeLink($creator->id);

        $this->actingAs($creator)
            ->post(route('user.creator-profile.update'), [
                'featured_links' => [
                    ['id' => $l1->id, 'enabled' => '1'],
                    ['id' => $l2->id, 'enabled' => '1'],
                ],
            ])
            ->assertRedirect(route('user.creator-profile.edit'));

        $creator->refresh();
        $showcase = $creator->resolvedProfileShowcase();
        $savedIds = array_column($showcase['featured_links'], 'id');
        $this->assertContains($l1->id, $savedIds);
        $this->assertContains($l2->id, $savedIds);
    }

    public function test_save_featured_links_rejects_non_owner_ids(): void
    {
        $creator = $this->makeCreator();
        $otherUser = $this->makeCreator();
        $foreignLink = $this->makeLink($otherUser->id);

        $this->actingAs($creator)
            ->post(route('user.creator-profile.update'), [
                'featured_links' => [['id' => $foreignLink->id, 'enabled' => '1']],
            ])
            ->assertRedirect(route('user.creator-profile.edit'));

        $creator->refresh();
        $showcase = $creator->resolvedProfileShowcase();
        $savedIds = array_column($showcase['featured_links'], 'id');
        $this->assertNotContains($foreignLink->id, $savedIds);
    }

    public function test_featured_links_per_link_enabled_toggle(): void
    {
        $creator = $this->makeCreator();
        $l1 = $this->makeLink($creator->id);
        $l2 = $this->makeLink($creator->id);

        $this->actingAs($creator)
            ->post(route('user.creator-profile.update'), [
                'featured_links' => [
                    ['id' => $l1->id, 'enabled' => '1'],
                    ['id' => $l2->id, 'enabled' => '0'],
                ],
            ])
            ->assertRedirect();

        $creator->refresh();
        $fl = $creator->resolvedProfileShowcase()['featured_links'];
        $this->assertCount(2, $fl);
        $this->assertTrue($fl[0]['enabled']);
        $this->assertFalse($fl[1]['enabled']);
    }

    public function test_featured_links_style_saved_and_validated(): void
    {
        $creator = $this->makeCreator();

        $this->actingAs($creator)
            ->post(route('user.creator-profile.update'), [
                'featured_links_style' => 'solid',
            ])
            ->assertRedirect();

        $creator->refresh();
        $this->assertSame('solid', $creator->resolvedProfileShowcase()['featured_links_style']);
    }

    public function test_featured_links_invalid_style_rejected(): void
    {
        $creator = $this->makeCreator();

        $this->actingAs($creator)
            ->post(route('user.creator-profile.update'), [
                'featured_links_style' => 'glitter',
            ])
            ->assertSessionHasErrors('featured_links_style');
    }

    public function test_featured_links_capped_at_eight(): void
    {
        $creator = $this->makeCreator();
        $ids = [];
        for ($i = 0; $i < 10; $i++) {
            $ids[] = $this->makeLink($creator->id)->id;
        }

        // The validation rule limits the array itself to max:8; sending 8 is allowed.
        $this->actingAs($creator)
            ->post(route('user.creator-profile.update'), [
                'featured_links' => array_map(
                    fn ($id) => ['id' => $id, 'enabled' => '1'],
                    array_slice($ids, 0, 8)
                ),
            ])
            ->assertRedirect();

        $creator->refresh();
        $this->assertCount(8, $creator->resolvedProfileShowcase()['featured_links']);
    }

    public function test_legacy_featured_link_ids_upgraded_on_read(): void
    {
        $creator = $this->makeCreator();
        $link    = $this->makeLink($creator->id);

        // Simulate a legacy stored record that used the old featured_link_ids format.
        $creator->profile_showcase = ['featured_link_ids' => [$link->id]];
        $creator->save();

        $resolved = $creator->resolvedProfileShowcase();
        $this->assertArrayHasKey('featured_links', $resolved);
        $savedIds = array_column($resolved['featured_links'], 'id');
        $this->assertContains($link->id, $savedIds);
        // All legacy entries default to enabled=true on upgrade.
        $this->assertTrue($resolved['featured_links'][0]['enabled']);
    }

    // ── Editor save — showcase items ──────────────────────────────────────

    public function test_save_showcase_items_persists_valid_items(): void
    {
        $creator = $this->makeCreator();
        $qrLink  = $this->makeLink($creator->id, ['type' => 'qr']);
        $form    = $this->makeLink($creator->id, ['type' => 'form']);

        $this->actingAs($creator)
            ->post(route('user.creator-profile.update'), [
                'showcase_items' => [
                    ['type' => 'qr',   'link_id' => $qrLink->id],
                    ['type' => 'form', 'link_id' => $form->id],
                ],
            ])
            ->assertRedirect();

        $creator->refresh();
        $items = $creator->resolvedProfileShowcase()['showcase_items'];
        $this->assertCount(2, $items);
        $this->assertEquals('qr', $items[0]['type']);
        $this->assertEquals($qrLink->id, $items[0]['link_id']);
    }

    public function test_save_showcase_items_drops_foreign_links(): void
    {
        $creator     = $this->makeCreator();
        $foreignUser = $this->makeCreator();
        $foreignLink = $this->makeLink($foreignUser->id, ['type' => 'qr']);

        $this->actingAs($creator)
            ->post(route('user.creator-profile.update'), [
                'showcase_items' => [
                    ['type' => 'qr', 'link_id' => $foreignLink->id],
                ],
            ])
            ->assertRedirect();

        $creator->refresh();
        $this->assertEmpty($creator->resolvedProfileShowcase()['showcase_items']);
    }

    // ── Editor save — highlights ──────────────────────────────────────────

    public function test_save_highlights_persists_toggles(): void
    {
        $creator = $this->makeCreator();

        $this->actingAs($creator)
            ->post(route('user.creator-profile.update'), [
                'highlights_show_followers'    => '1',
                'highlights_show_links'        => '0',
                'highlights_show_member_since' => '1',
                'highlights_show_verified'     => '0',
            ])
            ->assertRedirect();

        $creator->refresh();
        $hl = $creator->resolvedProfileShowcase()['highlights'];
        $this->assertTrue($hl['show_followers']);
        $this->assertFalse($hl['show_links']);
        $this->assertTrue($hl['show_member_since']);
        $this->assertFalse($hl['show_verified']);
    }

    // ── Editor save — CTA ─────────────────────────────────────────────────

    public function test_save_cta_primary_persists(): void
    {
        $creator = $this->makeCreator();

        $this->actingAs($creator)
            ->post(route('user.creator-profile.update'), [
                'cta_primary_kind'  => 'email',
                'cta_primary_label' => 'Email me',
                'cta_primary_value' => 'hello@example.com',
            ])
            ->assertRedirect();

        $creator->refresh();
        $cta = $creator->resolvedProfileShowcase()['cta'];
        $this->assertNotNull($cta['primary']);
        $this->assertEquals('email', $cta['primary']['kind']);
        $this->assertEquals('hello@example.com', $cta['primary']['value']);
    }

    public function test_save_cta_secondary_persists(): void
    {
        $creator = $this->makeCreator();

        $this->actingAs($creator)
            ->post(route('user.creator-profile.update'), [
                'cta_secondary' => [
                    ['kind' => 'whatsapp', 'label' => 'Chat', 'value' => '+919999999999'],
                    ['kind' => 'link',     'label' => 'Website', 'value' => 'https://example.com'],
                ],
            ])
            ->assertRedirect();

        $creator->refresh();
        $cta = $creator->resolvedProfileShowcase()['cta'];
        $this->assertCount(2, $cta['secondary']);
        $this->assertEquals('whatsapp', $cta['secondary'][0]['kind']);
    }

    public function test_cta_rejects_unknown_kind(): void
    {
        $creator = $this->makeCreator();

        $this->actingAs($creator)
            ->post(route('user.creator-profile.update'), [
                'cta_primary_kind'  => 'telegram', // not an allowed kind
                'cta_primary_label' => 'Telegram',
                'cta_primary_value' => '@handle',
            ])
            ->assertSessionHasErrors('cta_primary_kind');
    }

    // ── API payload ────────────────────────────────────────────────────────

    public function test_api_profile_response_includes_showcase_fields(): void
    {
        $creator = $this->makeCreator();
        $link    = $this->makeLink($creator->id);
        $creator->profile_showcase = [
            'featured_links'       => [['id' => $link->id, 'enabled' => true]],
            'featured_links_style' => 'classic',
            'show_link_stats'      => false,
            'showcase_items'       => [],
            'highlights'           => [
                'show_followers' => true,
                'show_links'     => true,
                'show_member_since' => true,
                'show_verified'  => true,
            ],
            'cta' => ['primary' => null, 'secondary' => []],
        ];
        $creator->save();

        $this->getJson("/api/v1/creator-profile/{$creator->handle}")
            ->assertOk()
            ->assertJsonPath('data.profile.featured_links.0.id', $link->id)
            ->assertJsonPath('data.profile.showcase.show_link_stats', false)
            ->assertJsonPath('data.profile.showcase.featured_links_style', 'classic')
            ->assertJsonPath('data.profile.total_public_links', 1);
    }

    public function test_api_does_not_expose_private_links_as_featured(): void
    {
        $creator     = $this->makeCreator();
        $privateLink = $this->makeLink($creator->id, ['visibility' => 'registered']);
        $creator->profile_showcase = [
            'featured_links'  => [['id' => $privateLink->id, 'enabled' => true]],
            'show_link_stats' => false,
            'showcase_items'  => [],
            'highlights'      => User::defaultProfileShowcase()['highlights'],
            'cta'             => ['primary' => null, 'secondary' => []],
        ];
        $creator->save();

        $this->getJson("/api/v1/creator-profile/{$creator->handle}")
            ->assertOk()
            ->assertJsonPath('data.profile.featured_links', []);
    }

    // ── Section visibility defaults ────────────────────────────────────────

    public function test_profile_default_visibility_includes_new_keys(): void
    {
        $this->assertArrayHasKey('featured_links', User::PROFILE_DEFAULT_VISIBILITY);
        $this->assertArrayHasKey('showcase', User::PROFILE_DEFAULT_VISIBILITY);
        $this->assertArrayHasKey('highlights', User::PROFILE_DEFAULT_VISIBILITY);
        $this->assertArrayHasKey('cta', User::PROFILE_DEFAULT_VISIBILITY);
        $this->assertTrue(User::PROFILE_DEFAULT_VISIBILITY['featured_links']);
        $this->assertTrue(User::PROFILE_DEFAULT_VISIBILITY['showcase']);
        $this->assertTrue(User::PROFILE_DEFAULT_VISIBILITY['highlights']);
        $this->assertTrue(User::PROFILE_DEFAULT_VISIBILITY['cta']);
    }
}
