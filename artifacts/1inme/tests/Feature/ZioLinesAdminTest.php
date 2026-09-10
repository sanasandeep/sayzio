<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Models\ZioLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Admin -> Zio's lines: the speech-bubble copy on the homepage hero, which
 * used to be hardcoded in the blade.
 *
 * The two things worth pinning here are not the CRUD (that is Laravel's) but
 * the pair of promises the hero depends on:
 *
 *   1. An edit is visible on the NEXT page load, not within five minutes.
 *      The hero reads a cached list, so every write has to flush it; without
 *      that the admin saves, reloads the homepage, sees the old line and
 *      reasonably concludes the feature is broken.
 *
 *   2. Hiding every line falls back to the shipped copy rather than rendering
 *      an empty bubble. "Untick all four" is one click away and Zio should
 *      not end up standing there mid-gesture with nothing to say.
 */
class ZioLinesAdminTest extends TestCase
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
            'name'     => 'Admin ' . Str::random(4),
            'email'    => 'a' . Str::random(8) . '@admin.test',
            'password' => Hash::make('x'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    public function test_migration_seeds_the_four_lines_that_were_hardcoded(): void
    {
        // The blade shipped with these; the table has to start life holding
        // them or the homepage copy silently changes on deploy.
        $this->assertSame(4, ZioLine::count());
        $this->assertSame(
            [
                "Hi, I'm Zio 👋",
                'I build your link page, QR codes and short links.',
                'Then I answer your visitors — and pick up your calls.',
                'Free forever. Want to try me?',
            ],
            ZioLine::activeTexts()
        );
    }

    public function test_page_requires_settings_manage(): void
    {
        $role = Role::firstOrCreate(['slug' => 'staff-none'], ['name' => 'Staff', 'guard' => 'admin']);
        $nobody = Admin::create([
            'name' => 'No Perms', 'email' => 'n' . Str::random(6) . '@admin.test',
            'password' => Hash::make('x'), 'role_id' => $role->id, 'status' => 'active',
        ]);

        $this->actingAs($nobody, 'admin')->get('/admin/zio-lines')->assertForbidden();
    }

    public function test_page_renders_every_line_for_a_permitted_admin(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/zio-lines')
            ->assertOk()
            ->assertSee('Free forever. Want to try me?', false)
            ->assertSee('I build your link page, QR codes and short links.', false);
    }

    public function test_admin_can_add_a_line_and_it_reaches_the_homepage_immediately(): void
    {
        // Warm the cache first, so a stale read would actually be observed --
        // testing the flush against a cold cache would prove nothing.
        ZioLine::activeTexts();

        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/zio-lines', ['text' => 'I also answer the phone.', 'is_active' => 1, 'sort_order' => 40])
            ->assertRedirect(route('admin.zio-lines.index'));

        $this->assertContains('I also answer the phone.', ZioLine::activeTexts());
        $this->get('/')->assertOk()->assertSee('I also answer the phone.', false);
    }

    public function test_editing_a_line_is_visible_on_the_next_load(): void
    {
        $line = ZioLine::ordered()->first();
        ZioLine::activeTexts();

        $this->actingAs($this->admin(), 'admin')
            ->put('/admin/zio-lines/' . $line->id, ['text' => 'Hello, I am Zio.', 'is_active' => 1, 'sort_order' => 0])
            ->assertRedirect();

        $this->assertContains('Hello, I am Zio.', ZioLine::activeTexts());
        $this->assertNotContains("Hi, I'm Zio 👋", ZioLine::activeTexts());
    }

    public function test_hiding_a_line_removes_it_without_deleting_it(): void
    {
        $line = ZioLine::ordered()->first();
        ZioLine::activeTexts();

        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/zio-lines/' . $line->id . '/toggle')
            ->assertRedirect();

        $this->assertNotContains($line->text, ZioLine::activeTexts());
        $this->assertDatabaseHas('zio_lines', ['id' => $line->id, 'is_active' => false]);
    }

    public function test_hiding_every_line_falls_back_to_the_shipped_copy(): void
    {
        ZioLine::query()->update(['is_active' => false]);
        ZioLine::flushCache();

        $this->assertSame([], ZioLine::activeTexts());

        // The hero must not render an empty bubble.
        $this->get('/')->assertOk()->assertSee('Free forever. Want to try me?', false);
    }

    public function test_a_line_too_long_for_the_bubble_is_rejected(): void
    {
        // The bubble is a fixed ellipse: text is laid inside a curve rather
        // than measured around it, so length is a real constraint, not style.
        $this->actingAs($this->admin(), 'admin')
            ->from('/admin/zio-lines')
            ->post('/admin/zio-lines', ['text' => str_repeat('a', ZioLine::MAX_LENGTH + 1), 'is_active' => 1])
            ->assertSessionHasErrors('text');

        $this->assertSame(4, ZioLine::count());
    }

    public function test_deleting_a_line_drops_it_from_the_homepage(): void
    {
        $line = ZioLine::ordered()->first();
        ZioLine::activeTexts();

        $this->actingAs($this->admin(), 'admin')
            ->delete('/admin/zio-lines/' . $line->id)
            ->assertRedirect();

        $this->assertNotContains($line->text, ZioLine::activeTexts());
        $this->assertDatabaseMissing('zio_lines', ['id' => $line->id]);
    }

    public function test_lines_come_back_in_sort_order(): void
    {
        ZioLine::query()->delete();
        ZioLine::create(['text' => 'third',  'is_active' => true, 'sort_order' => 30]);
        ZioLine::create(['text' => 'first',  'is_active' => true, 'sort_order' => 10]);
        ZioLine::create(['text' => 'second', 'is_active' => true, 'sort_order' => 20]);
        ZioLine::flushCache();

        $this->assertSame(['first', 'second', 'third'], ZioLine::activeTexts());
    }
}
