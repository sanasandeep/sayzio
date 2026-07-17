<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The bulk device-book import (`POST /api/v1/contacts/bulk`) reports how many
 * freshly created contacts now look like duplicates of existing ones via
 * `duplicates_found`, so the mobile import completion UI can offer a jump
 * straight into the duplicates review screen.
 */
class ApiContactBulkImportDuplicatesTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_bulk_import_counts_created_rows_that_match_existing_contacts(): void
    {
        $user = User::factory()->create();
        Contact::create(['user_id' => $user->id, 'display_name' => 'Same Person']);

        $res = $this->withToken($this->token($user))
            ->postJson('/api/v1/contacts/bulk', [
                'contacts' => [
                    // Same name as an existing contact but no shared email/phone,
                    // so it is created (not merged) and flagged as a duplicate.
                    ['display_name' => 'Same Person', 'phones' => [['value' => '+15550000001']]],
                    // Entirely new person — created, not a duplicate.
                    ['display_name' => 'Unique Human', 'phones' => [['value' => '+15550000002']]],
                ],
            ])
            ->assertOk();

        $res->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.updated', 0)
            ->assertJsonPath('data.duplicates_found', 1);
    }

    public function test_bulk_import_without_matches_reports_zero_duplicates(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/contacts/bulk', [
                'contacts' => [
                    ['display_name' => 'Fresh Face', 'emails' => [['value' => 'fresh@example.com']]],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.duplicates_found', 0);
    }

    public function test_rows_merged_into_existing_contacts_do_not_count_as_duplicates(): void
    {
        $user = User::factory()->create();
        $existing = Contact::create(['user_id' => $user->id, 'display_name' => 'Known Person']);
        $existing->emails()->create(['value' => 'known@example.com', 'label' => null, 'is_primary' => true]);

        $this->withToken($this->token($user))
            ->postJson('/api/v1/contacts/bulk', [
                'contacts' => [
                    // Shares the primary email → updated in place, not created.
                    ['display_name' => 'Known Person', 'emails' => [['value' => 'known@example.com']]],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.duplicates_found', 0);
    }
}
