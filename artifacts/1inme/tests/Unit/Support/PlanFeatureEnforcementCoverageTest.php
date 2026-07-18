<?php

namespace Tests\Unit\Support;

use App\Modules\Common\Support\PlanFormCatalogue;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Coverage guard: every plan-gated feature key that is actually *enforced*
 * somewhere in application code must be registered in one of the admin
 * "Create / Edit Plan" form's three per-plan sections —
 * {@see PlanFormCatalogue::quantityLimits()}, {@see PlanFormCatalogue::featureFlags()}
 * or {@see PlanFormCatalogue::aiSuite()} — OR be explicitly allow-listed below
 * with a reason.
 *
 * Why this exists: a feature key that some controller/middleware/service
 * reads off `$plan->features[...]` (directly or via `User::getPlanFeature()`
 * / `AiPlanAccess`) but that never made it into the plan form is a knob no
 * admin can actually configure per plan — every plan silently gets whatever
 * default the call site happens to pass. This test statically scans `app/`
 * and `routes/` for every call-site pattern known to read a plan feature key
 * and diffs the result against the three catalogue methods above, so a new
 * enforcement call site with a typo'd or forgotten key is caught immediately
 * instead of silently shipping ungoverned.
 *
 * This is a pure static-analysis unit test: it never boots the app, touches
 * the database, or evaluates a route/request. It only greps PHP source and
 * calls the catalogue's own (DB-free) static methods, so it runs even when
 * the pgsql Feature suite is skipped — same style as the sibling
 * {@see PlanSeedFeatureCoverageTest} / {@see PremiumFeaturesCatalogueDriftTest}
 * guards in this directory.
 *
 * This test is audit/report-only: it never edits plan seed data, never
 * touches {@see \App\Modules\Common\Support\PremiumFeatures} descriptions,
 * and never changes any enforcement behaviour. Its only job is to fail
 * loudly when a *newly discovered* enforced key is neither registered nor
 * allow-listed, forcing a conscious decision (register it, or explain why
 * it's a legitimate exception) rather than a silent gap.
 */
class PlanFeatureEnforcementCoverageTest extends TestCase
{
    /** Directories scanned for plan-feature enforcement call sites, relative to the app root. */
    private const SCAN_DIRS = ['app', 'routes'];

    /**
     * Quantity-feature alias => real plan feature key, mirroring
     * {@see \App\Services\AI\AiPlanAccess::QUANTITY_KEYS}. Call sites pass the
     * short alias (e.g. `AiPlanAccess::underQuantityCap($user, 'minds', ...)`)
     * but the plan actually stores/reads the real key (`max_minds`), so the
     * real key — not the alias — is what must appear in the catalogue.
     *
     * Duplicated here (rather than requiring the AiPlanAccess class) so this
     * test stays a pure string/array comparison with zero risk of triggering
     * app boot via an unexpected class dependency chain.
     */
    private const AI_QUANTITY_ALIAS_TO_KEY = [
        'minds' => 'max_minds',
        'personas' => 'max_personas',
        'companions' => 'max_companions',
        'brand_kits' => 'max_brand_kits',
        'marketing_strategies' => 'max_marketing_strategies',
    ];

    /**
     * AI coin-multiplier provider alias => real plan feature key, mirroring
     * {@see \App\Services\AI\AiPlanAccess::COIN_MULTIPLIER_KEYS}. A provider
     * with no entry here (e.g. `replicate`) never reaches a plan feature key
     * at all, so it's simply not translated into a candidate key.
     */
    private const AI_COIN_MULTIPLIER_ALIAS_TO_KEY = [
        'openai' => 'ai_openai_coin_multiplier',
        'elevenlabs' => 'ai_elevenlabs_coin_multiplier',
    ];

    /**
     * The `AiStaff` domains that {@see \App\Modules\User\Models\AiStaff::featureKey()}
     * turns into a plan feature key via `'ai_staff_' . $domain`. Call sites
     * build this key dynamically (`'ai_staff_' . $data['domain']` or
     * `$staff->featureKey()`), so no literal string scan can discover it —
     * it's reconstructed here from the same domain enum instead. Duplicated
     * (rather than requiring `AiStaff`) for the same zero-app-boot-risk
     * reason as {@see self::AI_QUANTITY_ALIAS_TO_KEY}.
     */
    private const AI_STAFF_DOMAINS = ['billing', 'contacts', 'inbox', 'general'];

    /**
     * Feature keys that a call site enforces but that are intentionally NOT
     * expected to appear in `quantityLimits()`/`featureFlags()`/`aiSuite()`.
     * Every entry must fall into exactly one of these buckets, documented
     * inline:
     *
     *  1. Documented via one of PlanFormCatalogue's OTHER methods
     *     (`modules()`, `moduleKeys()`, `aiCoinMultipliers()`,
     *     `aliasLinkTypes()`) — those are real catalogue registries too,
     *     just outside this test's three-method scope.
     *  2. A non-scalar / composite storage key (JSON blob, dotted path) that
     *     was never meant to be a single catalogue row.
     *  3. A known bug (wrong literal at a call site) — flagged rather than
     *     silently tolerated, but fixing the call site is out of this
     *     audit's scope (report only).
     *  4. A known real gap: genuinely plan-gated in code today but not yet
     *     wired into the admin plan form. Each of these silently falls back
     *     to whatever default its call site passes when a plan's seed data
     *     doesn't set it — tracked here as an explicit TODO instead of a
     *     silent miss. Removing one of these entries once it's registered
     *     is expected and encouraged (see the "stale allow-list" assertion
     *     below).
     *
     * @var array<string,string> key => human-readable reason
     */
    private const ALLOWED_NON_CATALOGUE_KEYS = [
        // --- (1) Documented via PlanFormCatalogue::modules() ---
        'module_calendar' => "Module toggle documented in PlanFormCatalogue::modules()['module_calendar']; modules() is a separate registry from quantityLimits()/featureFlags()/aiSuite().",

        // --- (1) Documented via PlanFormCatalogue::moduleKeys() ---
        'block_types_allowed' => "Documented in moduleKeys()['module_biolinks']; the block-type allow-list has its own control, not a quantity/flag/AI-suite row.",
        'teams' => "Documented in moduleKeys()['module_teams']; PlanWriter comment: \"Teams toggle (lives in the Team section, not in featureFlags)\".",
        'max_workspaces' => "Documented in moduleKeys()['module_teams'].",
        'max_seats_per_workspace' => "Documented in moduleKeys()['module_teams'].",
        'integration_accounts_max' => "Documented in moduleKeys()['module_integrations'].",
        'max_aliases_per_link_by_type' => "Documented in moduleKeys()['module_short_links']; the per-link-type override map for max_aliases_per_link is edited via PlanFormCatalogue::aliasLinkTypes(), not a scalar catalogue row.",

        // --- (1) Documented via PlanFormCatalogue::aiCoinMultipliers() ---
        'ai_openai_coin_multiplier' => "Documented in aiCoinMultipliers(); deliberately kept out of moduleKeys()/aiSuite() per that method's own doc comment (applies platform-wide, not just the AI suite module).",
        'ai_elevenlabs_coin_multiplier' => 'Documented in aiCoinMultipliers(); see ai_openai_coin_multiplier.',

        // --- (1) Documented via PlanFormCatalogue::includedCoinGrants() ---
        'included_coins_monthly' => "Documented in includedCoinGrants(); a dedicated grant-amount section, not a quantity-limit or feature-flag row.",
        'included_coins_yearly'  => 'Documented in includedCoinGrants(); see included_coins_monthly.',

        // --- (2) Non-scalar / composite storage keys ---
        'upload_limits' => 'A JSON blob of per-context upload caps, not a single scalar catalogue entry.',
        'resume.templates' => 'Dotted composite key (a per-template allow-list), not a single scalar catalogue entry.',

        // --- (3) Known bug, flagged not fixed (out of this audit's scope) ---
        'n' => "Bug: MarketingSuggestionApplier passes the literal 'n' instead of 'max_links' to getPlanFeature(); flagged here as a known issue rather than silently ignored. Fixing the call site is out of this audit's scope (report only).",

        // --- (4) Known real gaps: enforced today, not yet in the plan form ---
        'max_smart_rules' => 'TODO: register in quantityLimits(). Enforced via the link_smart_rules feature flag + CheckPlanLimit(link_smart_rules); not yet in the admin plan form.',
        'ab_tests' => 'TODO: register in featureFlags(). Enforced in LinkController::planAllowsAbTests(); not yet in the admin plan form.',
        'ab_max_variants' => 'TODO: register in quantityLimits(). Enforced in LinkController::planMaxAbVariants(); not yet in the admin plan form.',
        'ask_coach' => 'TODO: register in aiSuite(). Enforced via AiPlanAccess::featureAllowed(); not yet in the admin plan form (see AiPlanAccess::legacyAvailabilityFallback()).',
        'audience_type_estimation' => 'TODO: register in aiSuite(). Enforced via AudienceTypeEstimationService::FEATURE_KEY + AiPlanAccess::featureAllowed(); not yet in the admin plan form.',
        'card_scan' => 'TODO: register in aiSuite(). Enforced via AiPlanAccess::featureAllowed(); not yet in the admin plan form.',
        'ai_resume_tools' => 'TODO: register in aiSuite(). Enforced via GatesResumeAiTools + AiPlanAccess::featureAllowed(); not yet in the admin plan form.',
        'competitor_teardown' => 'TODO: register in aiSuite(). Enforced via AiPlanAccess::featureAllowed(); not yet in the admin plan form.',
        'dashboard_designer' => 'TODO: register in aiSuite(). Enforced via DashboardAiDesignerService::FEATURE + AiPlanAccess::featureAllowed(); not yet in the admin plan form.',
        'ai_staff_billing' => 'TODO: register in aiSuite(). Enforced via AiStaff::featureKey() ("ai_staff_" . domain) + AiPlanAccess::featureAllowed(); not yet in the admin plan form.',
        'ai_staff_contacts' => 'TODO: register in aiSuite(). See ai_staff_billing.',
        'ai_staff_inbox' => 'TODO: register in aiSuite(). See ai_staff_billing.',
        'ai_staff_general' => 'TODO: register in aiSuite(). See ai_staff_billing.',
        'max_minds' => "TODO: register in quantityLimits(). Enforced via AiPlanAccess::QUANTITY_KEYS['minds'] + CheckPlanLimit(ai_minds); not yet in the admin plan form.",
        'max_personas' => "TODO: register in quantityLimits(). Enforced via AiPlanAccess::QUANTITY_KEYS['personas'] + CheckPlanLimit(ai_personas); not yet in the admin plan form.",
        'max_companions' => "TODO: register in quantityLimits(). Enforced via AiPlanAccess::QUANTITY_KEYS['companions'] + CheckPlanLimit(ai_companions); not yet in the admin plan form.",
        'max_marketing_strategies' => "TODO: register in quantityLimits(). Enforced via AiPlanAccess::QUANTITY_KEYS['marketing_strategies']; not yet in the admin plan form.",
    ];

    /** @return string[] every key registered across the three in-scope catalogue methods. */
    private function registeredKeys(): array
    {
        $keys = [];
        foreach (PlanFormCatalogue::quantityLimits() as $row) {
            $keys[] = $row['key'];
        }
        foreach (PlanFormCatalogue::featureFlags() as $row) {
            $keys[] = $row['key'];
        }
        foreach (PlanFormCatalogue::aiSuite() as $row) {
            $keys[] = $row['key'];
        }
        return array_values(array_unique($keys));
    }

    /**
     * Statically discover every plan feature key actually read by
     * application code, keyed by feature key => list of "file:pattern"
     * provenance strings (for a readable failure message).
     *
     * @return array<string,string[]>
     */
    private function discoverEnforcedKeys(): array
    {
        $found = [];
        $record = function (string $key, string $where) use (&$found): void {
            $found[$key][] = $where;
        };

        $basePath = dirname(__DIR__, 3);
        $phpFiles = $this->collectPhpFiles($basePath);

        // Pass 1: resolve `ClassName::FEATURE` / `self::FEATURE` references.
        // Several services (WhatsAppAgentService, DashboardAiDesignerService,
        // MarketingStrategistService, the Inbox*Autopilot/Triage classes,
        // ...) declare `public const FEATURE = 'key';` and call sites pass
        // that constant (not a literal string) into AiPlanAccess. We collect
        // "short class name => FEATURE value" plus "file path => FEATURE
        // value" (for same-file `self::FEATURE`) up front.
        $classFeatureConst = [];
        $fileFeatureConst = [];
        foreach ($phpFiles as $path => $src) {
            if (!preg_match("/const\\s+FEATURE\\s*=\\s*['\"]([a-zA-Z0-9_.]+)['\"]/", $src, $cm)) {
                continue;
            }
            $fileFeatureConst[$path] = $cm[1];
            if (preg_match('/^\s*class\s+(\w+)/m', $src, $clm)) {
                $classFeatureConst[$clm[1]] = $cm[1];
            }
        }

        // Pass 2: run every discovery pattern against each file.
        foreach ($phpFiles as $path => $src) {
            $relative = ltrim(substr($path, strlen($basePath)), '/');

            // Pattern 1: User::getPlanFeature('key', ...) / planFeatureEnabled('key') /
            // planUnderLimit('key', ...) / planThatUnlocks('key', ...).
            if (preg_match_all(
                "/->(getPlanFeature|planFeatureEnabled|planUnderLimit|planThatUnlocks)\\(\\s*['\"]([a-zA-Z0-9_.]+)['\"]/",
                $src,
                $m
            )) {
                foreach ($m[2] as $i => $key) {
                    $record($key, "{$relative}::{$m[1][$i]}()");
                }
            }

            // Pattern 2: direct array access on a plan's features map —
            // $features['key'], $plan->features['key'], and nullable-chain
            // forms like $user->plan?->features['key'] — this is how
            // CheckPlanLimit and a handful of controllers/models read the
            // plan row without going through getPlanFeature(). Matched on
            // the bare `features[...]` token regardless of what precedes it
            // (variable, `->`, or `?->`) so every access shape is caught.
            if (preg_match_all(
                "/\\bfeatures\\s*\\[\\s*['\"]([a-zA-Z0-9_.]+)['\"]\\s*\\]/",
                $src,
                $m
            )) {
                foreach ($m[1] as $key) {
                    $record($key, "{$relative}::features[]");
                }
            }

            // Pattern 3: AiPlanAccess::featureAllowed($user, 'key') /
            // ::featureUpgradePlan($user, 'key') — availability features,
            // literal-string form.
            if (preg_match_all(
                "/AiPlanAccess::(featureAllowed|featureUpgradePlan)\\(\\s*\\\$\\w+\\s*,\\s*['\"]([a-zA-Z0-9_.]+)['\"]/",
                $src,
                $m
            )) {
                foreach ($m[2] as $i => $key) {
                    $record($key, "{$relative}::AiPlanAccess::{$m[1][$i]}()");
                }
            }

            // Pattern 3b: AiPlanAccess::featureAllowed($user, self::FEATURE) /
            // ::featureAllowed($user, ClassName::FEATURE) — availability
            // features, constant form. Resolved via the FEATURE-constant maps
            // built in pass 1.
            if (preg_match_all(
                "/AiPlanAccess::(featureAllowed|featureUpgradePlan)\\(\\s*\\\$\\w+\\s*,\\s*(self|\\w+)::FEATURE\\s*[,)]/",
                $src,
                $m
            )) {
                foreach ($m[2] as $i => $ref) {
                    $key = $ref === 'self'
                        ? ($fileFeatureConst[$path] ?? null)
                        : ($classFeatureConst[$ref] ?? null);
                    if ($key !== null) {
                        $record($key, "{$relative}::AiPlanAccess::{$m[1][$i]}({$ref}::FEATURE)");
                    }
                }
            }

            // Pattern 4: AiPlanAccess::(quantityCap|underQuantityCap|
            // quantityUpgradePlan|quantityLimitMessage)($user, 'alias', ...)
            // — quantity aliases, translated to the real key below.
            if (preg_match_all(
                "/AiPlanAccess::(quantityCap|underQuantityCap|quantityUpgradePlan|quantityLimitMessage)\\(\\s*\\\$\\w+\\s*,\\s*['\"](\\w+)['\"]/",
                $src,
                $m
            )) {
                foreach ($m[2] as $i => $alias) {
                    $key = self::AI_QUANTITY_ALIAS_TO_KEY[$alias] ?? null;
                    if ($key !== null) {
                        $record($key, "{$relative}::AiPlanAccess::{$m[1][$i]}('{$alias}')");
                    }
                }
            }

            // Pattern 5: AiPlanAccess::coinMultiplier($user, 'provider')
            // — coin-multiplier provider aliases, translated below.
            if (preg_match_all(
                "/AiPlanAccess::coinMultiplier\\(\\s*\\\$\\w+\\s*,\\s*['\"](\\w+)['\"]/",
                $src,
                $m
            )) {
                foreach ($m[1] as $alias) {
                    $key = self::AI_COIN_MULTIPLIER_ALIAS_TO_KEY[$alias] ?? null;
                    if ($key !== null) {
                        $record($key, "{$relative}::AiPlanAccess::coinMultiplier('{$alias}')");
                    }
                }
            }

            // Pattern 6: AiStaff's dynamically-built 'ai_staff_' . $domain
            // feature key. Neither the literal concatenation
            // ('ai_staff_' . $data['domain']) nor the $staff->featureKey()
            // call site carry a scannable literal, so we detect the
            // concatenation idiom and reconstruct every possible key from
            // the known domain enum instead of trying to resolve $domain
            // statically.
            if (str_contains($src, "'ai_staff_'")) {
                foreach (self::AI_STAFF_DOMAINS as $domain) {
                    $record('ai_staff_' . $domain, "{$relative}::'ai_staff_' . \$domain");
                }
            }
        }

        return $found;
    }

    /**
     * @return array<string,string> absolute file path => file contents, for
     *     every *.php file under {@see self::SCAN_DIRS}.
     */
    private function collectPhpFiles(string $basePath): array
    {
        $files = [];
        foreach (self::SCAN_DIRS as $dir) {
            $root = $basePath . '/' . $dir;
            if (!is_dir($root)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $path = $file->getPathname();
                $src = file_get_contents($path);
                if ($src !== false) {
                    $files[$path] = $src;
                }
            }
        }
        return $files;
    }

    public function test_the_scan_finds_a_meaningful_number_of_call_sites(): void
    {
        $enforced = $this->discoverEnforcedKeys();

        // Sanity: if the scan finds almost nothing, the patterns above have
        // drifted from the codebase (renamed methods, moved directories,
        // etc.) and every other assertion in this file would vacuously pass.
        $this->assertGreaterThan(
            40,
            count($enforced),
            'Static scan found suspiciously few enforced plan-feature keys; '
            . 'the discovery patterns in this test likely need updating.'
        );

        $this->assertArrayHasKey('max_links', $enforced, 'Expected to find the well-known max_links key.');
    }

    public function test_every_enforced_key_is_registered_or_allow_listed(): void
    {
        $enforced = $this->discoverEnforcedKeys();
        $registered = array_flip($this->registeredKeys());

        $undocumented = [];
        foreach ($enforced as $key => $sources) {
            if (isset($registered[$key]) || isset(self::ALLOWED_NON_CATALOGUE_KEYS[$key])) {
                continue;
            }
            $undocumented[] = "{$key} (found at: " . implode(', ', array_slice($sources, 0, 3)) . ')';
        }

        sort($undocumented);

        $this->assertSame(
            [],
            $undocumented,
            "These plan-feature keys are enforced in code but are neither registered in \n"
            . "PlanFormCatalogue::quantityLimits()/featureFlags()/aiSuite() nor allow-listed in \n"
            . "PlanFeatureEnforcementCoverageTest::ALLOWED_NON_CATALOGUE_KEYS, so no admin can \n"
            . "configure them per plan:\n - " . implode("\n - ", $undocumented)
        );
    }

    public function test_allow_list_has_no_stale_entries(): void
    {
        $enforced = $this->discoverEnforcedKeys();
        $registered = array_flip($this->registeredKeys());

        $stale = [];
        foreach (array_keys(self::ALLOWED_NON_CATALOGUE_KEYS) as $key) {
            // A "known gap" entry becomes stale (and should be deleted) once
            // the key is registered in the catalogue. A "documented
            // elsewhere" / "non-scalar" / "known bug" entry becomes stale
            // once the call site it describes disappears from the codebase.
            if (isset($registered[$key]) || !isset($enforced[$key])) {
                $stale[] = $key;
            }
        }

        sort($stale);

        $this->assertSame(
            [],
            $stale,
            "These ALLOWED_NON_CATALOGUE_KEYS entries no longer apply — the key is now \n"
            . "registered in the catalogue, or the call site that used to enforce it is gone. \n"
            . "Remove the stale entry (or entries) to keep the allow-list honest:\n - "
            . implode("\n - ", $stale)
        );
    }
}
