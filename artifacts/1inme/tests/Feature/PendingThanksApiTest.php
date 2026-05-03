<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\WorkspacePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * End-to-end coverage for the queued-thank-yous sync endpoints used by
 * the browser extension's "Pending thanks" panel:
 *
 *   GET  /api/v1/me/pending-thanks
 *   PUT  /api/v1/me/pending-thanks
 *
 * The queue lives under `workspaces.settings.pending_thanks` and is
 * reconciled via a last-write-wins ms timestamp. The server is the
 * source of truth for size limits and TTL pruning so a misbehaving
 * extension cannot bloat the workspace settings blob, and items past
 * the 30-day TTL are stripped on both write and read.
 */
class PendingThanksApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $user = User::create([
            'name'     => 'PT ' . Str::random(4),
            'email'    => 'pt-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
        $user->ensureDefaultWorkspace();
        return $user->fresh();
    }

    private function thankItem(array $overrides = []): array
    {
        return array_merge([
            'id'         => 'thank-' . Str::random(6),
            'templateId' => 'tmpl-email',
            'channel'    => 'email',
            'subject'    => 'Thanks!',
            'body'       => 'Just spotted your link, thanks!',
            'recipient'  => 'fan@example.com',
            'pageUrl'    => 'https://example.com/post',
            'matchedUrl' => 'https://1inme.com/x',
            'anchor'     => 'my work',
            'createdAt'  => (int) (now()->getPreciseTimestamp(3)) - 1_000,
        ], $overrides);
    }

    public function test_get_returns_empty_payload_for_a_fresh_workspace(): void
    {
        $user = $this->makeUser();
        $ws   = $user->ownedWorkspaces()->first();

        Sanctum::actingAs($user, ['*']);
        $resp = $this->getJson('/api/v1/me/pending-thanks?workspace_id=' . $ws->id);

        $resp->assertOk();
        $resp->assertJsonPath('data.workspace_id', $ws->id);
        $resp->assertJsonPath('data.items', []);
        $resp->assertJsonPath('data.updated_at_ms', null);
        $resp->assertJsonPath('data.max', 50);
    }

    public function test_put_persists_items_and_get_returns_them(): void
    {
        $user = $this->makeUser();
        $ws   = $user->ownedWorkspaces()->first();

        Sanctum::actingAs($user, ['*']);
        $items = [$this->thankItem(['id' => 'a']), $this->thankItem(['id' => 'b'])];
        $this->putJson('/api/v1/me/pending-thanks?workspace_id=' . $ws->id, [
            'items'         => $items,
            'updated_at_ms' => (int) (now()->getPreciseTimestamp(3)),
        ])->assertOk()
          ->assertJsonPath('data.items.0.id', 'a')
          ->assertJsonPath('data.items.1.id', 'b');

        $resp = $this->getJson('/api/v1/me/pending-thanks?workspace_id=' . $ws->id);
        $resp->assertOk();
        $this->assertSame(['a', 'b'], array_column($resp->json('data.items'), 'id'));
        $this->assertNotNull($resp->json('data.updated_at_ms'));
    }

    public function test_put_validates_required_fields(): void
    {
        $user = $this->makeUser();
        $ws   = $user->ownedWorkspaces()->first();

        Sanctum::actingAs($user, ['*']);

        // The /api/* exception handler wraps validation errors under
        // `error.details.<field>` instead of Laravel's default
        // top-level `errors` key, so we assert that shape directly.

        // Missing `items` entirely → 422.
        $resp = $this->putJson('/api/v1/me/pending-thanks?workspace_id=' . $ws->id, []);
        $resp->assertStatus(422);
        $this->assertArrayHasKey('items', (array) $resp->json('error.details'));

        // Item missing required `body`.
        $bad = $this->thankItem();
        unset($bad['body']);
        $resp = $this->putJson('/api/v1/me/pending-thanks?workspace_id=' . $ws->id, [
            'items' => [$bad],
        ]);
        $resp->assertStatus(422);
        $this->assertArrayHasKey('items.0.body', (array) $resp->json('error.details'));

        // Unknown channel → 422.
        $resp = $this->putJson('/api/v1/me/pending-thanks?workspace_id=' . $ws->id, [
            'items' => [$this->thankItem(['channel' => 'carrier-pigeon'])],
        ]);
        $resp->assertStatus(422);
        $this->assertArrayHasKey('items.0.channel', (array) $resp->json('error.details'));
    }

    public function test_put_dedupes_items_by_id_keeping_first_occurrence(): void
    {
        $user = $this->makeUser();
        $ws   = $user->ownedWorkspaces()->first();

        Sanctum::actingAs($user, ['*']);

        $items = [
            $this->thankItem(['id' => 'dup', 'body' => 'first wins']),
            $this->thankItem(['id' => 'dup', 'body' => 'second loses']),
            $this->thankItem(['id' => 'unique']),
        ];
        $resp = $this->putJson('/api/v1/me/pending-thanks?workspace_id=' . $ws->id, [
            'items' => $items,
        ])->assertOk();

        $stored = $resp->json('data.items');
        $this->assertSame(['dup', 'unique'], array_column($stored, 'id'));
        $this->assertSame('first wins', $stored[0]['body']);
    }

    public function test_put_caps_oversized_payload_keeping_the_newest_items(): void
    {
        // Oversized payloads are capped (drop oldest first) so a
        // client briefly out of sync with the 50-item limit converges
        // instead of hard-failing. The newest 50 items by createdAt
        // must survive the truncation.
        $user = $this->makeUser();
        $ws   = $user->ownedWorkspaces()->first();

        Sanctum::actingAs($user, ['*']);

        $base = (int) (now()->getPreciseTimestamp(3)) - 60_000;
        $items = [];
        for ($i = 0; $i < 60; $i++) {
            // createdAt strictly increases so item N is always newer
            // than item N-1 → newest 50 are ids cap-10..cap-59.
            $items[] = $this->thankItem([
                'id'        => 'cap-' . $i,
                'createdAt' => $base + $i,
            ]);
        }
        $resp = $this->putJson('/api/v1/me/pending-thanks?workspace_id=' . $ws->id, [
            'items' => $items,
        ]);
        $resp->assertOk();

        $stored = $resp->json('data.items');
        $this->assertCount(50, $stored);
        $ids = array_column($stored, 'id');
        $this->assertSame('cap-10', $ids[0]);
        $this->assertSame('cap-59', $ids[49]);
    }

    public function test_put_rejects_payloads_an_order_of_magnitude_over_the_cap(): void
    {
        // We still reject obviously abusive payloads (10x the cap or
        // more) before the dedupe/TTL/cap loop runs.
        $user = $this->makeUser();
        $ws   = $user->ownedWorkspaces()->first();

        Sanctum::actingAs($user, ['*']);

        $items = [];
        for ($i = 0; $i < 501; $i++) {
            $items[] = $this->thankItem(['id' => 'flood-' . $i]);
        }
        $resp = $this->putJson('/api/v1/me/pending-thanks?workspace_id=' . $ws->id, [
            'items' => $items,
        ]);
        $resp->assertStatus(422);
        $this->assertArrayHasKey('items', (array) $resp->json('error.details'));
    }

    public function test_put_drops_items_past_the_ttl_on_save(): void
    {
        $user = $this->makeUser();
        $ws   = $user->ownedWorkspaces()->first();

        Sanctum::actingAs($user, ['*']);
        $nowMs = (int) (now()->getPreciseTimestamp(3));
        $thirtyDaysMs = 30 * 24 * 60 * 60 * 1000;

        $resp = $this->putJson('/api/v1/me/pending-thanks?workspace_id=' . $ws->id, [
            'items' => [
                $this->thankItem(['id' => 'fresh', 'createdAt' => $nowMs - 1_000]),
                $this->thankItem(['id' => 'stale', 'createdAt' => $nowMs - $thirtyDaysMs - 5_000]),
            ],
        ])->assertOk();

        $this->assertSame(['fresh'], array_column($resp->json('data.items'), 'id'));
    }

    public function test_get_filters_out_items_past_the_ttl_even_if_stored(): void
    {
        // Simulate a queue saved before today that has aged past the
        // TTL: the GET path must hide it so a long-stale item never
        // resurrects on a new device.
        $user = $this->makeUser();
        $ws   = $user->ownedWorkspaces()->first();

        $nowMs = (int) (now()->getPreciseTimestamp(3));
        $thirtyDaysMs = 30 * 24 * 60 * 60 * 1000;

        $ws->settings = array_merge((array) $ws->settings, [
            'pending_thanks' => [
                'items' => [
                    $this->thankItem(['id' => 'fresh', 'createdAt' => $nowMs - 1_000]),
                    $this->thankItem(['id' => 'stale', 'createdAt' => $nowMs - $thirtyDaysMs - 5_000]),
                ],
                'updated_at_ms' => $nowMs - 5_000,
            ],
        ]);
        $ws->save();

        Sanctum::actingAs($user, ['*']);
        $resp = $this->getJson('/api/v1/me/pending-thanks?workspace_id=' . $ws->id);

        $resp->assertOk();
        $this->assertSame(['fresh'], array_column($resp->json('data.items'), 'id'));
    }

    public function test_cross_workspace_access_is_denied(): void
    {
        // userA owns wsA, userB owns wsB. userA must NOT be able to
        // read or write wsB's queue by passing ?workspace_id=wsB.
        $userA = $this->makeUser();
        $userB = $this->makeUser();
        $wsB   = $userB->ownedWorkspaces()->first();

        Sanctum::actingAs($userA, ['*']);

        $this->getJson('/api/v1/me/pending-thanks?workspace_id=' . $wsB->id)
            ->assertForbidden();

        $this->putJson('/api/v1/me/pending-thanks?workspace_id=' . $wsB->id, [
            'items' => [$this->thankItem()],
        ])->assertForbidden();

        // And wsB's queue must remain untouched.
        $wsB->refresh();
        $this->assertSame([], (array) data_get($wsB->settings, 'pending_thanks.items', []));
    }

    public function test_workspace_member_can_read_and_write_the_queue(): void
    {
        // A member of the workspace (not the owner) should be able to
        // read and write the queue — it's a workspace-scoped resource,
        // not a per-user one.
        $owner  = $this->makeUser();
        $ws     = $owner->ownedWorkspaces()->first();
        $member = $this->makeUser();
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id'      => $member->id,
            'role'         => 'editor',
            'permissions'  => WorkspacePermissions::roleActions()['editor'] ?? [],
        ]);

        Sanctum::actingAs($member, ['*']);
        $this->putJson('/api/v1/me/pending-thanks?workspace_id=' . $ws->id, [
            'items' => [$this->thankItem(['id' => 'team-1'])],
        ])->assertOk()
          ->assertJsonPath('data.items.0.id', 'team-1');

        $this->getJson('/api/v1/me/pending-thanks?workspace_id=' . $ws->id)
            ->assertOk()
            ->assertJsonPath('data.items.0.id', 'team-1');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/me/pending-thanks')->assertStatus(401);
        $this->putJson('/api/v1/me/pending-thanks', ['items' => []])->assertStatus(401);
    }
}
