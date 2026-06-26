/**
 * Brand-color guard — POST-BUILD complement.
 *
 * This is the sibling of the source-level `check-brand-color.ts` guard. That
 * guard scans SOURCE files; this one scans the COMPILED stylesheet that users
 * actually download.
 *
 * Why a separate check is needed
 * ------------------------------
 * `@tailwindcss/vite` auto-detects content and ALSO scans Laravel's compiled
 * blade cache (`storage/framework/views/*.php`). Those cached files can still
 * carry pre-sweep purple tokens (e.g. `bg-[#7c3aed]`), so Tailwind regenerates
 * the stale utilities into `artifacts/1inme/public/build/assets/app-*.css` even
 * when every source blade is clean. `public/build` is gitignored and is NOT
 * scanned by the source-level guard, so that regression would ship unnoticed.
 * See `.agents/memory/tailwind-scans-compiled-views.md`.
 *
 * What this does
 * --------------
 *   1. `php artisan view:clear`  — drop the stale compiled-blade cache.
 *   2. rebuild the Vite/Tailwind assets (`pnpm --dir artifacts/1inme run build`).
 *   3. grep the freshly compiled `public/build/assets/*.css` for the retired
 *      purple HEX / rgb forms, failing (exit 1) if any appear.
 *
 * Only the HEX and rgb forms are checked here: the `violet-`/`purple-<shade>`
 * Tailwind utility class names compile away to concrete color values, so they
 * never survive into the built stylesheet — the source-level guard owns those.
 *
 * Run:  pnpm --filter @workspace/scripts run check:brand-color-build
 */

import { spawnSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";

import { REPO_ROOT, BANNED_HEX_PATTERN, BANNED_RGB_PATTERNS } from "./check-brand-color";

const APP_DIR = path.join(REPO_ROOT, "artifacts/1inme");
const BUILD_ASSETS_DIR = path.join(APP_DIR, "public/build/assets");

/** Patterns that can survive into the COMPILED CSS (hex + rgb forms). */
const CSS_BANNED_PATTERNS: string[] = [BANNED_HEX_PATTERN, ...BANNED_RGB_PATTERNS];

function run(cmd: string, args: string[], cwd: string): void {
  console.log(`→ ${cmd} ${args.join(" ")}`);
  const res = spawnSync(cmd, args, { cwd, encoding: "utf8", stdio: "inherit" });
  if (res.error) {
    console.error(`post-build brand-color guard: failed to run \`${cmd}\`:`, res.error.message);
    process.exit(2);
  }
  if (res.status !== 0) {
    console.error(`post-build brand-color guard: \`${cmd} ${args.join(" ")}\` exited ${res.status}.`);
    process.exit(2);
  }
}

function main(): void {
  // 1. Drop the stale compiled-blade cache so Tailwind cannot regenerate
  //    pre-sweep purple utilities from cached views.
  run("php", ["artisan", "view:clear"], APP_DIR);

  // 2. Rebuild the Vite/Tailwind assets from clean sources.
  run("pnpm", ["--dir", APP_DIR, "run", "build"], REPO_ROOT);

  // 3. Grep the freshly compiled stylesheet for retired purple.
  const cssFiles = fs.existsSync(BUILD_ASSETS_DIR)
    ? fs.readdirSync(BUILD_ASSETS_DIR).filter((f) => f.endsWith(".css"))
    : [];

  if (cssFiles.length === 0) {
    console.error(
      `post-build brand-color guard: no compiled CSS found in ${path.relative(REPO_ROOT, BUILD_ASSETS_DIR)} after build.`,
    );
    process.exit(2);
  }

  const rgArgs = ["-i", "--no-heading", "--with-filename", "-n"];
  for (const p of CSS_BANNED_PATTERNS) rgArgs.push("-e", p);
  for (const f of cssFiles) rgArgs.push(path.join(BUILD_ASSETS_DIR, f));

  const res = spawnSync("rg", rgArgs, { encoding: "utf8", maxBuffer: 64 * 1024 * 1024 });
  if (res.error) {
    console.error("post-build brand-color guard: failed to run ripgrep:", res.error.message);
    process.exit(2);
  }
  // rg exit codes: 0 = matches found, 1 = no matches, 2 = error.
  if (res.status === 1) {
    console.log(
      `✓ post-build brand-color guard passed — no retired purple in compiled CSS (${cssFiles.join(", ")}).`,
    );
    process.exit(0);
  }
  if (res.status === 2) {
    console.error("post-build brand-color guard: ripgrep error:\n" + res.stderr);
    process.exit(2);
  }

  console.error("✗ post-build brand-color guard FAILED — retired purple palette found in the COMPILED stylesheet:\n");
  for (const line of res.stdout.split("\n")) {
    if (line.trim()) console.error("  " + line);
  }
  console.error(
    "\nThis is almost always a STALE compiled-blade cache leaking pre-sweep tokens into Tailwind's output.",
  );
  console.error(
    "Fix the offending source class/hex (the source-level `brand-color` guard names it), then re-run this guard.",
  );
  console.error("See .agents/memory/tailwind-scans-compiled-views.md for the root cause.");
  process.exit(1);
}

main();
