<?php

namespace App\Modules\User\Support;

/**
 * 100+ preset background gradients for the biolink Appearance picker.
 *
 * Each entry: ['id' => slug, 'name' => 'Sunset', 'category' => 'warm',
 *              'angle' => 135, 'type' => 'linear',
 *              'stops' => [['color'=>'#...', 'pos'=>0], …]]
 *
 * The picker stores the selected preset's id under
 * settings.biolink.gradient_preset_id so we can re-highlight it on edit, and
 * also writes the resolved stops/angle/type into the existing gradient_*
 * fields so the public page renders without needing to re-evaluate the
 * catalog (presets are pure presentation data and may evolve).
 */
class GradientCatalog
{
    public const CATEGORIES = [
        'featured' => 'Featured',
        'warm' => 'Warm',
        'cool' => 'Cool',
        'pastel' => 'Pastel',
        'dark' => 'Dark',
        'neon' => 'Neon',
        'mono' => 'Monochrome',
        'tropical' => 'Tropical',
        'metal' => 'Metal',
        'abstract' => 'Abstract',
        'classic' => 'Classic',
    ];

    /**
     * @return array<int, array{id:string, name:string, category:string,
     *   angle:int, type:string, stops:array<int,array{color:string,pos:int}>}>
     */
    public static function all(): array
    {
        // Compact builder: [id, name, category, angle, type, [stops...]]
        // type defaults to 'linear' when omitted.
        $raw = [
            // ---- FEATURED ----
            ['sunset-magic', 'Sunset Magic', 'featured', 135, ['#ff6b6b','#feca57','#48dbfb']],
            ['midnight-violet', 'Midnight Violet', 'featured', 135, ['#0a0612','#1a0533','#0a0612']],
            ['cosmic-fusion', 'Cosmic Fusion', 'featured', 135, ['#ff0844','#ffb199']],
            ['aurora', 'Aurora', 'featured', 160, ['#00c9ff','#92fe9d']],
            ['peach-perfect', 'Peach Perfect', 'featured', 135, ['#ed4264','#ffedbc']],
            ['deep-ocean', 'Deep Ocean', 'featured', 135, ['#2c3e50','#4ca1af']],
            ['vivid-bloom', 'Vivid Bloom', 'featured', 135, ['#ee0979','#ff6a00']],
            ['galaxy-swirl', 'Galaxy Swirl', 'featured', 135, ['#1a2980','#26d0ce']],
            ['raspberry-cream', 'Raspberry Cream', 'featured', 135, ['#dd5e89','#f7bb97']],
            ['violet-mist', 'Violet Mist', 'featured', 135, ['#7f7fd5','#86a8e7','#91eae4']],
            ['black-gold', 'Black Gold', 'featured', 135, ['#000000','#434343']],
            ['rose-quartz', 'Rose Quartz', 'featured', 135, ['#bdc3c7','#2c3e50']],

            // ---- WARM ----
            ['sunny-morning', 'Sunny Morning', 'warm', 120, ['#f6d365','#fda085']],
            ['warm-flame', 'Warm Flame', 'warm', 45, ['#ff9a9e','#fad0c4']],
            ['orange-fun', 'Orange Fun', 'warm', 135, ['#fc4a1a','#f7b733']],
            ['firewatch', 'Firewatch', 'warm', 135, ['#cb2d3e','#ef473a']],
            ['hot-flame', 'Hot Flame', 'warm', 135, ['#ff416c','#ff4b2b']],
            ['burning-orange', 'Burning Orange', 'warm', 135, ['#ff416c','#ff4b2b']],
            ['vice-city', 'Vice City', 'warm', 135, ['#3494e6','#ec6ead']],
            ['amber-glow', 'Amber Glow', 'warm', 90, ['#fceabb','#f8b500']],
            ['lavender-sun', 'Lavender Sun', 'warm', 135, ['#fbc2eb','#a6c1ee']],
            ['summer-breeze', 'Summer Breeze', 'warm', 90, ['#fbc8d4','#9795f0']],
            ['copper-shine', 'Copper Shine', 'warm', 135, ['#b79891','#94716b']],
            ['kashmir', 'Kashmir', 'warm', 135, ['#614385','#516395']],

            // ---- COOL ----
            ['blue-lagoon', 'Blue Lagoon', 'cool', 135, ['#43cea2','#185a9d']],
            ['ocean-blue', 'Ocean Blue', 'cool', 135, ['#2e3192','#1bffff']],
            ['azure-pop', 'Azure Pop', 'cool', 135, ['#ef32d9','#89fffd']],
            ['blueberry', 'Blueberry', 'cool', 135, ['#74ebd5','#9face6']],
            ['cool-blues', 'Cool Blues', 'cool', 135, ['#2193b0','#6dd5ed']],
            ['arctic', 'Arctic', 'cool', 135, ['#83a4d4','#b6fbff']],
            ['frost', 'Frost', 'cool', 135, ['#000428','#004e92']],
            ['stellar', 'Stellar', 'cool', 135, ['#7474bf','#348ac7']],
            ['sea-blizz', 'Sea Blizz', 'cool', 135, ['#1cd8d2','#93edc7']],
            ['jaipur', 'Jaipur', 'cool', 135, ['#dd5e89','#f7bb97']],
            ['polar', 'Polar', 'cool', 160, ['#0f2027','#203a43','#2c5364']],
            ['mint-leaf', 'Mint Leaf', 'cool', 135, ['#00b09b','#96c93d']],

            // ---- PASTEL ----
            ['cotton-candy', 'Cotton Candy', 'pastel', 135, ['#fbc2eb','#a6c1ee']],
            ['soft-pink', 'Soft Pink', 'pastel', 135, ['#ffdde1','#ee9ca7']],
            ['piggy-pink', 'Piggy Pink', 'pastel', 135, ['#ee9ca7','#ffdde1']],
            ['mojito', 'Mojito', 'pastel', 135, ['#1d976c','#93f9b9']],
            ['sky-aqua', 'Sky Aqua', 'pastel', 135, ['#a1c4fd','#c2e9fb']],
            ['fresh-mint', 'Fresh Mint', 'pastel', 135, ['#cfd9df','#e2ebf0']],
            ['baby-blue', 'Baby Blue', 'pastel', 135, ['#dbe6f6','#c5796d']],
            ['cherry-blossom', 'Cherry Blossom', 'pastel', 135, ['#ffe5ec','#ffc2d1']],
            ['marshmallow', 'Marshmallow', 'pastel', 135, ['#fffefb','#f9f7f4']],
            ['lemon-mist', 'Lemon Mist', 'pastel', 135, ['#fdfcfb','#e2d1c3']],
            ['lilac-dust', 'Lilac Dust', 'pastel', 135, ['#e0c3fc','#8ec5fc']],
            ['peach-mist', 'Peach Mist', 'pastel', 135, ['#ffecd2','#fcb69f']],

            // ---- DARK ----
            ['night-sky', 'Night Sky', 'dark', 135, ['#0f0c29','#302b63','#24243e']],
            ['deep-space', 'Deep Space', 'dark', 135, ['#000000','#0f0c29']],
            ['carbon', 'Carbon', 'dark', 135, ['#16222a','#3a6073']],
            ['eclipse', 'Eclipse', 'dark', 135, ['#000428','#004e92']],
            ['matte-black', 'Matte Black', 'dark', 135, ['#232526','#414345']],
            ['midnight-blue', 'Midnight Blue', 'dark', 135, ['#020024','#090979','#00d4ff']],
            ['obsidian', 'Obsidian', 'dark', 135, ['#1c1c1c','#2c2c2c']],
            ['storm', 'Storm', 'dark', 135, ['#373b44','#4286f4']],
            ['shadow-purple', 'Shadow Purple', 'dark', 135, ['#1a0533','#3a1c5e']],
            ['dark-knight', 'Dark Knight', 'dark', 135, ['#ba8b02','#181818']],
            ['ink', 'Ink', 'dark', 135, ['#000c40','#f0f2f0']],
            ['charcoal', 'Charcoal', 'dark', 135, ['#36454f','#000000']],

            // ---- NEON ----
            ['neon-life', 'Neon Life', 'neon', 135, ['#b3ffab','#12fff7']],
            ['cyber-grape', 'Cyber Grape', 'neon', 135, ['#7028e4','#e5b2ca']],
            ['electric-violet', 'Electric Violet', 'neon', 135, ['#4776e6','#8e54e9']],
            ['neon-pink', 'Neon Pink', 'neon', 135, ['#ff00cc','#333399']],
            ['toxic', 'Toxic', 'neon', 135, ['#39ff14','#0aff99']],
            ['retro-wave', 'Retro Wave', 'neon', 135, ['#ff00ff','#00ffff']],
            ['miami-vice', 'Miami Vice', 'neon', 135, ['#ff7e5f','#feb47b']],
            ['cyberpunk', 'Cyberpunk', 'neon', 135, ['#fc466b','#3f5efb']],
            ['plasma', 'Plasma', 'neon', 135, ['#f857a6','#ff5858']],
            ['ultraviolet', 'Ultraviolet', 'neon', 135, ['#654ea3','#eaafc8']],
            ['matrix', 'Matrix', 'neon', 135, ['#003300','#00ff66']],
            ['hot-magenta', 'Hot Magenta', 'neon', 135, ['#ff0080','#ff8c00']],

            // ---- MONOCHROME ----
            ['silver', 'Silver', 'mono', 135, ['#bdc3c7','#2c3e50']],
            ['fog', 'Fog', 'mono', 135, ['#606c88','#3f4c6b']],
            ['steel', 'Steel', 'mono', 135, ['#4b6cb7','#182848']],
            ['dust', 'Dust', 'mono', 135, ['#d3cce3','#e9e4f0']],
            ['paper', 'Paper', 'mono', 135, ['#f5f7fa','#c3cfe2']],
            ['concrete', 'Concrete', 'mono', 135, ['#bdc3c7','#7f8c8d']],
            ['mineral', 'Mineral', 'mono', 135, ['#283c86','#45a247']],
            ['iron', 'Iron', 'mono', 135, ['#52575c','#1a1a1d']],
            ['silk', 'Silk', 'mono', 135, ['#ece9e6','#ffffff']],
            ['stone', 'Stone', 'mono', 135, ['#dad299','#b0dab9']],

            // ---- TROPICAL ----
            ['palm-beach', 'Palm Beach', 'tropical', 135, ['#11998e','#38ef7d']],
            ['mango', 'Mango', 'tropical', 135, ['#ffe259','#ffa751']],
            ['coral-reef', 'Coral Reef', 'tropical', 135, ['#ff9966','#ff5e62']],
            ['lime-soda', 'Lime Soda', 'tropical', 135, ['#a8ff78','#78ffd6']],
            ['hibiscus', 'Hibiscus', 'tropical', 135, ['#ff5f6d','#ffc371']],
            ['paradise', 'Paradise', 'tropical', 135, ['#5ee7df','#b490ca']],
            ['kiwi', 'Kiwi', 'tropical', 135, ['#56ab2f','#a8e063']],
            ['caribbean', 'Caribbean', 'tropical', 135, ['#06beb6','#48b1bf']],
            ['flamingo', 'Flamingo', 'tropical', 135, ['#fc5c7d','#6a82fb']],
            ['sunset-tropics', 'Sunset Tropics', 'tropical', 135, ['#ff7e5f','#feb47b']],

            // ---- METAL ----
            ['gold', 'Gold', 'metal', 135, ['#ffd700','#b8860b']],
            ['rose-gold', 'Rose Gold', 'metal', 135, ['#b76e79','#eecda3']],
            ['platinum', 'Platinum', 'metal', 135, ['#e5e4e2','#c0c0c0']],
            ['bronze', 'Bronze', 'metal', 135, ['#cd7f32','#8c5a2b']],
            ['titanium', 'Titanium', 'metal', 135, ['#878681','#3a3a3a']],
            ['copper', 'Copper', 'metal', 135, ['#b87333','#704214']],
            ['chrome', 'Chrome', 'metal', 135, ['#dfe9f3','#ffffff']],
            ['gunmetal', 'Gunmetal', 'metal', 135, ['#2c3e50','#4ca1af']],

            // ---- ABSTRACT (radial / conic) ----
            ['radial-aurora', 'Radial Aurora', 'abstract', 0, ['#fc466b','#3f5efb'], 'radial'],
            ['radial-sun', 'Radial Sun', 'abstract', 0, ['#ffe000','#799f0c'], 'radial'],
            ['radial-galaxy', 'Radial Galaxy', 'abstract', 0, ['#0f0c29','#302b63','#24243e'], 'radial'],
            ['radial-bubble', 'Radial Bubble', 'abstract', 0, ['#fdfbfb','#ebedee'], 'radial'],
            ['radial-night', 'Radial Night', 'abstract', 0, ['#020024','#090979','#00d4ff'], 'radial'],
            ['conic-spectrum', 'Conic Spectrum', 'abstract', 0, ['#ff0000','#ffff00','#00ff00','#00ffff','#0000ff','#ff00ff','#ff0000'], 'conic'],
            ['conic-warm', 'Conic Warm', 'abstract', 0, ['#ff7e5f','#feb47b','#ff7e5f'], 'conic'],
            ['conic-cool', 'Conic Cool', 'abstract', 0, ['#43cea2','#185a9d','#43cea2'], 'conic'],
            ['conic-violet', 'Conic Violet', 'abstract', 0, ['#7028e4','#e5b2ca','#7028e4'], 'conic'],
            ['radial-rose', 'Radial Rose', 'abstract', 0, ['#ee9ca7','#ffdde1'], 'radial'],

            // ---- CLASSIC ----
            // Task #6204: the legacy BgPresetCatalog "gradients" group,
            // merged into this catalog (deduped against the entries above
            // and each other; rgb() converted to hex, angles rounded,
            // radial variants marked). The old preset KEYS still render
            // via BgPresetCatalog for existing pages; these rows are the
            // Gradient-tab replacements for new picks.
            ['royal-fusion', 'Royal Fusion', 'classic', 43, ['#4158d0','#c850c0','#ffcc70']],
            ['electric-sky', 'Electric Sky', 'classic', 19, ['#21d4fd','#b721ff']],
            ['amber-blaze', 'Amber Blaze', 'classic', 110, ['#ffb418','#f73131']],
            ['spring-tide', 'Spring Tide', 'classic', 135, ['#79f1a4','#0e5cad']],
            ['pink-punch', 'Pink Punch', 'classic', 180, ['#ff758c','#ff7eb3']],
            ['true-blue', 'True Blue', 'classic', 292, ['#3355ff','#0088ff']],
            ['slate-night', 'Slate Night', 'classic', 112, ['#202639','#3f4c77']],
            ['plum-void', 'Plum Void', 'classic', 0, ['#642b73','#040004'], 'radial'],
            ['periwinkle-haze', 'Periwinkle Haze', 'classic', 62, ['#8ec5fc','#e0c3fc']],
            ['candy-floss', 'Candy Floss', 'classic', 0, ['#ffdee9','#b5fffc']],
            ['mango-pink', 'Mango Pink', 'classic', 45, ['#fbda61','#ff5acd']],
            ['fuchsia-deep', 'Fuchsia Deep', 'classic', 180, ['#f915d7','#160062']],
            ['ocean-radial', 'Ocean Radial', 'classic', 0, ['#02254e','#04387e','#55f5dd'], 'radial'],
            ['violet-depth', 'Violet Depth', 'classic', 91, ['#8850e2','#100d5b']],
            ['lemon-ice', 'Lemon Ice', 'classic', 110, ['#fefdcd','#a3e6ff']],
            ['porcelain', 'Porcelain', 'classic', 174, ['#fff4e4','#f0f6ee']],
            ['sherbet', 'Sherbet', 'classic', 64, ['#fe7a98','#ffce86','#acfda3']],
            ['magenta-burst', 'Magenta Burst', 'classic', 0, ['#fc2567','#fa2697','#ba08b5'], 'radial'],
            ['sky-radial', 'Sky Radial', 'classic', 0, ['#61baff','#a6effd'], 'radial'],
            ['lavender-veil', 'Lavender Veil', 'classic', 0, ['#e1c8ef','#a3e1e9'], 'radial'],
            ['berry-wine', 'Berry Wine', 'classic', 110, ['#d10074','#6e2c6b']],
            ['peach-glow', 'Peach Glow', 'classic', 0, ['#ff6398','#fbd595'], 'radial'],
            ['cornflower', 'Cornflower', 'classic', 0, ['#b0cfff','#5c68a8']],
            ['greenfield', 'Greenfield', 'classic', 90, ['#01c656','#2f9818']],
            ['violet-coral', 'Violet Coral', 'classic', 90, ['#770ebf','#ee8d7d']],
            ['fire-lime', 'Fire Lime', 'classic', 0, ['#ee042c','#cacb28']],
            ['pink-abyss', 'Pink Abyss', 'classic', 0, ['#f613da','#021c33']],
            ['forest-flash', 'Forest Flash', 'classic', 45, ['#125813','#28e23b']],
            ['teal-nebula', 'Teal Nebula', 'classic', 0, ['#2ef3d4','#5011d5','#3f82d5'], 'radial'],
            ['sunset-radial', 'Sunset Radial', 'classic', 0, ['#fec35f','#e9772c','#f49d46'], 'radial'],
            ['orchid-fade', 'Orchid Fade', 'classic', 0, ['#eb29fe','#b92ad9','#862ab4','#542b8e','#212b69'], 'radial'],
            ['twilight-radial', 'Twilight Radial', 'classic', 0, ['#502054','#3e2351','#2c264f','#1a284c','#082b49'], 'radial'],
            ['pink-orbit', 'Pink Orbit', 'classic', 0, ['#ff0dbb','#73376e'], 'radial'],
            ['indigo-core', 'Indigo Core', 'classic', 0, ['#5f1efe','#42207f'], 'radial'],
            ['aqua-beam', 'Aqua Beam', 'classic', 0, ['#06ccd0','#1d65ee'], 'radial'],
            ['violet-sea', 'Violet Sea', 'classic', 0, ['#cb06e5','#097bcf'], 'radial'],
            ['amethyst-glow', 'Amethyst Glow', 'classic', 0, ['#cc5aff','#1e087c'], 'radial'],
            ['ember-radial', 'Ember Radial', 'classic', 0, ['#d56d27','#1d1005'], 'radial'],
            ['graphite-radial', 'Graphite Radial', 'classic', 0, ['#474747','#080808'], 'radial'],
            ['jade-shadow', 'Jade Shadow', 'classic', 132, ['#03946c','#212322']],
            ['sandstone', 'Sandstone', 'classic', 134, ['#f9ca78','#d4cea9','#acc0bf']],
            ['citrus-grove', 'Citrus Grove', 'classic', 54, ['#d2ff1f','#7bc255','#e3902b']],
            ['rainbow-run', 'Rainbow Run', 'classic', 132, ['#dd0203','#fb8902','#f8eb05','#007f26','#054bf9','#720682']],
            ['lava-lamp', 'Lava Lamp', 'classic', 132, ['#faaa00','#ed1313','#d54aff']],
            ['cyan-plum-fade', 'Cyan Plum Fade', 'classic', 72, ['#2cdef2','#9c2bab','#272b2c']],
            ['mauve-night', 'Mauve Night', 'classic', 132, ['#735d8e','#3a2156']],
            ['peach-sky', 'Peach Sky', 'classic', 132, ['#f9ceb7','#add1f2']],
            ['pink-periwinkle', 'Pink Periwinkle', 'classic', 132, ['#ffceec','#9796f0']],
            ['azure-flow', 'Azure Flow', 'classic', 132, ['#026cdf','#00f6fd']],
            ['lemon-amber', 'Lemon Amber', 'classic', 132, ['#f1f20b','#f8a11b']],
            ['magenta-shadow', 'Magenta Shadow', 'classic', 132, ['#920b7e','#341d31']],
            ['indigo-candy', 'Indigo Candy', 'classic', 132, ['#3b376a','#008fba','#ff95d5']],
            ['charcoal-flame', 'Charcoal Flame', 'classic', 292, ['#272b2c','#f13c2b']],
            ['glacier-blue', 'Glacier Blue', 'classic', 45, ['#338af9','#2ff8ff']],
            ['terracotta', 'Terracotta', 'classic', 90, ['#d77d39','#b85643']],
            ['crimson-rise', 'Crimson Rise', 'classic', 0, ['#5e0504','#fd133d']],
        ];

        $out = [];
        foreach ($raw as $row) {
            [$id, $name, $cat, $angle, $colors] = $row;
            $type = $row[5] ?? 'linear';
            $n = count($colors);
            $stops = [];
            foreach (array_values($colors) as $i => $c) {
                // Evenly distribute stop positions across 0..100.
                $pos = $n === 1 ? 50 : (int) round(($i / ($n - 1)) * 100);
                $stops[] = ['color' => $c, 'pos' => $pos];
            }
            $out[] = [
                'id' => $id,
                'name' => $name,
                'category' => $cat,
                'angle' => (int) $angle,
                'type' => $type,
                'stops' => $stops,
            ];
        }
        return $out;
    }

    /** Build the same CSS string the picker preview uses for one preset. */
    public static function toCss(array $preset): string
    {
        $stops = $preset['stops'] ?? [];
        $stopsStr = implode(', ', array_map(fn ($s) => $s['color'] . ' ' . $s['pos'] . '%', $stops));
        $type = $preset['type'] ?? 'linear';
        $angle = (int) ($preset['angle'] ?? 135);
        if ($type === 'radial') return 'radial-gradient(circle, ' . $stopsStr . ')';
        if ($type === 'conic') return 'conic-gradient(from ' . $angle . 'deg, ' . $stopsStr . ')';
        return 'linear-gradient(' . $angle . 'deg, ' . $stopsStr . ')';
    }

    public static function findById(string $id): ?array
    {
        foreach (self::all() as $p) {
            if ($p['id'] === $id) return $p;
        }
        return null;
    }
}
