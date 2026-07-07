/**
 * Font Awesome loader guard.
 *
 * Icons on every public page depend on ONE shared partial:
 *   artifacts/1inme/resources/views/common/partials/fontawesome.blade.php
 *
 * That partial loads Font Awesome non-blocking via the loadCSS media="print"
 * swap, plus an inline JS safety net (flips any still-pending
 * link[data-fa-async][media="print"] to media="all" on DOMContentLoaded and
 * window load) that fixes a Safari bug where the link onload never fires for
 * cached media=print stylesheets — leaving every fa-* glyph blank. A
 * <noscript> fallback keeps icons working with JS disabled.
 *
 * This guard fails (exit 1) if either regression ships:
 *   1. The shared partial loses any required piece:
 *      - the media="print" + onload="this.media='all'" swap link tagged
 *        data-fa-async,
 *      - the safety-net script (data-fa-async querySelector + DOMContentLoaded
 *        + window load listeners),
 *      - the <noscript> stylesheet fallback.
 *   2. A public-facing blade view OUTSIDE the partial ships its own raw Font
 *      Awesome <link> (blocking stylesheet, preload, or a hand-rolled
 *      print-swap) instead of @include('common.partials.fontawesome').
 *
 * Scope: only PUBLIC-facing view roots are scanned for offender links.
 * Authenticated surfaces (admin/, user/, portal/) intentionally use a plain
 * blocking FA <link> — render-blocking is acceptable there and Safari's
 * print-swap bug cannot occur without the swap.
 *
 * Run:  pnpm --filter @workspace/scripts run check:fontawesome-loader
 */

import { spawnSync } from "node:child_process";
import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

const VIEWS_REL = "artifacts/1inme/resources/views";

/** The single canonical loader partial (relative to repo root). */
export const PARTIAL_REL = `${VIEWS_REL}/common/partials/fontawesome.blade.php`;

/**
 * Public-facing view roots (relative to the views dir). Everything a
 * logged-out visitor can hit. admin/, user/, portal/, emails/ and vendor/ are
 * intentionally excluded (authenticated/back-office/mail surfaces).
 */
export const PUBLIC_SCAN_ROOTS: string[] = [
  "common",
  "public",
  "errors",
  "partials",
  "components",
  "home",
  "delivery-projects",
];

/** Public-facing top-level standalone views (relative to the views dir). */
export const PUBLIC_SCAN_FILES: string[] = [
  "home.blade.php",
  "welcome.blade.php",
  "maintenance.blade.php",
  "setup-required.blade.php",
];

/**
 * Requirements the shared partial must keep. Each entry is a named predicate
 * over the partial source; all must hold.
 */
export type PartialProblem = string;

export function checkPartialSource(src: string): PartialProblem[] {
  const problems: PartialProblem[] = [];

  // 1. Non-blocking print-swap link tagged data-fa-async, flipping to 'all'.
  const swapLink =
    /<link[^>]*rel=["']stylesheet["'][^>]*media=["']print["'][^>]*onload=["']this\.media=\\?'all\\?'["'][^>]*data-fa-async/i;
  const swapLinkAnyOrder =
    /<link(?=[^>]*rel=["']stylesheet["'])(?=[^>]*media=["']print["'])(?=[^>]*onload=["']this\.media=\\?'all\\?';?["'])(?=[^>]*data-fa-async)[^>]*>/i;
  if (!swapLink.test(src) && !swapLinkAnyOrder.test(src)) {
    problems.push(
      'missing the non-blocking swap link: <link rel="stylesheet" media="print" onload="this.media=\'all\'" data-fa-async>',
    );
  }

  // 2. Safety-net script: queries link[data-fa-async][media="print"], flips
  //    media, wired to DOMContentLoaded AND window load.
  const hasScript = /<script[\s>]/i.test(src);
  const queriesAsync = /querySelectorAll\(\s*['"]link\[data-fa-async\]\[media=\\?["']print\\?["']\]['"]\s*\)/.test(
    src,
  );
  const flipsMedia = /\.media\s*=\s*['"]all['"]/.test(src);
  const onDomReady = /addEventListener\(\s*['"]DOMContentLoaded['"]/.test(src);
  const onWindowLoad = /window\.addEventListener\(\s*['"]load['"]/.test(src);
  if (!hasScript || !queriesAsync || !flipsMedia || !onDomReady || !onWindowLoad) {
    problems.push(
      "missing the data-fa-async safety-net script (must query link[data-fa-async][media=\"print\"], " +
        "flip .media='all', and listen on BOTH DOMContentLoaded and window load — the Safari cached-stylesheet fix)",
    );
  }

  // 3. <noscript> fallback with a plain stylesheet link.
  const noscriptFallback = /<noscript>\s*<link[^>]*rel=["']stylesheet["'][^>]*>\s*<\/noscript>/i;
  if (!noscriptFallback.test(src)) {
    problems.push("missing the <noscript><link rel=\"stylesheet\" ...></noscript> fallback (JS-disabled visitors)");
  }

  return problems;
}

/** A raw-FA-link offender in a public view. */
export type Offender = { file: string; line: number; text: string };

/**
 * Matches any <link> tag that points at a Font Awesome stylesheet — covers
 * the vendored path (css/vendor/fontawesome-*), any "fontawesome"/
 * "font-awesome" href (incl. CDNs), whether blocking, preload, or a
 * hand-rolled print swap. Multi-line tags are handled by scanning tag spans.
 */
const LINK_TAG_RE = /<link\b[^>]*>/gi;
const FA_HREF_RE = /href=["'][^>]*?font-?awesome/i;

export function scanViewSource(relFile: string, src: string): Offender[] {
  const offenders: Offender[] = [];
  // Blade comments never render — blank them (newline-preserving).
  const cleaned = src.replace(/\{\{--[\s\S]*?--\}\}/g, (m) => m.replace(/[^\n]/g, " "));
  for (const m of cleaned.matchAll(LINK_TAG_RE)) {
    const tag = m[0];
    if (!FA_HREF_RE.test(tag)) continue;
    const line = cleaned.slice(0, m.index ?? 0).split("\n").length;
    offenders.push({ file: relFile, line, text: tag.replace(/\s+/g, " ").trim() });
  }
  return offenders;
}

/** List public-facing `.blade.php` files (relative to repo root). */
export function listPublicViews(): string[] {
  const roots = PUBLIC_SCAN_ROOTS.map((r) => path.join(VIEWS_REL, r)).filter((r) =>
    fs.existsSync(path.join(REPO_ROOT, r)),
  );
  const files: string[] = PUBLIC_SCAN_FILES.map((f) => path.join(VIEWS_REL, f)).filter((f) =>
    fs.existsSync(path.join(REPO_ROOT, f)),
  );
  if (roots.length > 0) {
    const res = spawnSync("rg", ["--files", "-g", "*.blade.php", ...roots], {
      cwd: REPO_ROOT,
      encoding: "utf8",
      maxBuffer: 64 * 1024 * 1024,
    });
    if (res.error || (res.status !== 0 && res.status !== 1)) {
      console.error("fontawesome-loader guard: failed to list view files:", res.error?.message ?? res.stderr);
      process.exit(2);
    }
    files.push(...res.stdout.split("\n").map((l) => l.trim()).filter(Boolean));
  }
  return files;
}

function main(): void {
  let failed = false;

  // --- Check 1: the shared partial keeps all its loader pieces. ---
  const partialAbs = path.join(REPO_ROOT, PARTIAL_REL);
  if (!fs.existsSync(partialAbs)) {
    console.error(`✗ fontawesome-loader guard FAILED — shared partial missing: ${PARTIAL_REL}`);
    failed = true;
  } else {
    const problems = checkPartialSource(fs.readFileSync(partialAbs, "utf8"));
    if (problems.length > 0) {
      console.error(`✗ fontawesome-loader guard FAILED — ${PARTIAL_REL} regressed:`);
      for (const p of problems) console.error(`  - ${p}`);
      console.error(
        "\nThe partial's media=print swap + data-fa-async safety-net script + <noscript> fallback are ALL required.\n" +
          "Without the safety net, Safari leaves cached FA stylesheets print-only and renders every icon blank.",
      );
      failed = true;
    }
  }

  // --- Check 2: no public view rolls its own FA <link>. ---
  const offenders: Offender[] = [];
  for (const rel of listPublicViews()) {
    if (path.normalize(rel) === path.normalize(PARTIAL_REL)) continue;
    let src: string;
    try {
      src = fs.readFileSync(path.join(REPO_ROOT, rel), "utf8");
    } catch {
      continue;
    }
    offenders.push(...scanViewSource(rel, src));
  }
  if (offenders.length > 0) {
    console.error("✗ fontawesome-loader guard FAILED — raw Font Awesome <link> in public-facing view(s):");
    for (const o of offenders) console.error(`  ${o.file}:${o.line}: ${o.text}`);
    console.error(
      "\nPublic pages must load Font Awesome via @include('common.partials.fontawesome') — never a raw <link>.\n" +
        "A hand-rolled blocking or print-swap link bypasses the Safari safety net and can silently blank all icons.",
    );
    failed = true;
  }

  if (failed) process.exit(1);
  console.log(
    "✓ fontawesome-loader guard passed — shared partial intact, no raw FA <link> in public views.",
  );
  process.exit(0);
}

// Only run when invoked directly (helpers above are imported by the test suite).
if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
