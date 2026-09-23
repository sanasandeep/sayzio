<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Support\PageBackground;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Four page types whose colour was decided by somebody other than their owner.
 *
 * Restaurant Menu, Store Menu, Service Booking and AI Chat all hardcode a
 * light page with a `prefers-color-scheme: dark` override, so the VISITOR's
 * operating system picked the colour. AI Chat adds a second decider: the
 * companion's own light/dark/auto control, stored on the companion rather
 * than the link.
 *
 * Giving these the background picker is therefore not a renderer swap, which
 * is why they were held back from the first rollout slice. Two deciders had
 * to be taken out of the loop, and a third thing had to come with them:
 *
 * THE CARDS SWITCH TOO. Each of these pages styles its items, quantity
 * buttons, cart bar, sheet and fields off the same media query. Painting the
 * page from the creator's choice while leaving the cards on the visitor's OS
 * produces a dark background with light-mode cards for anyone browsing in
 * light mode -- a page that looks broken to half the audience and fine to
 * the other half, which is the worst kind of bug to be told about.
 *
 * So a chosen background makes the page COMMIT to a scheme. The signal is
 * the creator's own font colour: a real choice, made in the same panel, and
 * nobody picks white text for a white page. Inferring from the background
 * does not work -- the only colour that always renders is
 * bg_fallback_color, which defaults to dark and is usually left alone
 * behind a light gradient.
 *
 * And, as in the first slice, all of it is opt-in: no saved background
 * means these pages render exactly as they always have.
 */
class AChosenBackgroundBeatsTheColourSchemeTest extends TestCase
{
    use RefreshDatabase;

    /** Types in this slice, and the marker proving the right view rendered. */
    private const TYPES = [
        'restaurant_menu' => 'cartbar',
        'store_menu'      => 'cartbar',
        'service_booking' => 'slot',
        'ai_chat'         => 'color-scheme: light dark',
    ];

    private function user(): User
    {
        return User::create([
            'name'     => 'cs'.Str::random(4),
            'email'    => 'cs'.Str::random(10).'@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
    }

    private function page(string $type, array $biolink = []): string
    {
        $user = $this->user();

        /** @var Link $link */
        $link = $user->links()->create([
            'user_id'   => $user->id,
            'type'      => $type,
            'alias'     => 'cs'.substr(Str::random(10), 0, 10),
            'is_active' => true,
        ]);
        if ($biolink !== []) {
            $link->settings = ['biolink' => $biolink];
            $link->save();
        }

        // Each of these needs its own record or the router falls through to
        // common.biolink, and the assertions below would be worthless.
        match ($type) {
            'restaurant_menu' => \App\Modules\User\Models\RestaurantMenu::create([
                'link_id' => $link->id, 'user_id' => $user->id, 'name' => 'Menu',
            ]),
            'store_menu' => \App\Modules\User\Models\StoreMenu::create([
                'link_id' => $link->id, 'user_id' => $user->id, 'name' => 'Store',
            ]),
            'service_booking' => \App\Modules\User\Models\ServiceBooking::create([
                'link_id' => $link->id, 'user_id' => $user->id, 'name' => 'Booking',
            ]),
            // ai_chat needs a companion, and a companion's persona_id points
            // at ai_persona_agents -- not ai_personas, which is a different
            // table with a very similar name.
            'ai_chat' => (function () use ($link, $user) {
                $agent = \App\Modules\User\Models\AiPersonaAgent::create([
                    'user_id' => $user->id,
                    'slug'          => 'a'.Str::random(8),
                    'name'          => 'Agent',
                    'system_prompt' => 'You are a helpful assistant.',
                    'model'         => 'claude-3-5-haiku',
                ]);
                $companion = \App\Modules\User\Models\AiCompanion::create([
                    'user_id'     => $user->id,
                    'persona_id'  => $agent->id,
                    'public_id'   => Str::random(24),
                    'name'        => 'Zio',
                    'placement'   => 'page',
                    'is_disabled' => false,
                ]);
                $link->aiCompanions()->attach($companion->id);

                return $companion;
            })(),
        };

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $this->assertStringContainsString(self::TYPES[$type], $html,
            "a {$type} link rendered some other view; this test would prove nothing");

        return $html;
    }

    /** Which page-level rules can repaint the page. */
    private function repaintersIn(string $html): array
    {
        $found = [];
        if (preg_match('/@media \(prefers-color-scheme: dark\)\s*\{\s*html,\s*body\s*\{[^}]*background/', $html)) {
            $found[] = 'prefers-color-scheme';
        }
        // Only PAGE-level rules count. `html[data-theme=dark] .msg.a {
        // background: ... }` paints a chat bubble, not the page, and
        // counting it would make this detector cry wolf.
        if (preg_match('/html\[data-theme="(dark|light)"\](,\s*html\[data-theme="(dark|light)"\]\s*body)?\s*\{[^}]*background/', $html)) {
            $found[] = 'data-theme';
        }

        return $found;
    }

    // ===== Opt-in =====

    /** An unedited page is byte-for-byte what it always was. */
    public function test_an_unchosen_page_keeps_both_of_its_schemes(): void
    {
        foreach (array_keys(self::TYPES) as $type) {
            $html = $this->page($type);

            $this->assertStringContainsString('background:#f6f6f9', $html,
                "an unedited {$type} page must keep its light default");
            // ai_chat legitimately has TWO deciders when nothing is chosen:
            // the visitor's OS and the companion's own light/dark control.
            $expected = $type === 'ai_chat'
                ? ['prefers-color-scheme', 'data-theme']
                : ['prefers-color-scheme'];

            $this->assertSame($expected, $this->repaintersIn($html),
                "an unedited {$type} page must keep deciding exactly as it always did");
            $this->assertStringNotContainsString('bg-layer', $html,
                "an unedited {$type} page must not acquire a background layer");
        }
    }

    // ===== The choice wins =====

    /** A chosen background paints, and nothing else repaints over it. */
    public function test_a_chosen_background_takes_the_scheme_out_of_the_loop(): void
    {
        foreach (array_keys(self::TYPES) as $type) {
            $html = $this->page($type, [
                'background_type'  => 'color',
                'background_color' => '#2b1055',
                'font_color'       => '#ffffff',
            ]);

            $this->assertStringContainsString('#2b1055', $html,
                "{$type} did not paint the chosen colour");
            $this->assertStringContainsString('bg-layer', $html,
                "{$type} did not render the shared background layer");
            $this->assertSame([], $this->repaintersIn($html),
                "{$type} still lets something else repaint the page over the creator's choice");
        }
    }

    /**
     * The surfaces follow the commitment, not the visitor.
     *
     * This is the half that is easy to forget: the page can be the right
     * colour while every card on it is the wrong one.
     */
    public function test_the_cards_follow_the_chosen_scheme_in_both_directions(): void
    {
        // ai_chat has no cart bar or sheet of its own; its page-level
        // commitment is covered by the repainter test above.
        foreach (['restaurant_menu', 'store_menu', 'service_booking'] as $type) {
            // Light ink -> the creator is on a dark page -> dark surfaces.
            $dark = $this->page($type, [
                'background_type'  => 'color',
                'background_color' => '#2b1055',
                'font_color'       => '#ffffff',
            ]);
            $this->assertMatchesRegularExpression(
                '/\.sheet\s*\{\s*background:#15151c/', $dark,
                "{$type}: a dark page must get dark cards for every visitor, not just dark-mode ones"
            );

            // Dark ink -> a pale page -> light surfaces, even for a dark-mode visitor.
            $light = $this->page($type, [
                'background_type'  => 'color',
                'background_color' => '#f4f1ea',
                'font_color'       => '#1a1a2e',
            ]);
            $this->assertMatchesRegularExpression(
                '/\.sheet\s*\{\s*background:#fff/', $light,
                "{$type}: a pale page must get light cards for every visitor, not just light-mode ones"
            );
        }
    }

    /** Service Booking has one surface the other two do not. */
    public function test_the_booking_slot_follows_the_scheme_too(): void
    {
        $dark = $this->page('service_booking', [
            'background_type' => 'color', 'background_color' => '#2b1055', 'font_color' => '#ffffff',
        ]);
        $this->assertStringContainsString('.slot { border-color:rgba(255,255,255,.2); }', $dark);

        $light = $this->page('service_booking', [
            'background_type' => 'color', 'background_color' => '#f4f1ea', 'font_color' => '#1a1a2e',
        ]);
        $this->assertStringContainsString('.slot { border-color:rgba(0,0,0,.2); }', $light);
    }

    /**
     * AI Chat's own surfaces follow the commitment too.
     *
     * Found by a detector that was crying wolf: it flagged
     * `html[data-theme=dark] .msg.a { background: ... }` as a page repaint.
     * It is not -- but it IS an assistant bubble whose fill was still being
     * chosen by the companion's control and the visitor's OS after the
     * creator had picked a background. A light grey block on a dark page is
     * not a subtle border.
     */
    public function test_the_chat_surfaces_follow_the_chosen_scheme(): void
    {
        $dark = $this->page('ai_chat', [
            'background_type' => 'color', 'background_color' => '#2b1055', 'font_color' => '#ffffff',
        ]);
        $this->assertStringContainsString('.msg.a    { background:rgba(255,255,255,.07); }', $dark,
            'the assistant bubble must be dark on a dark page, for every visitor');

        $light = $this->page('ai_chat', [
            'background_type' => 'color', 'background_color' => '#f4f1ea', 'font_color' => '#1a1a2e',
        ]);
        $this->assertStringContainsString('.msg.a    { background:rgba(0,0,0,.05); }', $light,
            'and light on a pale page, whatever the visitor\'s OS says');
    }

    // ===== The signal =====

    /** The ink decides, and the default matches the renderer's own. */
    public function test_the_ink_is_what_picks_the_scheme(): void
    {
        $this->assertTrue(PageBackground::inkIsLight(['font_color' => '#ffffff']));
        $this->assertTrue(PageBackground::inkIsLight(['font_color' => '#f5f5f7']));
        $this->assertFalse(PageBackground::inkIsLight(['font_color' => '#111111']));
        $this->assertFalse(PageBackground::inkIsLight(['font_color' => '#1a1a2e']));

        // No ink set, or unparseable: assume the biolink default of white
        // on dark, which is what the renderer already falls back to.
        $this->assertTrue(PageBackground::inkIsLight([]));
        $this->assertTrue(PageBackground::inkIsLight(['font_color' => 'not-a-colour']));
    }

    /**
     * The creator can reach the picker from the editor they are actually in.
     *
     * These three editors never included the shared header, so there was no
     * route from them to Appearance at all -- the capability existed and
     * nothing pointed at it.
     */
    public function test_each_editor_links_to_the_appearance_panel(): void
    {
        foreach ([
            'restaurant/editor', 'store/editor', 'service-booking/editor',
        ] as $editor) {
            $body = file_get_contents(base_path("resources/views/user/links/{$editor}.blade.php"));

            $this->assertStringContainsString("route('user.links.settings.appearance', \$link)", $body,
                "{$editor} offers no way to reach the background picker");
            // The label differs by editor -- the menu editors say
            // "Background & fonts" since their font control started
            // working (PR #158). What matters is that the row NAMES the
            // panel it links to, not that every screen uses one wording.
            $this->assertMatchesRegularExpression(
                '/Background(?:\s|&amp;|&)|Page background/i',
                $body,
                $editor.' links to Appearance without saying what is there'
            );

            // ...and it must LINK there rather than grow its own copy.
            $this->assertStringNotContainsString('biolink-background-card', $body,
                "{$editor} must not include its own picker; six of those is how this started");
        }
    }
}
