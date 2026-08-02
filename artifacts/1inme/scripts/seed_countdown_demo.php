<?php

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Support\BlockVariantCatalog;
use App\Modules\User\Support\BlockStyleSanitizer;
use App\Modules\User\Controllers\BiolinkBlockController;

// --- 1. Pick user + create/reuse the demo link idempotently ------------------
$user = User::where('email', 'demo@1inme.com')->first();
if (!$user) {
    // Fall back to any user that already owns a biolink link.
    $anyLink = Link::where('type', 'biolink')->first();
    $user = $anyLink ? User::find($anyLink->user_id) : User::first();
}
if (!$user) { fwrite(STDERR, "No user found\n"); exit(1); }

// project_id (workspace) — reuse the user's most recent biolink's project.
$sampleLink = Link::where('user_id', $user->id)->where('type', 'biolink')->latest('id')->first();
$projectId = $sampleLink?->project_id;

$alias = 'countdown-styles-demo';

$link = Link::where('alias', $alias)->first();
if ($link) {
    // Reuse: wipe its blocks so the seed is deterministic.
    BiolinkBlock::where('link_id', $link->id)->delete();
    echo "Reusing existing link id={$link->id} (blocks wiped)\n";
} else {
    $link = Link::create([
        'user_id'    => $user->id,
        'project_id' => $projectId,
        'type'       => Link::TYPE_BIOLINK,
        'alias'      => $alias,
        'title'      => 'Countdown Block Styles Demo',
        'is_active'  => true,
        'visibility' => 'public',
    ]);
    echo "Created link id={$link->id}\n";
}

// --- helper: build a countdown block exactly like the editor would -----------
$controller = app(BiolinkBlockController::class);

$targetFuture  = date('Y-m-d H:i:s', strtotime('+30 days'));
$targetExpired = date('Y-m-d H:i:s', strtotime('-2 days'));

$makeCountdown = function (array $overrides, ?string $variantKey, int $sortOrder) use ($link, $controller, $targetFuture) {
    // Base countdown settings (mirrors what the editor form posts).
    $settings = array_merge([
        'title'           => 'Countdown',
        'subtitle'        => 'Something big is coming',
        'target_date'     => $targetFuture,
        'show_days'       => true,
        'show_hours'      => true,
        'show_minutes'    => true,
        'show_seconds'    => true,
        'label_style'     => 'full',
        'expired_message' => "Time's up!",
        'expired_action'  => 'message',
        'button_text'     => 'Get tickets',
        'button_url'      => 'https://example.com',
    ], $overrides);

    // Seed baseline _style like store() does.
    $baseStyle = BlockStyleSanitizer::sanitize(BiolinkBlock::STYLE_DEFAULTS);

    if ($variantKey !== null) {
        // Apply the variant EXACTLY the way applyVariant() does:
        // STYLE_DEFAULTS <- variant['style'] <- {_variant,_variant_version}
        $variant = BlockVariantCatalog::find('countdown', $variantKey);
        if (!$variant) {
            fwrite(STDERR, "Unknown variant: {$variantKey}\n");
            exit(1);
        }
        $settings['_style'] = BlockStyleSanitizer::sanitize(array_merge(
            BiolinkBlock::STYLE_DEFAULTS,
            $variant['style'],
            [
                '_variant'         => $variantKey,
                '_variant_version' => BlockVariantCatalog::version(),
            ]
        ));
    } else {
        $settings['_style'] = $baseStyle;
    }

    // Run through the same content sanitizer the controller uses.
    $settings = $controller->sanitizeSettings('countdown', $settings);

    return $link->biolinkBlocks()->create([
        'type'       => 'countdown',
        'settings'   => $settings,
        'sort_order' => $sortOrder,
        'is_active'  => true,
        'parent_id'  => null,
    ]);
};

// --- 2. Ten variant blocks ---------------------------------------------------
$variants = [
    'flip_clock'     => 'Flip Clock',
    'pixel_clock'    => 'Pixel Clock',
    'minimal_inline' => 'Minimal Inline',
    'glass_cards'    => 'Glass Cards',
    'neon_glow'      => 'Neon Glow',
    'gradient_pop_cd'=> 'Gradient Pop',
    'soft_pastel_cd' => 'Soft Pastel',
    'bold_blocks'    => 'Bold Blocks',
    'outline_ring'   => 'Outline Ring',
    'elegant_serif'  => 'Elegant Serif',
];

$sort = 0;
$titles = [];
foreach ($variants as $key => $name) {
    $b = $makeCountdown([
        'title'    => $name,
        'subtitle' => "{$name} countdown style",
    ], $key, $sort++);
    $titles[] = $name;
    echo "  block id={$b->id} variant={$key} title=\"{$name}\"\n";
}

// --- 3. Expired-state block --------------------------------------------------
$expiredTitle = 'Expired Message Demo';
$eb = $makeCountdown([
    'title'           => $expiredTitle,
    'subtitle'        => 'This one is already past its target date',
    'target_date'     => $targetExpired,
    'expired_action'  => 'message',
    'expired_message' => 'This event has ended',
], 'minimal_inline', $sort++);
$titles[] = $expiredTitle;
echo "  block id={$eb->id} EXPIRED title=\"{$expiredTitle}\"\n";

echo "TOTAL blocks: " . BiolinkBlock::where('link_id', $link->id)->count() . "\n";
echo "ALIAS: {$alias}\n";
echo "TITLES: " . implode(' | ', $titles) . "\n";
