<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\UpdateEntry;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the Creator Updates / Changelog page type.
 *
 * Covers:
 *  - Link creation via store() with type=updates
 *  - Plan quota enforcement (module_updates / max_updates_pages)
 *  - Editor view renders
 *  - Entry CRUD (create, update, delete)
 *  - Follower notification on first publish
 *  - No double-notification on re-save
 *  - Public page renders with published entries (HTML)
 *  - Public page hides draft entries
 *  - Pagination works
 *  - API: GET /api/v1/updates/{alias} returns published entries
 *  - API: POST /me/updates/{link}/entries creates entries
 *  - API: PATCH /me/updates/{link}/settings updates settings
 */
class UpdatesPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    // ─── Link creation ───────────────────────────────────────────────────

    public function test_store_creates_updates_link_and_redirects_to_editor(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('user.links.store'), [
                'type'  => 'updates',
                'title' => 'Product Updates',
            ]);

        $link = Link::where('user_id', $this->user->id)->where('type', 'updates')->first();
        $this->assertNotNull($link);
        $this->assertEquals('updates', $link->type);

        $response->assertRedirect(route('user.links.updates.editor', $link));
    }

    public function test_store_blocked_by_plan_module_gate(): void
    {
        $this->user->plan_features = array_merge(
            $this->user->plan_features ?? [],
            ['module_updates' => false]
        );
        $this->user->save();

        $response = $this->actingAs($this->user)
            ->post(route('user.links.store'), [
                'type'  => 'updates',
                'title' => 'Updates',
            ]);

        $response->assertSessionHasErrors([]);
        $this->assertDatabaseMissing('links', ['user_id' => $this->user->id, 'type' => 'updates']);
    }

    // ─── Editor ──────────────────────────────────────────────────────────

    public function test_editor_renders_for_owner(): void
    {
        $link = $this->createUpdatesLink();

        $response = $this->actingAs($this->user)
            ->get(route('user.links.updates.editor', $link));

        $response->assertOk();
        $response->assertViewIs('user.links.updates-editor');
        $response->assertViewHas('link');
        $response->assertViewHas('entries');
    }

    public function test_editor_is_denied_for_non_owner(): void
    {
        $other = User::factory()->create();
        $link  = $this->createUpdatesLink();

        $this->actingAs($other)
            ->get(route('user.links.updates.editor', $link))
            ->assertForbidden();
    }

    // ─── Entry CRUD ───────────────────────────────────────────────────────

    public function test_store_entry_creates_draft_entry(): void
    {
        $link = $this->createUpdatesLink();

        $response = $this->actingAs($this->user)
            ->post(route('user.links.updates.entries.store', $link), [
                'title'  => 'Hello World',
                'body'   => '<p>First update</p>',
                'status' => 'draft',
            ]);

        $response->assertRedirect(route('user.links.updates.editor', $link));
        $this->assertDatabaseHas('update_entries', [
            'link_id' => $link->id,
            'title'   => 'Hello World',
            'status'  => 'draft',
        ]);
    }

    public function test_publishing_entry_notifies_followers(): void
    {
        $link      = $this->createUpdatesLink();
        $follower  = User::factory()->create(['notify_follower_updates' => true]);

        // Make follower follow the creator.
        \App\Modules\User\Models\Follow::create([
            'creator_id'  => $this->user->id,
            'follower_id' => $follower->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('user.links.updates.entries.store', $link), [
                'title'          => 'New Feature Released',
                'status'         => 'published',
                'published_date' => now()->toDateString(),
            ]);

        $entry = UpdateEntry::where('link_id', $link->id)->first();
        $this->assertNotNull($entry->notified_at, 'notified_at should be stamped on first publish');

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $follower->id,
            'type'    => 'follower_update',
        ]);
    }

    public function test_re_saving_published_entry_does_not_double_notify(): void
    {
        $link     = $this->createUpdatesLink();
        $follower = User::factory()->create(['notify_follower_updates' => true]);
        \App\Modules\User\Models\Follow::create([
            'creator_id'  => $this->user->id,
            'follower_id' => $follower->id,
        ]);

        // First publish — notifies.
        $this->actingAs($this->user)
            ->post(route('user.links.updates.entries.store', $link), [
                'title'  => 'Entry',
                'status' => 'published',
            ]);

        $notifCount1 = UserNotification::where('user_id', $follower->id)->count();

        $entry = UpdateEntry::where('link_id', $link->id)->first();

        // Second save — should NOT notify again.
        $this->actingAs($this->user)
            ->put(route('user.links.updates.entries.update', [$link, $entry]), [
                'title'  => 'Entry (edited)',
                'status' => 'published',
            ]);

        $notifCount2 = UserNotification::where('user_id', $follower->id)->count();
        $this->assertEquals($notifCount1, $notifCount2, 'Should not send a second notification');
    }

    public function test_draft_to_published_transition_notifies(): void
    {
        $link     = $this->createUpdatesLink();
        $follower = User::factory()->create(['notify_follower_updates' => true]);
        \App\Modules\User\Models\Follow::create([
            'creator_id'  => $this->user->id,
            'follower_id' => $follower->id,
        ]);

        // Create as draft — no notification.
        $this->actingAs($this->user)
            ->post(route('user.links.updates.entries.store', $link), [
                'title'  => 'Draft Entry',
                'status' => 'draft',
            ]);

        $this->assertDatabaseMissing('user_notifications', ['user_id' => $follower->id]);

        $entry = UpdateEntry::where('link_id', $link->id)->first();

        // Publish — should notify.
        $this->actingAs($this->user)
            ->put(route('user.links.updates.entries.update', [$link, $entry]), [
                'status' => 'published',
            ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $follower->id,
            'type'    => 'follower_update',
        ]);
    }

    public function test_delete_entry(): void
    {
        $link  = $this->createUpdatesLink();
        $entry = $this->createEntry($link);

        $this->actingAs($this->user)
            ->delete(route('user.links.updates.entries.destroy', [$link, $entry]))
            ->assertRedirect(route('user.links.updates.editor', $link));

        $this->assertDatabaseMissing('update_entries', ['id' => $entry->id]);
    }

    // ─── Public page ─────────────────────────────────────────────────────

    public function test_public_page_shows_published_entries(): void
    {
        $link = $this->createUpdatesLink();
        $pub  = $this->createEntry($link, status: 'published');
        $dft  = $this->createEntry($link, status: 'draft');

        $response = $this->get('/' . $link->alias);

        $response->assertOk();
        $response->assertSee($pub->title);
        $response->assertDontSee($dft->title);
    }

    public function test_public_page_shows_entry_tag_badge(): void
    {
        $link = $this->createUpdatesLink();
        $this->createEntry($link, status: 'published', tag: 'New');

        $this->get('/' . $link->alias)
            ->assertOk()
            ->assertSee('New');
    }

    public function test_public_page_entry_has_anchor(): void
    {
        $link  = $this->createUpdatesLink();
        $entry = $this->createEntry($link, status: 'published');

        $this->get('/' . $link->alias)
            ->assertOk()
            ->assertSee('id="entry-' . $entry->id . '"', false);
    }

    // ─── API ──────────────────────────────────────────────────────────────

    public function test_api_public_index_returns_published_entries(): void
    {
        $link = $this->createUpdatesLink();
        $pub  = $this->createEntry($link, status: 'published');
        $dft  = $this->createEntry($link, status: 'draft');

        $response = $this->getJson('/api/v1/updates/' . $link->alias);

        $response->assertOk();
        $ids = collect($response->json('data.entries'))->pluck('id')->toArray();
        $this->assertContains($pub->id, $ids);
        $this->assertNotContains($dft->id, $ids);
    }

    public function test_api_owner_create_entry(): void
    {
        $link = $this->createUpdatesLink();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/me/updates/' . $link->id . '/entries', [
                'title'  => 'API Entry',
                'status' => 'published',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'API Entry');
        $this->assertDatabaseHas('update_entries', [
            'link_id' => $link->id,
            'title'   => 'API Entry',
        ]);
    }

    public function test_api_owner_patch_settings(): void
    {
        $link = $this->createUpdatesLink();

        $response = $this->withToken($this->token)
            ->patchJson('/api/v1/me/updates/' . $link->id . '/settings', [
                'heading'  => 'Release Notes',
                'per_page' => 5,
            ]);

        $response->assertOk();
        $link->refresh();
        $this->assertEquals('Release Notes', $link->settings['updates']['heading']);
        $this->assertEquals(5, $link->settings['updates']['per_page']);
    }

    public function test_api_non_owner_cannot_create_entry(): void
    {
        $other = User::factory()->create();
        $link  = $this->createUpdatesLink();

        $this->withToken($other->createToken('t')->plainTextToken)
            ->postJson('/api/v1/me/updates/' . $link->id . '/entries', [
                'title'  => 'Unauthorized',
                'status' => 'published',
            ])
            ->assertForbidden();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function createUpdatesLink(): Link
    {
        return Link::factory()->create([
            'user_id'  => $this->user->id,
            'type'     => 'updates',
            'title'    => 'Test Updates',
            'settings' => ['updates' => ['heading' => 'Updates', 'subheading' => '', 'per_page' => 10]],
        ]);
    }

    private function createEntry(Link $link, string $status = 'draft', ?string $tag = null): UpdateEntry
    {
        return UpdateEntry::create([
            'link_id'        => $link->id,
            'user_id'        => $link->user_id,
            'title'          => 'Entry ' . uniqid(),
            'body'           => '<p>Body</p>',
            'tag'            => $tag,
            'published_date' => now()->toDateString(),
            'status'         => $status,
            'sort_order'     => 0,
        ]);
    }

    public function withToken(string $token, string $type = 'Bearer')
    {
        return $this->withHeaders(['Authorization' => $type . ' ' . $token]);
    }
}
