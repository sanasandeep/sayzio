<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The homepage's social-proof band is only honest when it has something to
 * show. Its heading -- "Built with AI, loved by creators" -- is a claim, and
 * on a fresh install with no approved testimonials the band used to render
 * that claim above an empty strip, complete with its section divider and
 * eighty pixels of padding.
 *
 * The two marquee rows each had their own `isNotEmpty()` guard, which is why
 * this looked correct in review: nothing broken rendered, the rows simply
 * did not appear. The heading was never inside either guard.
 *
 * Both directions are asserted, because a guard that hides the band always
 * would pass a one-sided test just as well as a correct one.
 */
class HomepageProofSectionVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * `create_testimonials_table` seeds a starter set, so a migrated database
     * is not an empty one. Each test states its own testimonial situation
     * rather than inheriting whichever rows the migrations happened to write.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Testimonial::query()->delete();
        Testimonial::flushCache();
    }

    protected function tearDown(): void
    {
        Testimonial::flushCache();
        parent::tearDown();
    }

    public function test_proof_band_is_absent_when_no_testimonials_are_approved(): void
    {
        $this->get('/home/sections')
            ->assertOk()
            ->assertDontSee('id="proof"', false)
            ->assertDontSee('loved by creators');
    }

    /**
     * A testimonial that exists but is not approved is the case that matters
     * most: submissions arrive from the public form, and one sitting in the
     * queue must not put the band on the page.
     */
    public function test_proof_band_stays_absent_for_unapproved_testimonials(): void
    {
        Testimonial::create([
            'quote'        => 'Waiting on a moderator.',
            'author_name'  => 'Pending P.',
            'author_role'  => 'Creator',
            'row'          => 'top',
            'is_active'    => true,
            'status'       => 'pending',
            'sort_order'   => 1,
        ]);
        Testimonial::flushCache();

        $this->get('/home/sections')
            ->assertOk()
            ->assertDontSee('id="proof"', false)
            ->assertDontSee('Pending P.');
    }

    public function test_proof_band_renders_once_a_testimonial_is_approved(): void
    {
        Testimonial::create([
            'quote'        => 'Set my page up in an afternoon.',
            'author_name'  => 'Approved A.',
            'author_role'  => 'Photographer',
            'row'          => 'top',
            'is_active'    => true,
            'status'       => 'approved',
            'sort_order'   => 1,
        ]);
        Testimonial::flushCache();

        $this->get('/home/sections')
            ->assertOk()
            ->assertSee('id="proof"', false)
            ->assertSee('loved by creators')
            ->assertSee('Approved A.');
    }

    /**
     * The band splits its testimonials into a top row and a bottom row by the
     * `row` column. An install whose only approved testimonial sits in the
     * bottom row still has something to show, so the guard has to consider
     * both rows rather than the top one alone.
     */
    public function test_proof_band_renders_for_a_bottom_row_only_testimonial(): void
    {
        Testimonial::create([
            'quote'        => 'The second row counts too.',
            'author_name'  => 'Bottom B.',
            'author_role'  => 'Designer',
            'row'          => 'bottom',
            'is_active'    => true,
            'status'       => 'approved',
            'sort_order'   => 1,
        ]);
        Testimonial::flushCache();

        $this->get('/home/sections')
            ->assertOk()
            ->assertSee('id="proof"', false)
            ->assertSee('Bottom B.');
    }
}
