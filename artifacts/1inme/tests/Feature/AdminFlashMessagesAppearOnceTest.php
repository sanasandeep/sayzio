<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Models\SiteStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * "Stat updated." twice, one under the other, on every save screen in the
 * admin.
 *
 * The admin layout prints session('success'), session('error') and
 * session('info') at the top of <main> for every page it wraps. Screens were
 * also printing their own copy -- 79 of them, 107 blocks -- so a save showed
 * the same sentence twice in two slightly different boxes, which reads like
 * the save happened twice.
 *
 * The layout is the one that is guaranteed to be there, so the per-page
 * copies went. Two tests: the rendered page carries one message, and the
 * views do not grow the duplicate back.
 */
class AdminFlashMessagesAppearOnceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'staff-settings-manage'],
            ['name' => 'Staff (settings.manage)', 'guard' => 'admin']
        );
        $perm = Permission::firstOrCreate(
            ['slug' => 'settings.manage'],
            ['name' => 'settings.manage', 'group' => 'settings']
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        return Admin::create([
            'name'     => 'Admin '.Str::random(4),
            'email'    => 'a'.Str::random(8).'@admin.test',
            'password' => Hash::make('x'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    public function test_a_save_shows_its_message_once(): void
    {
        $stat = SiteStat::create([
            'label' => 'Users Worldwide', 'value' => '3,75,000', 'suffix' => '+',
            'icon' => 'fa-users', 'color' => '#3d6bff', 'is_active' => true, 'sort_order' => 1,
        ]);

        $html = $this->actingAs($this->admin(), 'admin')
            ->followingRedirects()
            ->put('/admin/site-stats/'.$stat->id, [
                'label'      => 'Creators & Businesses Served',
                'value'      => '3,75,000',
                'suffix'     => '+',
                'icon'       => 'fa-users',
                'color'      => '#3d6bff',
                'is_active'  => 1,
                'sort_order' => 1,
            ])
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            substr_count($html, 'Stat updated.'),
            'the save confirmation is rendered more than once -- the page is '
            .'printing its own copy on top of the one the admin layout prints '
            .'for every screen'
        );
    }

    /**
     * The rule, rather than one instance of it: a screen wrapped by the admin
     * layout must not print the flash itself.
     *
     * Views outside that layout (the password-reset page builds its own
     * document) are exempt -- there is no layout above them to do it.
     */
    public function test_no_admin_view_prints_a_flash_the_layout_already_prints(): void
    {
        $root    = resource_path('views/admin');
        $layout  = $root.'/layouts/app.blade.php';
        $guilty  = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            if ($file->getPathname() === $layout) {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            if (! preg_match("/@extends\(['\"]admin\.layouts\.app['\"]/", $source)) {
                continue;
            }

            if (preg_match("/@if\s*\(\s*session\(['\"](success|error|info)['\"]\)\s*\)/", $source)) {
                $guilty[] = str_replace($root.'/', '', $file->getPathname());
            }
        }

        $this->assertSame(
            [],
            $guilty,
            "These views print a flash message the admin layout already prints, "
            ."so a save shows it twice:\n  ".implode("\n  ", $guilty)
            ."\nDelete the block; the layout covers every page it wraps."
        );
    }
}
