<?php

namespace Tests\Feature;

use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Seven tabs manage one event, and each had built its own header.
 *
 * They had drifted into three different page widths -- none on
 * Overview, max-w-3xl on Edit/People/Check-in/Messages, max-w-7xl on
 * Tickets/RSVPs -- so the page physically resized as the organiser
 * moved between tabs of the SAME event. Only Edit carried status
 * chips, so the tab bar jumped vertically too.
 *
 * None of that was visible to a PHP test, which is exactly why it
 * survived. These assertions are coarse on purpose: they pin the
 * things that drifted (one width, one header, chips present) rather
 * than trying to describe the design.
 *
 * Rendering every tab through its real route is half the point. Three
 * template breakages shipped this week with a green suite because
 * nothing ever asked the view to compile.
 */
class EveryEventTabWearsTheSameHeaderTest extends TestCase
{
    use RefreshDatabase;

    /** The six event-only tabs. Overview is deliberately absent: it is
     *  the generic link page shared with biolinks and resumes, its hero
     *  belongs to all of them, and it was already full width. */
    public static function tabs(): array
    {
        return [
            'Edit'     => ['user.links.ics.edit'],
            'Tickets'  => ['user.links.ics.tickets'],
            'RSVPs'    => ['user.links.rsvps.index'],
            'People'   => ['user.links.ics.people'],
            'Check-in' => ['user.links.ics.checkin'],
            'Messages' => ['user.links.ics.broadcast'],
        ];
    }

    private function owner(): User
    {
        $u = User::factory()->create(['onboarded_at' => now()->subMonths(6)]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);

        return $u->fresh();
    }

    private function event(User $host): Link
    {
        $link = Link::create([
            'user_id' => $host->id, 'type' => 'ics',
            'alias' => 'evt' . Str::random(8), 'title' => 'Founder Sessions', 'is_active' => true,
        ]);

        IcsData::create([
            'link_id' => $link->id, 'event_name' => 'Founder Sessions',
            'start_date' => now()->addDay()->toDateTimeString(),
            'end_date'   => now()->addDay()->addHours(4)->toDateTimeString(),
            'timezone'   => 'Asia/Kolkata',
        ]);

        return $link->fresh();
    }

    private function render(string $route): string
    {
        $owner = $this->owner();
        $event = $this->event($owner);

        return $this->actingAs($owner)
            ->get(route($route, $event, false))
            ->assertOk()
            ->getContent();
    }

    #[DataProvider('tabs')]
    public function test_the_tab_renders_at_all(string $route): void
    {
        // A template that does not compile is a 500, and that is the
        // failure this week keeps producing.
        $this->assertNotSame('', trim($this->render($route)));
    }

    #[DataProvider('tabs')]
    public function test_the_tab_is_full_width_like_every_other_page(string $route): void
    {
        $body = $this->render($route);

        // The three widths that had drifted apart. Matching the inner
        // content is not the point -- what matters is that the page
        // shell no longer pins itself narrower than its neighbours.
        $this->assertStringNotContainsString('<div class="max-w-3xl mx-auto">', $body);
        $this->assertStringNotContainsString('<div class="max-w-7xl mx-auto">', $body);
    }

    #[DataProvider('tabs')]
    public function test_the_tab_wears_the_shared_header(string $route): void
    {
        $body = $this->render($route);

        // The hero and the tab bar both render, and the status chip row
        // exists on every tab -- that row is what stopped the tab bar
        // moving vertically between tabs.
        $this->assertStringContainsString('page-hero', $body);
        $this->assertStringContainsString('evtab', $body);
        $this->assertStringContainsString('hero-chip', $body);
    }

    /**
     * The views this change actually touched, checked at the source
     * rather than in rendered output.
     *
     * The first version of this asserted against the rendered page and
     * failed on all six tabs -- for the quick-create widget in the
     * shared layout, which is not part of this change and is not even
     * broken: it writes var(--bg-input, rgba(255,255,255,0.05)), where
     * the literal is an unreachable fallback behind a token that is
     * defined in both themes. Scanning rendered HTML means scanning
     * every partial the layout pulls in, which is a different and much
     * larger claim than the one worth making here.
     */
    public static function views(): array
    {
        return [
            'broadcast'     => ['broadcast'],
            'event-checkin' => ['event-checkin'],
            'event-people'  => ['event-people'],
            'event-tickets' => ['event-tickets'],
            'rsvps'         => ['rsvps'],
            'edit-ics'      => ['edit-ics'],
        ];
    }

    /**
     * Drop every var(...) group, parens balanced, so a literal sitting
     * in a fallback slot does not read as a hardcoded value.
     */
    private function withoutCssVarFallbacks(string $css): string
    {
        $out = '';
        $i = 0;
        $len = strlen($css);

        while ($i < $len) {
            if (substr($css, $i, 4) === 'var(') {
                $depth = 1;
                $i += 4;
                while ($i < $len && $depth > 0) {
                    if ($css[$i] === '(') { $depth++; }
                    if ($css[$i] === ')') { $depth--; }
                    $i++;
                }
                continue;
            }

            $out .= $css[$i];
            $i++;
        }

        return $out;
    }

    #[DataProvider('views')]
    public function test_no_form_control_hardcodes_the_dark_palette(string $view): void
    {
        $source = file_get_contents(resource_path("views/user/links/{$view}.blade.php"));

        $this->assertNotFalse($source, "could not read {$view}.blade.php");

        preg_match_all('~<(?:input|textarea|select)\b[^>]*>~s', $source, $m);

        // rgba(255,255,255,0.04) on a white card is white, and a
        // 10%-white border on it is nothing. Those are the DARK theme's
        // token values pasted inline, so the field only exists in dark
        // mode -- an organiser in light mode cannot tell it is there
        // until they click into it.
        $hardcoded = array_values(array_filter(
            $m[0],
            fn (string $tag) => str_contains($this->withoutCssVarFallbacks($tag), 'rgba(255,255,255')
        ));

        $this->assertSame([], $hardcoded, sprintf(
            '%s: %d form control(s) still style themselves for dark mode only: %s',
            $view,
            count($hardcoded),
            implode(' | ', array_map(fn ($t) => mb_substr($t, 0, 90), $hardcoded))
        ));
    }

    public function test_uniformity_did_not_delete_the_numbers_that_matter(): void
    {
        // Ticketing and RSVPs put real counts in their hero chips. A
        // shared header is not a licence to throw those away, so they
        // are still there alongside the common ones.
        $owner = $this->owner();
        $event = $this->event($owner);

        $tickets = $this->actingAs($owner)
            ->get(route('user.links.ics.tickets', $event, false))->assertOk()->getContent();

        $this->assertStringContainsString('gross', $tickets);
        $this->assertStringContainsString('sold', $tickets);
        $this->assertStringContainsString('checked in', $tickets);

        $rsvps = $this->actingAs($owner)
            ->get(route('user.links.rsvps.index', $event, false))->assertOk()->getContent();

        $this->assertStringContainsString('responses', $rsvps);
        $this->assertStringContainsString('Export CSV', $rsvps);
    }
}
