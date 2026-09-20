<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\BgTemplate;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Support\TornStyleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A characterization net for the page background, written BEFORE it moved.
 *
 * Every rule that turns `settings.biolink.background_*` into pixels lives
 * inline in common/biolink.blade.php -- roughly 190 lines of resolution
 * tangled up with fonts, buttons and stickers, plus the CSS and the layer
 * markup further down the same file. Nothing else in the app can render a
 * background, which is why the conversational page shows a picker of 941
 * looks and honours two of them, and why no other page type can be offered
 * one at all.
 *
 * Extracting it is the fix. The danger in extracting it is that the
 * biolink -- the one page that DOES work today, on 375K accounts -- comes
 * out subtly different: a dropped fallback, a layer that stops being
 * fixed, a preset that loses its opacity.
 *
 * So this test does not describe what the background SHOULD be. It records
 * what it IS, byte for byte, across every branch of that code, and fails
 * on any drift. Written and made green before the move, it is the only
 * thing that makes the move safe.
 *
 * The recording is narrow on purpose: the page CSS rules and the
 * background layer elements, whole -- including the tiles' gradient spans
 * and the torn sheets, which ARE the background. Tailwind utilities like
 * `bg-black/60` are not, and must never drift in.
 *
 * To re-record after a DELIBERATE change, delete the golden file named in
 * the failure and run the test again.
 */
class ThePageBackgroundRendersIdenticallyTest extends TestCase
{
    use RefreshDatabase;

    private const GOLDEN = 'tests/Fixtures/page-background-goldens.txt';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\BgTemplateSeeder::class);
    }

    /**
     * One configuration per branch of the resolution code.
     *
     * @return array<string, array<string, mixed>>
     */
    private function cases(): array
    {
        $torn = TornStyleCatalog::PRESETS[array_key_first(TornStyleCatalog::PRESETS)];

        return [
            // The contrast safeguard fires only when background_type is
            // absent, so this case must stay first-class rather than merged
            // into "color".
            'no-type-at-all' => ['font_color' => '#212529'],

            'color-fixed'  => ['background_type' => 'color', 'background_color' => '#123456', 'bg_fallback_color' => '#123456'],
            'color-scroll' => ['background_type' => 'color', 'background_color' => '#123456', 'bg_attachment' => 'scroll'],

            'gradient' => [
                'background_type'     => 'gradient',
                'background_gradient' => 'linear-gradient(135deg, #ff6b6b 0%, #feca57 50%, #48dbfb 100%)',
                'gradient_preset_id'  => 'sunset-magic',
            ],

            'image' => ['background_type' => 'image', 'background_image' => 'https://cdn.example.com/bg.jpg'],

            'image-blur-and-dim' => [
                'background_type'    => 'image',
                'background_image'   => 'https://cdn.example.com/bg.jpg',
                'bg_blur'            => 18,
                'bg_overlay_color'   => '#101820',
                'bg_overlay_opacity' => 45,
            ],

            'slideshow' => [
                'background_type'     => 'slideshow',
                'slideshow_images'    => ['https://cdn.example.com/1.jpg', 'https://cdn.example.com/2.jpg'],
                'slideshow_interval'  => 7,
            ],

            'video' => [
                'background_type'   => 'video',
                'video_url'         => 'https://cdn.example.com/loop.mp4',
                'bg_fallback_image' => 'https://cdn.example.com/poster.jpg',
            ],

            'preset'             => ['background_type' => 'preset', 'bg_preset_key' => 'abstract_one'],
            'preset-translucent' => ['background_type' => 'preset', 'bg_preset_key' => 'abstract_one', 'bg_preset_opacity' => 40],

            // Two preset GROUPS are hidden from the picker but still render
            // for pages saved before they were retired (HIDDEN_PICKER_GROUPS).
            // The torn group is the only way the preset-torn composite branch
            // can fire at all, so leaving it out would let that branch be
            // refactored away unnoticed.
            'preset-torn-legacy'     => ['background_type' => 'preset', 'bg_preset_key' => 'torn_dusty_blue'],
            'preset-gradient-legacy' => ['background_type' => 'preset', 'bg_preset_key' => 'gradient_zero'],

            'mesh'    => ['background_type' => 'mesh',    'mesh_preset'    => 'mesh_aurora'],
            'pattern' => ['background_type' => 'pattern', 'pattern_preset' => 'pattern_dots_dark'],

            'tiles' => [
                'background_type' => 'tiles',
                'tiles_palette'   => 'tiles_midnight',
                'tiles_layout'    => 'metro',
                'tiles_animate'   => '1',
            ],

            'torn-colors' => [
                'background_type'      => 'torn',
                'torn_style'           => $torn['style'],
                'torn_paper_color'     => $torn['paper'],
                'torn_backdrop_color'  => $torn['backdrop'][0],
                'torn_backdrop_color2' => $torn['backdrop'][1],
            ],
            'torn-photo' => [
                'background_type'  => 'torn',
                'torn_style'       => $torn['style'],
                'torn_paper_color' => $torn['paper'],
                'torn_image'       => 'https://cdn.example.com/backdrop.jpg',
            ],

            'template'        => ['background_type' => 'template', '__template' => true],
            'template-scroll' => ['background_type' => 'template', '__template' => true, 'bg_attachment' => 'scroll'],
        ];
    }

    /**
     * The rendered page, reduced to its background and nothing else.
     *
     * Narrow by design. A whole-page golden would fail on every unrelated
     * copy tweak and be deleted by the third person who hit it.
     */
    private function fingerprint(string $html): string
    {
        $layer = '/\.bg-page-fixed|\.bg-tiles|\.bg-torn|\.bg-slideshow|\.bg-template|\.bg-video|\.bg-layer|\.bg-overlay|\.bg-blur|bgTilePulse/';
        $out   = [];

        if (preg_match_all('/<style\b[^>]*>(.*?)<\/style>/s', $html, $m)) {
            // Strip comments first: prose inside one must not match a rule test.
            $css = preg_replace('!/\*.*?\*/!s', '', implode("\n", $m[1]));
            $i   = 0;
            $len = strlen($css);

            while ($i < $len) {
                $brace = strpos($css, '{', $i);
                if ($brace === false) {
                    break;
                }
                $sel = trim(substr($css, $i, $brace - $i));

                // Balanced scan, so @media and @keyframes come out whole.
                $depth = 0;
                $j     = $brace;
                for (; $j < $len; $j++) {
                    if ($css[$j] === '{') {
                        $depth++;
                    } elseif ($css[$j] === '}') {
                        $depth--;
                        if ($depth === 0) {
                            break;
                        }
                    }
                }
                $body = substr($css, $brace, $j - $brace + 1);
                $i    = $j + 1;

                $sel  = preg_replace('/\s+/', ' ', $sel);
                $body = preg_replace('/\s+/', ' ', $body);

                if (preg_match('/^(body|html)\b/', $sel) || preg_match($layer, $sel.' '.$body)) {
                    $out[] = $sel.' '.$body;
                }
            }
        }

        // Each layer whole: the tiles' gradient spans, the torn sheets and
        // the slideshow images live inside these divs and ARE the background.
        $pos = 0;
        while (preg_match('/<div\b[^>]*class="[^"]*\bbg-layer\b[^"]*"[^>]*>/i', $html, $mm, PREG_OFFSET_CAPTURE, $pos)) {
            $start = $mm[0][1];
            $i     = $start;
            $depth = 0;
            $len   = strlen($html);

            while ($i < $len) {
                if (strcasecmp(substr($html, $i, 4), '<div') === 0) {
                    $depth++;
                    $i += 4;
                    continue;
                }
                if (strcasecmp(substr($html, $i, 6), '</div>') === 0) {
                    $depth--;
                    $i += 6;
                    if ($depth === 0) {
                        break;
                    }
                    continue;
                }
                $i++;
            }
            $out[] = preg_replace('/\s+/', ' ', trim(substr($html, $start, $i - $start)));
            $pos   = $i;
        }

        sort($out);

        return implode("\n", $out);
    }

    private function renderWith(array $settings): string
    {
        $user = User::create([
            'name'     => 'bg'.Str::random(4),
            'email'    => 'bg'.Str::random(10).'@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);

        if (!empty($settings['__template'])) {
            unset($settings['__template']);
            $settings['bg_template_id'] = BgTemplate::active()->orderBy('id')->firstOrFail()->id;
        }

        /** @var Link $link */
        $link = $user->links()->create([
            'user_id'   => $user->id,
            'type'      => 'biolink',
            'alias'     => 'bg'.substr(Str::random(10), 0, 10),
            'is_active' => true,
        ]);
        $link->settings = ['biolink' => $settings];
        $link->save();

        return $this->get('/'.$link->alias)->assertOk()->getContent();
    }

    /**
     * Every branch renders exactly what it rendered before the move.
     *
     * One test rather than fourteen: the golden is one file, and a failure
     * should show every branch that drifted at once, not the first.
     */
    public function test_every_background_renders_byte_for_byte_as_before(): void
    {
        $recorded = [];
        foreach ($this->cases() as $name => $settings) {
            $recorded[$name] = $this->fingerprint($this->renderWith($settings));
        }

        $golden = base_path(self::GOLDEN);
        $body   = '';
        foreach ($recorded as $name => $fp) {
            $body .= "===== {$name} =====\n{$fp}\n";
        }

        if (!file_exists($golden)) {
            @mkdir(dirname($golden), 0777, true);
            file_put_contents($golden, $body);
            $this->fail(
                'No golden existed, so one was recorded at '.self::GOLDEN."\n"
                .'Inspect it, commit it, and run this test again. It must be '
                .'recorded from KNOWN-GOOD code, never from the change you are '
                .'about to make.'
            );
        }

        $expected = file_get_contents($golden);

        if ($expected !== $body) {
            // Name the branches that drifted, so the failure is readable.
            $split = function (string $blob): array {
                $parts = [];
                foreach (preg_split('/^===== (.+) =====$/m', $blob, -1, PREG_SPLIT_DELIM_CAPTURE) as $k => $chunk) {
                    if ($k % 2 === 1) {
                        $key = $chunk;
                    } elseif ($k > 0) {
                        $parts[$key] = $chunk;
                    }
                }

                return $parts;
            };
            $was = $split($expected);
            $now = $split($body);

            $drifted = [];
            foreach ($now as $name => $fp) {
                if (!array_key_exists($name, $was)) {
                    $drifted[] = "{$name} (new)";
                } elseif ($was[$name] !== $fp) {
                    $drifted[] = $name;
                }
            }
            foreach (array_diff(array_keys($was), array_keys($now)) as $gone) {
                $drifted[] = "{$gone} (gone)";
            }

            file_put_contents($golden.'.actual', $body);

            $this->assertSame($was[$drifted[0]] ?? '', $now[$drifted[0]] ?? '',
                "The page background changed for: ".implode(', ', $drifted)."\n"
                ."Full output written to ".self::GOLDEN.".actual\n"
                ."If the change is deliberate, delete ".self::GOLDEN." and re-run to re-record.\n"
                ."First drifted branch shown below."
            );
        }

        $this->assertSame($expected, $body);
    }
}
