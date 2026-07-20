<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use App\Modules\User\Models\VerificationTickType;
use Database\Seeders\VerificationTickTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guards against the "empty tick-type catalog on a fresh deploy" failure mode:
 * the create-table migration only inserts the default tick types when IT
 * creates the table, so a deploy where verification_tick_types exists but is
 * empty would leave the user request form and the admin tick-type page blank.
 *
 * VerificationTickTypeSeeder (wired into DatabaseSeeder + post-merge.sh) must:
 *  - backfill the 5 defaults when the table is empty,
 *  - be idempotent (no duplicates on re-run),
 *  - never clobber admin edits (firstOrCreate keyed on slug).
 */
class VerificationTickTypeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_migration_leaves_at_least_one_tick_type(): void
    {
        // migrate:fresh (via RefreshDatabase) alone must yield a non-empty
        // catalog — the "done looks like" condition for fresh environments.
        $this->assertGreaterThan(0, VerificationTickType::count());
    }

    public function test_seeder_backfills_defaults_when_table_is_empty(): void
    {
        VerificationTickType::query()->delete();
        $this->assertSame(0, VerificationTickType::count());

        (new VerificationTickTypeSeeder())->run();

        $this->assertSame(count(VerificationTickTypeSeeder::DEFAULTS), VerificationTickType::count());
        foreach (VerificationTickTypeSeeder::DEFAULTS as $row) {
            $this->assertDatabaseHas('verification_tick_types', [
                'slug' => $row['slug'],
                'name' => $row['name'],
            ]);
        }

        // At least one publicly-requestable type so the user request form is
        // never empty out of the box.
        $this->assertGreaterThan(0, VerificationTickType::publicRequestable()->count());
    }

    public function test_seeder_is_idempotent_and_preserves_admin_edits(): void
    {
        VerificationTickType::query()->delete();
        (new VerificationTickTypeSeeder())->run();

        // Admin customizes a type via the tick-type admin page.
        $creator = VerificationTickType::where('slug', 'creator')->firstOrFail();
        $creator->update(['name' => 'Influencer', 'color' => '#123456', 'is_active' => false]);

        (new VerificationTickTypeSeeder())->run();

        $this->assertSame(count(VerificationTickTypeSeeder::DEFAULTS), VerificationTickType::count());
        $creator->refresh();
        $this->assertSame('Influencer', $creator->name);
        $this->assertSame('#123456', $creator->color);
        $this->assertFalse($creator->is_active);
    }

    public function test_admin_tick_type_page_shows_rows_out_of_the_box(): void
    {
        // Simulate the fresh-deploy gap, then the seeder backfill.
        VerificationTickType::query()->delete();
        (new VerificationTickTypeSeeder())->run();

        $user = User::create([
            'name'         => 'u' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@ex.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            'onboarded_at' => now(),
        ]);

        $role = Role::create([
            'name'  => 'R ' . Str::random(4),
            'slug'  => 'r-' . Str::random(8),
            'guard' => 'web',
        ]);
        $perm = Permission::firstOrCreate(
            ['slug' => 'user.verifications.review'],
            ['name' => 'Review Verifications', 'group' => 'user']
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
        $user->flushPermissionCache();

        $response = $this->actingAs($user)
            ->get(route('user.profile-verification.admin.tick-types'));

        $response->assertOk();
        foreach (VerificationTickTypeSeeder::DEFAULTS as $row) {
            $response->assertSee($row['name']);
        }
    }
}
