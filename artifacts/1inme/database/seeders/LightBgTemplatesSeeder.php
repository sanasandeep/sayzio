<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\BgTemplate;
use Illuminate\Database\Seeder;

/**
 * Light / airy / pastel background templates.
 *
 * Counterpart to BgPatternTemplatesSeeder, which skews dark. Every
 * template here uses a high-luminance base so it pairs well with dark
 * text and avatars. Categories reuse the existing taxonomy:
 *   - gradient   linear / radial / conic light washes
 *   - mesh       soft multi-radial pastel mesh
 *   - pattern    repeating geometric patterns on light bg
 *   - svg        repeating SVG data-URI patterns on light bg
 *   - animated   subtle CSS keyframe animations, all light
 *
 * Each row is upserted by slug, so re-running just refreshes CSS/JS.
 */
class LightBgTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $sort = 5000; // sit AFTER the dark pattern library (which uses 1000+)
        foreach ($this->templates() as $tpl) {
            $tpl['sort_order'] = $sort++;
            $tpl['is_active']  = true;
            $tpl['js']         = $tpl['js'] ?? null;
            $tpl['category']   = $tpl['category'] ?? 'gradient';
            BgTemplate::updateOrCreate(['slug' => $tpl['slug']], $tpl);
        }
    }

    private function slug(string $name): string
    {
        return 'light-' . preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($name)));
    }

    /** @return array<int, array<string,mixed>> */
    private function templates(): array
    {
        $out = [];

        // ───────────────── 1. Light linear gradients (24) ─────────────────
        $linear = [
            ['Cotton Candy',    '#ffe4ec, #e0c3fc',                      135],
            ['Morning Mist',    '#fdfbfb, #ebedee',                      135],
            ['Soft Peach',      '#ffecd2, #fcb69f',                      135],
            ['Vanilla Sky',     '#fffbd5, #b3e5fc',                      135],
            ['Lavender Haze',   '#e0c3fc, #8ec5fc',                      135],
            ['Pearl White',     '#ffffff, #f5f7fa',                      135],
            ['Ivory Cream',     '#fdfcfb, #e2d1c3',                      135],
            ['Mint Whisper',    '#d4fc79, #96e6a1',                      135],
            ['Sky Pastel',      '#a1c4fd, #c2e9fb',                      135],
            ['Rose Quartz',     '#ffdde1, #ee9ca7',                      135],
            ['Lemon Chiffon',   '#fdfcb1, #fef9d7',                      135],
            ['Baby Blue',       '#dbeafe, #f0f9ff',                      135],
            ['Cherry Cream',    '#fce4ec, #fff1f2',                      135],
            ['Sand Dune',       '#fef3c7, #fde68a',                      135],
            ['Sea Foam',        '#d1fae5, #a7f3d0',                      135],
            ['Powder Pink',     '#fbcfe8, #fce7f3',                      135],
            ['Light Sage',      '#dcfce7, #bbf7d0',                      135],
            ['Coconut',         '#fafaf9, #f5f5f4',                      135],
            ['Buttercup',       '#fef9c3, #fef08a',                      135],
            ['Periwinkle',      '#c7d2fe, #e0e7ff',                      135],
            ['Apricot Glow',    '#fed7aa, #ffedd5',                      135],
            ['Lilac Bloom',     '#e9d5ff, #f3e8ff',                      135],
            ['Aqua Pearl',      '#cffafe, #ecfeff',                      135],
            ['Champagne',       '#fef6e4, #f3d9a4',                      135],
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

        // ───────────────── 2. Light radial / conic (10) ─────────────────
        $radial = [
            ['Halo Soft',       'radial-gradient(circle at 50% 0%, #ffe4ec 0%, #e0c3fc 60%, #ffffff 100%)'],
            ['Sunlit Page',     'radial-gradient(ellipse at top, #fff7ed 0%, #fed7aa 60%, #fff 100%)'],
            ['Mint Halo',       'radial-gradient(circle at 30% 30%, #d1fae5 0%, #ecfdf5 60%, #fff 100%)'],
            ['Pearl Dome',      'radial-gradient(circle at center, #ffffff 0%, #f5f7fa 60%, #e2e8f0 100%)'],
            ['Sky Bloom',       'radial-gradient(ellipse at center, #dbeafe 0%, #eff6ff 60%, #fff 100%)'],
            ['Conic Pastel',    'conic-gradient(from 90deg at 50% 50%, #fbc7d4, #c7d2fe, #d1fae5, #fef3c7, #fbc7d4)'],
            ['Conic Bubblegum', 'conic-gradient(from 0deg, #fce7f3, #ddd6fe, #cffafe, #fef9c3, #fce7f3)'],
            ['Ivory Beam',      'radial-gradient(circle at 50% 100%, #fef3c7 0%, #fffbeb 60%, #fff 100%)'],
            ['Rose Beam',       'radial-gradient(circle at 50% 100%, #fbcfe8 0%, #fce7f3 60%, #fff 100%)'],
            ['Sun Spot',        'radial-gradient(circle at center, #fef9c3 0%, #fffbe6 70%, #fff 100%)'],
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

        // ───────────────── 3. Light mesh gradients (10) ─────────────────
        $mesh = [
            ['Pastel Mesh',
             'background:#fdf4ff;background-image:radial-gradient(at 20% 20%, #ffd6e7 0px, transparent 50%),radial-gradient(at 80% 0%, #c7d2fe 0px, transparent 50%),radial-gradient(at 0% 50%, #fef9c3 0px, transparent 50%),radial-gradient(at 80% 80%, #d1fae5 0px, transparent 50%),radial-gradient(at 0% 100%, #fed7aa 0px, transparent 50%);'],
            ['Cloudy Day',
             'background:#f8fafc;background-image:radial-gradient(at 20% 30%, #e0f2fe 0px, transparent 50%),radial-gradient(at 80% 20%, #f1f5f9 0px, transparent 50%),radial-gradient(at 50% 80%, #ede9fe 0px, transparent 50%);'],
            ['Sherbet Mesh',
             'background:#fffbeb;background-image:radial-gradient(at 0% 0%, #fbcfe8 0px, transparent 50%),radial-gradient(at 100% 0%, #fed7aa 0px, transparent 50%),radial-gradient(at 0% 100%, #d9f99d 0px, transparent 50%),radial-gradient(at 100% 100%, #bae6fd 0px, transparent 50%);'],
            ['Spring Garden',
             'background:#f0fdf4;background-image:radial-gradient(at 25% 25%, #bbf7d0 0px, transparent 50%),radial-gradient(at 75% 25%, #fde68a 0px, transparent 50%),radial-gradient(at 50% 75%, #fbcfe8 0px, transparent 50%);'],
            ['Soft Sunrise',
             'background:#fff7ed;background-image:radial-gradient(at 0% 100%, #fed7aa 0px, transparent 60%),radial-gradient(at 100% 0%, #fbcfe8 0px, transparent 60%),radial-gradient(at 50% 50%, #fef9c3 0px, transparent 70%);'],
            ['Polar Mist',
             'background:#f0f9ff;background-image:radial-gradient(at 30% 20%, #cffafe 0px, transparent 50%),radial-gradient(at 70% 80%, #ddd6fe 0px, transparent 50%),radial-gradient(at 50% 50%, #fff 0px, transparent 50%);'],
            ['Rosewater',
             'background:#fff1f2;background-image:radial-gradient(at 20% 20%, #fecdd3 0px, transparent 50%),radial-gradient(at 80% 80%, #fbcfe8 0px, transparent 50%),radial-gradient(at 50% 50%, #fff 0px, transparent 60%);'],
            ['Macaron',
             'background:#fdf2f8;background-image:radial-gradient(at 0% 30%, #ddd6fe 0px, transparent 50%),radial-gradient(at 100% 70%, #fde68a 0px, transparent 50%),radial-gradient(at 50% 50%, #fbcfe8 0px, transparent 60%);'],
            ['Citrus Cloud',
             'background:#fefce8;background-image:radial-gradient(at 30% 30%, #fef9c3 0px, transparent 50%),radial-gradient(at 70% 70%, #fed7aa 0px, transparent 50%),radial-gradient(at 50% 50%, #d9f99d 0px, transparent 60%);'],
            ['Linen',
             'background:#fafaf9;background-image:radial-gradient(at 20% 30%, #f5f5f4 0px, transparent 50%),radial-gradient(at 80% 70%, #e7e5e4 0px, transparent 50%),radial-gradient(at 50% 50%, #fff 0px, transparent 60%);'],
        ];
        foreach ($mesh as [$name, $bgDecl]) {
            $slug = $this->slug($name);
            $out[] = [
                'name'          => $name,
                'slug'          => $slug,
                // preview_color must be a valid background shorthand; the
                // mesh declaration already inlines `background:` + `background-image:`,
                // so we strip the trailing semicolon for the swatch and reuse
                // the same declaration block in the page CSS.
                'preview_color' => $bgDecl,
                'category'      => 'mesh',
                'css'           => ".bg-template-{$slug}{position:fixed;inset:0;z-index:-1;{$bgDecl}}",
            ];
        }

        // ───────────────── 4. Light geometric patterns (16) ─────────────────
        $patterns = [
            ['Polka Pink',
             '#fff1f2',
             "background-color:#fff1f2;background-image:radial-gradient(#fbcfe8 1.5px, transparent 1.5px);background-size:20px 20px;"],
            ['Polka Mint',
             '#ecfdf5',
             "background-color:#ecfdf5;background-image:radial-gradient(#a7f3d0 1.5px, transparent 1.5px);background-size:22px 22px;"],
            ['Polka Sky',
             '#eff6ff',
             "background-color:#eff6ff;background-image:radial-gradient(#bfdbfe 1.5px, transparent 1.5px);background-size:24px 24px;"],
            ['Grid Paper',
             '#fafafa',
             "background-color:#fafafa;background-image:linear-gradient(#e5e7eb 1px, transparent 1px), linear-gradient(90deg, #e5e7eb 1px, transparent 1px);background-size:24px 24px;"],
            ['Notebook Lines',
             '#fffefa',
             "background-color:#fffefa;background-image:linear-gradient(transparent 23px, #fde68a 23px, #fde68a 24px, transparent 24px);background-size:100% 24px;"],
            ['Soft Stripes',
             '#fafafa',
             "background-color:#fafafa;background-image:repeating-linear-gradient(45deg, #f3f4f6 0 14px, #fff 14px 28px);"],
            ['Candy Stripe',
             '#fff1f2',
             "background-color:#fff1f2;background-image:repeating-linear-gradient(0deg, #fecdd3 0 10px, transparent 10px 24px);"],
            ['Mint Stripe',
             '#ecfdf5',
             "background-color:#ecfdf5;background-image:repeating-linear-gradient(90deg, #bbf7d0 0 8px, transparent 8px 24px);"],
            ['Diagonal Cream',
             '#fffbeb',
             "background-color:#fffbeb;background-image:repeating-linear-gradient(45deg, #fef3c7 0 12px, transparent 12px 24px);"],
            ['Checker Light',
             '#ffffff',
             "background-color:#ffffff;background-image:linear-gradient(45deg, #f3f4f6 25%, transparent 25%, transparent 75%, #f3f4f6 75%, #f3f4f6), linear-gradient(45deg, #f3f4f6 25%, transparent 25%, transparent 75%, #f3f4f6 75%, #f3f4f6);background-size:30px 30px;background-position:0 0, 15px 15px;"],
            ['Hex Soft',
             '#f8fafc',
             "background-color:#f8fafc;background-image:radial-gradient(circle at 25% 25%, #e2e8f0 1.5px, transparent 1.6px), radial-gradient(circle at 75% 75%, #e2e8f0 1.5px, transparent 1.6px);background-size:30px 30px;"],
            ['Cross Pattern',
             '#fdfcf6',
             "background-color:#fdfcf6;background-image:linear-gradient(#f3e8d2 1px, transparent 1px), linear-gradient(90deg, #f3e8d2 1px, transparent 1px);background-size:40px 40px;"],
            ['Lavender Dots',
             '#faf5ff',
             "background-color:#faf5ff;background-image:radial-gradient(#ddd6fe 1.5px, transparent 1.5px);background-size:18px 18px;"],
            ['Honeycomb Light',
             '#fffbeb',
             "background-color:#fffbeb;background-image:radial-gradient(circle at 50% 50%, #fde68a 1.5px, transparent 1.6px);background-size:24px 24px;"],
            ['Pinstripe Cloud',
             '#f1f5f9',
             "background-color:#f1f5f9;background-image:repeating-linear-gradient(0deg, #e2e8f0 0 1px, transparent 1px 8px);"],
            ['Confetti Light',
             '#fffbf5',
             "background-color:#fffbf5;background-image:radial-gradient(#fbcfe8 1.5px, transparent 1.5px), radial-gradient(#bae6fd 1.5px, transparent 1.5px), radial-gradient(#fde68a 1.5px, transparent 1.5px);background-size:32px 32px, 32px 32px, 32px 32px;background-position:0 0, 16px 8px, 8px 24px;"],
        ];
        foreach ($patterns as [$name, $preview, $decl]) {
            $slug = $this->slug($name);
            $out[] = [
                'name'          => $name,
                'slug'          => $slug,
                'preview_color' => $preview,
                'category'      => 'pattern',
                'css'           => ".bg-template-{$slug}{position:fixed;inset:0;z-index:-1;{$decl}}",
            ];
        }

        // ───────────────── 5. Light SVG patterns (6) ─────────────────
        // Tiny inline SVGs, base64-free so they remain readable.
        $svg = [
            ['Wavy Cream', '#fffbeb',
             "url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='40' height='20' viewBox='0 0 40 20'><path d='M0 10 Q10 0 20 10 T40 10' fill='none' stroke='%23fde68a' stroke-width='1'/></svg>\")"],
            ['Wavy Sky', '#eff6ff',
             "url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='40' height='20' viewBox='0 0 40 20'><path d='M0 10 Q10 0 20 10 T40 10' fill='none' stroke='%23bfdbfe' stroke-width='1'/></svg>\")"],
            ['Plus Light', '#fafafa',
             "url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24'><path d='M11 6h2v5h5v2h-5v5h-2v-5H6v-2h5z' fill='%23e5e7eb'/></svg>\")"],
            ['Triangles Pastel', '#fff1f2',
             "url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='30' height='26' viewBox='0 0 30 26'><polygon points='15,2 28,24 2,24' fill='none' stroke='%23fecdd3' stroke-width='1'/></svg>\")"],
            ['Stars Soft', '#fffbeb',
             "url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='40' height='40' viewBox='0 0 40 40'><path d='M20 6l2.5 7.5H30l-6.2 4.5L26 26l-6-4.5L14 26l2.2-8L10 13.5h7.5z' fill='%23fde68a'/></svg>\")"],
            ['Hearts Blush', '#fff1f2',
             "url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='30' height='28' viewBox='0 0 30 28'><path d='M15 24c-7-4.5-12-9-12-14a6 6 0 0 1 12-2 6 6 0 0 1 12 2c0 5-5 9.5-12 14z' fill='%23fbcfe8'/></svg>\")"],
        ];
        foreach ($svg as [$name, $bgColor, $svgUrl]) {
            $slug = $this->slug($name);
            $decl = "background-color:{$bgColor};background-image:{$svgUrl};";
            $out[] = [
                'name'          => $name,
                'slug'          => $slug,
                'preview_color' => $decl,
                'category'      => 'svg',
                'css'           => ".bg-template-{$slug}{position:fixed;inset:0;z-index:-1;{$decl}}",
            ];
        }

        // ───────────────── 6. Light animated (6) ─────────────────
        // Subtle, slow keyframes — never seizure-y. Each one uses a
        // unique class scope and animation name keyed by slug to avoid
        // collisions with the dark animated set.
        $animated = [
            [
                'Drifting Pastels',
                'linear-gradient(135deg, #ffe4ec, #e0c3fc, #c2e9fb, #d1fae5)',
                "
.bg-template-{slug}{position:fixed;inset:0;z-index:-1;background:linear-gradient(135deg,#ffe4ec,#e0c3fc,#c2e9fb,#d1fae5,#ffe4ec);background-size:400% 400%;animation:{slug}-shift 24s ease infinite;}
@keyframes {slug}-shift{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}",
            ],
            [
                'Soft Aurora',
                'radial-gradient(at 30% 30%, #fbcfe8, transparent), radial-gradient(at 70% 70%, #c7d2fe, transparent), #fff',
                "
.bg-template-{slug}{position:fixed;inset:0;z-index:-1;background:#fff;overflow:hidden;}
.bg-template-{slug}::before,.bg-template-{slug}::after{content:'';position:absolute;width:140%;height:140%;top:-20%;left:-20%;animation:{slug}-rot 30s linear infinite;}
.bg-template-{slug}::before{background:radial-gradient(ellipse at 30% 40%, rgba(251,207,232,0.55) 0%, transparent 55%),radial-gradient(ellipse at 70% 60%, rgba(199,210,254,0.5) 0%, transparent 55%);}
.bg-template-{slug}::after{background:radial-gradient(ellipse at 60% 50%, rgba(186,230,253,0.5) 0%, transparent 55%),radial-gradient(ellipse at 40% 30%, rgba(254,240,138,0.4) 0%, transparent 55%);animation-duration:38s;animation-direction:reverse;}
@keyframes {slug}-rot{from{transform:rotate(0)}to{transform:rotate(360deg)}}",
            ],
            [
                'Floating Bubbles Light',
                '#f0f9ff',
                "
.bg-template-{slug}{position:fixed;inset:0;z-index:-1;background:linear-gradient(180deg,#eff6ff,#f0fdfa);overflow:hidden;}
.bg-template-{slug} .lb{position:absolute;bottom:-60px;border-radius:50%;background:radial-gradient(circle at 30% 30%, rgba(255,255,255,0.9), rgba(186,230,253,0.6));animation:{slug}-rise linear infinite;}
@keyframes {slug}-rise{0%{transform:translateY(0) scale(0.9);opacity:0}10%{opacity:.9}100%{transform:translateY(-110vh) scale(1.1);opacity:0}}",
                "
(function(){var c=document.querySelector('.bg-template-{slug}');if(!c)return;for(var i=0;i<14;i++){var b=document.createElement('div');b.className='lb';var s=10+Math.random()*50;b.style.width=b.style.height=s+'px';b.style.left=Math.random()*100+'%';b.style.animationDuration=(10+Math.random()*14)+'s';b.style.animationDelay=Math.random()*10+'s';c.appendChild(b);}})();",
            ],
            [
                'Petal Drift',
                '#fff1f2',
                "
.bg-template-{slug}{position:fixed;inset:0;z-index:-1;background:linear-gradient(180deg,#fff1f2,#fef9c3);overflow:hidden;}
.bg-template-{slug} .pt{position:absolute;top:-20px;width:14px;height:14px;background:#fbcfe8;border-radius:60% 0 60% 0;opacity:.85;animation:{slug}-fall linear infinite;}
@keyframes {slug}-fall{0%{transform:translateY(-20px) rotate(0)}100%{transform:translateY(110vh) rotate(540deg)}}",
                "
(function(){var c=document.querySelector('.bg-template-{slug}');if(!c)return;var palette=['#fbcfe8','#fecdd3','#fde68a','#ddd6fe'];for(var i=0;i<20;i++){var p=document.createElement('div');p.className='pt';p.style.left=Math.random()*100+'%';p.style.background=palette[i%palette.length];p.style.animationDuration=(8+Math.random()*10)+'s';p.style.animationDelay=Math.random()*8+'s';p.style.transform='scale('+(0.6+Math.random()*0.9)+')';c.appendChild(p);}})();",
            ],
            [
                'Sun Rays Soft',
                'conic-gradient(from 0deg, #fef9c3, #fff, #fef9c3, #fff, #fef9c3)',
                "
.bg-template-{slug}{position:fixed;inset:0;z-index:-1;background:#fffbeb;overflow:hidden;}
.bg-template-{slug}::before{content:'';position:absolute;width:200%;height:200%;top:-50%;left:-50%;background:conic-gradient(from 0deg at 50% 50%, rgba(254,240,138,0.35) 0deg, transparent 30deg, rgba(254,240,138,0.35) 60deg, transparent 90deg, rgba(254,240,138,0.35) 120deg, transparent 150deg, rgba(254,240,138,0.35) 180deg, transparent 210deg, rgba(254,240,138,0.35) 240deg, transparent 270deg, rgba(254,240,138,0.35) 300deg, transparent 330deg);animation:{slug}-spin 60s linear infinite;}
@keyframes {slug}-spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}",
            ],
            [
                'Cloud Drift',
                '#e0f2fe',
                "
.bg-template-{slug}{position:fixed;inset:0;z-index:-1;background:linear-gradient(180deg,#dbeafe 0%,#eff6ff 60%,#fff 100%);overflow:hidden;}
.bg-template-{slug} .cl{position:absolute;background:rgba(255,255,255,0.85);border-radius:50%;filter:blur(20px);animation:{slug}-drift linear infinite;}
@keyframes {slug}-drift{0%{transform:translateX(-30%)}100%{transform:translateX(130vw)}}",
                "
(function(){var c=document.querySelector('.bg-template-{slug}');if(!c)return;for(var i=0;i<6;i++){var d=document.createElement('div');d.className='cl';var w=120+Math.random()*200;d.style.width=w+'px';d.style.height=(w*0.45)+'px';d.style.top=(5+Math.random()*70)+'%';d.style.animationDuration=(40+Math.random()*40)+'s';d.style.animationDelay=(-Math.random()*60)+'s';c.appendChild(d);}})();",
            ],
        ];
        foreach ($animated as $entry) {
            $name = $entry[0];
            $preview = $entry[1];
            $cssTpl = $entry[2];
            $jsTpl = $entry[3] ?? null;
            $slug = $this->slug($name);
            $css = str_replace('{slug}', $slug, $cssTpl);
            $js  = $jsTpl !== null ? str_replace('{slug}', $slug, $jsTpl) : null;
            $out[] = [
                'name'          => $name,
                'slug'          => $slug,
                'preview_color' => $preview,
                'category'      => 'animated',
                'css'           => $css,
                'js'            => $js,
            ];
        }

        return $out;
    }
}
