/**
 * Em-dash drift guard.
 *
 * Fails (exit 1) if a U+2014 em dash (—) appears in user-visible text inside
 * the marketing Blade views or PHP seeders/support files that feed public-page
 * copy. Em dashes are banned from visible copy because they are typographically
 * ambiguous on all platforms, break naturally in screen readers, and are easily
 * confused with en dashes or hyphens in diff review.
 *
 * What is scanned
 * ---------------
 *   1inme  resources/views/public/      — marketing Blade templates
 *          resources/views/user|admin|common/ — dashboard/admin/shared Blade views
 *          resources/views/errors/      — error-page Blade templates
 *          resources/views/emails/      — outbound email templates
 *          resources/views/layouts/site.blade.php  — page title template
 *          database/seeders/SitePagesSeeder.php
 *          database/seeders/MarketingBlogPostsSeeder.php
 *          database/seeders/LinkTypeExplainerSeeder.php
 *          database/seeders/PlansAndAddonsSeeder.php
 *          database/seeders/CoinPackagesSeeder.php
 *          app/Modules/Common/Support/SitePagesContent.php
 *   1inme-mobile  app/ + components/    — Expo mobile app copy
 *
 * What is NOT scanned
 * -------------------
 *   resources/views/vendor/  — third-party views (do-not-touch per project rules)
 *   Comment blocks: C-style /* *\/, Blade {{-- --}}, HTML <!-- -->, trailing //
 *   PHP/JS fallback placeholders: ?? '—' or || '—' patterns (no-price displays)
 *
 * Run:  pnpm --filter @workspace/scripts run check:em-dash
 */

import { spawnSync } from "node:child_process";
import { fileURLToPath } from "node:url";
import fs from "node:fs";
import path from "node:path";

const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

const EM_DASH = "\u2014";

/**
 * Blank out comment spans so their contents are never flagged, while preserving
 * newlines (and column offsets) so reported line numbers still point at the real
 * source. Handles:
 *   - C-style block comments /* *\/  (CSS, JS/TS, PHP)
 *   - Blade {{-- --}} and HTML <!-- --> block comments
 *   - `//` line comments (not the `//` in `https://`, so URLs survive)
 */
function blankComments(src: string): string {
  const blank = (m: string) => m.replace(/[^\n]/g, " ");
  let out = src.replace(/\/\*[\s\S]*?\*\//g, blank);
  out = out.replace(/\{\{--[\s\S]*?--\}\}|<!--[\s\S]*?-->/g, blank);
  out = out.replace(/(^|[^:])(\/\/[^\n]*)/gm, (_m, pre: string, cmt: string) => pre + " ".repeat(cmt.length));
  return out;
}

/**
 * After blanking comments, also blank PHP/JS fallback-placeholder em dashes.
 * Patterns like `?? '—'`, `?: '—'`, `|| '—'`, `!== '—'`, `: '—'` (JS ternary)
 * are no-price/no-value display sentinels, not marketing copy
 * (declared out-of-scope in scratchpad).
 */
function blankFallbackPlaceholders(src: string): string {
  // A quoted string containing ONLY an em dash is always a no-value display
  // sentinel (`?? '—'`, `return '—';`, `: "—"`), never copy.
  let out = src.replace(/(['"])—\1/g, (m) => " ".repeat(m.length));
  // Same for an element whose entire text is a lone em dash: <td>—</td>
  out = out.replace(/>[ \t]*—[ \t]*</g, (m) => " ".repeat(m.length));
  // And a line whose entire content is a lone em dash (Blade @else fallbacks)
  out = out.replace(/^[ \t]*—[ \t]*$/gm, (m) => " ".repeat(m.length));
  return out;
}

/** Scan roots and individual files (relative to repo root). */
const SCAN_TARGETS: string[] = [
  "artifacts/1inme/resources/views/public",
  "artifacts/1inme/resources/views/user",
  "artifacts/1inme/resources/views/admin",
  "artifacts/1inme/resources/views/common",
  "artifacts/1inme/resources/views/errors",
  "artifacts/1inme/resources/views/emails",
  "artifacts/1inme-mobile/app",
  "artifacts/1inme-mobile/components",
  "artifacts/1inme/resources/views/public/layouts/site.blade.php",
  "artifacts/1inme/database/seeders/SitePagesSeeder.php",
  "artifacts/1inme/database/seeders/MarketingBlogPostsSeeder.php",
  "artifacts/1inme/database/seeders/LinkTypeExplainerSeeder.php",
  "artifacts/1inme/database/seeders/PlansAndAddonsSeeder.php",
  "artifacts/1inme/database/seeders/CoinPackagesSeeder.php",
  "artifacts/1inme/database/seeders/DemoContentSeeder.php",
  "artifacts/1inme/database/seeders/ShowcaseAccountSeeder.php",
  "artifacts/1inme/database/seeders/StarterPageTemplatesSeeder.php",
  "artifacts/1inme/database/seeders/ReadonlyDemoAccountSeeder.php",
  "artifacts/1inme/app/Modules/Common/Support/SitePagesContent.php",
];

/** Glob-level exclusions passed to ripgrep. */
const EXCLUDE_GLOBS: string[] = [
  "!**/node_modules/**",
  "!**/vendor/**",
];

type Offender = { file: string; line: number; text: string };

function scanFile(relPath: string): Offender[] {
  const abs = path.join(REPO_ROOT, relPath);
  let src: string;
  try {
    src = fs.readFileSync(abs, "utf8");
  } catch {
    return [];
  }
  const cleaned = blankFallbackPlaceholders(blankComments(src));
  const offenders: Offender[] = [];
  const rawLines = src.split("\n");
  const cleanLines = cleaned.split("\n");
  for (let i = 0; i < cleanLines.length; i++) {
    if ((cleanLines[i] ?? "").includes(EM_DASH)) {
      offenders.push({ file: relPath, line: i + 1, text: (rawLines[i] ?? "").trimEnd() });
    }
  }
  return offenders;
}

function listFiles(): string[] {
  const args = ["--files"];
  for (const g of EXCLUDE_GLOBS) {
    args.push("-g", g);
  }
  args.push(...SCAN_TARGETS);

  const res = spawnSync("rg", args, {
    cwd: REPO_ROOT,
    encoding: "utf8",
    maxBuffer: 64 * 1024 * 1024,
  });
  if (res.error) {
    console.error("em-dash guard: failed to list files:", res.error.message);
    process.exit(2);
  }
  if (res.status === 2) {
    console.error("em-dash guard: ripgrep error:\n" + res.stderr);
    process.exit(2);
  }
  return res.stdout
    .split("\n")
    .map((l) => l.trim())
    .filter(Boolean);
}

function main(): void {
  const files = listFiles();
  const allOffenders: Offender[] = [];
  for (const rel of files) {
    allOffenders.push(...scanFile(rel));
  }

  if (allOffenders.length === 0) {
    console.log(
      "\u2713 em-dash guard passed \u2014 no em dashes in user-visible marketing copy.",
    );
    process.exit(0);
  }

  console.error(
    "\u2717 em-dash guard FAILED \u2014 em dash (U+2014) found in user-visible text:\n",
  );
  for (const o of allOffenders) {
    console.error(`  ${o.file}:${o.line}: ${o.text}`);
  }
  console.error(
    `\n${allOffenders.length} occurrence(s). Rewrite naturally: use a comma, colon, semicolon, parentheses, or a new sentence instead.`,
  );
  process.exit(1);
}

main();
