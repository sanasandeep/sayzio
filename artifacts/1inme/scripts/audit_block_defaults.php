<?php
/**
 * Coverage matrix audit for Task #1197 block defaults.
 *
 * Boots Laravel and exercises BiolinkBlockController::getDefaultSettings()
 * (via reflection) plus BlockDefaults::styleForType() for every canonical
 * type in BiolinkBlock::TYPES. Asserts every type produces:
 *   - non-empty default settings (no `default => []` fall-through), or
 *     is on the explicit "intentionally empty" allowlist (system blocks
 *     that are id-references or locked verified content);
 *   - `_placeholder => true` flag for non-system blocks;
 *   - style override containing only structural tokens (no hardcoded
 *     bg_color / border_color / text_color — those must come from the
 *     active theme via STYLE_DEFAULTS).
 *
 * Mirrors both the web `store()` create path and the mobile picker
 * `store()` create path — they hit the same controller method, so a
 * single audit covers both surfaces.
 *
 * Run from the artifact root:
 *   php scripts/audit_block_defaults.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\User\Controllers\BiolinkBlockController;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Support\BlockDefaults;
use App\Modules\User\Support\BlockTypeRegistry;

// System / id-reference / locked-content blocks where empty defaults
// are correct (they store an id pointing to another resource, or are
// locked by VerificationController).
$emptyAllowed = [
    'social_proof', 'ai_companion', 'form',
    'verified_heading', 'verified_avatar',
];

// Style fields that must NOT appear in styleForType() — they are
// theme-resolved at render time via STYLE_DEFAULTS.
$themeColorFields = ['bg_color', 'border_color', 'text_color', 'bg_image'];

$controller = new BiolinkBlockController();
$ref = new ReflectionClass($controller);
$getDefaults = $ref->getMethod('getDefaultSettings');
$getDefaults->setAccessible(true);

$total      = 0;
$failures   = [];
$noFlag     = [];
$noStyle    = [];
$themeBleed = [];

foreach (array_keys(BiolinkBlock::TYPES) as $type) {
    $total++;
    $canonical = BlockTypeRegistry::canonical($type);
    $defaults  = $getDefaults->invoke($controller, $type);
    $style     = BlockDefaults::styleForType($type);

    // 1) Non-empty defaults (or in the system allowlist)
    if (empty($defaults) && !in_array($type, $emptyAllowed, true) && !in_array($canonical, $emptyAllowed, true)) {
        $failures[] = $type;
    }

    // 2) `_placeholder` flag for non-system content blocks
    if (!empty($defaults) && empty($defaults['_placeholder']) && !in_array($type, $emptyAllowed, true)
        && !in_array($type, ['divider', 'spacer'], true)) {
        $noFlag[] = $type;
    }

    // 3) styleForType returns at least one structural token
    if (empty($style) && !in_array($type, ['divider', 'spacer'], true)) {
        $noStyle[] = $type;
    }

    // 4) styleForType must not bake in theme colours
    foreach ($themeColorFields as $f) {
        if (array_key_exists($f, $style)) {
            $themeBleed[] = "$type.$f";
        }
    }
}

echo "── Coverage matrix for Task #1197 ──\n";
echo "Canonical types audited: $total\n";
echo "Create paths covered: web BiolinkBlockController::store() + mobile picker (same endpoint)\n\n";

echo "1. Non-empty defaults:\n";
echo empty($failures) ? "   PASS — every type returns a non-empty payload (or is on the system allowlist).\n"
                      : "   FAIL — empty defaults for: " . implode(', ', $failures) . "\n";

echo "2. _placeholder flag on content blocks:\n";
echo empty($noFlag) ? "   PASS — every seeded content block carries `_placeholder => true`.\n"
                    : "   FAIL — missing flag on: " . implode(', ', $noFlag) . "\n";

echo "3. Structural style overrides:\n";
echo empty($noStyle) ? "   PASS — every type has at least one structural style token.\n"
                     : "   FAIL — empty styleForType for: " . implode(', ', $noStyle) . "\n";

echo "4. No theme-color bleed in styleForType:\n";
echo empty($themeBleed) ? "   PASS — bg_color / border_color / text_color / bg_image left to theme.\n"
                        : "   FAIL — hardcoded theme colours: " . implode(', ', $themeBleed) . "\n";

$ok = empty($failures) && empty($noFlag) && empty($noStyle) && empty($themeBleed);
echo "\n" . ($ok ? "OVERALL: PASS\n" : "OVERALL: FAIL\n");
exit($ok ? 0 : 1);
