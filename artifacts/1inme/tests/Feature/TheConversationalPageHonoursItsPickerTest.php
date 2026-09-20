<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Support\BackgroundLibrary;
use App\Modules\User\Support\PageBackground;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A picker of 941 looks that honoured two of them.
 *
 * The conversational editor has always included the same background card
 * the biolink uses -- the full Colour / Style / Media picker. Its renderer
 * hand-rolled `image` and `slideshow` and ignored the rest, taking the page
 * colour from `settings.biolink.theme.background` instead. That key has one
 * reader in the entire codebase (that line) and zero writers, so it was
 * always the hardcoded fallback: every colour, every gradient and all 941
 * ready-made looks rendered as a flat #0f172a.
 *
 * It survived because there was nothing to compare against. The whole
 * background translation lived inside common/biolink.blade.php, so
 * "conversational renders backgrounds differently" was not a divergence
 * anyone could see -- it was the only other implementation in existence.
 *
 * Now there is one renderer. These tests are about conversational actually
 * using it, and about the two things that could regress for pages already
 * live: text that stops being readable, and photo backgrounds losing the
 * scrim that has always sat under their chat bubbles.
 */
class TheConversationalPageHonoursItsPickerTest extends TestCase
{
    use RefreshDatabase;

    private function page(array $biolink): string
    {
        $user = User::create([
            'name'     => 'cv'.Str::random(4),
            'email'    => 'cv'.Str::random(10).'@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);

        /** @var Link $link */
        $link = $user->links()->create([
            'user_id'   => $user->id,
            'type'      => 'conversational',
            'alias'     => 'cv'.substr(Str::random(10), 0, 10),
            'is_active' => true,
        ]);
        $link->settings = ['biolink' => $biolink];
        $link->save();

        // Without a PUBLISHED flow, RedirectController falls back to
        // common.biolink (RedirectController:667) -- so a test that skips
        // this renders the wrong view and passes for the wrong reason.
        \App\Modules\User\Models\ConversationFlow::create([
            'link_id'       => $link->id,
            'name'          => 'flow',
            'is_published'  => true,
            'is_active'     => true,
            'intro_message' => 'hi',
        ]);

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $this->assertStringContainsString('cv-shell', $html,
            'this must be the conversational renderer, not the biolink fallback');

        return $html;
    }

    /** The dead key is gone, and nothing may start reading it again. */
    public function test_nothing_reads_the_key_no_controller_writes(): void
    {
        $renderer = file_get_contents(
            base_path('resources/views/common/biolink-conversational.blade.php')
        );

        $this->assertStringNotContainsString("\$theme['background']", $renderer,
            'settings.biolink.theme.background has no writer anywhere in the app; '
            .'reading it is how this page ignored its own picker');

        // And confirm the premise rather than trusting it: no writer exists.
        $writers = [];
        foreach ([app_path(), base_path('resources/views')] as $root) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($it as $file) {
                if (!$file->isFile() || !in_array($file->getExtension(), ['php'], true)) {
                    continue;
                }
                $body = file_get_contents($file->getPathname());
                if (preg_match('/\[.theme.\]\s*\[.background.\]\s*=[^=]/', $body)) {
                    $writers[] = $file->getPathname();
                }
            }
        }
        $this->assertSame([], $writers,
            'a writer appeared for theme.background -- this test is now wrong, not the code');
    }

    /** A colour reaches the page, which it never did before. */
    public function test_a_solid_colour_actually_paints_the_page(): void
    {
        $html = $this->page(['background_type' => 'color', 'background_color' => '#c8f7c5']);

        $this->assertStringContainsString('#c8f7c5', $html,
            'the chosen colour must reach the rendered page');
        $this->assertStringNotContainsString('#0f172a', $html,
            'the old hardcoded fallback must not be what renders');
    }

    /** A gradient reaches the page. */
    public function test_a_gradient_actually_paints_the_page(): void
    {
        $gradient = 'linear-gradient(135deg, #ff6b6b 0%, #feca57 50%, #48dbfb 100%)';
        $html     = $this->page([
            'background_type'     => 'gradient',
            'background_gradient' => $gradient,
        ]);

        $this->assertStringContainsString($gradient, $html);
    }

    /**
     * Every library category renders something of its own.
     *
     * One case per category rather than all 941: the point is that no
     * category is silently dropped, which is what was happening to seven
     * of the nine.
     */
    public function test_every_style_category_renders_its_own_layer_or_css(): void
    {
        $byCategory = [];
        foreach (BackgroundLibrary::items() as $item) {
            $byCategory[$item['category']] ??= $item;
        }
        $this->assertNotEmpty($byCategory);

        $flat = [];
        foreach ($byCategory as $category => $item) {
            $settings = match ($item['type']) {
                'template' => ['background_type' => 'template', 'bg_template_id' => $item['value']],
                'preset'   => ['background_type' => 'preset',   'bg_preset_key'  => $item['value']],
                'mesh'     => ['background_type' => 'mesh',     'mesh_preset'    => $item['value']],
                'pattern'  => ['background_type' => 'pattern',  'pattern_preset' => $item['value']],
                'tiles'    => ['background_type' => 'tiles',    'tiles_palette'  => $item['value']],
                'torn'     => ['background_type' => 'torn'] + [
                    'torn_style'           => $item['torn']['style'],
                    'torn_paper_color'     => $item['torn']['paper'],
                    'torn_backdrop_color'  => $item['torn']['backdrop'],
                    'torn_backdrop_color2' => $item['torn']['backdrop2'],
                ],
                'gradient' => ['background_type' => 'gradient', 'gradient_preset_id' => $item['value']],
                default    => null,
            };
            if ($settings === null) {
                continue;
            }

            $html = $this->page($settings);

            // Each look must leave a marker only IT could have left. A loose
            // check here (does the page contain "background:" anywhere?) is
            // true of every page ever rendered and proves nothing -- the old
            // renderer passed that version of this test.
            $pb     = PageBackground::resolve($settings);
            $marker = match ($item['type']) {
                'template' => 'bg-template-'.$pb['template']?->slug,
                'tiles'    => 'class="bg-tiles bg-layer"',
                'torn'     => 'class="bg-torn-paper bg-layer"',
                // The catalog CSS itself, which nothing else would emit.
                'preset', 'mesh', 'pattern' => substr(trim((string) $pb['presetCss']), 0, 60),
                'gradient' => $pb['gradient'],
            };

            if ($marker === '' || !str_contains($html, $marker)) {
                $flat[] = $category.' ('.$item['type'].' '.$item['value'].')';
            }
        }

        $this->assertSame([], $flat,
            'these categories render nothing on a conversational page: '.implode(', ', $flat));
    }

    /**
     * Text follows the background.
     *
     * The old renderer hardcoded near-white text, which was fine when the
     * page was always #0f172a. Now that a creator can choose a pale
     * background, white-on-white is one setting away.
     */
    public function test_the_text_colour_follows_the_saved_font_colour(): void
    {
        $html = $this->page([
            'background_type'  => 'color',
            'background_color' => '#ffffff',
            'font_color'       => '#1a1a2e',
        ]);

        $this->assertStringContainsString('color: #1a1a2e', $html,
            'a dark font on a white background must actually render dark');
    }

    /**
     * Photo backgrounds keep the scrim they have always had -- and lose it
     * when the creator sets their own dim, so the two never stack.
     */
    public function test_a_photo_background_keeps_its_readability_scrim(): void
    {
        $withoutDim = $this->page([
            'background_type'  => 'image',
            'background_image' => 'https://cdn.example.com/bg.jpg',
        ]);
        $this->assertStringContainsString('cv-bg-overlay', $withoutDim,
            'pages that read correctly today must keep their scrim');

        $withDim = $this->page([
            'background_type'    => 'image',
            'background_image'   => 'https://cdn.example.com/bg.jpg',
            'bg_overlay_color'   => '#101820',
            'bg_overlay_opacity' => 60,
        ]);
        $this->assertStringNotContainsString('cv-bg-overlay', $withDim,
            'the creator set a dim; stacking the automatic scrim on top doubles it');
        $this->assertMatchesRegularExpression('/rgba\(16,\s*24,\s*32,\s*0\.6\)/', $withDim,
            'the creator-chosen dim must be what renders in its place');
    }

    /** A colour background gets no scrim -- it never had one. */
    public function test_a_flat_background_gets_no_scrim(): void
    {
        $html = $this->page(['background_type' => 'color', 'background_color' => '#c8f7c5']);

        $this->assertStringNotContainsString('cv-bg-overlay', $html);
    }

    /** The slideshow rotator drives the shared layer, not the retired one. */
    public function test_the_slideshow_rotator_targets_the_shared_layer(): void
    {
        $html = $this->page([
            'background_type'    => 'slideshow',
            'slideshow_images'   => ['https://cdn.example.com/1.jpg', 'https://cdn.example.com/2.jpg'],
            'slideshow_interval' => 9,
        ]);

        $this->assertStringContainsString('.bg-slideshow img', $html,
            'the rotator must select the shared layer it now renders');
        $this->assertStringNotContainsString('cv-bg-slideshow', $html,
            'the hand-rolled layer is gone');
        $this->assertStringContainsString('9000', $html, 'the saved interval must drive it');
    }

    /** Both renderers read the same fields, so neither can quietly drift. */
    public function test_both_page_types_resolve_through_the_one_renderer(): void
    {
        foreach ([
            'resources/views/common/biolink.blade.php',
            'resources/views/common/biolink-conversational.blade.php',
        ] as $view) {
            $body = file_get_contents(base_path($view));
            $this->assertStringContainsString('PageBackground::resolve', $body,
                "{$view} must resolve its background through the shared renderer");
            $this->assertStringContainsString("common.page-background.layers", $body,
                "{$view} must render the shared background layers");
        }

        // The renderer's field list is the contract; keep it honest.
        $this->assertContains('background_type', PageBackground::FIELDS);
        $this->assertContains('tiles_palette', PageBackground::FIELDS);
        $this->assertContains('torn_style', PageBackground::FIELDS);
    }
}
