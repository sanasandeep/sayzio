<?php

namespace App\Modules\User\Support;

/**
 * "Tiles" background type (Task #6204): a full-page metro-style grid of
 * gradient tiles. Only palette + layout KEYS are stored in link settings;
 * the tile gradients and grid spans are always resolved server-side from
 * this catalog, never from client input. The optional animation is a
 * gentle opacity pulse that is disabled under prefers-reduced-motion.
 */
class TilesBgCatalog
{
    public const LAYOUTS = [
        'uniform' => 'Uniform grid',
        'metro'   => 'Metro mix',
        'brick'   => 'Brick rows',
    ];

    public const TILE_COUNT = 24;

    /**
     * Each palette: a cycling list of tile gradient CSS values (safe,
     * curated `linear-gradient(...)` strings only).
     *
     * @var array<string, array{label: string, tiles: list<string>, colors: list<string>}>
     */
    private const PALETTES = [
        'tiles_midnight' => ['label' => 'Midnight', 'colors' => ['#1e293b', '#3b82f6', '#0f172a'], 'tiles' => [
            'linear-gradient(135deg, #1e293b, #0f172a)', 'linear-gradient(135deg, #1d4ed8, #1e3a8a)',
            'linear-gradient(135deg, #334155, #1e293b)', 'linear-gradient(135deg, #0ea5e9, #0369a1)',
            'linear-gradient(135deg, #312e81, #1e1b4b)', 'linear-gradient(135deg, #475569, #1f2937)',
        ]],
        'tiles_sunset' => ['label' => 'Sunset', 'colors' => ['#f97316', '#db2777', '#7c2d12'], 'tiles' => [
            'linear-gradient(135deg, #fb923c, #ea580c)', 'linear-gradient(135deg, #f43f5e, #be123c)',
            'linear-gradient(135deg, #fbbf24, #d97706)', 'linear-gradient(135deg, #db2777, #831843)',
            'linear-gradient(135deg, #c2410c, #7c2d12)', 'linear-gradient(135deg, #fda4af, #e11d48)',
        ]],
        'tiles_forest' => ['label' => 'Forest', 'colors' => ['#16a34a', '#065f46', '#365314'], 'tiles' => [
            'linear-gradient(135deg, #22c55e, #15803d)', 'linear-gradient(135deg, #0d9488, #115e59)',
            'linear-gradient(135deg, #84cc16, #4d7c0f)', 'linear-gradient(135deg, #065f46, #022c22)',
            'linear-gradient(135deg, #4ade80, #16a34a)', 'linear-gradient(135deg, #365314, #1a2e05)',
        ]],
        'tiles_berry' => ['label' => 'Berry', 'colors' => ['#a21caf', '#7c3aed', '#4a044e'], 'tiles' => [
            'linear-gradient(135deg, #c026d3, #86198f)', 'linear-gradient(135deg, #8b5cf6, #6d28d9)',
            'linear-gradient(135deg, #ec4899, #be185d)', 'linear-gradient(135deg, #6b21a8, #3b0764)',
            'linear-gradient(135deg, #d946ef, #a21caf)', 'linear-gradient(135deg, #4c1d95, #2e1065)',
        ]],
        'tiles_ocean' => ['label' => 'Ocean', 'colors' => ['#0891b2', '#0e7490', '#164e63'], 'tiles' => [
            'linear-gradient(135deg, #22d3ee, #0891b2)', 'linear-gradient(135deg, #0ea5e9, #0369a1)',
            'linear-gradient(135deg, #2dd4bf, #0f766e)', 'linear-gradient(135deg, #155e75, #164e63)',
            'linear-gradient(135deg, #38bdf8, #0284c7)', 'linear-gradient(135deg, #075985, #0c4a6e)',
        ]],
        'tiles_mono' => ['label' => 'Mono', 'colors' => ['#404040', '#737373', '#171717'], 'tiles' => [
            'linear-gradient(135deg, #525252, #262626)', 'linear-gradient(135deg, #737373, #404040)',
            'linear-gradient(135deg, #a3a3a3, #525252)', 'linear-gradient(135deg, #262626, #0a0a0a)',
            'linear-gradient(135deg, #404040, #171717)', 'linear-gradient(135deg, #8a8a8a, #3f3f46)',
        ]],
        'tiles_pastel' => ['label' => 'Pastel', 'colors' => ['#fbcfe8', '#bfdbfe', '#fde68a'], 'tiles' => [
            'linear-gradient(135deg, #fbcfe8, #f9a8d4)', 'linear-gradient(135deg, #bfdbfe, #93c5fd)',
            'linear-gradient(135deg, #fde68a, #fcd34d)', 'linear-gradient(135deg, #bbf7d0, #86efac)',
            'linear-gradient(135deg, #ddd6fe, #c4b5fd)', 'linear-gradient(135deg, #fed7aa, #fdba74)',
        ]],

        // Task #6231: the picker showed seven palettes against a hundred-odd
        // looks in every other category. Forty-five more, each a six-tile
        // cycle of one colour family so the metro grid stays coherent
        // whichever layout and span pattern lands on it. Labels are checked
        // for collisions against the template library and the torn combos,
        // because one merged library means one namespace for search.
        'tiles_coral_bay' => ['label' => 'Coral Bay', 'colors' => ['#ff7a5c', '#f9c784', '#2a7f7f'], 'tiles' => [
            'linear-gradient(135deg, #ff7a5c, #994937)',
            'linear-gradient(135deg, #ff9f7a, #995f49)',
            'linear-gradient(135deg, #f9c784, #95774f)',
            'linear-gradient(135deg, #4fb0a5, #2f6963)',
            'linear-gradient(135deg, #2a7f7f, #194c4c)',
            'linear-gradient(135deg, #f2765a, #914636)',
        ]],
        'tiles_turmeric' => ['label' => 'Turmeric', 'colors' => ['#f59e0b', '#d97706', '#fcd34d'], 'tiles' => [
            'linear-gradient(135deg, #f59e0b, #935e06)',
            'linear-gradient(135deg, #fbbf24, #967215)',
            'linear-gradient(135deg, #d97706, #824703)',
            'linear-gradient(135deg, #b45309, #6c3105)',
            'linear-gradient(135deg, #fcd34d, #977e2e)',
            'linear-gradient(135deg, #a16207, #603a04)',
        ]],
        'tiles_lavender_field' => ['label' => 'Lavender Field', 'colors' => ['#a78bfa', '#8b5cf6', '#ddd6fe'], 'tiles' => [
            'linear-gradient(135deg, #a78bfa, #645396)',
            'linear-gradient(135deg, #c4b5fd, #756c97)',
            'linear-gradient(135deg, #8b5cf6, #533793)',
            'linear-gradient(135deg, #7c3aed, #4a228e)',
            'linear-gradient(135deg, #ddd6fe, #848098)',
            'linear-gradient(135deg, #6d28d9, #411882)',
        ]],
        'tiles_deep_sea' => ['label' => 'Deep Sea', 'colors' => ['#0ea5e9', '#0e7490', '#0891b2'], 'tiles' => [
            'linear-gradient(135deg, #0ea5e9, #08638b)',
            'linear-gradient(135deg, #0c4a6e, #072c42)',
            'linear-gradient(135deg, #0e7490, #084556)',
            'linear-gradient(135deg, #164e63, #0d2e3b)',
            'linear-gradient(135deg, #0891b2, #04576a)',
            'linear-gradient(135deg, #075985, #04354f)',
        ]],
        'tiles_rosewood' => ['label' => 'Rosewood', 'colors' => ['#9f1239', '#e11d48', '#fb7185'], 'tiles' => [
            'linear-gradient(135deg, #9f1239, #5f0a22)',
            'linear-gradient(135deg, #be123c, #720a24)',
            'linear-gradient(135deg, #e11d48, #87112b)',
            'linear-gradient(135deg, #881337, #510b21)',
            'linear-gradient(135deg, #fb7185, #96434f)',
            'linear-gradient(135deg, #7f1d3a, #4c1122)',
        ]],
        'tiles_moss' => ['label' => 'Moss', 'colors' => ['#4d7c0f', '#3f6212', '#2f4f0a'], 'tiles' => [
            'linear-gradient(135deg, #4d7c0f, #2e4a09)',
            'linear-gradient(135deg, #65a30d, #3c6107)',
            'linear-gradient(135deg, #3f6212, #253a0a)',
            'linear-gradient(135deg, #84cc16, #4f7a0d)',
            'linear-gradient(135deg, #2f4f0a, #1c2f06)',
            'linear-gradient(135deg, #365314, #20310c)',
        ]],
        'tiles_copper' => ['label' => 'Copper', 'colors' => ['#b45309', '#ea580c', '#f97316'], 'tiles' => [
            'linear-gradient(135deg, #b45309, #6c3105)',
            'linear-gradient(135deg, #c2410c, #742707)',
            'linear-gradient(135deg, #ea580c, #8c3407)',
            'linear-gradient(135deg, #92400e, #572608)',
            'linear-gradient(135deg, #f97316, #95450d)',
            'linear-gradient(135deg, #7c2d12, #4a1b0a)',
        ]],
        'tiles_arctic' => ['label' => 'Arctic', 'colors' => ['#bae6fd', '#38bdf8', '#e0f2fe'], 'tiles' => [
            'linear-gradient(135deg, #bae6fd, #6f8a97)',
            'linear-gradient(135deg, #7dd3fc, #4b7e97)',
            'linear-gradient(135deg, #38bdf8, #217194)',
            'linear-gradient(135deg, #0ea5e9, #08638b)',
            'linear-gradient(135deg, #e0f2fe, #869198)',
            'linear-gradient(135deg, #60a5fa, #396396)',
        ]],
        'tiles_damson' => ['label' => 'Damson', 'colors' => ['#6b21a8', '#581c87', '#4a1272'], 'tiles' => [
            'linear-gradient(135deg, #6b21a8, #401364)',
            'linear-gradient(135deg, #7e22ce, #4b147b)',
            'linear-gradient(135deg, #581c87, #341051)',
            'linear-gradient(135deg, #a21caf, #611069)',
            'linear-gradient(135deg, #4a1272, #2c0a44)',
            'linear-gradient(135deg, #86198f, #500f55)',
        ]],
        'tiles_desert_sand' => ['label' => 'Desert Sand', 'colors' => ['#fde68a', '#f59e0b', '#d97706'], 'tiles' => [
            'linear-gradient(135deg, #fde68a, #978a52)',
            'linear-gradient(135deg, #fcd34d, #977e2e)',
            'linear-gradient(135deg, #f59e0b, #935e06)',
            'linear-gradient(135deg, #fef3c7, #989177)',
            'linear-gradient(135deg, #d97706, #824703)',
            'linear-gradient(135deg, #fbbf24, #967215)',
        ]],
        'tiles_slate_steel' => ['label' => 'Slate Steel', 'colors' => ['#475569', '#334155', '#1e293b'], 'tiles' => [
            'linear-gradient(135deg, #475569, #2a333f)',
            'linear-gradient(135deg, #64748b, #3c4553)',
            'linear-gradient(135deg, #334155, #1e2733)',
            'linear-gradient(135deg, #94a3b8, #58616e)',
            'linear-gradient(135deg, #1e293b, #121823)',
            'linear-gradient(135deg, #cbd5e1, #797f87)',
        ]],
        'tiles_emerald_city' => ['label' => 'Emerald City', 'colors' => ['#059669', '#047857', '#065f46'], 'tiles' => [
            'linear-gradient(135deg, #059669, #035a3f)',
            'linear-gradient(135deg, #10b981, #096f4d)',
            'linear-gradient(135deg, #047857, #024834)',
            'linear-gradient(135deg, #34d399, #1f7e5b)',
            'linear-gradient(135deg, #065f46, #03392a)',
            'linear-gradient(135deg, #6ee7b7, #428a6d)',
        ]],
        'tiles_cherry_soda' => ['label' => 'Cherry Soda', 'colors' => ['#f43f5e', '#e11d48', '#be123c'], 'tiles' => [
            'linear-gradient(135deg, #f43f5e, #922538)',
            'linear-gradient(135deg, #fb7185, #96434f)',
            'linear-gradient(135deg, #e11d48, #87112b)',
            'linear-gradient(135deg, #fda4af, #976269)',
            'linear-gradient(135deg, #be123c, #720a24)',
            'linear-gradient(135deg, #fecdd3, #987b7e)',
        ]],
        'tiles_indigo_night' => ['label' => 'Indigo Night', 'colors' => ['#4338ca', '#3730a3', '#312e81'], 'tiles' => [
            'linear-gradient(135deg, #4338ca, #282179)',
            'linear-gradient(135deg, #4f46e5, #2f2a89)',
            'linear-gradient(135deg, #3730a3, #211c61)',
            'linear-gradient(135deg, #6366f1, #3b3d90)',
            'linear-gradient(135deg, #312e81, #1d1b4d)',
            'linear-gradient(135deg, #818cf8, #4d5494)',
        ]],
        'tiles_terracotta' => ['label' => 'Terracotta', 'colors' => ['#c2410c', '#9a3412', '#7c2d12'], 'tiles' => [
            'linear-gradient(135deg, #c2410c, #742707)',
            'linear-gradient(135deg, #ea580c, #8c3407)',
            'linear-gradient(135deg, #9a3412, #5c1f0a)',
            'linear-gradient(135deg, #fb923c, #965724)',
            'linear-gradient(135deg, #7c2d12, #4a1b0a)',
            'linear-gradient(135deg, #fdba74, #976f45)',
        ]],
        'tiles_sea_glass' => ['label' => 'Sea Glass', 'colors' => ['#5eead4', '#14b8a6', '#99f6e4'], 'tiles' => [
            'linear-gradient(135deg, #5eead4, #388c7f)',
            'linear-gradient(135deg, #2dd4bf, #1b7f72)',
            'linear-gradient(135deg, #14b8a6, #0c6e63)',
            'linear-gradient(135deg, #0d9488, #075851)',
            'linear-gradient(135deg, #99f6e4, #5b9388)',
            'linear-gradient(135deg, #0f766e, #094642)',
        ]],
        'tiles_grape_soda' => ['label' => 'Grape Soda', 'colors' => ['#a855f7', '#9333ea', '#7e22ce'], 'tiles' => [
            'linear-gradient(135deg, #a855f7, #643394)',
            'linear-gradient(135deg, #c084fc, #734f97)',
            'linear-gradient(135deg, #9333ea, #581e8c)',
            'linear-gradient(135deg, #d8b4fe, #816c98)',
            'linear-gradient(135deg, #7e22ce, #4b147b)',
            'linear-gradient(135deg, #e9d5ff, #8b7f99)',
        ]],
        'tiles_honey' => ['label' => 'Honey', 'colors' => ['#eab308', '#ca8a04', '#a16207'], 'tiles' => [
            'linear-gradient(135deg, #eab308, #8c6b04)',
            'linear-gradient(135deg, #facc15, #967a0c)',
            'linear-gradient(135deg, #ca8a04, #795202)',
            'linear-gradient(135deg, #fde047, #97862a)',
            'linear-gradient(135deg, #a16207, #603a04)',
            'linear-gradient(135deg, #fef08a, #989052)',
        ]],
        'tiles_thunderhead' => ['label' => 'Thunderhead', 'colors' => ['#334155', '#475569', '#64748b'], 'tiles' => [
            'linear-gradient(135deg, #334155, #1e2733)',
            'linear-gradient(135deg, #1e293b, #121823)',
            'linear-gradient(135deg, #475569, #2a333f)',
            'linear-gradient(135deg, #0f172a, #090d19)',
            'linear-gradient(135deg, #64748b, #3c4553)',
            'linear-gradient(135deg, #243244, #151e28)',
        ]],
        'tiles_blossom' => ['label' => 'Blossom', 'colors' => ['#f9a8d4', '#ec4899', '#db2777'], 'tiles' => [
            'linear-gradient(135deg, #f9a8d4, #95647f)',
            'linear-gradient(135deg, #f472b6, #92446d)',
            'linear-gradient(135deg, #ec4899, #8d2b5b)',
            'linear-gradient(135deg, #fbcfe8, #967c8b)',
            'linear-gradient(135deg, #db2777, #831747)',
            'linear-gradient(135deg, #fce7f3, #978a91)',
        ]],
        'tiles_pine' => ['label' => 'Pine', 'colors' => ['#166534', '#14532d', '#0b3d1c'], 'tiles' => [
            'linear-gradient(135deg, #166534, #0d3c1f)',
            'linear-gradient(135deg, #15803d, #0c4c24)',
            'linear-gradient(135deg, #14532d, #0c311b)',
            'linear-gradient(135deg, #16a34a, #0d612c)',
            'linear-gradient(135deg, #0b3d1c, #062410)',
            'linear-gradient(135deg, #22c55e, #147638)',
        ]],
        'tiles_rust_belt' => ['label' => 'Rust Belt', 'colors' => ['#92400e', '#78350f', '#713f12'], 'tiles' => [
            'linear-gradient(135deg, #92400e, #572608)',
            'linear-gradient(135deg, #a16207, #603a04)',
            'linear-gradient(135deg, #78350f, #481f09)',
            'linear-gradient(135deg, #b45309, #6c3105)',
            'linear-gradient(135deg, #713f12, #43250a)',
            'linear-gradient(135deg, #c2410c, #742707)',
        ]],
        'tiles_cyan_pop' => ['label' => 'Cyan Pop', 'colors' => ['#06b6d4', '#0891b2', '#0e7490'], 'tiles' => [
            'linear-gradient(135deg, #06b6d4, #036d7f)',
            'linear-gradient(135deg, #22d3ee, #147e8e)',
            'linear-gradient(135deg, #0891b2, #04576a)',
            'linear-gradient(135deg, #67e8f9, #3d8b95)',
            'linear-gradient(135deg, #0e7490, #084556)',
            'linear-gradient(135deg, #a5f3fc, #639197)',
        ]],
        'tiles_mulberry' => ['label' => 'Mulberry', 'colors' => ['#9d174d', '#db2777', '#ec4899'], 'tiles' => [
            'linear-gradient(135deg, #9d174d, #5e0d2e)',
            'linear-gradient(135deg, #be185d, #720e37)',
            'linear-gradient(135deg, #db2777, #831747)',
            'linear-gradient(135deg, #831843, #4e0e28)',
            'linear-gradient(135deg, #ec4899, #8d2b5b)',
            'linear-gradient(135deg, #6b0f36, #400920)',
        ]],
        'tiles_olive_grove' => ['label' => 'Olive Grove', 'colors' => ['#4d7c0f', '#3f6212', '#a3e635'], 'tiles' => [
            'linear-gradient(135deg, #4d7c0f, #2e4a09)',
            'linear-gradient(135deg, #65a30d, #3c6107)',
            'linear-gradient(135deg, #3f6212, #253a0a)',
            'linear-gradient(135deg, #84cc16, #4f7a0d)',
            'linear-gradient(135deg, #a3e635, #618a1f)',
            'linear-gradient(135deg, #2f4f0a, #1c2f06)',
        ]],
        'tiles_denim' => ['label' => 'Denim', 'colors' => ['#1d4ed8', '#1e40af', '#1e3a8a'], 'tiles' => [
            'linear-gradient(135deg, #1d4ed8, #112e81)',
            'linear-gradient(135deg, #2563eb, #163b8d)',
            'linear-gradient(135deg, #1e40af, #122669)',
            'linear-gradient(135deg, #3b82f6, #234e93)',
            'linear-gradient(135deg, #1e3a8a, #122252)',
            'linear-gradient(135deg, #60a5fa, #396396)',
        ]],
        'tiles_peach_melba' => ['label' => 'Peach Melba', 'colors' => ['#fdba74', '#f97316', '#ea580c'], 'tiles' => [
            'linear-gradient(135deg, #fdba74, #976f45)',
            'linear-gradient(135deg, #fb923c, #965724)',
            'linear-gradient(135deg, #f97316, #95450d)',
            'linear-gradient(135deg, #fed7aa, #988166)',
            'linear-gradient(135deg, #ea580c, #8c3407)',
            'linear-gradient(135deg, #ffedd5, #998e7f)',
        ]],
        'tiles_graphite' => ['label' => 'Graphite', 'colors' => ['#404040', '#525252', '#737373'], 'tiles' => [
            'linear-gradient(135deg, #404040, #262626)',
            'linear-gradient(135deg, #262626, #161616)',
            'linear-gradient(135deg, #525252, #313131)',
            'linear-gradient(135deg, #171717, #0d0d0d)',
            'linear-gradient(135deg, #737373, #454545)',
            'linear-gradient(135deg, #0a0a0a, #060606)',
        ]],
        'tiles_aquamarine' => ['label' => 'Aquamarine', 'colors' => ['#0d9488', '#115e59', '#2dd4bf'], 'tiles' => [
            'linear-gradient(135deg, #0d9488, #075851)',
            'linear-gradient(135deg, #0f766e, #094642)',
            'linear-gradient(135deg, #115e59, #0a3835)',
            'linear-gradient(135deg, #14b8a6, #0c6e63)',
            'linear-gradient(135deg, #2dd4bf, #1b7f72)',
            'linear-gradient(135deg, #134e4a, #0b2e2c)',
        ]],
        'tiles_fuchsia' => ['label' => 'Fuchsia', 'colors' => ['#c026d3', '#a21caf', '#86198f'], 'tiles' => [
            'linear-gradient(135deg, #c026d3, #73167e)',
            'linear-gradient(135deg, #d946ef, #822a8f)',
            'linear-gradient(135deg, #a21caf, #611069)',
            'linear-gradient(135deg, #e879f9, #8b4895)',
            'linear-gradient(135deg, #86198f, #500f55)',
            'linear-gradient(135deg, #f0abfc, #906697)',
        ]],
        'tiles_autumn_leaf' => ['label' => 'Autumn Leaf', 'colors' => ['#b91c1c', '#ea580c', '#7f1d1d'], 'tiles' => [
            'linear-gradient(135deg, #b91c1c, #6f1010)',
            'linear-gradient(135deg, #dc2626, #841616)',
            'linear-gradient(135deg, #ea580c, #8c3407)',
            'linear-gradient(135deg, #f59e0b, #935e06)',
            'linear-gradient(135deg, #7f1d1d, #4c1111)',
            'linear-gradient(135deg, #d97706, #824703)',
        ]],
        'tiles_fresh_mint' => ['label' => 'Fresh Mint', 'colors' => ['#a7f3d0', '#34d399', '#10b981'], 'tiles' => [
            'linear-gradient(135deg, #a7f3d0, #64917c)',
            'linear-gradient(135deg, #6ee7b7, #428a6d)',
            'linear-gradient(135deg, #34d399, #1f7e5b)',
            'linear-gradient(135deg, #d1fae5, #7d9689)',
            'linear-gradient(135deg, #10b981, #096f4d)',
            'linear-gradient(135deg, #ecfdf5, #8d9793)',
        ]],
        'tiles_royal' => ['label' => 'Royal', 'colors' => ['#1e3a8a', '#4c1d95', '#5b21b6'], 'tiles' => [
            'linear-gradient(135deg, #1e3a8a, #122252)',
            'linear-gradient(135deg, #312e81, #1d1b4d)',
            'linear-gradient(135deg, #4c1d95, #2d1159)',
            'linear-gradient(135deg, #1e1b4b, #12102d)',
            'linear-gradient(135deg, #5b21b6, #36136d)',
            'linear-gradient(135deg, #2e1065, #1b093c)',
        ]],
        'tiles_clay' => ['label' => 'Clay', 'colors' => ['#a8a29e', '#57534e', '#44403c'], 'tiles' => [
            'linear-gradient(135deg, #a8a29e, #64615e)',
            'linear-gradient(135deg, #78716c, #484340)',
            'linear-gradient(135deg, #57534e, #34312e)',
            'linear-gradient(135deg, #d6d3d1, #807e7d)',
            'linear-gradient(135deg, #44403c, #282624)',
            'linear-gradient(135deg, #292524, #181615)',
        ]],
        'tiles_neon_lime' => ['label' => 'Neon Lime', 'colors' => ['#a3e635', '#84cc16', '#65a30d'], 'tiles' => [
            'linear-gradient(135deg, #a3e635, #618a1f)',
            'linear-gradient(135deg, #bef264, #72913c)',
            'linear-gradient(135deg, #84cc16, #4f7a0d)',
            'linear-gradient(135deg, #d9f99d, #82955e)',
            'linear-gradient(135deg, #65a30d, #3c6107)',
            'linear-gradient(135deg, #ecfccb, #8d9779)',
        ]],
        'tiles_twilight' => ['label' => 'Twilight', 'colors' => ['#5b21b6', '#4c1d95', '#2e1065'], 'tiles' => [
            'linear-gradient(135deg, #5b21b6, #36136d)',
            'linear-gradient(135deg, #6d28d9, #411882)',
            'linear-gradient(135deg, #4c1d95, #2d1159)',
            'linear-gradient(135deg, #7c3aed, #4a228e)',
            'linear-gradient(135deg, #2e1065, #1b093c)',
            'linear-gradient(135deg, #8b5cf6, #533793)',
        ]],
        'tiles_candy_floss' => ['label' => 'Candy Floss', 'colors' => ['#f472b6', '#c084fc', '#a78bfa'], 'tiles' => [
            'linear-gradient(135deg, #f472b6, #92446d)',
            'linear-gradient(135deg, #fb7185, #96434f)',
            'linear-gradient(135deg, #c084fc, #734f97)',
            'linear-gradient(135deg, #f9a8d4, #95647f)',
            'linear-gradient(135deg, #a78bfa, #645396)',
            'linear-gradient(135deg, #fbcfe8, #967c8b)',
        ]],
        'tiles_espresso' => ['label' => 'Espresso', 'colors' => ['#78350f', '#451a03', '#292524'], 'tiles' => [
            'linear-gradient(135deg, #78350f, #481f09)',
            'linear-gradient(135deg, #57534e, #34312e)',
            'linear-gradient(135deg, #451a03, #290f01)',
            'linear-gradient(135deg, #3f3f46, #25252a)',
            'linear-gradient(135deg, #292524, #181615)',
            'linear-gradient(135deg, #1c1917, #100f0d)',
        ]],
        'tiles_sky_line' => ['label' => 'Sky Line', 'colors' => ['#0284c7', '#38bdf8', '#7dd3fc'], 'tiles' => [
            'linear-gradient(135deg, #0284c7, #014f77)',
            'linear-gradient(135deg, #0ea5e9, #08638b)',
            'linear-gradient(135deg, #38bdf8, #217194)',
            'linear-gradient(135deg, #075985, #04354f)',
            'linear-gradient(135deg, #7dd3fc, #4b7e97)',
            'linear-gradient(135deg, #0c4a6e, #072c42)',
        ]],
        'tiles_marigold' => ['label' => 'Marigold', 'colors' => ['#d97706', '#fbbf24', '#fcd34d'], 'tiles' => [
            'linear-gradient(135deg, #d97706, #824703)',
            'linear-gradient(135deg, #f59e0b, #935e06)',
            'linear-gradient(135deg, #fbbf24, #967215)',
            'linear-gradient(135deg, #b45309, #6c3105)',
            'linear-gradient(135deg, #fcd34d, #977e2e)',
            'linear-gradient(135deg, #92400e, #572608)',
        ]],
        'tiles_iris' => ['label' => 'Iris', 'colors' => ['#6366f1', '#a5b4fc', '#c7d2fe'], 'tiles' => [
            'linear-gradient(135deg, #6366f1, #3b3d90)',
            'linear-gradient(135deg, #818cf8, #4d5494)',
            'linear-gradient(135deg, #a5b4fc, #636c97)',
            'linear-gradient(135deg, #4f46e5, #2f2a89)',
            'linear-gradient(135deg, #c7d2fe, #777e98)',
            'linear-gradient(135deg, #3730a3, #211c61)',
        ]],
        'tiles_jade' => ['label' => 'Jade', 'colors' => ['#10b981', '#6ee7b7', '#a7f3d0'], 'tiles' => [
            'linear-gradient(135deg, #10b981, #096f4d)',
            'linear-gradient(135deg, #34d399, #1f7e5b)',
            'linear-gradient(135deg, #6ee7b7, #428a6d)',
            'linear-gradient(135deg, #059669, #035a3f)',
            'linear-gradient(135deg, #a7f3d0, #64917c)',
            'linear-gradient(135deg, #047857, #024834)',
        ]],
        'tiles_crimson_ink' => ['label' => 'Crimson Ink', 'colors' => ['#991b1b', '#dc2626', '#ef4444'], 'tiles' => [
            'linear-gradient(135deg, #991b1b, #5b1010)',
            'linear-gradient(135deg, #b91c1c, #6f1010)',
            'linear-gradient(135deg, #dc2626, #841616)',
            'linear-gradient(135deg, #7f1d1d, #4c1111)',
            'linear-gradient(135deg, #ef4444, #8f2828)',
            'linear-gradient(135deg, #5c1010, #370909)',
        ]],
        'tiles_powder' => ['label' => 'Powder', 'colors' => ['#bfdbfe', '#60a5fa', '#3b82f6'], 'tiles' => [
            'linear-gradient(135deg, #bfdbfe, #728398)',
            'linear-gradient(135deg, #93c5fd, #587697)',
            'linear-gradient(135deg, #60a5fa, #396396)',
            'linear-gradient(135deg, #dbeafe, #838c98)',
            'linear-gradient(135deg, #3b82f6, #234e93)',
            'linear-gradient(135deg, #eff6ff, #8f9399)',
        ]],
        'tiles_ultraviolet' => ['label' => 'Ultraviolet', 'colors' => ['#4c1d95', '#5b21b6', '#6d28d9'], 'tiles' => [
            'linear-gradient(135deg, #4c1d95, #2d1159)',
            'linear-gradient(135deg, #3b0764, #23043c)',
            'linear-gradient(135deg, #5b21b6, #36136d)',
            'linear-gradient(135deg, #2e1065, #1b093c)',
            'linear-gradient(135deg, #6d28d9, #411882)',
            'linear-gradient(135deg, #7e22ce, #4b147b)',
        ]],    ];

    /**
     * Per-layout [colSpan, rowSpan] cycles applied to the tile sequence.
     * The grid itself is 4 columns wide (see the public renderer CSS).
     *
     * @var array<string, list<array{int, int}>>
     */
    private const LAYOUT_SPANS = [
        'uniform' => [[1, 1]],
        'metro'   => [[2, 2], [1, 1], [1, 1], [2, 1], [1, 2], [1, 1], [2, 1], [1, 1]],
        'brick'   => [[2, 1], [2, 1], [1, 1], [2, 1], [1, 1], [2, 1]],
    ];

    /** @return array<string, array{label: string, tiles: list<string>, colors: list<string>}> */
    public static function palettes(): array
    {
        return self::PALETTES;
    }

    public static function isValidPalette(string $key): bool
    {
        return isset(self::PALETTES[$key]);
    }

    public static function isValidLayout(string $key): bool
    {
        return isset(self::LAYOUTS[$key]);
    }

    /**
     * Fully-resolved tile list for the renderer: TILE_COUNT entries of
     * ['css' => gradient, 'col' => span, 'row' => span].
     *
     * @return list<array{css: string, col: int, row: int}>
     */
    public static function tiles(string $palette, string $layout): array
    {
        $p = self::PALETTES[$palette] ?? null;
        if (!$p) {
            return [];
        }
        $spans = self::LAYOUT_SPANS[$layout] ?? self::LAYOUT_SPANS['uniform'];
        $out = [];
        for ($i = 0; $i < self::TILE_COUNT; $i++) {
            [$col, $row] = $spans[$i % count($spans)];
            $out[] = [
                'css' => $p['tiles'][$i % count($p['tiles'])],
                'col' => $col,
                'row' => $row,
            ];
        }
        return $out;
    }

    /** @return list<string> representative colors for the mobile fallback */
    public static function colors(string $palette): array
    {
        return self::PALETTES[$palette]['colors'] ?? [];
    }
}
