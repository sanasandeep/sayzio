<?php

/**
 * Regression guard: hardcoded database row ids in seed/maintenance scripts.
 *
 * Background: scripts/seed-demo-folders.php once shipped with a hardcoded
 * numeric domain id ('domain_id' => <production row id>) — a production-only
 * row id that differs per environment, silently binding seeded links to the
 * wrong (or a nonexistent) domain everywhere else. It is fixed to look the
 * primary brand domain up dynamically, but nothing stopped the next tinker or
 * seeder script from repeating the mistake with domains, plans, users, or any
 * other seeded row. Row ids are environment-specific; scripts must resolve
 * them by a stable natural key (domain name, plan slug, email, alias, ...) at
 * runtime instead.
 *
 * Mechanics: statically scans PHP files under scripts/, database/seeders/,
 * database/migrations/ (one-off data migrations can hardcode row ids just as
 * easily as seeders) and app/Console/Commands/ (maintenance/backfill commands
 * like reconcile or reseed jobs write seeded rows too; comments blanked via
 * the tokenizer so docblocks can discuss the pattern) and app/Jobs/ (queued
 * background jobs write rows outside any request context and can hardcode an
 * environment-specific row id just as easily)
 * and fails on any *_id key or property receiving a bare integer literal:
 *
 *   1. array keys:            'domain_id' => 2         (also "..." keys)
 *   2. property assignments:  $link->domain_id = 2
 *   3. query comparisons:     ->where('domain_id', 2)  / whereIn('plan_id', [1, 2])
 *
 * What is SAFE (never flagged):
 *   - ids resolved at runtime: 'domain_id' => $primaryDomainId, ->value('id'),
 *     firstWhere('slug', ...)->id, and so on — only bare integer literals match.
 *   - non-id numeric config keys ('grid_id' is flagged only if it truly ends
 *     in `_id`; use the ALLOWLIST below when a key is not a DB row reference).
 *   - anything in comments.
 *
 * Adding a legit exception (e.g. a synthetic non-database `_id` key): append
 * to ALLOWLIST below with a reason — never weaken the matcher.
 *
 * Usage:
 *   php scripts/check-hardcoded-row-ids.php [dir ...]   # default: scripts database/seeders database/migrations app/Console/Commands app/Jobs
 *
 * Exit codes:
 *   0  no hardcoded row ids found
 *   1  at least one non-allowlisted hardcoded row id found
 */

declare(strict_types=1);

$root = dirname(__DIR__);

/*
 * File (relative to the artifact root) => [needle => reason]. A finding is
 * suppressed only when its exact matched snippet contains an allowlisted
 * needle for that file. Every entry must explain why the literal is not an
 * environment-specific database row id.
 */
const ALLOWLIST = [
    // Example:
    // 'database/seeders/SomeSeeder.php' => [
    //     "'external_id' => 0" => 'sentinel value for a third-party API field, not a DB row id',
    // ],
];

/** Regexes over comment-blanked source. Each must expose the offending snippet as match [0]. */
const PATTERNS = [
    'array key' => '/[\'"][A-Za-z0-9]\w*_id[\'"]\s*=>\s*-?\d+/',
    'property assignment' => '/->\s*\w*_id\s*=(?![=>])\s*-?\d+/',
    'query comparison' => '/\bwhere(?:In|NotIn)?\s*\(\s*[\'"][A-Za-z0-9.]\w*(?:\.\w+)?_id[\'"]\s*,\s*\[?\s*-?\d+/i',
];

$dirs = array_slice($argv, 1);
if ($dirs === []) {
    $dirs = ['scripts', 'database/seeders', 'database/migrations', 'app/Console/Commands', 'app/Jobs'];
}

$self = __FILE__;
$failures = [];

foreach ($dirs as $dir) {
    $abs = $root.'/'.$dir;
    if (!is_dir($abs)) {
        fwrite(STDERR, "check-hardcoded-row-ids: directory not found: {$dir}\n");
        exit(1);
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if ($file->getExtension() !== 'php' || $file->getPathname() === $self) {
            continue;
        }
        $contents = file_get_contents($file->getPathname());
        if ($contents === false) {
            continue;
        }

        // Blank comments (preserving newlines) so docblocks can describe the
        // forbidden pattern without tripping the guard.
        $blanked = '';
        foreach (token_get_all($contents) as $token) {
            if (is_array($token)) {
                [$id, $text] = $token;
                $blanked .= ($id === T_COMMENT || $id === T_DOC_COMMENT)
                    ? preg_replace('/[^\n]/', ' ', $text)
                    : $text;
            } else {
                $blanked .= $token;
            }
        }

        $rel = ltrim(str_replace($root, '', $file->getPathname()), '/');
        $lines = explode("\n", $blanked);

        foreach (PATTERNS as $label => $pattern) {
            if (!preg_match_all($pattern, $blanked, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($m[0] as [$snippet, $offset]) {
                $allowed = false;
                foreach (ALLOWLIST[$rel] ?? [] as $needle => $reason) {
                    if (str_contains($snippet, $needle)) {
                        $allowed = true;
                        break;
                    }
                }
                if ($allowed) {
                    continue;
                }
                $line = substr_count($blanked, "\n", 0, $offset) + 1;
                $failures[] = sprintf('%s:%d  [%s]  %s', $rel, $line, $label, trim($lines[$line - 1] ?? $snippet));
            }
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "\ncheck-hardcoded-row-ids FAILED:\n\n");
    foreach ($failures as $lineOut) {
        fwrite(STDERR, "  - {$lineOut}\n");
    }
    fwrite(STDERR, <<<'EOT'

Numeric row ids are environment-specific: the same row has a different id on
production, dev, and every fresh schema, so a hardcoded id silently points at
the wrong (or a nonexistent) row everywhere but the environment it was copied
from. Resolve the row at runtime by a stable natural key instead, e.g.:

  $domainId = Domain::whereNull('user_id')
      ->where('domain', PlatformHosts::primaryBrandDomain())
      ->value('id');

  $planId = Plan::where('slug', 'pro')->value('id');

If a literal is genuinely NOT a database row id (a sentinel for an external
API field, a synthetic key, ...), add an ALLOWLIST entry in
scripts/check-hardcoded-row-ids.php WITH a reason — never weaken the matcher.

EOT);
    exit(1);
}

fwrite(STDOUT, "check-hardcoded-row-ids OK (no hardcoded row ids under ".implode(', ', $dirs).").\n");
exit(0);
