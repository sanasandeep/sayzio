<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\Review;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use App\Modules\User\Services\Contacts\ContactIdentityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unified contact linking (Task #6501): captures (subscribers, reviews, …)
 * are tied to the owning creator's Contact by email/phone via
 * ContactIdentityResolver — matching an existing contact, enriching it, or
 * auto-creating one flagged is_auto_captured; the plan cap degrades
 * gracefully (no create, no crash); everything is per-creator isolated.
 */
class ContactUnifiedLinkingTest extends TestCase
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

    private function resolver(): ContactIdentityResolver
    {
        return app(ContactIdentityResolver::class);
    }

    public function test_resolver_matches_existing_contact_by_email(): void
    {
        $owner = $this->makeUser('owner');
        $contact = Contact::create(['user_id' => $owner->id, 'display_name' => 'Jane']);
        ContactEmail::create(['contact_id' => $contact->id, 'value' => 'jane@example.com', 'is_primary' => true]);

        $resolved = $this->resolver()->resolve($owner->id, 'Jane@Example.com', null, 'Jane Doe', 'subscriber');

        $this->assertNotNull($resolved);
        $this->assertSame($contact->id, $resolved->id);
        $this->assertFalse((bool) $resolved->is_auto_captured, 'matching must not flag an existing contact');
    }

    public function test_resolver_matches_existing_contact_by_phone(): void
    {
        $owner = $this->makeUser('owner');
        $contact = Contact::create(['user_id' => $owner->id, 'display_name' => 'Phil']);
        ContactPhone::create([
            'contact_id' => $contact->id,
            'value'      => '+15551230001',
            'value_e164' => '+15551230001',
            'is_primary' => true,
        ]);

        $resolved = $this->resolver()->resolve($owner->id, null, '+1 (555) 123-0001', null, 'rsvp');

        $this->assertNotNull($resolved);
        $this->assertSame($contact->id, $resolved->id);
    }

    public function test_resolver_auto_creates_contact_flagged_auto_captured(): void
    {
        $owner = $this->makeUser('owner');

        $resolved = $this->resolver()->resolve($owner->id, 'new-capture@example.com', null, 'New Person', 'form');

        $this->assertNotNull($resolved);
        $this->assertTrue((bool) $resolved->is_auto_captured);
        $this->assertSame($owner->id, (int) $resolved->user_id);
        $this->assertSame('new-capture@example.com', $resolved->emails()->first()?->value);
    }

    public function test_resolver_is_per_creator_isolated(): void
    {
        $a = $this->makeUser('a');
        $b = $this->makeUser('b');
        $contactA = Contact::create(['user_id' => $a->id, 'display_name' => 'Shared']);
        ContactEmail::create(['contact_id' => $contactA->id, 'value' => 'shared@example.com', 'is_primary' => true]);

        $resolvedB = $this->resolver()->resolve($b->id, 'shared@example.com', null, 'Shared', 'subscriber');

        $this->assertNotNull($resolvedB);
        $this->assertNotSame($contactA->id, $resolvedB->id, 'creator B must never see creator A contacts');
        $this->assertSame($b->id, (int) $resolvedB->user_id);
    }

    public function test_resolver_returns_null_without_identity(): void
    {
        $owner = $this->makeUser('owner');
        $this->assertNull($this->resolver()->resolve($owner->id, null, null, 'No Identity', 'inbox'));
        $this->assertNull($this->resolver()->resolve($owner->id, '', '', 'No Identity', 'inbox'));
    }

    public function test_plan_cap_is_not_enforced_against_auto_captured_linking(): void
    {
        $owner = $this->makeUser('owner');
        // Put the owner at a contacts_max cap of 1…
        $plan = \App\Modules\Admin\Models\Plan::create([
            'name' => 'Capped', 'slug' => 'p' . strtolower(Str::random(6)),
            'monthly_price' => 0, 'annual_price' => 0, 'trial_days' => 0,
            'status' => 'active', 'features' => ['contacts_max' => 1],
        ]);
        $owner->plan_id = $plan->id;
        $owner->save();
        $owner->refresh();
        Contact::create(['user_id' => $owner->id, 'display_name' => 'Existing']);

        // …auto-captured linking must still create the contact (caps are
        // only enforced on manual "add contact" surfaces — a customer
        // order/subscription must never be dropped or left unlinked).
        $resolved = $this->resolver()->resolve($owner->id, 'over-cap@example.com', null, 'Over Cap', 'subscriber');

        $this->assertNotNull($resolved, 'cap must not block auto-captured linking');
        $this->assertTrue((bool) $resolved->is_auto_captured);
        $this->assertSame(
            2,
            Contact::withoutGlobalScope('workspace')->where('user_id', $owner->id)->count()
        );
    }

    public function test_subscriber_capture_dispatches_link_job(): void
    {
        Queue::fake();
        $owner = $this->makeUser('owner');

        Subscriber::create([
            'user_id' => $owner->id,
            'email'   => 'sub-' . Str::random(6) . '@example.com',
            'name'    => 'Sub Scriber',
            'status'  => 'active',
        ]);

        Queue::assertPushed(\App\Jobs\LinkCaptureToContactJob::class);
    }

    public function test_subscriber_capture_links_end_to_end_on_sync_queue(): void
    {
        $owner = $this->makeUser('owner');
        $email = 'sync-' . Str::random(6) . '@example.com';

        $sub = Subscriber::create([
            'user_id' => $owner->id,
            'email'   => $email,
            'name'    => 'Sync Sub',
            'status'  => 'active',
        ]);

        $sub->refresh();
        $this->assertNotNull($sub->contact_id, 'sync queue should have linked the subscriber');
        $contact = Contact::withoutGlobalScope('workspace')->find($sub->contact_id);
        $this->assertSame($owner->id, (int) $contact->user_id);
        $this->assertTrue((bool) $contact->is_auto_captured);
    }

    public function test_spam_review_is_not_linked(): void
    {
        Queue::fake();
        $owner = $this->makeUser('owner');

        Review::withoutGlobalScope('workspace')->create([
            'user_id'      => $owner->id,
            'author_email' => 'spammer@example.com',
            'author_name'  => 'Spam',
            'body'         => 'spam body',
            'status'       => 'pending',
            'is_spam'      => true,
        ]);

        Queue::assertNotPushed(\App\Jobs\LinkCaptureToContactJob::class);
    }

    public function test_capture_never_blocks_when_resolver_fails(): void
    {
        $owner = $this->makeUser('owner');

        // Bind a resolver that always explodes — the subscriber save must
        // still succeed (the job swallows failures; creation isn't blocked).
        $this->app->bind(ContactIdentityResolver::class, function () {
            return new class extends ContactIdentityResolver {
                public function resolve(int $ownerUserId, ?string $email, ?string $phone, ?string $name = null, string $source = 'capture'): ?Contact
                {
                    throw new \RuntimeException('boom');
                }
            };
        });

        $sub = Subscriber::create([
            'user_id' => $owner->id,
            'email'   => 'never-block-' . Str::random(6) . '@example.com',
            'status'  => 'active',
        ]);

        $this->assertTrue($sub->exists, 'capture flow must never be blocked by linking failures');
    }

    public function test_backfill_command_links_historical_rows(): void
    {
        $owner = $this->makeUser('owner');

        // Simulate a historical row: create quietly so no hook fires.
        $sub = new Subscriber([
            'user_id' => $owner->id,
            'email'   => 'historic-' . Str::random(6) . '@example.com',
            'name'    => 'Historic',
            'status'  => 'active',
        ]);
        $sub->saveQuietly();
        $this->assertNull($sub->fresh()->contact_id);

        $this->artisan('contacts:link-captures', ['--source' => ['subscriber'], '--user' => $owner->id])
            ->assertExitCode(0);

        $this->assertNotNull($sub->fresh()->contact_id);

        // Idempotent: a second run leaves the link untouched.
        $linkedId = $sub->fresh()->contact_id;
        $this->artisan('contacts:link-captures', ['--source' => ['subscriber'], '--user' => $owner->id])
            ->assertExitCode(0);
        $this->assertSame($linkedId, $sub->fresh()->contact_id);
    }

    public function test_activity_api_returns_groups_and_scopes_ownership(): void
    {
        $owner = $this->makeUser('owner');
        $other = $this->makeUser('other');

        $sub = Subscriber::create([
            'user_id' => $owner->id,
            'email'   => 'api-' . Str::random(6) . '@example.com',
            'name'    => 'Api Sub',
            'status'  => 'active',
        ]);
        $sub->refresh();
        $this->assertNotNull($sub->contact_id);

        $ownerToken = $owner->createToken('t')->plainTextToken;
        $res = $this->withHeader('Authorization', 'Bearer ' . $ownerToken)
            ->getJson('/api/v1/contacts/' . $sub->contact_id . '/activity');
        $res->assertOk();
        $groups = collect($res->json('data.groups'));
        $this->assertTrue($groups->contains(fn ($g) => $g['key'] === 'subscriptions' && $g['count'] >= 1));
        $this->assertTrue((bool) $res->json('data.is_auto_captured'));

        // Another user must not be able to read this contact's activity.
        $otherToken = $other->createToken('t')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $otherToken)
            ->getJson('/api/v1/contacts/' . $sub->contact_id . '/activity')
            ->assertStatus(404);
    }
}
