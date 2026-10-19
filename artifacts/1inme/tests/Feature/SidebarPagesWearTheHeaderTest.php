<?php

namespace Tests\Feature;

use App\Modules\User\Support\HeroCount;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Sidebar destinations use the shared page header, and their chips count
 * the right thing.
 *
 * The header was on 32 of the 49 pages the sidebar links to -- found by
 * fetching every one of them and looking, after a static route-to-view map
 * produced confident nonsense. These are the ones brought across so far.
 *
 * The chip bug this guards against is specific: ->count() on a paginator
 * returns the rows on the current page, so a header over a paginated list
 * reads "15 posts" on page one of eighty. It is wrong only for the people with
 * enough data to care, which is why it survives review.
 */
class SidebarPagesWearTheHeaderTest extends TestCase
{
    /** view path => true once it uses the shared hero */
    private const BROUGHT_ACROSS = [
        'views/user/social-proofs/index.blade.php',
        'views/user/pixels/index.blade.php',
        'views/user/followers/index.blade.php',
        'views/user/identifiers/index.blade.php',
        'views/user/notifications/index.blade.php',
        'views/user/posts/index.blade.php',
        'views/user/referrals/index.blade.php',
        // Create Link is on its own branch with its own guard
        // (CreateLinkPageHeaderTest). Listing it here would make this test fail
        // until that one merges, which is a dependency between two PRs that do
        // not otherwise touch the same files.
    ];

    /**
     * Sidebar pages still carrying a one-off header. Each line is work left,
     * not a page that opted out -- when one is converted it moves up to
     * BROUGHT_ACROSS and leaves this list.
     */
    private const STILL_TO_DO = [
        'views/user/stats/index.blade.php',
        'views/user/resume/editor.blade.php',
        'views/user/files/index.blade.php',
        'views/user/subscribers/index.blade.php',
        'views/user/leads/index.blade.php',
        'views/user/calendars/my-calendar.blade.php',
    ];

    public function test_the_converted_pages_use_the_shared_header(): void
    {
        $without = [];
        foreach (self::BROUGHT_ACROSS as $view) {
            $path = resource_path($view);
            if (! is_file($path)) {
                $without[] = $view.' (file missing)';
                continue;
            }
            if (! str_contains(file_get_contents($path), "user.partials.page-hero")) {
                $without[] = $view;
            }
        }

        sort($without);
        $this->assertSame([], $without, 'these pages were converted to the shared header and have drifted back: '.implode(', ', $without));
    }

    /**
     * Keeps the to-do list honest. A page that quietly gains the header without
     * leaving this list makes the list a lie, and the next person trusts it.
     */
    public function test_the_remaining_list_is_still_accurate(): void
    {
        $stale = [];
        foreach (self::STILL_TO_DO as $view) {
            $path = resource_path($view);
            if (! is_file($path)) {
                $stale[] = $view.' (file missing)';
                continue;
            }
            if (str_contains(file_get_contents($path), "user.partials.page-hero")) {
                $stale[] = $view.' (now has the header - move it to BROUGHT_ACROSS)';
            }
        }

        sort($stale);
        $this->assertSame([], $stale, implode('; ', $stale));
    }

    public function test_hero_count_reads_a_paginator_total_not_its_page(): void
    {
        $page = new LengthAwarePaginator(range(1, 15), 1200, 15, 1);

        $this->assertSame(15, $page->count(), 'sanity: a paginator counts its own page');
        $this->assertSame(1200, HeroCount::of($page), 'the chip must show the whole set, not the current page');
    }

    public function test_hero_count_handles_the_other_shapes_a_view_gets(): void
    {
        $this->assertSame(0, HeroCount::of(null));
        $this->assertSame(0, HeroCount::of([]));
        $this->assertSame(3, HeroCount::of([1, 2, 3]));
        $this->assertSame(2, HeroCount::of(new Collection(['a', 'b'])));
        $this->assertSame(4, HeroCount::of(new Paginator(range(1, 4), 10, 1)));
    }

    public function test_hero_count_labels_one_without_an_s(): void
    {
        $this->assertSame('1 follower', HeroCount::label([1], 'follower'));
        $this->assertSame('2 followers', HeroCount::label([1, 2], 'follower'));
        $this->assertSame('0 followers', HeroCount::label([], 'follower'));
        $this->assertSame('1,200 trackers', HeroCount::label(new LengthAwarePaginator([], 1200, 15, 1), 'tracker'));
    }
}
