<?php

/**
 * Regression guard: raw `$plan->features` reads in gating code paths.
 *
 * Background: plan gating must honor the `user.plan_limits.bypass` permission.
 * The blessed accessors are:
 *   - numeric caps  -> User::getPlanFeature($key, $default)
 *   - boolean gates -> an explicit `$user->hasPermission('user.plan_limits.bypass')`
 *     short-circuit before reading the plan
 * A raw `$user->plan->features['max_...']` read silently ignores the bypass,
 * which is exactly the class of bug a prior sweep removed. This guard keeps it
 * from coming back.
 *
 * Mechanics: statically greps `app/` for `plan->features` / `plan?->features`
 * reads and compares per-file occurrence counts against the ALLOWLIST below.
 *   - A match in a file NOT in the allowlist fails the build.
 *   - A file exceeding its allowlisted count fails the build.
 *   - A file dropping below its count prints an advisory to shrink the
 *     baseline (ratchet: baselines only shrink, never grow without a reason).
 *
 * Adding a legit exception: append to ALLOWLIST with a reason — the only
 * acceptable reasons are display-only reads (pricing/recommendation UI, admin
 * plan editors), plan-mutation code (admin writers, billing activation), or
 * infrastructure that itself implements the bypass contract.
 *
 * Usage:
 *   php scripts/check-plan-features-reads.php [dir ...]   # default: app
 *
 * Exit codes:
 *   0  no new raw plan-features reads
 *   1  a non-allowlisted read (or count growth) was found
 */

declare(strict_types=1);

$root = dirname(__DIR__);

/*
 * Per-file baseline: path (relative to the artifact root) => [max occurrence
 * count, reason]. Every entry must have a reason explaining why raw reads are
 * intentional there.
 */
const ALLOWLIST = [
    // Infrastructure that implements or feeds the bypass contract itself.
    'app/Modules/User/Models/User.php' => [3, 'getPlanFeature() home — the blessed accessor reads the raw features here'],
    'app/Modules/User/Middleware/CheckPlanLimit.php' => [2, 'central gate; checks user.plan_limits.bypass before reading features'],
    'app/Services/EffectivePlanFeatures.php' => [4, 'canonical merge of plan features + overrides; consumed by bypass-aware readers'],
    'app/Services/AI/AiPlanAccess.php' => [2, 'single AI-feature gate; bypass handled at its call boundary'],

    // Display-only readers (no gating decisions).
    'app/Services/PlanRecommender.php' => [1, 'display-only: usage gauges / recommended-plan UI'],
    'app/Modules/Common/Support/PremiumFeatures.php' => [2, 'display-only: pricing grid / feature_highlights catalogue'],
    'app/Modules/Common/Support/StatsRetentionPolicy.php' => [1, 'retention window lookup; intentional plan-literal read'],
    'app/Modules/User/Services/ReferralService.php' => [2, 'reads reward-plan features for referral rewards, not the acting user\'s gate'],

    // Admin plan editors / plan-mutation code (operate ON plans, not gate BY them).
    'app/Modules/Admin/Controllers/PlanController.php' => [6, 'admin plan editor: reads/writes the features payload'],
    'app/Modules/Admin/Support/PlanWriter.php' => [1, 'admin plan writer: merges submitted features'],
    'app/Modules/Api/Controllers/AdminPlanController.php' => [2, 'mobile admin plan editor payload'],
    'app/Actions/Billing/ActivateSubscription.php' => [4, 'billing activation copies plan features into the subscription snapshot'],

    // Pre-existing reads audited by the bypass sweep (kept as-is deliberately;
    // shrink these counts when the call sites move to the blessed accessors).
    'app/Modules/User/Controllers/ContactController.php' => [2, 'audited: google-sync boolean gate, bypass handled upstream'],
    'app/Modules/Api/Controllers/ContactController.php' => [1, 'audited: google-sync boolean gate, bypass handled upstream'],
    'app/Modules/User/Services/Contacts/GoogleContactsSyncService.php' => [2, 'audited: background sync reads owner plan features'],
    'app/Modules/Api/Controllers/LinkController.php' => [2, 'audited: feature reads paired with explicit bypass checks'],
    'app/Modules/User/Models/Domain.php' => [1, 'audited: custom-domains flag for display context'],
];

const PATTERN = '/\bplan\s*(?:\?->|->)\s*features\b/';

$dirs = array_slice($argv, 1);
if ($dirs === []) {
    $dirs = ['app'];
}

$counts = [];

foreach ($dirs as $dir) {
    $abs = $root.'/'.$dir;
    if (!is_dir($abs)) {
        fwrite(STDERR, "check-plan-features-reads: directory not found: {$dir}\n");
        exit(1);
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $contents = file_get_contents($file->getPathname());
        if ($contents === false) {
            continue;
        }
        $n = preg_match_all(PATTERN, $contents);
        if ($n > 0) {
            $rel = ltrim(str_replace($root, '', $file->getPathname()), '/');
            $counts[$rel] = ($counts[$rel] ?? 0) + $n;
        }
    }
}

$failures = [];
$advisories = [];

foreach ($counts as $rel => $n) {
    if (!array_key_exists($rel, ALLOWLIST)) {
        $failures[] = "NEW raw plan-features read in {$rel} ({$n} occurrence(s)) — not in the allowlist.";
        continue;
    }
    [$max] = ALLOWLIST[$rel];
    if ($n > $max) {
        $failures[] = "{$rel}: {$n} raw plan-features read(s), baseline allows {$max} — a new read was added.";
    } elseif ($n < $max) {
        $advisories[] = "{$rel}: {$n} read(s) but baseline allows {$max} — shrink the ALLOWLIST count.";
    }
}

foreach (ALLOWLIST as $rel => [$max]) {
    if (!isset($counts[$rel]) && is_file($root.'/'.$rel)) {
        $advisories[] = "{$rel}: 0 reads but still allowlisted ({$max}) — remove the entry.";
    }
}

foreach ($advisories as $line) {
    fwrite(STDOUT, "advisory: {$line}\n");
}

if ($failures !== []) {
    fwrite(STDERR, "\ncheck-plan-features-reads FAILED:\n\n");
    foreach ($failures as $line) {
        fwrite(STDERR, "  - {$line}\n");
    }
    fwrite(STDERR, <<<'EOT'

Raw `$plan->features` reads bypass the `user.plan_limits.bypass` contract.
Fix by using the blessed accessors instead:

  - numeric caps:  $user->getPlanFeature('max_...', $default)
  - boolean gates: check $user->hasPermission('user.plan_limits.bypass')
                   before any plan-feature read

If the read is genuinely display-only, plan-mutation code, or bypass-aware
infrastructure, add/adjust an ALLOWLIST entry in
scripts/check-plan-features-reads.php WITH a reason — never weaken the matcher.

EOT);
    exit(1);
}

$total = array_sum($counts);
fwrite(STDOUT, "check-plan-features-reads OK ({$total} allowlisted read(s) across ".count($counts)." file(s)).\n");
exit(0);
