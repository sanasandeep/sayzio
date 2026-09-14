<?php

namespace Tests\Feature;

use App\Jobs\ProcessContactImportJob;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactImport;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A CSV contact import must produce contacts the importer can actually see.
 *
 * Contact carries the BelongsToWorkspace trait, whose global scope filters
 * every read to `workspace_id = <the active workspace>`. The trait fills that
 * column on create from the container-bound `current_workspace` -- which is
 * bound by request middleware, and therefore is NOT bound inside a queued job.
 *
 * ProcessContactImportJob creates each Contact with `user_id` alone. Running
 * on the database queue, with no workspace bound and no parentForWorkspace()
 * fallback on the model, every imported row was written with a NULL
 * workspace_id, and the scope then hid all of them from the person who did the
 * importing. The import reported success and the contacts were simply gone.
 *
 * The one-time backfill migration does not help: it ran once, and these rows
 * are created continuously afterwards.
 */
class ImportedContactsLandInTheOwnersWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    /** Two parsed rows in the shape the import controller hands the job. */
    private function rows(): array
    {
        return [
            ['display_name' => 'Ada Lovelace', 'phones' => ['+15550000001'], 'emails' => ['ada@example.com']],
            ['display_name' => 'Grace Hopper', 'phones' => ['+15550000002'], 'emails' => ['grace@example.com']],
        ];
    }

    private function import(User $user): ContactImport
    {
        return ContactImport::create([
            'user_id'           => $user->id,
            'original_filename' => 'contacts.csv',
            'status'            => 'pending',
            'rows'              => $this->rows(),
        ]);
    }

    /**
     * The job runs with nothing bound, exactly as a queue worker would. The
     * assertion is deliberately made through the ordinary scoped query the
     * contacts list uses -- not withoutGlobalScope -- because "the row exists
     * in the table" was never the problem.
     */
    public function test_contacts_imported_by_a_queue_worker_are_visible_to_their_owner(): void
    {
        $user = User::factory()->create();

        // Resolve the workspace the way a request would, then forget it again:
        // the worker process has no request and no binding.
        $workspace = app(WorkspaceContext::class)->resolve($user);
        $import = $this->import($user);
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');

        app()->call([new ProcessContactImportJob($import->id), 'handle']);

        // Now look at them as the owner does, through the workspace scope.
        app()->instance('current_workspace', $workspace);
        app()->instance('workspace_owner', $user);

        $visible = Contact::where('user_id', $user->id)->get();

        $this->assertCount(
            2,
            $visible,
            'imported contacts must be visible in the importing workspace, not stranded on a NULL workspace_id'
        );
        $this->assertEqualsCanonicalizing(
            ['Ada Lovelace', 'Grace Hopper'],
            $visible->pluck('display_name')->all()
        );
    }

    /** Every imported row carries the workspace, not just the ones we read back. */
    public function test_no_imported_contact_is_left_without_a_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = app(WorkspaceContext::class)->resolve($user);
        $import = $this->import($user);
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');

        app()->call([new ProcessContactImportJob($import->id), 'handle']);

        $orphans = Contact::withoutGlobalScope('workspace')
            ->where('user_id', $user->id)
            ->whereNull('workspace_id')
            ->count();

        $this->assertSame(0, $orphans, 'a contact with no workspace_id is invisible to everyone');

        $this->assertSame(
            2,
            Contact::withoutGlobalScope('workspace')
                ->where('user_id', $user->id)
                ->where('workspace_id', $workspace->id)
                ->count()
        );
    }
}
