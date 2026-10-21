<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\EventsModule;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Events shipped with twelve surfaces and no way into any of them: the
 * sidebar's only event-shaped entry was a "Calendar" item that marked
 * itself active on `user.events.*` and then linked somewhere else. The
 * way in was All Links -> open the event, every time.
 *
 * Two things these tests are really guarding.
 *
 * First, the menu is rendered TWICE per page -- a desktop aside and a
 * mobile drawer about seven hundred lines apart -- and a change applied
 * to one block only is invisible until someone opens the other on a
 * phone. Every assertion here checks both, and one test reads the Blade
 * source directly so a desktop-only addition fails even if no mobile
 * render happens to exercise it.
 *
 * Second, the group mixes two things with different lifetimes. The three
 * events items die with the platform module switch (Task #6726); the two
 * calendar items do not, because those routes keep working when events
 * are off. Gating the whole group on the switch would have stranded a
 * working calendar behind a hidden header, so the split is asserted
 * rather than left to a reader of the Blade.
 */
class EventsGetTheirOwnPlaceInTheSidebarTest extends TestCase
{
    use RefreshDatabase;

    /** Items that vanish with the Events module. */
    private const EVENT_ITEMS = [
        '/user/links?type=ics',
        '/user/links-ics/create',
        '/user/events',
    ];

    /** Items that must survive it -- these routes are not events-gated. */
    private const CALENDAR_ITEMS = [
        '/user/my-calendar',
        '/user/calendar',
    ];

    private function owner(): User
    {
        // An established owner: owners pass every WorkspacePermissions
        // check, and an older onboarded_at keeps the onboarding gate from
        // answering 302 before the sidebar is ever rendered.
        return User::factory()->create(['onboarded_at' => now()->subMonths(6)])->fresh();
    }

    /**
     * Render the dashboard and split out the two nav regions so each can
     * be asserted independently.
     *
     * @return array{0:string,1:string} [desktop, mobile]
     */
    private function navRegions(User $user): array
    {
        $html = (string) $this->actingAs($user)->get('/user/dashboard')->assertOk()->getContent();

        $dStart = strpos($html, 'sidebar-v2 sidebar-shell');
        $this->assertNotFalse($dStart, 'desktop sidebar not found');
        $dEnd = strpos($html, '</aside>', $dStart);
        $this->assertNotFalse($dEnd, 'desktop sidebar end not found');

        $mStart = strpos($html, 'x-show="mobileMenu"');
        $this->assertNotFalse($mStart, 'mobile drawer not found');
        $mEnd = strpos($html, '<main', $mStart);
        $this->assertNotFalse($mEnd, 'mobile drawer end not found');

        return [
            substr($html, $dStart, $dEnd - $dStart),
            substr($html, $mStart, $mEnd - $mStart),
        ];
    }

    private function assertInBothBlocks(User $user, array $paths, bool $present): void
    {
        [$desktop, $mobile] = $this->navRegions($user);

        foreach ($paths as $path) {
            // Hrefs are absolute, so match the path plus its closing quote:
            // that keeps "/user/calendar" from matching "/user/calendars/...".
            $needle = $path . '"';

            if ($present) {
                $this->assertStringContainsString($needle, $desktop, "Desktop nav missing {$path}");
                $this->assertStringContainsString($needle, $mobile, "Mobile nav missing {$path}");
            } else {
                $this->assertStringNotContainsString($needle, $desktop, "Desktop nav leaked {$path}");
                $this->assertStringNotContainsString($needle, $mobile, "Mobile nav leaked {$path}");
            }
        }
    }

    public function test_the_group_has_a_header_in_both_nav_blocks(): void
    {
        [$desktop, $mobile] = $this->navRegions($this->owner());

        $this->assertStringContainsString('Events &amp; Calendar', $desktop);
        $this->assertStringContainsString('Events &amp; Calendar', $mobile);
    }

    public function test_every_event_surface_is_reachable_from_both_nav_blocks(): void
    {
        $this->assertInBothBlocks($this->owner(), self::EVENT_ITEMS, true);
    }

    public function test_the_calendar_items_are_there_too(): void
    {
        $this->assertInBothBlocks($this->owner(), self::CALENDAR_ITEMS, true);
    }

    public function test_the_group_opens_rather_than_starting_collapsed(): void
    {
        // A launch choice: nobody is on events yet, so the group is a
        // discovery surface rather than clutter. If someone flips this to
        // start collapsed it should be a deliberate edit, not a drive-by.
        [$desktop, $mobile] = $this->navRegions($this->owner());

        foreach (['desktop' => $desktop, 'mobile' => $mobile] as $where => $block) {
            $header = strpos($block, 'Events &amp; Calendar');
            $this->assertNotFalse($header, "no group header in {$where} nav");

            // The x-data that governs this group is the one immediately
            // preceding its header button.
            $openDecl = strrpos(substr($block, 0, $header), 'x-data="{ open:');
            $this->assertNotFalse($openDecl, "no x-data before the {$where} group header");
            $this->assertStringStartsWith(
                'x-data="{ open: true }"',
                substr($block, $openDecl, 40),
                "The {$where} Events group should start open"
            );
        }
    }

    public function test_turning_the_module_off_hides_events_but_keeps_the_calendars(): void
    {
        AppSetting::put(EventsModule::KEY, false);
        $this->assertFalse(EventsModule::enabled(), 'module switch did not take');

        $user = $this->owner();

        $this->assertInBothBlocks($user, self::EVENT_ITEMS, false);
        // The point of the split: a working calendar must not disappear
        // because the events module was switched off.
        $this->assertInBothBlocks($user, self::CALENDAR_ITEMS, true);
    }

    public function test_the_old_calendar_item_is_gone_rather_than_duplicated(): void
    {
        // The retired item in "Workspace & Tools" also linked to
        // /user/my-calendar. Re-adding it would give each block two links
        // to the same page, which is how a sidebar quietly grows a
        // duplicate nobody notices.
        [$desktop, $mobile] = $this->navRegions($this->owner());

        $this->assertSame(1, substr_count($desktop, '/user/my-calendar"'), 'duplicate My Calendar in desktop nav');
        $this->assertSame(1, substr_count($mobile, '/user/my-calendar"'), 'duplicate My Calendar in mobile nav');
    }

    public function test_both_nav_blocks_were_edited_not_just_the_desktop_one(): void
    {
        // Read the Blade itself. The rendered-HTML tests above only catch
        // a one-sided edit when both blocks are rendered and reachable;
        // this catches it at the source, which is where the mistake is
        // actually made.
        $blade = file_get_contents(resource_path('views/user/layouts/app.blade.php'));
        $this->assertNotFalse($blade);

        $routes = [
            "route('user.links.index', ['type' => 'ics'])",
            "route('user.links.ics.create')",
            "route('user.events.index')",
            "route('user.calendars.mine')",
            "route('user.calendar.index')",
        ];

        foreach ($routes as $call) {
            $this->assertSame(
                2,
                substr_count($blade, $call),
                "{$call} should appear exactly twice in app.blade.php (desktop aside + mobile drawer)"
            );
        }

        $this->assertSame(
            2,
            substr_count($blade, 'Events &amp; Calendar'),
            'The group header should appear in both nav blocks'
        );
    }
}
