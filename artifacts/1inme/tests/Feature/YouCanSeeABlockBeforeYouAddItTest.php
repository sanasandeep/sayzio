<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sana, from customers: "Add blocks... users dont understand wat block
 * mean".
 *
 * The palette has never shown anyone what a block looks like. You read a
 * name, you guess, you add it, you look at the result, and if it was the
 * wrong guess you delete it and guess again. On a page with 139 types to
 * choose from, where several of the names are indistinguishable without
 * context -- Text, Formatted text, Markdown -- that loop is the product.
 *
 * This endpoint renders one TYPE as it would look on THIS page, so the
 * palette flyout can answer the question before the guess.
 *
 * The thing worth testing is not that it returns 200. It is that the
 * sample is built the same way a real block is built. A preview assembled
 * by a second, parallel recipe drifts the moment either side changes, and
 * a preview that lies costs more trust than no preview at all -- so the
 * tests below pin the two places drift would show first: aliases must
 * preview as themselves rather than collapsing to their canonical type,
 * and the frame must wear the creator's own page colours rather than a
 * neutral stage.
 */
class YouCanSeeABlockBeforeYouAddItTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    private function link(array $biolinkSettings = []): Link
    {
        return Link::create([
            'user_id'  => $this->user->id,
            'type'     => 'biolink',
            'alias'    => 'bl'.fake()->unique()->numerify('#####'),
            'title'    => 'My Bio',
            'is_active' => true,
            'settings' => $biolinkSettings ? ['biolink' => $biolinkSettings] : null,
        ]);
    }

    private function previewUrl(Link $link, string $type): string
    {
        return route('user.links.blocks.typePreview', [$link, $type]);
    }

    /** Types the palette offers, by the palette's own rule. */
    private function paletteTypes(): array
    {
        $out = [];
        foreach (BiolinkBlock::TYPES as $slug => $info) {
            if (!empty($info['system']) || ($info['category'] ?? '') === 'verified') {
                continue;
            }
            $out[] = $slug;
        }

        return $out;
    }

    public function test_a_creator_can_preview_a_type_on_their_own_page(): void
    {
        $link = $this->link();

        $this->actingAs($this->user)
            ->get($this->previewUrl($link, 'heading'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('data-block-type="heading"', false);
    }

    public function test_the_preview_wears_this_pages_own_colours(): void
    {
        $link = $this->link([
            'background_type'  => 'color',
            'background_color' => '#eceef1',
            'font_color'       => '#101828',
        ]);

        $this->actingAs($this->user)
            ->get($this->previewUrl($link, 'heading'))
            ->assertOk()
            ->assertSee('#eceef1', false)
            ->assertSee('#101828', false);
    }

    /**
     * link_big, cta_button and featured_pin all canonicalise to `link`.
     * Previewing the canonical type would render all four as the same
     * plain button -- which is the exact confusion the flyout exists to
     * clear up, reproduced faithfully inside the fix.
     */
    public function test_an_alias_previews_as_itself_not_as_its_canonical_type(): void
    {
        $link = $this->link();

        foreach (['link', 'link_big', 'cta_button', 'featured_pin'] as $type) {
            $this->actingAs($this->user)
                ->get($this->previewUrl($link, $type))
                ->assertOk()
                ->assertSee('data-block-type="'.$type.'"', false);
        }
    }

    /**
     * The types nearly every page uses. If one of these falls through to
     * the "only fills in once it is on your page" frame, a partial broke.
     */
    public function test_the_everyday_types_render_for_real(): void
    {
        $link = $this->link();

        $fellBack = [];
        foreach (['link', 'heading', 'paragraph', 'paragraph_rich', 'list', 'divider', 'spacer', 'image', 'faq', 'testimonials'] as $type) {
            $html = $this->actingAs($this->user)->get($this->previewUrl($link, $type))->assertOk()->getContent();
            if (!str_contains($html, 'data-block-type="'.$type.'"')) {
                $fellBack[] = $type;
            }
        }

        $this->assertSame([], $fellBack, 'These fell back to the placeholder frame: '.implode(', ', $fellBack));
    }

    public function test_every_palette_type_answers_rather_than_erroring(): void
    {
        $link = $this->link();

        $broken = [];
        foreach ($this->paletteTypes() as $type) {
            $status = $this->actingAs($this->user)->get($this->previewUrl($link, $type))->getStatusCode();
            if ($status !== 200) {
                $broken[] = $type.' => '.$status;
            }
        }

        $this->assertSame([], $broken, "Palette types that did not answer:\n  ".implode("\n  ", $broken));
    }

    public function test_previewing_writes_nothing(): void
    {
        $link = $this->link();
        $before = BiolinkBlock::count();

        foreach (['link', 'heading', 'card', 'product'] as $type) {
            $this->actingAs($this->user)->get($this->previewUrl($link, $type))->assertOk();
        }

        $this->assertSame($before, BiolinkBlock::count(), 'A preview created a real block.');
    }

    public function test_someone_elses_page_is_refused(): void
    {
        $stranger = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($stranger);
        $theirLink = Link::create([
            'user_id'   => $stranger->id,
            'type'      => 'biolink',
            'alias'     => 'bl'.fake()->unique()->numerify('#####'),
            'title'     => 'Not yours',
            'is_active' => true,
        ]);

        app(WorkspaceContext::class)->resolve($this->user);

        $this->actingAs($this->user)
            ->get($this->previewUrl($theirLink, 'heading'))
            ->assertForbidden();
    }

    public function test_a_type_that_does_not_exist_is_refused(): void
    {
        $link = $this->link();

        $this->actingAs($this->user)
            ->get($this->previewUrl($link, 'not_a_real_type'))
            ->assertNotFound();
    }

    public function test_a_signed_out_visitor_cannot_preview(): void
    {
        $link = $this->link();

        $response = $this->get($this->previewUrl($link, 'heading'));

        $this->assertNotSame(200, $response->getStatusCode(), 'The preview answered a signed-out request.');
    }
}
