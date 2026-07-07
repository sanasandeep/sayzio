/**
 * Brand-color guard — POST-BUILD complement.
 *
 * This is the sibling of the source-level `check-brand-color.ts` guard. That
 * guard scans SOURCE files; this one scans the COMPILED stylesheets that users
 * actually download. `public/build` (Laravel) and `dist/public` (Vite) are all
 * gitignored, so a pre-sweep purple token regressing into built output would
 * ship unnoticed by the source-level scan.
 *
 * The Laravel product artifact ships compiled stylesheets checked here:
 *
 *   1inme       (Laravel)   public/build/assets/*.css
 *
 * Why the Laravel app needs a build-time check
 * --------------------------------------------
 * `@tailwindcss/vite` ALSO scans Laravel's compiled blade cache
 * (`storage/framework/views/*.php`). Those cached files can still carry
 * pre-sweep purple tokens (e.g. `bg-[#7c3aed]`), so Tailwind regenerates the
 * stale utilities into the built CSS even when every source blade is clean.
 * We `php artisan view:clear` first to drop that cache. See
 * `.agents/memory/tailwind-scans-compiled-views.md`.
 *
 * Hex vs. utility classes
 * -----------------------
 * Only HEX (incl. 8-digit alpha) and rgb() forms are checked here. Tailwind v4
 * compiles `violet-*` / `purple-*` UTILITY classes down to oklch theme vars, so
 * those names never survive verbatim into built CSS — the source-level guard
 * owns them. Arbitrary values (`bg-[#7c3aed]`, `border-[rgb(139,92,246)]`) DO
 * survive as hex (rgb() is even normalized to `#rrggbb[aa]`), so those are what
 * we grep for.
 *
 * Run:  pnpm --filter @workspace/scripts run check:brand-color-build
 */

import { spawnSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";

import { REPO_ROOT, BANNED_HEX_PATTERN, BANNED_RGB_PATTERNS } from "./check-brand-color";

const APP_DIR = path.join(REPO_ROOT, "artifacts/1inme");
const LARAVEL_BUILD_ASSETS_DIR = path.join(APP_DIR, "public/build/assets");

/** Patterns that can survive into the Laravel COMPILED CSS (hex + rgb forms). */
const LARAVEL_CSS_BANNED_PATTERNS: string[] = [BANNED_HEX_PATTERN, ...BANNED_RGB_PATTERNS];

function run(cmd: string, args: string[], cwd: string, env: NodeJS.ProcessEnv = process.env): void {
  console.log(`→ ${cmd} ${args.join(" ")}`);
  const res = spawnSync(cmd, args, { cwd, env, encoding: "utf8", stdio: "inherit" });
  if (res.error) {
    console.error(`post-build brand-color guard: failed to run \`${cmd}\`:`, res.error.message);
    process.exit(2);
  }
  if (res.status !== 0) {
    console.error(`post-build brand-color guard: \`${cmd} ${args.join(" ")}\` exited ${res.status}.`);
    process.exit(2);
  }
}

function cssFilesIn(dir: string): string[] {
  return fs.existsSync(dir) ? fs.readdirSync(dir).filter((f) => f.endsWith(".css")) : [];
}

/**
 * Grep the given files for any of `patterns` (case-insensitive). Returns the
 * matching `file:line:text` lines, or null on a ripgrep error (exit 2).
 */
function grepMatches(patterns: string[], files: string[]): string[] | null {
  const rgArgs = ["-i", "--no-heading", "--with-filename", "-n"];
  for (const p of patterns) rgArgs.push("-e", p);
  rgArgs.push(...files);

  const res = spawnSync("rg", rgArgs, { encoding: "utf8", maxBuffer: 64 * 1024 * 1024 });
  if (res.error) {
    console.error("post-build brand-color guard: failed to run ripgrep:", res.error.message);
    process.exit(2);
  }
  if (res.status === 2) {
    console.error("post-build brand-color guard: ripgrep error:\n" + res.stderr);
    return null;
  }
  if (res.status === 1) return []; // no matches
  return res.stdout.split("\n").filter((l) => l.trim());
}

type ArtifactResult = { name: string; offenders: string[]; cssFiles: string[] };

function checkLaravel(): ArtifactResult {
  console.log("\n=== 1inme (Laravel) ===");
  // Drop the stale compiled-blade cache so Tailwind cannot regenerate pre-sweep
  // purple utilities from cached views, then rebuild from clean sources.
  run("php", ["artisan", "view:clear"], APP_DIR);
  run("pnpm", ["--dir", APP_DIR, "run", "build"], REPO_ROOT);

  const cssFiles = cssFilesIn(LARAVEL_BUILD_ASSETS_DIR);
  if (cssFiles.length === 0) {
    console.error(
      `post-build brand-color guard: no compiled CSS found in ${path.relative(REPO_ROOT, LARAVEL_BUILD_ASSETS_DIR)} after build.`,
    );
    process.exit(2);
  }
  const matches = grepMatches(
    LARAVEL_CSS_BANNED_PATTERNS,
    cssFiles.map((f) => path.join(LARAVEL_BUILD_ASSETS_DIR, f)),
  );
  if (matches === null) process.exit(2);
  return { name: "1inme", offenders: matches, cssFiles };
}

function main(): void {
  const results: ArtifactResult[] = [checkLaravel()];

  const failed = results.filter((r) => r.offenders.length > 0);
  if (failed.length === 0) {
    console.log("\n✓ post-build brand-color guard passed — no retired purple in any compiled stylesheet:");
    for (const r of results) console.log(`    ${r.name}: ${r.cssFiles.join(", ")}`);
    process.exit(0);
  }

  console.error("\n✗ post-build brand-color guard FAILED — retired purple in COMPILED stylesheet(s):\n");
  for (const r of failed) {
    console.error(`  [${r.name}]`);
    for (const line of r.offenders) console.error("    " + line);
  }
  console.error("\nThe brand accent is blue (use --color-primary-* / *-primary-<shade>).");
  console.error(
    "For the Laravel app this is usually a STALE compiled-blade cache leaking pre-sweep tokens into Tailwind's output;",
  );
  console.error("fix the offending source class/hex (the source-level `brand-color` guard names it), then re-run.");
  console.error("See .agents/memory/tailwind-scans-compiled-views.md for the root cause.");
  process.exit(1);
}

main();
