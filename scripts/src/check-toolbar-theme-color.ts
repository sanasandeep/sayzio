/**
 * Safari toolbar theme-color guard.
 *
 * Every standalone public Blade view — one that renders its own full HTML
 * document (`<!DOCTYPE …>`) instead of extending a layout — must declare the
 * dark toolbar color so Safari tints the browser toolbar #0a0a14 to match the
 * brand. The canonical way is:
 *
 *   @include('common.partials.toolbar-theme-color')
 *
 * but a raw `<meta name="theme-color" …>` also counts (some layouts ship one
 * directly). Task #5339 backfilled every existing standalone page; this guard
 * fails CI if a FUTURE standalone page under
 * `artifacts/1inme/resources/views/{common,public}` ships without either.
 *
 * What is scanned
 * ---------------
 *   - `*.blade.php` files under the two roots whose (comment-stripped) source
 *     contains `<!DOCTYPE` (case-insensitive) — i.e. full standalone documents.
 *   - Files without a DOCTYPE (partials, layout children) are ignored.
 *
 * What satisfies the guard
 * ------------------------
 *   - `@include('common.partials.toolbar-theme-color')` (quotes flexible), or
 *   - a literal `<meta name="theme-color"` tag, or
 *   - an entry in ALLOWLIST (with a reason) for intentional exceptions.
 *
 * Run:  pnpm --filter @workspace/scripts run check:toolbar-theme-color
 */

import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

/** View roots to scan (relative to repo root). */
export const SCAN_ROOTS: string[] = [
  "artifacts/1inme/resources/views/common",
  "artifacts/1inme/resources/views/public",
];

/**
 * Intentional exceptions, keyed by path relative to repo root, with a reason.
 * Empty today — every standalone page carries the partial. Add an entry ONLY
 * for a page that must not tint the toolbar (none known).
 */
export const ALLOWLIST: Record<string, string> = {};

const DOCTYPE_RE = /<!DOCTYPE/i;
const INCLUDE_RE = /@include\(\s*['"]common\.partials\.toolbar-theme-color['"]/;
const META_RE = /<meta\s[^>]*name=['"]theme-color['"]/i;

/** Strip Blade comments so a commented-out include/meta never satisfies (or a commented DOCTYPE never triggers) the guard. */
export function stripBladeComments(src: string): string {
  return src.replace(/\{\{--[\s\S]*?--\}\}/g, "");
}

export type Verdict = "ok" | "not-standalone" | "missing";

/** Pure classifier, exposed for unit testing. */
export function classifySource(src: string): Verdict {
  const cleaned = stripBladeComments(src);
  if (!DOCTYPE_RE.test(cleaned)) return "not-standalone";
  if (INCLUDE_RE.test(cleaned) || META_RE.test(cleaned)) return "ok";
  return "missing";
}

function walk(dir: string, out: string[]): void {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(full, out);
    else if (entry.isFile() && entry.name.endsWith(".blade.php")) out.push(full);
  }
}

function main(): void {
  const files: string[] = [];
  for (const root of SCAN_ROOTS) {
    const abs = path.join(REPO_ROOT, root);
    if (!fs.existsSync(abs)) {
      console.error(`toolbar-theme-color guard: scan root missing: ${root}`);
      process.exit(2);
    }
    walk(abs, files);
  }

  const offenders: string[] = [];
  let standalone = 0;
  for (const abs of files) {
    const rel = path.relative(REPO_ROOT, abs);
    const verdict = classifySource(fs.readFileSync(abs, "utf8"));
    if (verdict === "not-standalone") continue;
    standalone++;
    if (verdict === "missing" && !(rel in ALLOWLIST)) offenders.push(rel);
  }

  if (offenders.length === 0) {
    console.log(
      `✓ toolbar-theme-color guard passed — ${standalone} standalone view(s) all declare the dark Safari toolbar color.`,
    );
    process.exit(0);
  }

  console.error(
    "✗ toolbar-theme-color guard FAILED — standalone public view(s) ship a full <!DOCTYPE> document without the dark Safari toolbar color:\n",
  );
  for (const rel of offenders.sort()) console.error(`  ${rel}`);
  console.error(
    `\n${offenders.length} file(s). Safari renders these pages with a default (light) toolbar instead of the brand-dark #0a0a14 one.`,
  );
  console.error(
    "Fix: add @include('common.partials.toolbar-theme-color') inside the page's <head>. " +
      "If a page intentionally must not tint the toolbar, add it to ALLOWLIST in scripts/src/check-toolbar-theme-color.ts with a reason.",
  );
  process.exit(1);
}

// Only run when invoked directly (helpers above are importable by tests).
if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
