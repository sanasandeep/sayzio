<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\BgTemplate;
use Illuminate\Database\Seeder;

/**
 * 100+ pure-CSS / SVG background pattern templates for biolink pages.
 *
 * Each entry produces a row in `bg_templates`. The CSS uses
 * `.bg-template-<slug>` as its scope so it only paints the dedicated
 * background layer rendered by resources/views/common/biolink.blade.php.
 *
 * Categories used:
 *   - gradient   linear / radial / conic gradient washes
 *   - mesh       multi-stop multi-radial mesh gradients
 *   - pattern    repeating geometric patterns (dots, grid, stripes, ...)
 *   - svg        repeating SVG data-URI patterns
 *   - animated   subtle CSS keyframe animations (no JS)
 *   - neon       cyberpunk / neon glow patterns
 *
 * Preview swatches use the `preview_color` column directly as a CSS
 * `background:` value, so it can be any valid background shorthand.
 */
class BgPatternTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $sort = 1000; // start AFTER existing animated templates
        foreach ($this->templates() as $tpl) {
            $tpl['sort_order'] = $sort++;
            $tpl['is_active']  = true;
            $tpl['js']         = $tpl['js'] ?? null;
            $tpl['category']   = $tpl['category'] ?? 'pattern';
            BgTemplate::updateOrCreate(['slug' => $tpl['slug']], $tpl);
        }
    }

    /** @return array<int, array<string,mixed>> */
    private function templates(): array
    {
        $out = [];

        // ───────────────── 1. Linear gradients (18) ─────────────────
        $linear = [
            ['Sunset Blaze',     '#ff6b6b, #f06595, #845ec2',                    135],
            ['Ocean Drift',      '#0093E9, #80D0C7',                              135],
            ['Lush Green',       '#11998e, #38ef7d',                              135],
            ['Plum Dream',       '#42275a, #734b6d',                              135],
            ['Citrus Punch',     '#fbb034, #ffdd00',                              135],
            ['Cherry Blossom',   '#ee9ca7, #ffdde1',                              135],
            ['Royal Indigo',     '#4e54c8, #8f94fb',                              135],
            ['Mint Cream',       '#76b852, #8DC26F',                              135],
            ['Berry Smoothie',   '#8E2DE2, #4A00E0',                              135],
            ['Polar Night',      '#000428, #004e92',                              135],
            ['Volcano',          '#420516, #ff5722, #ffca28',                     160],
            ['Rose Gold',        '#b76e79, #f4cad7, #b76e79',                     135],
            ['Aqua Marine',      '#1A2980, #26D0CE',                              135],
            ['Lava Lamp',        '#ff0844, #ffb199',                              135],
            ['Forest Mist',      '#0f2027, #203a43, #2c5364',                     135],
            ['Neon Sunset',      '#ff00cc, #333399',                              135],
            ['Pastel Sky',       '#a1c4fd, #c2e9fb',                              135],
            ['Midnight City',    '#232526, #414345',                              135],
        ];
        foreach ($linear as [$name, $stops, $deg]) {
            $slug = $this->slug($name);
            $bg   = "linear-gradient({$deg}deg, {$stops})";
            $out[] = [
                'name'          => $name,
                'slug'          => $slug,
                'preview_color' => $bg,
                'category'      => 'gradient',
                'css'           => ".bg-template-{$slug}{position:fixed;inset:0;z-index:-1;background:{$bg};}",
            ];
        }

        // ───────────────── 2. Radial / conic (12) ─────────────────
        $radial = [
            ['Halo Light',     'radial-gradient(circle at 50% 0%, #fbc7d4 0%, #9796f0 60%, #1a1a2e 100%)'],
            ['Spotlight',      'radial-gradient(ellipse at center, #f6d365 0%, #fda085 40%, #1a1a2e 100%)'],
            ['Orb Bloom',      'radial-gradient(circle at 30% 30%, #ff9a9e 0%, #fad0c4 50%, #1a0533 100%)'],
            ['Eclipse',        'radial-gradient(circle at center, #000 0%, #000 30%, #ff7e5f 60%, #feb47b 100%)'],
            ['Cosmic Ring',    'radial-gradient(circle at 50% 50%, #0d1b2a 0%, #1b263b 40%, #415a77 70%, #778da9 100%)'],
            ['Conic Rainbow',  'conic-gradient(from 0deg, #ff5757, #ffbd57, #fff157, #57ff75, #57c5ff, #b557ff, #ff5757)'],
            ['Conic Pastel',   'conic-gradient(from 90deg at 50% 50%, #fbc7d4, #9796f0, #fbc7d4)'],
            ['Conic Cyber',    'conic-gradient(from 45deg, #06b6d4, #8b5cf6, #ec4899, #06b6d4)'],
            ['Sun Burst',      'radial-gradient(circle at center, #ffe53b 0%, #ff2525 70%)'],
            ['Lagoon',         'radial-gradient(ellipse at top, #2bc0e4 0%, #eaecc6 100%)'],
            ['Northern Sky',   'radial-gradient(circle at 20% 0%, #1a2980 0%, #26d0ce 100%)'],
            ['Cherry Pop',     'radial-gradient(circle at 70% 30%, #ff4e50 0%, #f9d423 100%)'],
        ];
        foreach ($radial as [$name, $bg]) {
            $slug = $this->slug($name);
            $out[] = [
                'name'          => $name,
                'slug'          => $slug,
                'preview_color' => $bg,
                'category'      => 'gradient',
                'css'           => ".bg-template-{$slug}{position:fixed;inset:0;z-index:-1;background:{$bg};}",
            ];
        }

        // ───────────────── 3. Mesh gradients (10) ─────────────────
        $mesh = [
            ['Mesh Sunset', '#0a0612', [
                ['18%','22%','#ff7e5f',0.55,'40%'],
                ['82%','30%','#feb47b',0.45,'40%'],
                ['28%','78%','#7f53ac',0.55,'45%'],
                ['72%','82%','#647dee',0.45,'45%'],
            ]],
            ['Mesh Lagoon', '#06141b', [
                ['20%','25%','#06b6d4',0.55,'45%'],
                ['78%','22%','#3b82f6',0.45,'45%'],
                ['30%','82%','#10b981',0.5 ,'50%'],
                ['80%','78%','#06b6d4',0.4 ,'45%'],
            ]],
            ['Mesh Plum',   '#150b22', [
                ['25%','20%','#a855f7',0.55,'45%'],
                ['80%','30%','#ec4899',0.45,'45%'],
                ['30%','80%','#7c3aed',0.55,'50%'],
                ['78%','78%','#f472b6',0.4 ,'45%'],
            ]],
            ['Mesh Mint',   '#06140e', [
                ['20%','22%','#34d399',0.5 ,'45%'],
                ['80%','25%','#22d3ee',0.45,'45%'],
                ['28%','82%','#10b981',0.5 ,'50%'],
                ['80%','80%','#a3e635',0.45,'45%'],
            ]],
            ['Mesh Citrus', '#160a05', [
                ['25%','25%','#fbbf24',0.5 ,'45%'],
                ['78%','22%','#f97316',0.5 ,'45%'],
                ['30%','78%','#facc15',0.5 ,'45%'],
                ['80%','80%','#ef4444',0.4 ,'45%'],
            ]],
            ['Mesh Rosé',   '#1a0612', [
                ['20%','20%','#fb7185',0.55,'45%'],
                ['80%','25%','#f472b6',0.45,'45%'],
                ['30%','80%','#e879f9',0.5 ,'45%'],
                ['80%','82%','#fda4af',0.4 ,'45%'],
            ]],
            ['Mesh Arctic', '#0a1220', [
                ['22%','22%','#93c5fd',0.45,'45%'],
                ['78%','25%','#a5b4fc',0.45,'45%'],
                ['30%','78%','#67e8f9',0.45,'45%'],
                ['80%','80%','#bae6fd',0.4 ,'45%'],
            ]],
            ['Mesh Ember',  '#180404', [
                ['22%','22%','#dc2626',0.55,'45%'],
                ['80%','25%','#f59e0b',0.45,'45%'],
                ['30%','78%','#9a3412',0.5 ,'45%'],
                ['80%','82%','#fbbf24',0.4 ,'45%'],
            ]],
            ['Mesh Galaxy', '#04031a', [
                ['22%','22%','#6366f1',0.55,'45%'],
                ['80%','25%','#8b5cf6',0.45,'45%'],
                ['30%','78%','#ec4899',0.5 ,'45%'],
                ['80%','82%','#06b6d4',0.4 ,'45%'],
            ]],
            ['Mesh Forest', '#0a1408', [
                ['22%','22%','#16a34a',0.55,'45%'],
                ['80%','25%','#65a30d',0.45,'45%'],
                ['30%','78%','#0d9488',0.5 ,'45%'],
                ['80%','82%','#84cc16',0.4 ,'45%'],
            ]],
        ];
        foreach ($mesh as [$name, $base, $blobs]) {
            $slug = $this->slug($name);
            $layers = [];
            foreach ($blobs as [$x,$y,$col,$op,$size]) {
                $rgba = $this->hexAlpha($col, $op);
                $layers[] = "radial-gradient(ellipse at {$x} {$y}, {$rgba} 0%, transparent {$size})";
            }
            $bg = implode(',', $layers) . ", {$base}";
            $out[] = [
                'name'          => $name,
                'slug'          => $slug,
                'preview_color' => $bg,
                'category'      => 'mesh',
                'css'           => ".bg-template-{$slug}{position:fixed;inset:0;z-index:-1;background:{$bg};background-attachment:fixed;}",
            ];
        }

        // ───────────────── 4. CSS geometric patterns (24) ─────────────────
        // Each tuple: [name, base bg color, pattern css fragment, preview_color]
        $patterns = [
            // Dots
            ['Dots Mono',     '#0f172a', 'background-image:radial-gradient(rgba(255,255,255,0.18) 1.5px, transparent 1.5px);background-size:22px 22px;'],
            ['Dots Lavender', '#1a0b2e', 'background-image:radial-gradient(rgba(167,139,250,0.35) 1.5px, transparent 1.5px);background-size:22px 22px;'],
            ['Dots Cyan',     '#06141b', 'background-image:radial-gradient(rgba(34,211,238,0.35) 1.5px, transparent 1.5px);background-size:22px 22px;'],
            ['Dots Coral',    '#1a0a05', 'background-image:radial-gradient(rgba(251,146,60,0.4) 2px, transparent 2px);background-size:24px 24px;'],
            ['Dots Big',      '#101828', 'background-image:radial-gradient(rgba(255,255,255,0.22) 3px, transparent 3px);background-size:36px 36px;'],

            // Grid
            ['Grid Slate',    '#0a0f1f', 'background-image:linear-gradient(rgba(148,163,184,0.18) 1px, transparent 1px),linear-gradient(90deg, rgba(148,163,184,0.18) 1px, transparent 1px);background-size:32px 32px;'],
            ['Grid Neon',     '#04060d', 'background-image:linear-gradient(rgba(34,211,238,0.25) 1px, transparent 1px),linear-gradient(90deg, rgba(34,211,238,0.25) 1px, transparent 1px);background-size:40px 40px;'],
            ['Grid Mauve',    '#150b22', 'background-image:linear-gradient(rgba(192,132,252,0.22) 1px, transparent 1px),linear-gradient(90deg, rgba(192,132,252,0.22) 1px, transparent 1px);background-size:36px 36px;'],
            ['Grid Mint',     '#06140e', 'background-image:linear-gradient(rgba(52,211,153,0.22) 1px, transparent 1px),linear-gradient(90deg, rgba(52,211,153,0.22) 1px, transparent 1px);background-size:36px 36px;'],
            ['Grid Tight',    '#101828', 'background-image:linear-gradient(rgba(255,255,255,0.12) 1px, transparent 1px),linear-gradient(90deg, rgba(255,255,255,0.12) 1px, transparent 1px);background-size:18px 18px;'],

            // Diagonal stripes
            ['Stripes Diag',  '#0f172a', 'background-image:repeating-linear-gradient(45deg, rgba(255,255,255,0.06) 0 12px, transparent 12px 24px);'],
            ['Stripes Neon',  '#04060d', 'background-image:repeating-linear-gradient(45deg, rgba(168,85,247,0.18) 0 14px, rgba(34,211,238,0.12) 14px 28px);'],
            ['Stripes Sun',   '#1a0a05', 'background-image:repeating-linear-gradient(135deg, rgba(251,191,36,0.18) 0 18px, rgba(239,68,68,0.12) 18px 36px);'],

            // Vertical / horizontal lines
            ['Lines V',       '#0a0f1f', 'background-image:repeating-linear-gradient(90deg, rgba(255,255,255,0.08) 0 1px, transparent 1px 28px);'],
            ['Lines H',       '#0a0f1f', 'background-image:repeating-linear-gradient(0deg, rgba(255,255,255,0.08) 0 1px, transparent 1px 28px);'],

            // Checkerboard
            ['Checker Dark',  '#101828', 'background-image:linear-gradient(45deg, rgba(255,255,255,0.05) 25%, transparent 25%, transparent 75%, rgba(255,255,255,0.05) 75%, rgba(255,255,255,0.05)),linear-gradient(45deg, rgba(255,255,255,0.05) 25%, transparent 25%, transparent 75%, rgba(255,255,255,0.05) 75%, rgba(255,255,255,0.05));background-size:40px 40px;background-position:0 0, 20px 20px;'],
            ['Checker Cyber', '#04060d', 'background-image:linear-gradient(45deg, rgba(168,85,247,0.18) 25%, transparent 25%, transparent 75%, rgba(168,85,247,0.18) 75%),linear-gradient(45deg, rgba(168,85,247,0.18) 25%, transparent 25%, transparent 75%, rgba(168,85,247,0.18) 75%);background-size:30px 30px;background-position:0 0, 15px 15px;'],

            // Diamonds
            ['Diamonds',      '#0f172a', 'background-image:linear-gradient(45deg, rgba(255,255,255,0.07) 25%, transparent 25%),linear-gradient(-45deg, rgba(255,255,255,0.07) 25%, transparent 25%),linear-gradient(45deg, transparent 75%, rgba(255,255,255,0.07) 75%),linear-gradient(-45deg, transparent 75%, rgba(255,255,255,0.07) 75%);background-size:32px 32px;background-position:0 0, 0 16px, 16px -16px, -16px 0px;'],

            // Crosshatch
            ['Crosshatch',    '#0a0f1f', 'background-image:repeating-linear-gradient(45deg, rgba(255,255,255,0.06) 0 1px, transparent 1px 12px),repeating-linear-gradient(135deg, rgba(255,255,255,0.06) 0 1px, transparent 1px 12px);'],

            // Carbon fiber
            ['Carbon Fiber',  '#0a0a0a', 'background-image:linear-gradient(45deg, #1a1a1a 25%, transparent 25%),linear-gradient(-45deg, #1a1a1a 25%, transparent 25%),linear-gradient(45deg, transparent 75%, #1a1a1a 75%),linear-gradient(-45deg, transparent 75%, #1a1a1a 75%);background-size:8px 8px;background-position:0 0, 0 4px, 4px -4px, -4px 0px;'],

            // Polka dot 2-color
            ['Polka Pink',    '#1a0612', 'background-image:radial-gradient(rgba(244,114,182,0.5) 3px, transparent 3px),radial-gradient(rgba(167,139,250,0.4) 3px, transparent 3px);background-size:36px 36px;background-position:0 0, 18px 18px;'],

            // Concentric circles via radial layered
            ['Bubbles',       '#06141b', 'background-image:radial-gradient(circle at 20% 30%, rgba(34,211,238,0.25) 0 30px, transparent 32px),radial-gradient(circle at 70% 60%, rgba(167,139,250,0.25) 0 40px, transparent 42px),radial-gradient(circle at 40% 80%, rgba(236,72,153,0.2) 0 50px, transparent 52px);background-size:160px 160px;'],

            // Tilted grid
            ['Tilt Grid',     '#0f172a', 'background-image:linear-gradient(60deg, rgba(255,255,255,0.08) 1px, transparent 1px),linear-gradient(120deg, rgba(255,255,255,0.08) 1px, transparent 1px);background-size:32px 56px;'],

            // Wave bands
            ['Wave Bands',    '#0a0f1f', 'background-image:repeating-linear-gradient(180deg, rgba(99,102,241,0.16) 0 30px, transparent 30px 60px);'],
        ];
        foreach ($patterns as [$name, $base, $patternCss]) {
            $slug = $this->slug($name);
            $css  = ".bg-template-{$slug}{position:fixed;inset:0;z-index:-1;background-color:{$base};{$patternCss}}";
            $out[] = [
                'name'          => $name,
                'slug'          => $slug,
                'preview_color' => "{$base} {$patternCss}", // also valid as preview swatch
                'category'      => 'pattern',
                'css'           => $css,
            ];
        }

        // ───────────────── 5. SVG repeating patterns (16) ─────────────────
        $svgPatterns = [
            ['Hex Grid Slate',    '#0f172a', $this->svgHex('rgba(148,163,184,0.25)', 1, 28)],
            ['Hex Grid Neon',     '#04060d', $this->svgHex('rgba(34,211,238,0.35)', 1, 28)],
            ['Hex Grid Mauve',    '#150b22', $this->svgHex('rgba(192,132,252,0.3)', 1, 28)],
            ['Triangles Slate',   '#0f172a', $this->svgTriangles('rgba(148,163,184,0.22)', 30)],
            ['Triangles Neon',    '#04060d', $this->svgTriangles('rgba(167,139,250,0.32)', 30)],
            ['Plus Mono',         '#0a0f1f', $this->svgPlus('rgba(255,255,255,0.18)', 26)],
            ['Plus Cyan',         '#06141b', $this->svgPlus('rgba(34,211,238,0.32)', 26)],
            ['Wave SVG Slate',    '#0a0f1f', $this->svgWaves('rgba(148,163,184,0.25)', 80, 18)],
            ['Wave SVG Coral',    '#1a0a05', $this->svgWaves('rgba(251,146,60,0.32)', 80, 18)],
            ['Topography Slate',  '#0a0f1f', $this->svgTopo('rgba(148,163,184,0.18)')],
            ['Topography Neon',   '#04060d', $this->svgTopo('rgba(34,211,238,0.22)')],
            ['Circuit Slate',     '#0a0f1f', $this->svgCircuit('rgba(148,163,184,0.22)')],
            ['Circuit Neon',      '#04060d', $this->svgCircuit('rgba(34,211,238,0.32)')],
            ['Diagonal Lines',    '#0f172a', $this->svgDiagLines('rgba(255,255,255,0.18)', 14)],
            ['Confetti SVG',      '#101828', $this->svgConfetti()],
            ['Crosses',           '#0a0f1f', $this->svgCrosses('rgba(167,139,250,0.32)', 28)],
        ];
        foreach ($svgPatterns as [$name, $base, $svgUrl]) {
            $slug = $this->slug($name);
            $css  = ".bg-template-{$slug}{position:fixed;inset:0;z-index:-1;background-color:{$base};background-image:url(\"{$svgUrl}\");}";
            $out[] = [
                'name'          => $name,
                'slug'          => $slug,
                'preview_color' => "{$base} url(\"{$svgUrl}\")",
                'category'      => 'svg',
                'css'           => $css,
            ];
        }

        // ───────────────── 6. Animated CSS (no-JS) (14) ─────────────────
        $animated = [
            [
                'Aurora Drift', '#0a0612, #1a0533',
                "@keyframes auroraDrift_{slug}{0%,100%{background-position:0% 50%}50%{background-position:100% 50%}}"
                . ".bg-template-{slug}{position:fixed;inset:0;z-index:-1;"
                . "background:linear-gradient(120deg,#7c3aed,#06b6d4,#ec4899,#7c3aed);"
                . "background-size:300% 300%;animation:auroraDrift_{slug} 14s ease-in-out infinite;}",
            ],
            [
                'Sunset Drift', '#1a0533, #ff6a00',
                "@keyframes sunsetDrift_{slug}{0%,100%{background-position:0% 50%}50%{background-position:100% 50%}}"
                . ".bg-template-{slug}{position:fixed;inset:0;z-index:-1;"
                . "background:linear-gradient(120deg,#ff6a00,#ee0979,#7c3aed,#ff6a00);"
                . "background-size:300% 300%;animation:sunsetDrift_{slug} 16s ease-in-out infinite;}",
            ],
            [
                'Conic Spin', 'conic-gradient(from 0deg, #06b6d4, #8b5cf6, #ec4899, #06b6d4)',
                "@keyframes conicSpin_{slug}{to{transform:rotate(360deg)}}"
                . ".bg-template-{slug}{position:fixed;inset:0;z-index:-1;background:#04060d;overflow:hidden}"
                . ".bg-template-{slug}::before{content:\"\";position:absolute;inset:-50%;"
                . "background:conic-gradient(from 0deg,#06b6d4,#8b5cf6,#ec4899,#06b6d4);"
                . "filter:blur(40px);opacity:0.55;animation:conicSpin_{slug} 18s linear infinite;}",
            ],
            [
                'Pulse Glow', '#1a0533, #06b6d4',
                "@keyframes pulseGlow_{slug}{0%,100%{opacity:0.45;transform:scale(1)}50%{opacity:0.75;transform:scale(1.15)}}"
                . ".bg-template-{slug}{position:fixed;inset:0;z-index:-1;background:#04060d;overflow:hidden}"
                . ".bg-template-{slug}::before{content:\"\";position:absolute;inset:-20%;"
                . "background:radial-gradient(circle at 30% 30%,#7c3aed,transparent 60%),radial-gradient(circle at 70% 70%,#06b6d4,transparent 60%);"
                . "animation:pulseGlow_{slug} 9s ease-in-out infinite;}",
            ],
            [
                'Slow Drift Mesh', '#0a0612, #06b6d4',
                "@keyframes slowMesh_{slug}{0%,100%{background-position:0% 0%, 100% 100%, 50% 50%}50%{background-position:100% 100%, 0% 0%, 60% 40%}}"
                . ".bg-template-{slug}{position:fixed;inset:0;z-index:-1;"
                . "background:radial-gradient(circle at 30% 30%, rgba(124,58,237,0.45), transparent 50%),"
                . "radial-gradient(circle at 70% 70%, rgba(6,182,212,0.45), transparent 50%),"
                . "radial-gradient(circle at 50% 50%, rgba(236,72,153,0.35), transparent 50%),#04060d;"
                . "background-size:200% 200%, 200% 200%, 200% 200%;"
                . "animation:slowMesh_{slug} 20s ease-in-out infinite;}",
            ],
            [
                'Pan Stripes', '#04060d',
                "@keyframes panStripes_{slug}{from{background-position:0 0}to{background-position:200px 0}}"
                . ".bg-template-{slug}{position:fixed;inset:0;z-index:-1;"
                . "background-color:#04060d;"
                . "background-image:repeating-linear-gradient(45deg, rgba(167,139,250,0.18) 0 14px, transparent 14px 28px);"
                . "animation:panStripes_{slug} 6s linear infinite;}",
            ],
            [
                'Drift Dots', '#0a0f1f',
                "@keyframes driftDots_{slug}{from{background-position:0 0}to{background-position:44px 44px}}"
                . ".bg-template-{slug}{position:fixed;inset:0;z-index:-1;"
                . "background-color:#0a0f1f;"
                . "background-image:radial-gradient(rgba(34,211,238,0.35) 1.5px, transparent 1.5px);"
                . "background-size:22px 22px;"
                . "animation:driftDots_{slug} 12s linear infinite;}",
            ],
            [
                'Drift Grid', '#04060d',
                "@keyframes driftGrid_{slug}{from{background-position:0 0,0 0}to{background-position:40px 40px,40px 40px}}"
                . ".bg-template-{slug}{position:fixed;inset:0;z-index:-1;"
                . "background-color:#04060d;"
                . "background-image:linear-gradient(rgba(34,211,238,0.25) 1px, transparent 1px),linear-gradient(90deg, rgba(34,211,238,0.25) 1px, transparent 1px);"
                . "background-size:40px 40px;"
                . "animation:driftGrid_{slug} 18s linear infinite;}",
            ],
            [
                'Twilight Sweep', '#0a0612, #06b6d4',
                "@keyframes twilightSweep_{slug}{0%,100%{background-position:0% 50%}50%{background-position:100% 50%}}"
                . ".bg-template-{slug}{position:fixed;inset:0;z-index:-1;"
                . "background:linear-gradient(135deg,#0a0612 0%,#1a0533 30%,#06b6d4 60%,#0a0612 100%);"
                . "background-size:300% 300%;animation:twilightSweep_{slug} 22s ease-in-out infinite;}",
            ],
            [
                'Pastel Drift', 'linear-gradient(135deg,#fbc7d4,#9796f0)',
                "@keyframes pastelDrift_{slug}{0%,100%{background-position:0% 50%}50%{background-position:100% 50%}}"
                . ".bg-template-{slug}{position:fixed;inset:0;z-index:-1;"
                . "background:linear-gradient(120deg,#fbc7d4,#9796f0,#a1c4fd,#fbc7d4);"
                . "background-size:300% 300%;animation:pastelDrift_{slug} 16s ease-in-out infinite;}",
            ],
            [
                'Ember Pulse', '#180404',
                "@keyframes emberPulse_{slug}{0%,100%{opacity:0.5}50%{opacity:0.85}}"
                . ".bg-template-{slug}{position:fixed;inset:0;z-index:-1;background:#180404;overflow:hidden}"
                . ".bg-template-{slug}::before{content:\"\";position:absolute;inset:-10%;"
                . "background:radial-gradient(circle at 30% 80%,#dc2626,transparent 50%),radial-gradient(circle at 70% 70%,#f59e0b,transparent 55%);"
                . "animation:emberPulse_{slug} 4s ease-in-out infinite;}",
            ],
            [
                'Galaxy Spin', '#04031a',
                "@keyframes galaxySpin_{slug}{to{transform:rotate(360deg)}}"
                . ".bg-template-{slug}{position:fixed;inset:0;z-index:-1;background:#04031a;overflow:hidden}"
                . ".bg-template-{slug}::before{content:\"\";position:absolute;inset:-50%;"
                . "background:conic-gradient(from 0deg,#6366f1,#8b5cf6,#ec4899,#06b6d4,#6366f1);"
                . "filter:blur(60px);opacity:0.45;animation:galaxySpin_{slug} 30s linear infinite;}",
            ],
            [
                'Rain Lines', '#0a0f1f',
                "@keyframes rainLines_{slug}{from{background-position:0 0}to{background-position:0 200px}}"
                . ".bg-template-{slug}{position:fixed;inset:0;z-index:-1;"
                . "background-color:#0a0f1f;"
                . "background-image:repeating-linear-gradient(180deg, rgba(34,211,238,0.18) 0 1px, transparent 1px 22px);"
                . "animation:rainLines_{slug} 1.5s linear infinite;}",
            ],
            [
                'Slow Hue Shift', 'linear-gradient(135deg,#ff0080,#7928ca)',
                "@keyframes hueShift_{slug}{from{filter:hue-rotate(0deg)}to{filter:hue-rotate(360deg)}}"
                . ".bg-template-{slug}{position:fixed;inset:0;z-index:-1;"
                . "background:linear-gradient(135deg,#ff0080,#7928ca,#06b6d4);"
                . "animation:hueShift_{slug} 22s linear infinite;}",
            ],
        ];
        foreach ($animated as [$name, $previewBg, $cssTpl]) {
            $slug = $this->slug($name);
            $css  = str_replace('{slug}', $slug, $cssTpl);
            $out[] = [
                'name'          => $name,
                'slug'          => $slug,
                'preview_color' => $previewBg,
                'category'      => 'animated',
                'css'           => $css,
            ];
        }

        // ───────────────── 6b. More gradients (24) ─────────────────
        $extraLinear = [
            ['Coral Reef',     '#ff9966, #ff5e62',                 135],
            ['Indigo Wash',    '#6a11cb, #2575fc',                 135],
            ['Mango Tango',    '#ffb347, #ffcc33',                 135],
            ['Glacier',        '#83a4d4, #b6fbff',                 135],
            ['Twilight Hue',   '#2c3e50, #fd746c',                 135],
            ['Lemon Lime',     '#a8e063, #56ab2f',                 135],
            ['Bubblegum',      '#fc5c7d, #6a82fb',                 135],
            ['Spring Bud',     '#a8ff78, #78ffd6',                 135],
            ['Plum Wine',      '#3a1c71, #d76d77, #ffaf7b',        135],
            ['Royal Pink',     '#ff007f, #800080',                 135],
            ['Saffron',        '#f7971e, #ffd200',                 135],
            ['Mojito',         '#1d976c, #93f9b9',                 135],
            ['Fjord',          '#005c97, #363795',                 135],
            ['Hot Pink',       '#ff5f6d, #ffc371',                 135],
            ['Slate Blue',     '#283c86, #45a247',                 135],
            ['Magenta Burn',   '#ee0979, #ff6a00',                 135],
            ['Iron',           '#232526, #414345',                 135],
            ['Powder Blue',    '#74ebd5, #acb6e5',                 135],
            ['Velvet',         '#41295a, #2f0743',                 135],
            ['Soft Sand',      '#fdfcfb, #e2d1c3',                 135],
            ['Storm',          '#373b44, #4286f4',                 135],
            ['Persimmon',      '#ee9617, #ed213a',                 135],
            ['Avocado',        '#dad299, #b0dab9',                 135],
            ['Marine',         '#43cea2, #185a9d',                 135],
        ];
        foreach ($extraLinear as [$name, $stops, $deg]) {
            $slug = $this->slug($name);
            $bg   = "linear-gradient({$deg}deg, {$stops})";
            $out[] = [
                'name'          => $name,
                'slug'          => $slug,
                'preview_color' => $bg,
                'category'      => 'gradient',
                'css'           => ".bg-template-{$slug}{position:fixed;inset:0;z-index:-1;background:{$bg};}",
            ];
        }

        // ───────────────── 6c. More patterns (16) ─────────────────
        $morePatterns = [
            ['Dots Tiny',      '#0f172a', 'background-image:radial-gradient(rgba(255,255,255,0.18) 1px, transparent 1px);background-size:14px 14px;'],
            ['Dots Mint',      '#06140e', 'background-image:radial-gradient(rgba(52,211,153,0.4) 2px, transparent 2px);background-size:24px 24px;'],
            ['Dots Sun',       '#1a0a05', 'background-image:radial-gradient(rgba(251,191,36,0.4) 2px, transparent 2px);background-size:24px 24px;'],
            ['Dots Rosé',      '#1a0612', 'background-image:radial-gradient(rgba(244,114,182,0.4) 2px, transparent 2px);background-size:24px 24px;'],
            ['Grid Coral',     '#1a0a05', 'background-image:linear-gradient(rgba(251,146,60,0.25) 1px, transparent 1px),linear-gradient(90deg, rgba(251,146,60,0.25) 1px, transparent 1px);background-size:36px 36px;'],
            ['Grid Rose',      '#1a0612', 'background-image:linear-gradient(rgba(244,114,182,0.25) 1px, transparent 1px),linear-gradient(90deg, rgba(244,114,182,0.25) 1px, transparent 1px);background-size:36px 36px;'],
            ['Grid Wide',      '#0a0f1f', 'background-image:linear-gradient(rgba(255,255,255,0.12) 1px, transparent 1px),linear-gradient(90deg, rgba(255,255,255,0.12) 1px, transparent 1px);background-size:64px 64px;'],
            ['Stripes Cool',   '#04060d', 'background-image:repeating-linear-gradient(45deg, rgba(34,211,238,0.18) 0 14px, rgba(99,102,241,0.12) 14px 28px);'],
            ['Stripes Warm',   '#1a0a05', 'background-image:repeating-linear-gradient(135deg, rgba(251,146,60,0.22) 0 14px, rgba(244,63,94,0.18) 14px 28px);'],
            ['Stripes Mint',   '#06140e', 'background-image:repeating-linear-gradient(60deg, rgba(52,211,153,0.22) 0 14px, rgba(16,185,129,0.18) 14px 28px);'],
            ['Stripes Pastel', '#0a0f1f', 'background-image:repeating-linear-gradient(45deg, rgba(251,207,232,0.22) 0 18px, rgba(165,180,252,0.18) 18px 36px);'],
            ['Lines Diag',     '#0a0f1f', 'background-image:repeating-linear-gradient(135deg, rgba(255,255,255,0.08) 0 1px, transparent 1px 18px);'],
            ['Diamonds Mauve', '#150b22', 'background-image:linear-gradient(45deg, rgba(192,132,252,0.15) 25%, transparent 25%),linear-gradient(-45deg, rgba(192,132,252,0.15) 25%, transparent 25%),linear-gradient(45deg, transparent 75%, rgba(192,132,252,0.15) 75%),linear-gradient(-45deg, transparent 75%, rgba(192,132,252,0.15) 75%);background-size:32px 32px;background-position:0 0, 0 16px, 16px -16px, -16px 0px;'],
            ['Polka Cyan',     '#06141b', 'background-image:radial-gradient(rgba(34,211,238,0.45) 3px, transparent 3px),radial-gradient(rgba(99,102,241,0.35) 3px, transparent 3px);background-size:36px 36px;background-position:0 0, 18px 18px;'],
            ['Wave Bands Hot', '#1a0a05', 'background-image:repeating-linear-gradient(180deg, rgba(244,63,94,0.2) 0 30px, transparent 30px 60px);'],
            ['Crosshatch Hot', '#1a0a05', 'background-image:repeating-linear-gradient(45deg, rgba(251,146,60,0.18) 0 1px, transparent 1px 12px),repeating-linear-gradient(135deg, rgba(251,146,60,0.18) 0 1px, transparent 1px 12px);'],
        ];
        foreach ($morePatterns as [$name, $base, $patternCss]) {
            $slug = $this->slug($name);
            $css  = ".bg-template-{$slug}{position:fixed;inset:0;z-index:-1;background-color:{$base};{$patternCss}}";
            $out[] = [
                'name'          => $name,
                'slug'          => $slug,
                'preview_color' => $base,
                'category'      => 'pattern',
                'css'           => $css,
            ];
        }

        // ───────────────── 6d. More SVG patterns (10) ─────────────────
        $moreSvg = [
            ['Hex Grid Coral',  '#1a0a05', $this->svgHex('rgba(251,146,60,0.32)', 1, 28)],
            ['Hex Grid Mint',   '#06140e', $this->svgHex('rgba(52,211,153,0.32)', 1, 28)],
            ['Triangles Mauve', '#150b22', $this->svgTriangles('rgba(192,132,252,0.3)', 30)],
            ['Triangles Mint',  '#06140e', $this->svgTriangles('rgba(52,211,153,0.3)', 30)],
            ['Plus Mauve',      '#150b22', $this->svgPlus('rgba(192,132,252,0.32)', 26)],
            ['Plus Sun',        '#1a0a05', $this->svgPlus('rgba(251,191,36,0.32)', 26)],
            ['Wave SVG Mauve',  '#150b22', $this->svgWaves('rgba(192,132,252,0.32)', 80, 18)],
            ['Wave SVG Cyan',   '#06141b', $this->svgWaves('rgba(34,211,238,0.32)', 80, 18)],
            ['Topography Rose', '#1a0612', $this->svgTopo('rgba(244,114,182,0.22)')],
            ['Crosses Cyan',    '#06141b', $this->svgCrosses('rgba(34,211,238,0.32)', 28)],
        ];
        foreach ($moreSvg as [$name, $base, $svgUrl]) {
            $slug = $this->slug($name);
            $css  = ".bg-template-{$slug}{position:fixed;inset:0;z-index:-1;background-color:{$base};background-image:url(\"{$svgUrl}\");}";
            $out[] = [
                'name'          => $name,
                'slug'          => $slug,
                'preview_color' => $base,
                'category'      => 'svg',
                'css'           => $css,
            ];
        }

        // ───────────────── 6e. More animated CSS (50) ─────────────────
        $palettes = [
            ['Aurora',     ['#7c3aed','#06b6d4','#ec4899','#7c3aed'], '#0a0612'],
            ['Sunset',     ['#ff6a00','#ee0979','#ff6a00','#ffb199'], '#1a0a05'],
            ['Lagoon',     ['#06b6d4','#3b82f6','#10b981','#06b6d4'], '#04141b'],
            ['Citrus',     ['#fbbf24','#f97316','#ef4444','#fbbf24'], '#160a05'],
            ['Plum',       ['#a855f7','#ec4899','#7c3aed','#f472b6'], '#150b22'],
            ['Mint',       ['#34d399','#22d3ee','#10b981','#a3e635'], '#06140e'],
            ['Rosé',       ['#fb7185','#f472b6','#e879f9','#fda4af'], '#1a0612'],
            ['Arctic',     ['#93c5fd','#a5b4fc','#67e8f9','#bae6fd'], '#0a1220'],
            ['Ember',      ['#dc2626','#f59e0b','#9a3412','#fbbf24'], '#180404'],
            ['Galaxy',     ['#6366f1','#8b5cf6','#ec4899','#06b6d4'], '#04031a'],
            ['Forest',     ['#16a34a','#65a30d','#0d9488','#84cc16'], '#0a1408'],
            ['Pastel',     ['#fbc7d4','#9796f0','#a1c4fd','#fbc7d4'], '#1a1a2e'],
            ['Royal',      ['#4e54c8','#8f94fb','#4e54c8','#a78bfa'], '#0a0a2a'],
            ['Sand',       ['#fde68a','#fca5a5','#fed7aa','#fbbf24'], '#1a1006'],
            ['Toxic',      ['#84cc16','#22c55e','#10b981','#65a30d'], '#04060d'],
        ];

        foreach ($palettes as [$pname, $colors, $base]) {
            $low = strtolower($pname);

            // (a) Gradient drift
            $name = "Drift {$pname}";
            $slug = $this->slug($name);
            $stops = implode(',', $colors);
            $css = "@keyframes drift_{$slug}{0%,100%{background-position:0% 50%}50%{background-position:100% 50%}}"
                . ".bg-template-{$slug}{position:fixed;inset:0;z-index:-1;"
                . "background:linear-gradient(120deg,{$stops});"
                . "background-size:300% 300%;animation:drift_{$slug} 16s ease-in-out infinite;}";
            $out[] = ['name'=>$name,'slug'=>$slug,'preview_color'=>"linear-gradient(120deg,{$stops})",'category'=>'animated','css'=>$css];

            // (b) Conic spin behind blur
            $name = "Conic Spin {$pname}";
            $slug = $this->slug($name);
            $cstops = implode(',', array_merge($colors, [$colors[0]]));
            $css = "@keyframes spin_{$slug}{to{transform:rotate(360deg)}}"
                . ".bg-template-{$slug}{position:fixed;inset:0;z-index:-1;background:{$base};overflow:hidden}"
                . ".bg-template-{$slug}::before{content:\"\";position:absolute;inset:-50%;"
                . "background:conic-gradient(from 0deg,{$cstops});filter:blur(50px);opacity:0.55;"
                . "animation:spin_{$slug} 22s linear infinite;}";
            $out[] = ['name'=>$name,'slug'=>$slug,'preview_color'=>"conic-gradient(from 0deg,{$cstops})",'category'=>'animated','css'=>$css];

            // (c) Pulse glow (two orbs breathing)
            $name = "Pulse {$pname}";
            $slug = $this->slug($name);
            $a = $this->hexAlpha($colors[0], 0.85);
            $b = $this->hexAlpha($colors[1], 0.85);
            $css = "@keyframes pulse_{$slug}{0%,100%{opacity:0.45;transform:scale(1)}50%{opacity:0.85;transform:scale(1.15)}}"
                . ".bg-template-{$slug}{position:fixed;inset:0;z-index:-1;background:{$base};overflow:hidden}"
                . ".bg-template-{$slug}::before{content:\"\";position:absolute;inset:-15%;"
                . "background:radial-gradient(circle at 30% 30%,{$a},transparent 55%),radial-gradient(circle at 70% 70%,{$b},transparent 55%);"
                . "filter:blur(40px);animation:pulse_{$slug} 8s ease-in-out infinite;}";
            $out[] = ['name'=>$name,'slug'=>$slug,'preview_color'=>"radial-gradient(circle at 30% 30%,{$colors[0]},{$base})",'category'=>'animated','css'=>$css];

            // (d) Hue shift
            if (in_array($pname, ['Aurora','Sunset','Plum','Galaxy','Rosé','Royal'])) {
                $name = "Hue Shift {$pname}";
                $slug = $this->slug($name);
                $css = "@keyframes hue_{$slug}{from{filter:hue-rotate(0deg)}to{filter:hue-rotate(360deg)}}"
                    . ".bg-template-{$slug}{position:fixed;inset:0;z-index:-1;"
                    . "background:linear-gradient(135deg,{$colors[0]},{$colors[1]},{$colors[2]});"
                    . "animation:hue_{$slug} 24s linear infinite;}";
                $out[] = ['name'=>$name,'slug'=>$slug,'preview_color'=>"linear-gradient(135deg,{$colors[0]},{$colors[1]},{$colors[2]})",'category'=>'animated','css'=>$css];
            }
        }

        // (e) Drifting dot/grid sets in palette colors (8)
        $driftPatterns = [
            ['Drift Dots Cyan',   '#04060d', 'rgba(34,211,238,0.4)',  'dots'],
            ['Drift Dots Mauve',  '#150b22', 'rgba(192,132,252,0.4)', 'dots'],
            ['Drift Dots Coral',  '#1a0a05', 'rgba(251,146,60,0.4)',  'dots'],
            ['Drift Dots Mint',   '#06140e', 'rgba(52,211,153,0.4)',  'dots'],
            ['Drift Grid Cyan',   '#04060d', 'rgba(34,211,238,0.25)', 'grid'],
            ['Drift Grid Mauve',  '#150b22', 'rgba(192,132,252,0.25)','grid'],
            ['Drift Grid Coral',  '#1a0a05', 'rgba(251,146,60,0.25)', 'grid'],
            ['Drift Grid Rose',   '#1a0612', 'rgba(244,114,182,0.25)','grid'],
        ];
        foreach ($driftPatterns as [$name, $base, $col, $kind]) {
            $slug = $this->slug($name);
            if ($kind === 'dots') {
                $css = "@keyframes pan_{$slug}{from{background-position:0 0}to{background-position:44px 44px}}"
                    . ".bg-template-{$slug}{position:fixed;inset:0;z-index:-1;background-color:{$base};"
                    . "background-image:radial-gradient({$col} 2px, transparent 2px);background-size:22px 22px;"
                    . "animation:pan_{$slug} 14s linear infinite;}";
            } else {
                $css = "@keyframes pan_{$slug}{from{background-position:0 0,0 0}to{background-position:40px 40px,40px 40px}}"
                    . ".bg-template-{$slug}{position:fixed;inset:0;z-index:-1;background-color:{$base};"
                    . "background-image:linear-gradient({$col} 1px, transparent 1px),linear-gradient(90deg, {$col} 1px, transparent 1px);"
                    . "background-size:40px 40px;animation:pan_{$slug} 18s linear infinite;}";
            }
            $out[] = ['name'=>$name,'slug'=>$slug,'preview_color'=>$base,'category'=>'animated','css'=>$css];
        }

        // (f) Scrolling stripes (4)
        $scrollStripes = [
            ['Scroll Stripes Cyan',   '#04060d', 'rgba(34,211,238,0.22)'],
            ['Scroll Stripes Mauve',  '#150b22', 'rgba(192,132,252,0.22)'],
            ['Scroll Stripes Coral',  '#1a0a05', 'rgba(251,146,60,0.22)'],
            ['Scroll Stripes Mint',   '#06140e', 'rgba(52,211,153,0.22)'],
        ];
        foreach ($scrollStripes as [$name,$base,$col]) {
            $slug = $this->slug($name);
            $css = "@keyframes scroll_{$slug}{from{background-position:0 0}to{background-position:200px 0}}"
                . ".bg-template-{$slug}{position:fixed;inset:0;z-index:-1;background-color:{$base};"
                . "background-image:repeating-linear-gradient(45deg, {$col} 0 14px, transparent 14px 28px);"
                . "animation:scroll_{$slug} 8s linear infinite;}";
            $out[] = ['name'=>$name,'slug'=>$slug,'preview_color'=>$base,'category'=>'animated','css'=>$css];
        }

        // (g) Falling rain lines (3)
        $rains = [
            ['Rain Cyan',  '#0a0f1f', 'rgba(34,211,238,0.22)'],
            ['Rain Mauve', '#150b22', 'rgba(192,132,252,0.22)'],
            ['Rain Mint',  '#06140e', 'rgba(52,211,153,0.22)'],
        ];
        foreach ($rains as [$name,$base,$col]) {
            $slug = $this->slug($name);
            $css = "@keyframes rain_{$slug}{from{background-position:0 0}to{background-position:0 200px}}"
                . ".bg-template-{$slug}{position:fixed;inset:0;z-index:-1;background-color:{$base};"
                . "background-image:repeating-linear-gradient(180deg, {$col} 0 1px, transparent 1px 22px);"
                . "animation:rain_{$slug} 1.6s linear infinite;}";
            $out[] = ['name'=>$name,'slug'=>$slug,'preview_color'=>$base,'category'=>'animated','css'=>$css];
        }

        // (h) Breathing radial (3)
        $breaths = [
            ['Breathe Plum',  '#04031a', '#a855f7'],
            ['Breathe Cyan',  '#04060d', '#22d3ee'],
            ['Breathe Coral', '#1a0a05', '#fb7185'],
        ];
        foreach ($breaths as [$name,$base,$col]) {
            $slug = $this->slug($name);
            $rgba = $this->hexAlpha($col, 0.7);
            $css = "@keyframes breathe_{$slug}{0%,100%{transform:scale(1);opacity:0.55}50%{transform:scale(1.25);opacity:0.85}}"
                . ".bg-template-{$slug}{position:fixed;inset:0;z-index:-1;background:{$base};overflow:hidden}"
                . ".bg-template-{$slug}::before{content:\"\";position:absolute;inset:0;"
                . "background:radial-gradient(circle at 50% 50%,{$rgba},transparent 60%);"
                . "filter:blur(30px);animation:breathe_{$slug} 6s ease-in-out infinite;transform-origin:center;}";
            $out[] = ['name'=>$name,'slug'=>$slug,'preview_color'=>"radial-gradient(circle at 50% 50%,{$col},{$base})",'category'=>'animated','css'=>$css];
        }

        // ───────────────── 7. Neon / cyberpunk (8) ─────────────────
        $neon = [
            [
                'Neon Grid Floor', '#04060d',
                ".bg-template-{slug}{position:fixed;inset:0;z-index:-1;background:linear-gradient(180deg,#04060d 0%,#1a0533 60%,#04060d 100%);overflow:hidden}"
                . ".bg-template-{slug}::after{content:\"\";position:absolute;left:50%;bottom:-20%;width:200%;height:60%;transform:translateX(-50%) perspective(400px) rotateX(60deg);"
                . "background-image:linear-gradient(rgba(236,72,153,0.55) 2px,transparent 2px),linear-gradient(90deg,rgba(34,211,238,0.55) 2px,transparent 2px);"
                . "background-size:60px 60px;}",
            ],
            [
                'Neon Lines', '#04060d',
                ".bg-template-{slug}{position:fixed;inset:0;z-index:-1;background-color:#04060d;"
                . "background-image:repeating-linear-gradient(90deg, rgba(34,211,238,0.45) 0 2px, transparent 2px 80px),"
                . "repeating-linear-gradient(0deg, rgba(236,72,153,0.35) 0 2px, transparent 2px 80px);}",
            ],
            [
                'Neon Hex',  '#04060d',
                ".bg-template-{slug}{position:fixed;inset:0;z-index:-1;background-color:#04060d;"
                . "background-image:url(\"" . $this->svgHex('rgba(34,211,238,0.35)', 1, 28) . "\"),"
                . "radial-gradient(circle at 50% 50%, rgba(167,139,250,0.18), transparent 60%);}",
            ],
            [
                'Neon Pulse', '#04060d',
                "@keyframes neonPulse_{slug}{0%,100%{opacity:0.45}50%{opacity:0.85}}"
                . ".bg-template-{slug}{position:fixed;inset:0;z-index:-1;background:#04060d;overflow:hidden}"
                . ".bg-template-{slug}::before{content:\"\";position:absolute;inset:-10%;"
                . "background:radial-gradient(circle at 30% 30%,#06b6d4,transparent 55%),radial-gradient(circle at 70% 70%,#ec4899,transparent 55%);"
                . "filter:blur(40px);animation:neonPulse_{slug} 6s ease-in-out infinite;}",
            ],
            [
                'Synthwave Sun', '#04060d',
                ".bg-template-{slug}{position:fixed;inset:0;z-index:-1;"
                . "background:radial-gradient(circle at 50% 70%, #ff7e5f 0%, #ff4e8c 30%, #6f1d70 60%, #04060d 100%);}",
            ],
            [
                'Vapor Glow', '#04060d',
                ".bg-template-{slug}{position:fixed;inset:0;z-index:-1;"
                . "background:linear-gradient(135deg, #fa709a 0%, #fee140 100%);"
                . "filter:saturate(1.1)}",
            ],
            [
                'Toxic Slime', '#04060d',
                ".bg-template-{slug}{position:fixed;inset:0;z-index:-1;"
                . "background:radial-gradient(circle at 50% 50%, #84cc16 0%, #166534 50%, #04060d 100%);}",
            ],
            [
                'Cyber Magenta', '#04060d',
                ".bg-template-{slug}{position:fixed;inset:0;z-index:-1;"
                . "background-color:#04060d;"
                . "background-image:linear-gradient(rgba(236,72,153,0.35) 1px, transparent 1px),"
                . "linear-gradient(90deg, rgba(34,211,238,0.35) 1px, transparent 1px);"
                . "background-size:48px 48px;}",
            ],
        ];
        foreach ($neon as [$name, $previewBg, $cssTpl]) {
            $slug = $this->slug($name);
            $css  = str_replace('{slug}', $slug, $cssTpl);
            $out[] = [
                'name'          => $name,
                'slug'          => $slug,
                'preview_color' => $previewBg,
                'category'      => 'neon',
                'css'           => $css,
            ];
        }

        return $out;
    }

    // ─────────────────────────── helpers ───────────────────────────

    private function slug(string $name): string
    {
        return 'p-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($name)));
    }

    /** Convert #rrggbb + alpha to rgba(...) string. */
    private function hexAlpha(string $hex, float $a): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "rgba({$r},{$g},{$b}," . round($a, 2) . ')';
    }

    private function svgEncode(string $svg): string
    {
        // Inline-friendly URL encoding for SVG data URIs.
        $svg = str_replace(["\n", "\r", "\t"], '', $svg);
        $svg = str_replace(['"', '<', '>', '#', '%'], ['%22', '%3C', '%3E', '%23', '%25'], $svg);
        return "data:image/svg+xml;utf8,{$svg}";
    }

    private function svgHex(string $stroke, float $w, int $size): string
    {
        $r = $size; $h = $size * 1.732;
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='{$r}' height='{$h}' viewBox='0 0 {$r} {$h}' fill='none' stroke='{$stroke}' stroke-width='{$w}'>"
             . "<polygon points='" . ($r/2) . ",0 {$r}," . ($h/4) . " {$r}," . (3*$h/4) . " " . ($r/2) . ",{$h} 0," . (3*$h/4) . " 0," . ($h/4) . "'/>"
             . "</svg>";
        return $this->svgEncode($svg);
    }

    private function svgTriangles(string $fill, int $size): string
    {
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='{$size}' height='{$size}' viewBox='0 0 {$size} {$size}'>"
             . "<polygon points='0,{$size} " . ($size/2) . ",0 {$size},{$size}' fill='{$fill}'/>"
             . "</svg>";
        return $this->svgEncode($svg);
    }

    private function svgPlus(string $fill, int $size): string
    {
        $c  = $size / 2;
        $w  = max(2, intval($size / 8));
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='{$size}' height='{$size}' viewBox='0 0 {$size} {$size}' fill='{$fill}'>"
             . "<rect x='" . ($c - $w/2) . "' y='" . ($size*0.2) . "' width='{$w}' height='" . ($size*0.6) . "' rx='1'/>"
             . "<rect x='" . ($size*0.2) . "' y='" . ($c - $w/2) . "' width='" . ($size*0.6) . "' height='{$w}' rx='1'/>"
             . "</svg>";
        return $this->svgEncode($svg);
    }

    private function svgWaves(string $stroke, int $w, int $h): string
    {
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='{$w}' height='{$h}' viewBox='0 0 {$w} {$h}' fill='none' stroke='{$stroke}' stroke-width='1.5'>"
             . "<path d='M0 " . ($h/2) . " Q " . ($w/4) . " 0 " . ($w/2) . " " . ($h/2) . " T {$w} " . ($h/2) . "'/>"
             . "</svg>";
        return $this->svgEncode($svg);
    }

    private function svgTopo(string $stroke): string
    {
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='120' height='120' viewBox='0 0 120 120' fill='none' stroke='{$stroke}' stroke-width='1'>"
             . "<circle cx='60' cy='60' r='10'/>"
             . "<circle cx='60' cy='60' r='25'/>"
             . "<circle cx='60' cy='60' r='40'/>"
             . "<circle cx='60' cy='60' r='55'/>"
             . "</svg>";
        return $this->svgEncode($svg);
    }

    private function svgCircuit(string $stroke): string
    {
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='80' height='80' viewBox='0 0 80 80' fill='none' stroke='{$stroke}' stroke-width='1'>"
             . "<path d='M0 20 H30 V0 M50 0 V30 H80 M0 60 H20 V80 M40 80 V50 H80 M30 30 H50 V50 H30 Z'/>"
             . "<circle cx='30' cy='20' r='2' fill='{$stroke}'/><circle cx='50' cy='30' r='2' fill='{$stroke}'/>"
             . "<circle cx='20' cy='60' r='2' fill='{$stroke}'/><circle cx='40' cy='50' r='2' fill='{$stroke}'/>"
             . "</svg>";
        return $this->svgEncode($svg);
    }

    private function svgDiagLines(string $stroke, int $size): string
    {
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='{$size}' height='{$size}' viewBox='0 0 {$size} {$size}' fill='none' stroke='{$stroke}' stroke-width='1'>"
             . "<line x1='0' y1='{$size}' x2='{$size}' y2='0'/>"
             . "</svg>";
        return $this->svgEncode($svg);
    }

    private function svgConfetti(): string
    {
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='80' height='80' viewBox='0 0 80 80'>"
             . "<rect x='10' y='12' width='6' height='2' fill='%23ec4899' transform='rotate(20 13 13)'/>"
             . "<rect x='40' y='28' width='6' height='2' fill='%2306b6d4' transform='rotate(-30 43 29)'/>"
             . "<rect x='62' y='10' width='6' height='2' fill='%23facc15' transform='rotate(45 65 11)'/>"
             . "<rect x='20' y='52' width='6' height='2' fill='%23a855f7' transform='rotate(-15 23 53)'/>"
             . "<rect x='52' y='62' width='6' height='2' fill='%2310b981' transform='rotate(60 55 63)'/>"
             . "<circle cx='32' cy='70' r='1.5' fill='%23ef4444'/>"
             . "<circle cx='68' cy='44' r='1.5' fill='%2322d3ee'/>"
             . "</svg>";
        // Note: %23 already inlined for color hex inside attribute values.
        // svgEncode would double-encode; build URI manually instead.
        $svg = str_replace(['"', '<', '>'], ['%22', '%3C', '%3E'], $svg);
        return "data:image/svg+xml;utf8,{$svg}";
    }

    private function svgCrosses(string $fill, int $size): string
    {
        $c  = $size / 2;
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='{$size}' height='{$size}' viewBox='0 0 {$size} {$size}' fill='none' stroke='{$fill}' stroke-width='1.5'>"
             . "<line x1='" . ($c-4) . "' y1='{$c}' x2='" . ($c+4) . "' y2='{$c}'/>"
             . "<line x1='{$c}' y1='" . ($c-4) . "' x2='{$c}' y2='" . ($c+4) . "'/>"
             . "</svg>";
        return $this->svgEncode($svg);
    }
}
