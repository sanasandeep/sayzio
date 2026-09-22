<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Support\BrandKitPageTemplates;
use App\Modules\User\Support\PaidPageTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Two page types that already had a page colour -- inside something bigger.
 *
 * The rollout plan called these "types that own a background concept", and
 * for Brand Kit that is roughly true: its four chrome themes are a page
 * colour and an ink colour. For a Paid Page it is not true at all. One of
 * its templates is TWENTY-FIVE design tokens -- hero background, accent,
 * card surface, card text, card border, card input, corner radius, font,
 * hero style, ambient pattern, motion -- and `page_bg` is one of them. The
 * card colours are chosen AGAINST that page colour.
 *
 * So migrating page_bg onto background_* and retiring the picker, which is
 * what "migrate the ones that own a background" would have meant, takes one
 * token out of a designed set and leaves the other twenty-four pointing at
 * a background that is no longer there. Cards designed for a dark template,
 * on whatever pale gradient the creator picks.
 *
 * The background is therefore an OVERRIDE of that single token. Everything
 * else the template sets survives, the template pickers stay, and -- as
 * everywhere else in this rollout -- a page with nothing chosen renders
 * exactly as it always has.
 */
class ABackgroundOverridesATemplateWithoutBreakingItTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $type, array $biolink = []): string
    {
        $user = User::create([
            'name'     => 'ov'.Str::random(4),
            'email'    => 'ov'.Str::random(10).'@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            // The paid-page view builds a creator-profile tip URL from the
            // owner's handle and 500s without one.
            'handle'   => 'h'.Str::random(10),
        ]);

        /** @var Link $link */
        $link = $user->links()->create([
            'user_id'   => $user->id,
            'type'      => $type,
            'alias'     => 'ov'.substr(Str::random(10), 0, 10),
            'is_active' => true,
        ]);

        $settings = $type === 'paid_page'
            ? ['paid_page' => ['template' => PaidPageTemplates::DEFAULT_ID]]
            : ['brand_kit' => ['template' => 'studio']];

        if ($biolink !== []) {
            $settings['biolink'] = $biolink;
        }
        $link->settings = $settings;
        $link->save();

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $marker = $type === 'paid_page' ? '--pp-page-bg' : '--bk-page-bg';
        $this->assertStringContainsString($marker, $html,
            "a {$type} link rendered some other view; this test would prove nothing");

        return $html;
    }

    /** The thing this change was nearly built on top of, stated as a fact. */
    public function test_a_paid_page_template_is_a_design_system_not_a_background(): void
    {
        $template = PaidPageTemplates::get(PaidPageTemplates::DEFAULT_ID);

        $this->assertGreaterThan(15, count($template),
            'if a template is ever just a background, the override in this change '
            .'should be reconsidered as a migration');

        foreach (['page_bg', 'hero_bg', 'accent', 'card_bg', 'card_text', 'card_border', 'font'] as $token) {
            $this->assertArrayHasKey($token, $template,
                "{$token} is designed against page_bg; replacing page_bg alone would strand it");
        }
    }

    /** Nothing chosen: the template renders exactly as it always did. */
    public function test_an_unchosen_page_renders_its_template_untouched(): void
    {
        $paid = $this->page('paid_page');
        $this->assertStringContainsString(
            '--pp-page-bg: '.PaidPageTemplates::get(PaidPageTemplates::DEFAULT_ID)['page_bg'],
            $paid,
            "an unedited paid page must keep its template's page colour"
        );
        $this->assertStringContainsString('background: var(--pp-page-bg)', $paid,
            'and must still paint from that token');
        $this->assertStringNotContainsString('bg-layer', $paid);

        $brand = $this->page('brand_kit');
        $this->assertStringContainsString('background:var(--bk-page-bg)', $brand);
        $this->assertStringNotContainsString('bg-layer', $brand);
    }

    /** A chosen background replaces the page colour, and only that. */
    public function test_a_chosen_background_overrides_only_the_page_colour(): void
    {
        $html = $this->page('paid_page', [
            'background_type'  => 'color',
            'background_color' => '#123456',
        ]);

        // The background took over the page.
        $this->assertStringContainsString('#123456', $html);
        $this->assertStringContainsString('bg-layer', $html);
        $this->assertStringNotContainsString('background: var(--pp-page-bg)', $html,
            'the page must paint from the chosen background, not the template token');

        // ...and every other token the template sets is still doing its job.
        $template = PaidPageTemplates::get(PaidPageTemplates::DEFAULT_ID);
        foreach (['hero_bg', 'accent', 'card_bg', 'card_text', 'card_border'] as $token) {
            $this->assertStringContainsString((string) $template[$token], $html,
                "{$token} must survive a background override; the cards were designed with it");
        }
    }

    /** Same for the brand kit, whose themes are smaller but still paired. */
    public function test_a_brand_kit_keeps_its_theme_ink_when_the_background_changes(): void
    {
        $html = $this->page('brand_kit', [
            'background_type'  => 'color',
            'background_color' => '#123456',
        ]);

        $this->assertStringContainsString('#123456', $html);
        $this->assertStringContainsString('bg-layer', $html);

        $theme = BrandKitPageTemplates::all()['studio'];

        // A brand kit theme is a PAIR -- page surface and the ink chosen to
        // read on it -- which is why the ink must survive a background
        // change even though the surface it was picked for has gone.
        $this->assertArrayHasKey('text', $theme);
        $this->assertStringContainsString((string) $theme['text'], $html,
            "the theme's ink must survive: it was chosen to read on this kit, not on the old page colour");
    }

    /**
     * A paid page's own image/video override still wins.
     *
     * It paints its own full-bleed layer over everything, which is what it
     * has always done. The two are not rivals -- the editor now presents
     * the shared picker inside that same "your own background" section
     * rather than as a second offer somewhere else.
     */
    public function test_a_custom_image_still_wins_over_a_chosen_background(): void
    {
        $user = User::create([
            'name' => 'ov'.Str::random(4), 'email' => 'ov'.Str::random(10).'@example.com',
            'password' => Hash::make('x'), 'status'   => 'active',
            // The paid-page view builds a creator-profile tip URL from the
            // owner's handle and 500s without one.
            'handle'   => 'h'.Str::random(10),
        ]);
        /** @var Link $link */
        $link = $user->links()->create([
            'user_id' => $user->id, 'type' => 'paid_page',
            'alias' => 'ov'.substr(Str::random(10), 0, 10), 'is_active' => true,
        ]);
        $link->settings = [
            'paid_page' => [
                'template'     => PaidPageTemplates::DEFAULT_ID,
                'bg_image_url' => 'https://cdn.example.com/mine.jpg',
            ],
            'biolink' => ['background_type' => 'color', 'background_color' => '#123456'],
        ];
        $link->save();

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $this->assertStringContainsString('https://cdn.example.com/mine.jpg', $html,
            "the creator's own image must still render");
        $this->assertStringContainsString('pp-bg-image', $html,
            'and still on its own full-bleed layer, as it always has');
    }

    /** Both editors point at the shared picker; neither grows its own. */
    public function test_both_editors_link_to_the_one_picker(): void
    {
        foreach (['paid-page-editor', 'brand-kit-editor'] as $editor) {
            $body = file_get_contents(base_path("resources/views/user/links/{$editor}.blade.php"));

            $this->assertStringContainsString("route('user.links.settings.appearance', \$link)", $body,
                "{$editor} offers no way to reach the background picker");
            $this->assertStringNotContainsString('biolink-background-card', $body,
                "{$editor} must link to the one picker, not embed a copy of it");
        }
    }

    /** And they can actually save one. */
    public function test_both_types_can_save_a_background_but_nothing_else(): void
    {
        foreach (['paid_page', 'brand_kit'] as $type) {
            $user = User::create([
                'name' => 'ov'.Str::random(4), 'email' => 'ov'.Str::random(10).'@example.com',
                'password' => Hash::make('x'), 'status'   => 'active',
                // The paid-page view builds a creator-profile tip URL from the
                // owner's handle and 500s without one.
                'handle'   => 'h'.Str::random(10),
            ]);
            /** @var Link $link */
            $link = $user->links()->create([
                'user_id' => $user->id, 'type' => $type,
                'alias' => 'ov'.substr(Str::random(10), 0, 10), 'is_active' => true,
            ]);

            $this->actingAs($user)->post('/user/links/'.$link->id.'/page-settings', [
                'background_type'  => 'color',
                'background_color' => '#123456',
                'font_family'      => 'Comic Sans MS',
            ]);

            $bio = $link->refresh()->settings['biolink'] ?? [];
            $this->assertSame('#123456', $bio['background_color'] ?? null, "{$type} could not save");
            $this->assertArrayNotHasKey('font_family', $bio,
                "{$type} has a template that owns its font; the page surface stays narrowed");
        }
    }
}
