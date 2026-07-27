<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\BlockComment;
use App\Modules\User\Models\CommunityMember;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\FanPoint;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * When an ADMIN renames a user from the admin panel (users.update), the same
 * display-name sync that fires on the self-service profile paths must run:
 * personal workspace + linked admin inline, and the queued fan-out over
 * block comments, community rosters, fan points, subscriber entries and
 * internally-linked contacts, plus creator-surface cache busting.
 */
class AdminRenameUserNameSyncTest extends TestCase
{
    use RefreshDatabase;

    private Admin $operator;
    private User $creator;
    private Link $link;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );
        $this->operator = Admin::create([
            'name'     => 'Operator',
            'email'    => 'operator' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);

        $this->creator = User::factory()->create(['name' => 'Creator'])->fresh();
        $this->link = Link::create([
            'user_id' => $this->creator->id,
            'alias'   => 'admin-sync-' . uniqid(),
            'type'    => 'biolink',
            'url'     => null,
        ]);
    }

    private function seedDenormalizedRows(User $fan): array
    {
        $block = \App\Modules\User\Models\BiolinkBlock::create([
            'link_id'  => $this->link->id,
            'type'     => 'text',
            'settings' => [],
            'order'    => 1,
        ]);
        $comment = BlockComment::create([
            'link_id'        => $this->link->id,
            'block_id'       => $block->id,
            'viewer_user_id' => $fan->id,
            'author_name'    => $fan->name,
            'body'           => 'hello',
            'status'         => 'visible',
        ]);
        $member = CommunityMember::create([
            'user_id'        => $this->creator->id,
            'link_id'        => $this->link->id,
            'viewer_user_id' => $fan->id,
            'email'          => $fan->email,
            'display_name'   => $fan->name,
            'tier'           => 'free',
            'status'         => 'active',
            'joined_at'      => now(),
        ]);
        $point = FanPoint::create([
            'user_id'        => $this->creator->id,
            'link_id'        => $this->link->id,
            'viewer_user_id' => $fan->id,
            'display_name'   => $fan->name,
            'action'         => 'comment',
            'points'         => 5,
            'subject_id'     => $this->link->id,
            'subject_type'   => Link::class,
        ]);
        $sub = Subscriber::create([
            'user_id'       => $this->creator->id,
            'link_id'       => $this->link->id,
            'type'          => 'email',
            'email'         => strtolower($fan->email),
            'name'          => $fan->name,
            'status'        => 'active',
            'source'        => 'test',
            'subscribed_at' => now(),
        ]);
        $linkedContact = Contact::create([
            'user_id'         => $this->creator->id,
            'display_name'    => $fan->name,
            'biolink_user_id' => $fan->id,
        ]);

        return compact('comment', 'member', 'point', 'sub', 'linkedContact');
    }

    public function test_admin_rename_propagates_to_all_denormalized_copies(): void
    {
        $fan = User::factory()->create(['name' => 'Old Fan'])->fresh();
        $rows = $this->seedDenormalizedRows($fan);

        $resp = $this->actingAs($this->operator, 'admin')->put(
            route('admin.users.update', $fan),
            ['name' => 'Admin Renamed']
        );
        $resp->assertSessionHasNoErrors();
        $resp->assertSessionHas('success');

        $this->assertSame('Admin Renamed', $fan->fresh()->name);
        $this->assertSame('Admin Renamed', $rows['comment']->fresh()->author_name);
        $this->assertSame('Admin Renamed', $rows['member']->fresh()->display_name);
        $this->assertSame('Admin Renamed', $rows['point']->fresh()->display_name);
        $this->assertSame('Admin Renamed', $rows['sub']->fresh()->name);
        $this->assertSame('Admin Renamed', $rows['linkedContact']->fresh()->display_name);
    }

    public function test_admin_rename_syncs_personal_workspace_and_busts_creator_cache(): void
    {
        $fan = User::factory()->create(['name' => 'Old Fan'])->fresh();
        $personal = $fan->ownedWorkspaces()->where('is_personal', true)->first();
        if ($personal) {
            // Ensure it still carries the auto-generated default so it syncs.
            $personal->update(['name' => "Old Fan's workspace"]);
        }
        Cache::put(\App\Modules\Common\Controllers\CreatorsController::DEFAULT_CACHE_KEY, ['stale'], 300);

        $this->actingAs($this->operator, 'admin')->put(
            route('admin.users.update', $fan),
            ['name' => 'New Fan']
        )->assertSessionHasNoErrors();

        if ($personal) {
            $this->assertSame("New Fan's workspace", $personal->fresh()->name);
        }
        $this->assertNull(Cache::get(\App\Modules\Common\Controllers\CreatorsController::DEFAULT_CACHE_KEY));
    }

    public function test_admin_save_without_name_change_does_not_touch_snapshots(): void
    {
        $fan = User::factory()->create(['name' => 'Same Name'])->fresh();
        $rows = $this->seedDenormalizedRows($fan);
        // Manually stale a snapshot; an unchanged-name save must NOT repair it
        // (that's the backfill command's job, not the admin edit path).
        $rows['comment']->update(['author_name' => 'Stale Snapshot']);

        $this->actingAs($this->operator, 'admin')->put(
            route('admin.users.update', $fan),
            ['name' => 'Same Name']
        )->assertSessionHasNoErrors();

        $this->assertSame('Stale Snapshot', $rows['comment']->fresh()->author_name);
    }
}
