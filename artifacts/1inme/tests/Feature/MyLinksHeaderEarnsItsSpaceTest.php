<?php

namespace Tests\Feature;

use App\Modules\User\Models\LinkClick;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * My Links: the header says each thing once, and the largest element on the
 * page says something the number beside it cannot.
 *
 * What stood here: two chips, a 148px ring, and three metric tiles. Between
 * them the link count appeared three times and the click total twice, all
 * above the fold, and the first actual link sat about a thousand pixels down
 * a page whose entire job is listing links. The ring showed no progress --
 * it was a circle drawn around a number that was also printed beside it.
 *
 * The figures are all still here, as one line of text. The ring's space now
 * carries seven days of clicks per day.
 */
class MyLinksHeaderEarnsItsSpaceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->user = User::factory()->create()->fresh();
    }

    private function makeLink(string $type = 'url', int $clicks = 0)
    {
        $link = $this->user->links()->create([
            'user_id'      => $this->user->id,
            'type'         => $type,
            'alias'        => 'hd'.substr(Str::random(10), 0, 10),
            'long_url'     => 'https://example.com',
            'is_active'    => true,
            'total_clicks' => $clicks,
        ]);

        $ws = $this->user->ownedWorkspaces()->first();
        if ($ws && (int) $link->workspace_id !== (int) $ws->id) {
            $link->forceFill(['workspace_id' => $ws->id])->save();
        }

        return $link->fresh();
    }

    private function page(): string
    {
        $ws = $this->user->ownedWorkspaces()->first();

        return $this->actingAs($this->user)
            ->withSession($ws ? [WorkspaceContext::SESSION_KEY => $ws->id] : [])
            ->get('/user/links')
            ->assertOk()
            ->getContent();
    }

    /** Strip scripts and styles: a class name in CSS is not a rendered tile. */
    private function visible(string $html): string
    {
        return (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html);
    }

    public function test_the_header_still_carries_every_figure(): void
    {
        $this->makeLink('biolink', 213);
        $this->makeLink('slides', 95);

        $html = $this->page();

        $this->assertStringContainsString('links-facts', $html);
        $this->assertStringContainsString('2</strong> links', $html);
        $this->assertStringContainsString('2</strong> active', $html);
        $this->assertStringContainsString('308', $html, 'the click total is missing from the header');
    }

    /**
     * The three metric tiles are gone. They repeated what the line above them
     * already said, and cost the list its place on the first screen.
     */
    public function test_the_metric_tiles_are_gone(): void
    {
        $this->makeLink();

        $visible = $this->visible($this->page());

        foreach (['Total Links', 'pulse-orb', 'bento-tile'] as $ghost) {
            $this->assertStringNotContainsString(
                $ghost,
                $visible,
                "'$ghost' is back in the header: the page is printing the same "
                .'figures a second time in boxes of their own'
            );
        }
    }

    /** The space the ring held now shows seven days of clicks. */
    public function test_the_trend_replaces_the_ring(): void
    {
        $link = $this->makeLink('biolink', 60);

        // 3 + 9 + 5 + 14 + 8 + 21 + 17 = 77 across the last seven days.
        foreach ([3, 9, 5, 14, 8, 21, 17] as $i => $n) {
            for ($k = 0; $k < $n; $k++) {
                LinkClick::create([
                    'link_id'    => $link->id,
                    'alias'      => $link->alias,
                    'clicked_at' => now()->subDays(6 - $i)->setTime(9, 0),
                ]);
            }
        }

        // And one outside the window, which must not be counted.
        LinkClick::create([
            'link_id'    => $link->id,
            'alias'      => $link->alias,
            'clicked_at' => now()->subDays(30),
        ]);

        $html = $this->page();

        $this->assertStringContainsString('linksSpark', $html, 'the sparkline is not drawn');
        $this->assertStringContainsString('+77', $html, 'the seven-day total is wrong or missing');
        $this->assertStringNotContainsString(
            'pulse-orb',
            $this->visible($html),
            'the ring is back'
        );
    }

    /** A quiet week says so, rather than drawing a flat line through zero. */
    public function test_a_week_with_no_clicks_says_so(): void
    {
        $this->makeLink();

        $html = $this->page();

        $this->assertStringContainsString('No clicks in the last 7 days', $html);
        $this->assertStringNotContainsString('linksSpark', $html);
    }

    /**
     * The filters moved out of their card into one row. They are the same
     * form fields, which is the part that matters: a prettier toolbar that
     * cannot filter is a worse page.
     */
    public function test_every_filter_survived_the_move_to_the_toolbar(): void
    {
        $this->makeLink();

        $html = $this->page();

        $this->assertStringContainsString('links-toolbar', $html);

        foreach (['search', 'type', 'project_id', 'status', 'sort'] as $field) {
            $this->assertMatchesRegularExpression(
                '/name="'.$field.'"/',
                $html,
                "the '$field' filter was lost when the filter card became a toolbar"
            );
        }
    }

    /** And they still actually filter. */
    public function test_the_toolbar_filters_still_filter(): void
    {
        $keep = $this->makeLink('biolink');
        $drop = $this->makeLink('url');

        $ws = $this->user->ownedWorkspaces()->first();
        $html = $this->actingAs($this->user)
            ->withSession($ws ? [WorkspaceContext::SESSION_KEY => $ws->id] : [])
            ->get('/user/links?type=biolink')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($keep->alias, $html);
        $this->assertStringNotContainsString($drop->alias, $html);
    }

}
