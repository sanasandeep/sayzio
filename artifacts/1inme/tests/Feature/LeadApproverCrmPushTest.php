<?php

namespace Tests\Feature;

use App\Jobs\PushLeadToCrmJob;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\ConnectedApp;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\Lead;
use App\Modules\User\Models\User;
use App\Modules\User\Services\LeadApprover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Locks in that approving a lead which *merges* into an existing contact keeps
 * feeding connected CRMs the same way a brand-new contact does. The regression
 * that matters: the `created` model observer only fires on the new-contact
 * branch, so before {@see Contact::queueCrmPush()} was shared, merged/enriched
 * contacts silently stopped syncing — creators saw new leads in their CRM but
 * not merged updates. This path has no other coverage, so a refactor of the
 * observer, the job, or {@see LeadApprover::fillMissingFields()}'s bool return
 * could quietly break it.
 *
 * The CRM push is intentionally dispatched with afterCommit(); Bus::fake()
 * records the dispatch immediately (it does not honour the transaction
 * deferral), so these assertions are reliable under RefreshDatabase.
 */
class LeadApproverCrmPushTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $plan = Plan::create([
            'name'               => 'Pro',
            'slug'               => 'plan-' . Str::lower(Str::random(6)),
            'monthly_price'      => 0,
            'annual_price'       => 0,
            'trial_days'         => 0,
            'grace_days'         => 0,
            'refund_window_days' => 0,
            'status'             => 'active',
            'sort_order'         => 1,
            'features'           => ['connected_apps' => true],
        ]);

        return User::factory()->create([
            'plan_id'  => $plan->id,
        ])->fresh();
    }

    private function connectCrm(User $user): ConnectedApp
    {
        return ConnectedApp::create([
            'user_id'      => $user->id,
            'provider'     => 'hubspot',
            'kind'         => 'crm',
            'status'       => ConnectedApp::STATUS_CONNECTED,
            'push_enabled' => true,
            'pull_enabled' => true,
            'connected_at' => now(),
        ]);
    }

    /**
     * Build an existing contact carrying a phone (and optionally an email)
     * BEFORE any CRM is connected, so its own `created` observer is a no-op
     * and cannot pollute the Bus::fake() recorded by the test.
     *
     * @param string[] $sources
     */
    private function existingContact(User $user, string $phoneE164, ?string $email = null, array $sources = ['lead:form_submission']): Contact
    {
        $contact = Contact::create([
            'user_id'             => $user->id,
            'display_name'        => 'Existing Lead',
            'sources'             => $sources,
            'locally_modified_at' => now(),
        ]);
        $contact->phones()->create([
            'label' => 'Other', 'value' => $phoneE164, 'value_e164' => $phoneE164, 'is_primary' => true,
        ]);
        if ($email !== null) {
            $contact->emails()->create([
                'label' => 'Other', 'value' => strtolower($email), 'is_primary' => true,
            ]);
        }

        return $contact->fresh();
    }

    /** @return array<string,mixed> a normalized pending-lead payload. */
    private function lead(string $phone, ?string $email, int $sourceId = 101): array
    {
        return [
            'name'        => 'Existing Lead',
            'email'       => $email,
            'phone'       => $phone,
            'source_type' => 'form_submission',
            'source_id'   => $sourceId,
            'context'     => null,
        ];
    }

    public function test_merging_a_lead_that_adds_new_contact_data_pushes_to_a_connected_crm(): void
    {
        $user = $this->makeUser();
        // Existing contact matched by phone; the lead brings a brand-new email.
        $existing = $this->existingContact($user, '+14155550001');
        $this->connectCrm($user);

        Bus::fake();

        $result = (new LeadApprover())->approve($user, $this->lead('+14155550001', 'newlead@example.com'));

        $this->assertSame(LeadApprover::RESULT_MERGED, $result['result']);
        $this->assertSame($existing->id, $result['contact']->id);
        // The enrichment actually landed (proves the merge branch ran).
        $this->assertTrue(
            $existing->emails()->where('value', 'newlead@example.com')->exists(),
            'the new email should be added to the merged contact'
        );

        Bus::assertDispatched(
            PushLeadToCrmJob::class,
            fn (PushLeadToCrmJob $job) => true
        );
    }

    public function test_no_push_when_the_merge_adds_nothing_new(): void
    {
        $user = $this->makeUser();
        // Existing contact already has BOTH the phone and the email the lead carries.
        $this->existingContact($user, '+14155550001', 'known@example.com');
        $this->connectCrm($user);

        Bus::fake();

        // Same as re-approving the same lead: nothing new to add.
        $result = (new LeadApprover())->approve($user, $this->lead('+14155550001', 'known@example.com'));

        $this->assertSame(LeadApprover::RESULT_MERGED, $result['result']);
        Bus::assertNotDispatched(PushLeadToCrmJob::class);
    }

    public function test_no_push_when_no_crm_is_connected(): void
    {
        $user = $this->makeUser();
        $this->existingContact($user, '+14155550001');
        // No ConnectedApp at all.

        Bus::fake();

        $result = (new LeadApprover())->approve($user, $this->lead('+14155550001', 'newlead@example.com'));

        $this->assertSame(LeadApprover::RESULT_MERGED, $result['result']);
        Bus::assertNotDispatched(PushLeadToCrmJob::class);
    }

    public function test_a_contact_imported_from_a_crm_is_not_echoed_back_on_merge(): void
    {
        $user = $this->makeUser();
        // Contact originated FROM a CRM (loop-safe skip must apply).
        $this->existingContact($user, '+14155550001', null, ['crm:hubspot']);
        $this->connectCrm($user);

        Bus::fake();

        // Adds genuinely new data (email), so fillMissingFields() returns true
        // and queueCrmPush() runs — but the crm: source must short-circuit it.
        $result = (new LeadApprover())->approve($user, $this->lead('+14155550001', 'newlead@example.com'));

        $this->assertSame(LeadApprover::RESULT_MERGED, $result['result']);
        Bus::assertNotDispatched(PushLeadToCrmJob::class);
    }
}
