<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactMergeAudit;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * contacts:prune-merge-audits must delete merge-undo audit rows (which carry
 * a full PII snapshot of the merged-away contact) once the 30-day undo
 * window plus the grace period has passed, while leaving rows still inside
 * the window (undoable or not) untouched.
 */
class PruneContactMergeAuditsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create([
            'email'  => 'prune-' . Str::random(8) . '@example.com',
            'status' => 'active',
            'handle' => 'pr' . strtolower(substr(Str::random(8), 0, 8)),
        ]);
    }

    private function makeAudit(User $user, \DateTimeInterface $createdAt, ?\DateTimeInterface $undoneAt = null): ContactMergeAudit
    {
        $primary = Contact::withoutGlobalScopes()->create([
            'user_id'      => $user->id,
            'display_name' => 'Primary ' . Str::random(4),
        ]);

        $audit = ContactMergeAudit::create([
            'user_id'            => $user->id,
            'primary_contact_id' => $primary->id,
            'source_contact_id'  => $primary->id + 1000,
            'source_snapshot'    => ['display_name' => 'Merged Away', 'phones' => [['value' => '+15550001111']]],
            'moved'              => [],
            'undone_at'          => $undoneAt,
        ]);

        // created_at drives the cutoff; set it explicitly past the fillable guard.
        DB::table('contact_merge_audits')->where('id', $audit->id)->update([
            'created_at' => $createdAt,
        ]);

        return $audit;
    }

    public function test_prunes_rows_past_window_plus_grace_and_keeps_the_rest(): void
    {
        $user  = $this->makeUser();
        $grace = 7;
        $limit = ContactMergeAudit::UNDO_WINDOW_DAYS + $grace;

        $longExpired    = $this->makeAudit($user, now()->subDays($limit + 30));
        $justPastCutoff = $this->makeAudit($user, now()->subDays($limit + 1));
        $expiredUndone  = $this->makeAudit($user, now()->subDays($limit + 5), now()->subDays($limit + 4));
        $inGraceWindow  = $this->makeAudit($user, now()->subDays(ContactMergeAudit::UNDO_WINDOW_DAYS + 2));
        $stillUndoable  = $this->makeAudit($user, now()->subDays(5));

        $this->artisan('contacts:prune-merge-audits')->assertSuccessful();

        $this->assertDatabaseMissing('contact_merge_audits', ['id' => $longExpired->id]);
        $this->assertDatabaseMissing('contact_merge_audits', ['id' => $justPastCutoff->id]);
        $this->assertDatabaseMissing('contact_merge_audits', ['id' => $expiredUndone->id]);
        $this->assertDatabaseHas('contact_merge_audits', ['id' => $inGraceWindow->id]);
        $this->assertDatabaseHas('contact_merge_audits', ['id' => $stillUndoable->id]);
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $user = $this->makeUser();
        $row  = $this->makeAudit($user, now()->subDays(ContactMergeAudit::UNDO_WINDOW_DAYS + 60));

        $this->artisan('contacts:prune-merge-audits', ['--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseHas('contact_merge_audits', ['id' => $row->id]);
    }

    public function test_custom_grace_window_is_honoured(): void
    {
        $user = $this->makeUser();
        // 2 days past the undo window: kept under default grace (7), pruned at grace 0.
        $row = $this->makeAudit($user, now()->subDays(ContactMergeAudit::UNDO_WINDOW_DAYS + 2));

        $this->artisan('contacts:prune-merge-audits', ['--grace-days' => 0])->assertSuccessful();

        $this->assertDatabaseMissing('contact_merge_audits', ['id' => $row->id]);
    }
}
