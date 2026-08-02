<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use App\Modules\User\Services\Contacts\ContactMergeService;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * "Merge into…" for duplicate auto-captured contacts (Task #6504):
 * a contact can be absorbed into a picked surviving contact — emails/phones
 * move over, all capture rows (subscribers, form submissions, orders,
 * bookings, RSVPs, tickets, reviews, inbox threads) are repointed to the
 * survivor, and the duplicate is deleted. Ownership-safe and idempotent.
 */
class ContactMergeIntoTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $prefix = 'u'): User
    {
        return User::factory()->create([
            'name'   => $prefix . Str::random(4),
            'email'  => $prefix . '-' . Str::random(8) . '@example.com',
            'status' => 'active',
            'handle' => strtolower($prefix) . substr(Str::random(8), 0, 8),
        ]);
    }

    private function actAsOwner(User $user): void
    {
        $this->be($user, 'web');
        $ws = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);
    }

    /** Duplicate pair: A auto-captured by email, B captured by phone. */
    private function makePair(User $owner): array
    {
        $a = Contact::create(['user_id' => $owner->id, 'display_name' => 'Jane Email', 'is_auto_captured' => true]);
        ContactEmail::create(['contact_id' => $a->id, 'value' => 'jane-' . Str::random(6) . '@example.com', 'is_primary' => true]);

        $b = Contact::create(['user_id' => $owner->id, 'display_name' => 'Jane Phone', 'is_auto_captured' => true]);
        ContactPhone::create(['contact_id' => $b->id, 'value' => '+15551239876', 'value_e164' => '+15551239876', 'is_primary' => true]);

        return [$a, $b];
    }

    public function test_merge_into_moves_identifiers_repoints_captures_and_deletes_duplicate(): void
    {
        $owner = $this->makeUser('owner');
        $this->actAsOwner($owner);
        [$dupe, $survivor] = $this->makePair($owner);

        // Capture rows pointing at the duplicate (model event queues run sync,
        // so overwrite contact_id directly after create for determinism).
        $sub = Subscriber::create([
            'user_id' => $owner->id,
            'email'   => 'cap-' . Str::random(6) . '@example.com',
            'status'  => 'active',
        ]);
        DB::table('subscribers')->where('id', $sub->id)->update(['contact_id' => $dupe->id]);

        // Raw rows in other capture tables so we don't need full model
        // fixtures — parent rows created first to satisfy FKs.
        $formId = DB::table('forms')->insertGetId([
            'user_id'    => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ] + $this->requiredDefaults('forms', ['user_id']));
        DB::table('form_submissions')->insert([
            'form_id'    => $formId,
            'data'       => json_encode([]),
            'contact_id' => $dupe->id,
            'created_at' => now(),
            'updated_at' => now(),
        ] + $this->requiredDefaults('form_submissions', ['form_id', 'data']));

        $resp = $this->post(route('user.contacts.merge-into', $dupe), [
            'target_id' => $survivor->id,
        ]);

        $resp->assertRedirect(route('user.contacts.show', $survivor));

        // Duplicate deleted, survivor has both identifiers
        $this->assertDatabaseMissing('contacts', ['id' => $dupe->id]);
        $survivor->refresh()->loadMissing(['emails', 'phones']);
        $this->assertCount(1, $survivor->emails, 'email must move to the survivor');
        $this->assertCount(1, $survivor->phones, 'survivor keeps its phone');

        // Capture rows repointed
        $this->assertSame($survivor->id, (int) DB::table('subscribers')->where('id', $sub->id)->value('contact_id'));
        $this->assertSame(1, DB::table('form_submissions')->where('contact_id', $survivor->id)->count());
        $this->assertSame(0, DB::table('form_submissions')->where('contact_id', $dupe->id)->count());
    }

    public function test_merge_service_repoints_all_capture_tables(): void
    {
        $owner = $this->makeUser('owner');
        [$dupe, $survivor] = $this->makePair($owner);

        foreach (ContactMergeService::CAPTURE_TABLES as $table) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Schema::hasColumn($table, 'contact_id'),
                "capture table {$table} must have a contact_id column"
            );
        }

        // Seed a minimal raw RSVP (with a real parent link to satisfy FKs).
        $linkId = DB::table('links')->insertGetId([
            'user_id'    => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ] + $this->requiredDefaults('links', ['user_id']));
        DB::table('rsvps')->insert([
            'contact_id' => $dupe->id,
            'link_id'    => $linkId,
            'created_at' => now(),
            'updated_at' => now(),
        ] + $this->requiredDefaults('rsvps', ['link_id']));

        app(ContactMergeService::class)->merge($survivor, [$dupe]);

        $this->assertSame(0, DB::table('rsvps')->where('contact_id', $dupe->id)->count());
        $this->assertSame(1, DB::table('rsvps')->where('contact_id', $survivor->id)->count());
    }

    /** Fill NOT NULL columns (besides id/timestamps/contact_id) with zero-values. */
    private function requiredDefaults(string $table, array $except = []): array
    {
        $skip = array_merge(['id', 'contact_id', 'created_at', 'updated_at'], $except);
        $cols = DB::select("
            SELECT column_name, data_type
            FROM information_schema.columns
            WHERE table_name = ? AND is_nullable = 'NO' AND column_default IS NULL
              AND column_name != ALL(?)
        ", [$table, '{' . implode(',', $skip) . '}']);
        $out = [];
        foreach ($cols as $c) {
            $out[$c->column_name] = match (true) {
                str_contains($c->data_type, 'int')                       => 0,
                in_array($c->data_type, ['json', 'jsonb'], true)         => '{}',
                str_contains($c->data_type, 'bool')                      => false,
                str_contains($c->data_type, 'timestamp'),
                str_contains($c->data_type, 'date')                      => now(),
                default                                                  => 'x-' . Str::random(6),
            };
        }
        return $out;
    }

    public function test_cannot_merge_into_another_users_contact(): void
    {
        $owner = $this->makeUser('owner');
        $other = $this->makeUser('other');
        $this->actAsOwner($owner);
        [$dupe] = $this->makePair($owner);
        $foreign = Contact::create(['user_id' => $other->id, 'display_name' => 'Foreign']);

        $resp = $this->post(route('user.contacts.merge-into', $dupe), [
            'target_id' => $foreign->id,
        ]);

        $resp->assertRedirect(route('user.contacts.show', $dupe));
        $resp->assertSessionHas('error');
        $this->assertDatabaseHas('contacts', ['id' => $dupe->id]);
        $this->assertDatabaseHas('contacts', ['id' => $foreign->id]);
    }

    public function test_cannot_merge_foreign_contact_as_source(): void
    {
        $owner = $this->makeUser('owner');
        $other = $this->makeUser('other');
        $this->actAsOwner($owner);
        $foreign = Contact::create(['user_id' => $other->id, 'display_name' => 'Foreign']);
        [, $survivor] = $this->makePair($owner);

        $resp = $this->post(route('user.contacts.merge-into', $foreign), [
            'target_id' => $survivor->id,
        ]);

        $this->assertContains($resp->getStatusCode(), [403, 404]);
        $this->assertDatabaseHas('contacts', ['id' => $foreign->id]);
    }

    public function test_cannot_merge_contact_into_itself(): void
    {
        $owner = $this->makeUser('owner');
        $this->actAsOwner($owner);
        [$dupe] = $this->makePair($owner);

        $resp = $this->post(route('user.contacts.merge-into', $dupe), [
            'target_id' => $dupe->id,
        ]);

        $resp->assertSessionHas('error');
        $this->assertDatabaseHas('contacts', ['id' => $dupe->id]);
    }

    public function test_merge_candidates_excludes_self_and_foreign_contacts(): void
    {
        $owner = $this->makeUser('owner');
        $other = $this->makeUser('other');
        $this->actAsOwner($owner);
        [$dupe, $survivor] = $this->makePair($owner);
        Contact::create(['user_id' => $other->id, 'display_name' => 'Foreign Guy']);

        $resp = $this->getJson(route('user.contacts.merge-candidates', $dupe));

        $resp->assertOk();
        $ids = collect($resp->json('data.candidates'))->pluck('id');
        $this->assertTrue($ids->contains($survivor->id));
        $this->assertFalse($ids->contains($dupe->id), 'a contact must never offer itself');
        $this->assertCount(1, $ids, 'foreign contacts must never be listed');
    }

    public function test_merge_is_idempotent_on_replay(): void
    {
        $owner = $this->makeUser('owner');
        $this->actAsOwner($owner);
        [$dupe, $survivor] = $this->makePair($owner);
        $dupeId = $dupe->id;

        $this->post(route('user.contacts.merge-into', $dupe), ['target_id' => $survivor->id]);

        // Replaying against the now-deleted duplicate must not crash or
        // mutate anything (route model binding 404s the dead id).
        $resp = $this->post(route('user.contacts.merge-into', ['contact' => $dupeId]), [
            'target_id' => $survivor->id,
        ]);
        $resp->assertNotFound();

        $survivor->refresh()->loadMissing(['emails', 'phones']);
        $this->assertCount(1, $survivor->emails);
        $this->assertCount(1, $survivor->phones);
    }
}
