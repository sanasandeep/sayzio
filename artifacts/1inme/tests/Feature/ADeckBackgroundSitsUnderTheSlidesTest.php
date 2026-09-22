<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The one type where the unit is not the page.
 *
 * Every other page in this rollout has one background. A slide deck has
 * one per SLIDE -- colour, image, gradient, crossfade slideshow, video or
 * template, each configured on the slide itself -- and the deck theme's
 * `background` is only the colour a slide falls back to when it sets none.
 *
 * So "slides can use the 941 looks" had two possible meanings, and the
 * bigger one is not obviously better: putting the whole picker on every
 * slide would be a per-slide data model change, a per-slide editor, and a
 * published-snapshot change, to add a sixth way of setting something
 * slides can already set five ways.
 *
 * The picker applies to the DECK BASE instead: the canvas the deck sits
 * on. A slide that sets its own background still paints over it, which is
 * the entire point of per-slide backgrounds. A slide that sets none goes
 * transparent so the canvas shows through -- and that transparency is the
 * one genuinely risky edit here, because it changes what an unset slide
 * renders. It only happens when a deck background was chosen.
 */
class ADeckBackgroundSitsUnderTheSlidesTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create();
    }

    /**
     * A published deck, optionally with a chosen page background.
     *
     * @param  array  $slides  per-slide payloads
     */
    private function deck(array $slides, array $biolink = []): string
    {
        $owner = $this->owner();

        // A deck lives on a BIOLINK toggled into slides mode, not on a
        // link of type 'slides' -- so the deck's page background is the
        // same settings.biolink namespace every other page type uses.
        // workspace_id must match what the workspace.scope middleware
        // resolves at request time, or route-model binding filters the row
        // out under acting-as auth and every endpoint 404s.
        $ws = app(WorkspaceContext::class)->resolve($owner);

        /** @var Link $link */
        $link = Link::create([
            'user_id'      => $owner->id,
            'workspace_id' => $ws?->id,
            'type'         => 'biolink',
            'alias'        => Link::generateAlias(),
            'title'        => 'Deck',
            'is_active'    => true,
        ]);

        $this->actingAs($owner)
            ->postJson("/user/links/{$link->id}/slides/toggle", ['enabled' => true])
            ->assertOk();

        if ($biolink !== []) {
            $link->refresh();
            $link->settings = ['biolink' => array_merge($link->settings['biolink'] ?? [], $biolink)];
            $link->save();
        }

        $block = BiolinkBlock::create([
            'link_id'    => $link->id,
            'type'       => 'paragraph',
            'sort_order' => 1,
            'is_active'  => true,
            'settings'   => ['text' => 'Body copy'],
        ]);
        foreach ($slides as $i => $s) {
            $slides[$i] = array_merge(['title' => 'Slide', 'block_ids' => [$block->id]], $s);
        }

        $this->actingAs($owner)->postJson('/user/links/'.$link->id.'/slides', [
            'is_published' => true,
            'settings'     => [
                'theme'        => ['background' => '#0f172a', 'accent' => '#8b5cf6', 'text' => '#f8fafc'],
                'transition'   => 'slide',
                'auto_advance' => 0,
                'loop'         => false,
            ],
            'slides' => $slides,
        ])->assertOk();

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $this->assertStringContainsString('sl-deck', $html,
            'this must be the slides renderer, not the biolink fallback');

        return $html;
    }

    /** Nothing chosen: an unset slide still falls back to the deck colour. */
    public function test_an_unchosen_deck_renders_exactly_as_before(): void
    {
        $html = $this->deck([
            ['background' => []],
            ['background' => ['type' => 'color', 'color' => '#334155']],
        ]);

        $this->assertStringContainsString('background:#0f172a', $html,
            'an unset slide must still fall back to the deck theme colour');
        $this->assertStringNotContainsString('style="background: transparent;"', $html,
            'no SLIDE should go transparent until a deck background is chosen');
        // Careful: the deck's own per-slide layer is `sl-bg-layer`, which
        // contains this string. Only the shared layer counts.
        $this->assertStringNotContainsString('class="bg-page-fixed bg-layer"', $html,
            'no shared background layer until one is chosen');
    }

    /** A chosen background becomes the canvas the deck sits on. */
    public function test_a_chosen_background_becomes_the_deck_canvas(): void
    {
        $html = $this->deck(
            [['background' => []]],
            ['background_type' => 'color', 'background_color' => '#c8f7c5']
        );

        $this->assertStringContainsString('#c8f7c5', $html,
            'the chosen colour must paint the deck canvas');
        $this->assertStringContainsString('class="bg-page-fixed bg-layer"', $html,
            'the shared background layer must render');
        $this->assertStringContainsString('style="background: transparent;"', $html,
            'a slide with no background of its own must let the canvas through');

        // And the deck shell must not sit opaquely on top of the layer.
        $this->assertStringContainsString('.sl-deck { background: transparent; }', $html);
    }

    /**
     * A slide that sets its own background still wins. This is the property
     * that makes the change additive rather than a replacement.
     */
    public function test_a_slide_with_its_own_background_still_paints_over_the_canvas(): void
    {
        $html = $this->deck(
            [
                ['background' => ['type' => 'color', 'color' => '#334155']],
                ['background' => []],
            ],
            ['background_type' => 'color', 'background_color' => '#c8f7c5']
        );

        $this->assertStringContainsString('background:#334155', $html,
            "a slide's own colour must survive a chosen deck background");
        $this->assertStringContainsString('style="background: transparent;"', $html,
            'while the slide that set none lets the canvas through');
    }

    /** The richer per-slide types are untouched by any of this. */
    public function test_the_per_slide_background_types_still_work(): void
    {
        $html = $this->deck(
            [
                ['background' => ['type' => 'gradient', 'from_color' => '#ff0000', 'to_color' => '#0000ff']],
                ['background' => ['type' => 'image', 'image_url' => 'https://cdn.example.com/s.jpg']],
            ],
            ['background_type' => 'color', 'background_color' => '#c8f7c5']
        );

        $this->assertStringContainsString('linear-gradient(135deg, #ff0000, #0000ff)', $html,
            'per-slide gradients are a different feature and must keep working');
        $this->assertStringContainsString('https://cdn.example.com/s.jpg', $html,
            'per-slide images likewise');
    }

    /**
     * The deck's own "Default slide background" control now reaches the
     * slides it is the default FOR.
     *
     * Found while building the canvas: the published snapshot fabricated
     * ['type'=>'color','color'=>'#0f172a'] for every slide that had no
     * background, so by render time nothing was ever unset. The deck theme
     * colour was therefore dead -- the editor's control wrote a value the
     * renderer could never reach, because `$bgConf['color'] ?? $bg` always
     * found a colour. Changing it did nothing to a published deck.
     */
    public function test_the_deck_default_colour_reaches_slides_that_set_none(): void
    {
        $owner = User::factory()->create();
        $ws    = app(WorkspaceContext::class)->resolve($owner);

        /** @var Link $link */
        $link = Link::create([
            'user_id' => $owner->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
            'alias' => Link::generateAlias(), 'title' => 'Deck', 'is_active' => true,
        ]);
        $this->actingAs($owner)
            ->postJson("/user/links/{$link->id}/slides/toggle", ['enabled' => true])
            ->assertOk();

        $block = BiolinkBlock::create([
            'link_id' => $link->id, 'type' => 'paragraph', 'sort_order' => 1,
            'is_active' => true, 'settings' => ['text' => 'Body'],
        ]);

        $this->actingAs($owner)->postJson('/user/links/'.$link->id.'/slides', [
            'is_published' => true,
            'settings'     => [
                // A deck colour that is NOT the old hardcoded navy.
                'theme'        => ['background' => '#3b0764', 'accent' => '#8b5cf6', 'text' => '#f8fafc'],
                'transition'   => 'slide',
                'auto_advance' => 0,
                'loop'         => false,
            ],
            'slides' => [['title' => 'S', 'block_ids' => [$block->id], 'background' => []]],
        ])->assertOk();

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $this->assertStringContainsString('style="background:#3b0764;"', $html,
            "the deck's default colour must reach a slide that sets none");
        $this->assertStringNotContainsString('style="background:#0f172a;"', $html,
            'the snapshot must stop baking the old hardcoded navy over it');
    }

    /** The deck editor already reaches Appearance; it must keep doing so. */
    public function test_the_slides_editor_reaches_the_picker(): void
    {
        $editor = file_get_contents(
            base_path('resources/views/user/links/slides/editor.blade.php')
        );

        $this->assertStringContainsString('editor-header', $editor,
            'the shared header is what carries the Settings tab to Appearance');
        $this->assertStringNotContainsString('biolink-background-card', $editor,
            'the deck editor must not grow its own copy of the picker');
    }
}
