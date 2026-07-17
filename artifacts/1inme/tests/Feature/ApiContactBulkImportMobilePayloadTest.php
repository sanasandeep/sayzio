<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the wire contract between the mobile device-contacts import
 * (artifacts/1inme-mobile/lib/deviceContacts.ts → lib/api/contacts.ts
 * bulkImportContacts) and `POST /api/v1/contacts/bulk`.
 *
 * The mobile client sends rows shaped exactly like ContactImportPayload:
 * display_name / given_name / family_name / organization plus
 * emails[]/phones[] entries of `{ value, label }`, and consumes
 * `data.{created,updated,skipped,duplicates_found}` from the response.
 * If the server-side validation rules or response keys change, these tests
 * fail before a release can silently break device imports.
 */
class ApiContactBulkImportMobilePayloadTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /** A row exactly as the mobile mapper emits it for a full address-book entry. */
    private function mobileRow(): array
    {
        return [
            'display_name' => 'Ada Lovelace',
            'given_name'   => 'Ada',
            'family_name'  => 'Lovelace',
            'organization' => 'Analytical Engines Ltd',
            'emails'       => [
                ['value' => 'ada@example.com', 'label' => 'work'],
                ['value' => 'ada.home@example.com', 'label' => 'home'],
            ],
            'phones'       => [
                ['value' => '+44 20 7946 0958', 'label' => 'mobile'],
                ['value' => '+44 20 7946 0000', 'label' => 'work'],
            ],
        ];
    }

    public function test_full_mobile_payload_creates_contact_with_all_fields_and_labels(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/contacts/bulk', ['contacts' => [$this->mobileRow()]])
            ->assertOk()
            ->assertJsonStructure(['data' => ['created', 'updated', 'skipped', 'duplicates_found']])
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.updated', 0)
            ->assertJsonPath('data.skipped', 0)
            ->assertJsonPath('data.duplicates_found', 0);

        $contact = Contact::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Ada Lovelace', $contact->display_name);
        $this->assertSame('Ada', $contact->given_name);
        $this->assertSame('Lovelace', $contact->family_name);
        $this->assertSame('Analytical Engines Ltd', $contact->organization);

        $emails = $contact->emails()->orderBy('id')->get();
        $this->assertCount(2, $emails);
        $this->assertSame('ada@example.com', $emails[0]->value);
        $this->assertSame('work', $emails[0]->label);
        $this->assertTrue((bool) $emails[0]->is_primary);
        $this->assertSame('home', $emails[1]->label);

        $phones = $contact->phones()->orderBy('id')->get();
        $this->assertCount(2, $phones);
        $this->assertSame('mobile', $phones[0]->label);
        $this->assertTrue((bool) $phones[0]->is_primary);
        $this->assertSame('work', $phones[1]->label);
    }

    public function test_reimporting_the_same_contact_updates_in_place_not_duplicated(): void
    {
        $user  = User::factory()->create();
        $token = $this->token($user);

        $this->withToken($token)
            ->postJson('/api/v1/contacts/bulk', ['contacts' => [$this->mobileRow()]])
            ->assertOk()
            ->assertJsonPath('data.created', 1);

        // Second device sync: same person (matched by email), org changed.
        $row = $this->mobileRow();
        $row['organization'] = 'Difference Engines Ltd';

        $this->withToken($token)
            ->postJson('/api/v1/contacts/bulk', ['contacts' => [$row]])
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.skipped', 0)
            ->assertJsonPath('data.duplicates_found', 0);

        // Still one contact, fields refreshed, emails/phones not duplicated.
        $this->assertSame(1, Contact::where('user_id', $user->id)->count());
        $contact = Contact::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Difference Engines Ltd', $contact->organization);
        $this->assertSame(2, $contact->emails()->count());
        $this->assertSame(2, $contact->phones()->count());
    }

    public function test_phone_only_row_dedupes_by_phone_value(): void
    {
        $user  = User::factory()->create();
        $token = $this->token($user);

        $row = [
            'display_name' => 'Grace Hopper',
            'phones'       => [['value' => '+1 555 010 2030', 'label' => 'mobile']],
        ];

        $this->withToken($token)
            ->postJson('/api/v1/contacts/bulk', ['contacts' => [$row]])
            ->assertOk()
            ->assertJsonPath('data.created', 1);

        $this->withToken($token)
            ->postJson('/api/v1/contacts/bulk', ['contacts' => [$row]])
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.updated', 1);

        $this->assertSame(1, Contact::where('user_id', $user->id)->count());
        $this->assertSame(1, Contact::where('user_id', $user->id)->firstOrFail()->phones()->count());
    }

    public function test_empty_rows_are_skipped_and_counted(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/contacts/bulk', [
                'contacts' => [
                    // No name, no emails, no phones — the mobile mapper can emit
                    // this for address-book entries with only unsupported fields.
                    ['display_name' => null, 'emails' => [], 'phones' => []],
                    ['display_name' => 'Real Person', 'phones' => [['value' => '+15550001111', 'label' => null]]],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.updated', 0)
            ->assertJsonPath('data.skipped', 1);

        $this->assertSame(1, Contact::where('user_id', $user->id)->count());
    }

    public function test_mixed_batch_reports_created_updated_and_duplicates_found(): void
    {
        $user = User::factory()->create();

        // Pre-existing contact that the batch will update via shared email.
        $existing = Contact::create(['user_id' => $user->id, 'display_name' => 'Known Person']);
        $existing->emails()->create(['value' => 'known@example.com', 'label' => null, 'is_primary' => true]);

        // Pre-existing contact with the same name as a new row but no shared
        // email/phone, so the new row is created and flagged as a duplicate.
        Contact::create(['user_id' => $user->id, 'display_name' => 'Same Name']);

        $this->withToken($this->token($user))
            ->postJson('/api/v1/contacts/bulk', [
                'contacts' => [
                    ['display_name' => 'Known Person', 'emails' => [['value' => 'known@example.com', 'label' => 'work']]],
                    ['display_name' => 'Same Name', 'phones' => [['value' => '+15550002222', 'label' => 'mobile']]],
                    ['display_name' => 'Brand New', 'phones' => [['value' => '+15550003333', 'label' => null]]],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.skipped', 0)
            ->assertJsonPath('data.duplicates_found', 1);
    }
}
