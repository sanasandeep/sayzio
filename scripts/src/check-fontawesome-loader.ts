/**
 * Font Awesome loader guard.
 *
 * Icons on every public page depend on ONE shared partial:
 *   artifacts/1inme/resources/views/common/partials/fontawesome.blade.php
 *
 * That partial loads Font Awesome as a PLAIN BLOCKING <link rel="stylesheet">
 * (tagged data-fa-stylesheet) plus woff2 font preloads (fa-solid-900 +
 * fa-brands-400, as="font" crossorigin — the FA css is font-display:block, so
 * without the preloads glyphs render as blank boxes until the fonts arrive).
 *
 * History: the partial previously used the loadCSS media="print" swap with an
 * inline safety net + timed retries + re-insert recovery. Real-world Safari
 * STILL rendered blank icons (cached print links never fire onload, and Safari
 * was seen ignoring the media flip entirely), so the swap was removed for good.
 * Any media="print" / data-fa-async loader in the partial is now itself a
 * regression.
 *
 * This guard fails (exit 1) if either regression ships:
 *   1. The shared partial loses any required piece or regresses:
 *      - the plain blocking stylesheet link tagged data-fa-stylesheet,
 *      - the two woff2 font preloads (crossorigin),
 *      - OR reintroduces a media="print" swap / data-fa-async loader.
 *   2. A public-facing blade view OUTSIDE the partial ships its own raw Font
 *      Awesome <link> (blocking stylesheet, preload, or a hand-rolled
 *      print-swap) instead of @include('common.partials.fontawesome').
 *
 * Scope: only PUBLIC-facing view roots are scanned for offender links.
 * Authenticated surfaces (admin/, user/, portal/) already use a plain
 * blocking FA <link>.
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

  // 1. Plain blocking stylesheet link tagged data-fa-stylesheet, WITHOUT any
  //    media attribute trickery.
  const blockingLink =
    /<link(?=[^>]*rel=["']stylesheet["'])(?=[^>]*data-fa-stylesheet)[^>]*>/i;
  const blockingMatch = src.match(blockingLink);
  if (!blockingMatch) {
    problems.push(
      'missing the plain blocking stylesheet link: <link rel="stylesheet" href="..." data-fa-stylesheet>',
    );
  } else if (/media=/i.test(blockingMatch[0])) {
    problems.push(
      "the data-fa-stylesheet link must NOT carry a media attribute (no print-swap trickery)",
    );
  }

  // 2. Both woff2 font preloads with crossorigin (font-display:block means
  //    blank glyph boxes until the fonts arrive without these).
  for (const font of ["fa-solid-900.woff2", "fa-brands-400.woff2"]) {
    const re = new RegExp(
      `<link(?=[^>]*rel=["']preload["'])(?=[^>]*as=["']font["'])(?=[^>]*${font.replace(/[.]/g, "\\.")})(?=[^>]*crossorigin)[^>]*>`,
      "i",
    );
    if (!re.test(src)) {
      problems.push(`missing the ${font} font preload (<link rel="preload" as="font" crossorigin>)`);
    }
  }

  // 3. FORBIDDEN: any return of the media=print swap / data-fa-async loader —
  //    the exact pattern that blanked icons in Safari.
  if (/media=["']print["']/i.test(src) || /data-fa-async/i.test(src)) {
    problems.push(
      "the media=\"print\" swap / data-fa-async loader is FORBIDDEN — it repeatedly blanked all icons in Safari; keep the plain blocking stylesheet",
    );
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
        "\nThe partial must keep the plain blocking data-fa-stylesheet link + both woff2 font preloads,\n" +
          "and must NEVER reintroduce a media=print swap — Safari repeatedly rendered blank icons with it.",
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
        "A hand-rolled link drifts from the canonical loader (and a print-swap re-blanks all icons in Safari).",
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
