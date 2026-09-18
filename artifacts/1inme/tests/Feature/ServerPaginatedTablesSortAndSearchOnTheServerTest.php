<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use App\Support\TableSort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * A table the database paginates must not also be searched and sorted in the
 * browser.
 *
 * common/partials/enhanced-table adds a search box, a page-size picker,
 * sortable headers and its own paginator to any table.enhanced-table, all of
 * which operate on the rows already in the DOM. On the admin users list --
 * paginate(15) over 126 accounts -- that produced a search box reporting
 * "Showing 1-2 of 2" directly above Laravel's "Showing 1 to 15 of 126
 * results", and, far worse than the contradiction, a search that quietly only
 * looked at the current page. Typing a name belonging to someone on page 4
 * returned "no results" with a straight face.
 *
 * The fix is data-server-paginated on the table, which makes the enhancer
 * stand down, plus real ?sort= sorting in the query. These tests pin both
 * halves: the markup opts out, and sorting reorders the whole result set
 * rather than the page you happen to be on.
 */
class ServerPaginatedTablesSortAndSearchOnTheServerTest extends TestCase
{
    use RefreshDatabase;

    /** Every table that carries a Laravel paginator must opt out of the enhancer. */
    public function test_no_server_paginated_table_leaves_the_client_enhancer_switched_on(): void
    {
        $views = base_path('resources/views');
        $offenders = [];

        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($views));
        foreach ($rii as $file) {
            if ($file->isDir() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $body = file_get_contents($file->getPathname());

            // Only tables that hand themselves to the enhancer are in scope.
            if (! str_contains($body, 'enhanced-table')) {
                continue;
            }
            // The partial itself defines the behaviour; it is not a table.
            if (str_contains($file->getPathname(), 'partials/enhanced-table')) {
                continue;
            }
            // A table with no Laravel paginator is genuinely client-side.
            if (! preg_match('/->links\(\)/', $body)) {
                continue;
            }

            if (! str_contains($body, 'data-server-paginated')) {
                $offenders[] = str_replace($views . '/', '', $file->getPathname());
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "These views paginate on the server AND let the client enhancer add its own "
            . "search/sort/pagination over one page of rows. Add data-server-paginated to "
            . "the table:\n  - " . implode("\n  - ", $offenders)
        );
    }

    /** The enhancer must actually honour the opt-out it is handed. */
    public function test_the_enhancer_returns_early_for_server_paginated_tables(): void
    {
        $partial = file_get_contents(
            base_path('resources/views/common/partials/enhanced-table.blade.php')
        );

        $this->assertStringContainsString(
            "if (table.hasAttribute('data-server-paginated')) return;",
            $partial,
            'the opt-out attribute must short-circuit enhance() before any toolbar is built'
        );
    }

    /**
     * The point of the whole change: sorting reaches the query.
     *
     * Two pages of users, sorted by name ascending. The first page must open
     * with the alphabetically first account in the WHOLE table, which is
     * exactly what the client-side sort could never do -- it could only order
     * whichever fifteen rows had already arrived.
     */
    public function test_sorting_orders_the_whole_result_set_not_just_the_current_page(): void
    {
        $this->seedPlan();

        // Created newest-first so the default ordering is the reverse of the
        // alphabetical one. If sorting silently did nothing, the assertion
        // below would get 'Zoe' rather than 'Aaron'.
        foreach (['Zoe', 'Yusuf', 'Xavier', 'Blake', 'Aaron'] as $i => $name) {
            User::factory()->create([
                'name'       => $name,
                'email'      => strtolower($name) . '@example.com',
                'created_at' => now()->subDays($i),
            ]);
        }

        $query = User::query();
        $request = Request::create('/admin/users', 'GET', ['sort' => 'user', 'dir' => 'asc']);

        $sort = TableSort::apply($query, $request, [
            'user'    => ['name', 'email'],
            'created' => 'created_at',
        ], defaultKey: 'created');

        $this->assertSame(['key' => 'user', 'dir' => 'asc'], $sort);

        $names = $query->limit(2)->pluck('name')->all();
        $this->assertSame(['Aaron', 'Blake'], $names);
    }

    /** ?sort= is a key into an allow-list, never a column name from the URL. */
    public function test_an_unknown_sort_key_falls_back_instead_of_reaching_the_query(): void
    {
        $this->seedPlan();
        User::factory()->create(['name' => 'Only', 'email' => 'only@example.com']);

        $query = User::query();
        $request = Request::create('/admin/users', 'GET', [
            'sort' => 'password) --',
            'dir'  => 'asc',
        ]);

        $sort = TableSort::apply($query, $request, [
            'user'    => 'name',
            'created' => 'created_at',
        ], defaultKey: 'created', defaultDir: 'desc');

        $this->assertSame('created', $sort['key'], 'an unlisted key must fall back to the default');
        $this->assertSame('desc', $sort['dir']);

        // And the query still runs, which it would not if the key had been
        // interpolated into the ORDER BY. Asserted by looking for the account
        // this test made rather than by counting rows: a migration seeds system
        // accounts, so a fresh database does not start empty.
        $this->assertTrue(
            $query->pluck('email')->contains('only@example.com'),
            'the query must still execute and return its rows'
        );
    }

    /**
     * Pagination over a non-unique sort column has to be deterministic.
     *
     * Without a unique tiebreaker Postgres is free to return tied rows in a
     * different order for each page, so a row can show up on two pages and
     * another on none -- which reads to the person using the page as data
     * appearing and disappearing.
     */
    public function test_a_tied_sort_column_still_paginates_without_repeating_a_row(): void
    {
        $this->seedPlan();

        // Six accounts, all the same status: the sort column cannot separate them.
        foreach (range(1, 6) as $i) {
            User::factory()->create([
                'name'   => "Tied {$i}",
                'email'  => "tied{$i}@example.com",
                'status' => 'active',
            ]);
        }

        $ids = [];
        foreach ([1, 2, 3] as $page) {
            $query = User::query();
            $request = Request::create('/admin/users', 'GET', ['sort' => 'status', 'dir' => 'asc']);
            TableSort::apply($query, $request, [
                'status'  => 'status',
                'created' => 'created_at',
            ], defaultKey: 'created');

            $ids = array_merge($ids, $query->forPage($page, 2)->pluck('id')->all());
        }

        $this->assertCount(6, $ids);
        $this->assertSame(
            count($ids),
            count(array_unique($ids)),
            'a row appeared on more than one page, which means another appeared on none'
        );
    }

    /** Re-sorting must not strand the reader on page 7 of a new ordering. */
    public function test_the_sort_link_resets_to_the_first_page(): void
    {
        $partial = file_get_contents(
            base_path('resources/views/common/partials/sort-link.blade.php')
        );

        $this->assertStringContainsString(
            "'page' => null",
            $partial,
            'a sort link must drop ?page= or the reader lands mid-way through a different order'
        );
    }

    /**
     * The admin users page itself, rendered.
     *
     * The rest of this suite tests the pieces. This one loads the page the
     * report came from and checks the two things that were wrong on it: the
     * table opts out of the enhancer, and its headers are sort links that
     * reach the query. Rendering also catches a missing $sort in the
     * controller, which the unit-level tests cannot see.
     */
    public function test_the_admin_users_page_renders_sortable_headers_and_opts_out(): void
    {
        $this->withoutVite();
        $this->seedPlan();
        User::factory()->create(['name' => 'Rendered', 'email' => 'rendered@example.com']);

        $this->be($this->makeViewerStaff(), 'admin');

        $response = $this->get('/admin/users?sort=user&dir=asc')->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('data-server-paginated', $html,
            'the users table must tell the enhancer to stand down');
        $this->assertStringContainsString('sort=user', $html,
            'the User column must be a link that sorts in the query');
        $this->assertStringContainsString('class="et-sort-link is-active"', $html,
            'the column being sorted must be marked as active');
    }

    private function makeViewerStaff(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'table-sort-viewer'],
            ['name' => 'Table Sort Viewer', 'guard' => 'admin']
        );

        foreach (['users.view'] as $slug) {
            $perm = \App\Modules\Admin\Models\Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'group' => explode('.', $slug)[0]]
            );
            $role->permissions()->syncWithoutDetaching([$perm->id]);
        }

        return Admin::create([
            'name'     => 'Table Sort Viewer',
            'email'    => 'tablesort' . \Illuminate\Support\Str::random(8) . '@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('x'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    private function seedPlan(): void
    {
        Plan::firstOrCreate(['slug' => 'free'], [
            'name' => 'Free', 'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'grace_days' => 0, 'refund_window_days' => 0,
            'status' => 'active', 'sort_order' => 0, 'features' => [],
            'is_default' => true,
        ]);
    }
}
