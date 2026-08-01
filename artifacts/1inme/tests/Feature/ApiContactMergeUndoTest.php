<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactMergeAudit;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\User;
use App\Modules\User\Services\Contacts\ContactMergeService;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sanctum-surface coverage for the merge-undo endpoints behind the mobile
 * contacts screens (web parity with user.contacts.merges.undo):
 *
 *   GET  /api/v1/contacts/merges/undoable        (optional ?contact_id=)
 *   POST /api/v1/contacts/merges/{audit}/undo
 *
 * Pins: only the owner's undoable merges are listed, contact_id narrows to
 * the surviving primary, undo restores the merged-away contact, a foreign
 * audit id 404s, an expired/undone audit 422s (undo_failed), and
 * unauthenticated requests are rejected.
 */
class ApiContactMergeUndoTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function mergedPair(User $owner): array
    {
        // Bind workspace context so merge bookkeeping matches web behavior.
        $ws = app(WorkspaceContext::class)->resolve($owner);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $owner);

        $dupe = Contact::create(['user_id' => $owner->id, 'display_name' => 'Dupe ' . Str::random(4)]);
        ContactPhone::create(['contact_id' => $dupe->id, 'value' => '+15551230000', 'value_e164' => '+15551230000', 'is_primary' => true]);
        $survivor = Contact::create(['user_id' => $owner->id, 'display_name' => 'Survivor ' . Str::random(4)]);

        app(ContactMergeService::class)->merge($survivor, [$dupe]);

        $audit = ContactMergeAudit::where('user_id', $owner->id)->latest('id')->firstOrFail();

        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');

        return [$survivor, $audit];
    }

    public function test_lists_own_undoable_merges_and_filters_by_contact(): void
    {
        $owner = User::factory()->create();
        [$survivor, $audit] = $this->mergedPair($owner);

        $resp = $this->withToken($this->token($owner))
            ->getJson('/api/v1/contacts/merges/undoable')
            ->assertOk();

        $ids = collect($resp->json('data.merges'))->pluck('id');
        $this->assertTrue($ids->contains($audit->id));
        $this->assertSame(ContactMergeAudit::UNDO_WINDOW_DAYS, $resp->json('data.undo_window_days'));

        // contact_id narrows to the surviving primary.
        $this->withToken($this->token($owner))
            ->getJson('/api/v1/contacts/merges/undoable?contact_id=' . $survivor->id)
            ->assertOk()
            ->assertJsonPath('data.merges.0.id', $audit->id);

        $this->withToken($this->token($owner))
            ->getJson('/api/v1/contacts/merges/undoable?contact_id=999999')
            ->assertOk()
            ->assertJsonCount(0, 'data.merges');
    }

    public function test_foreign_merges_are_never_listed_or_undoable(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [, $audit] = $this->mergedPair($other);

        $resp = $this->withToken($this->token($owner))
            ->getJson('/api/v1/contacts/merges/undoable')
            ->assertOk();
        $this->assertFalse(collect($resp->json('data.merges'))->pluck('id')->contains($audit->id));

        $this->withToken($this->token($owner))
            ->postJson("/api/v1/contacts/merges/{$audit->id}/undo")
            ->assertNotFound();
    }

    public function test_undo_restores_the_merged_away_contact(): void
    {
        $owner = User::factory()->create();
        [, $audit] = $this->mergedPair($owner);
        $sourceName = $audit->sourceName();

        $resp = $this->withToken($this->token($owner))
            ->postJson("/api/v1/contacts/merges/{$audit->id}/undo")
            ->assertOk();

        $restoredId = $resp->json('data.contact.id');
        $this->assertNotNull($restoredId);
        $this->assertSame($sourceName, $resp->json('data.contact.display_name'));
        $this->assertDatabaseHas('contacts', ['id' => $restoredId, 'user_id' => $owner->id]);

        // A second undo of the same audit is rejected (already undone).
        $this->withToken($this->token($owner))
            ->postJson("/api/v1/contacts/merges/{$audit->id}/undo")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'undo_failed');
    }

    public function test_expired_audit_is_not_listed_and_cannot_be_undone(): void
    {
        $owner = User::factory()->create();
        [, $audit] = $this->mergedPair($owner);
        $audit->forceFill(['created_at' => now()->subDays(ContactMergeAudit::UNDO_WINDOW_DAYS + 1)])->save();

        $resp = $this->withToken($this->token($owner))
            ->getJson('/api/v1/contacts/merges/undoable')
            ->assertOk();
        $this->assertFalse(collect($resp->json('data.merges'))->pluck('id')->contains($audit->id));

        $this->withToken($this->token($owner))
            ->postJson("/api/v1/contacts/merges/{$audit->id}/undo")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'undo_failed');
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/contacts/merges/undoable')->assertUnauthorized();
        $this->postJson('/api/v1/contacts/merges/1/undo')->assertUnauthorized();
    }
}
