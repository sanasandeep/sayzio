/**
 * Home-page biolink-preview block guard.
 *
 * The marketing home page (#features section, `artifacts/1inme/resources/views/home.blade.php`)
 * shows a "build your biolink" demo: a left-hand `.build-list` of draggable
 * block rows (each tagged `data-bl-style="<type>"`) and a right-hand phone
 * mockup (`.bb-phone`) that renders every block type as a small inline-CSS div
 * using `.bb-*` classes (`.bb-hero`, `.bb-video`, `.bb-audio`, …).
 *
 * The failure this guards against
 * --------------------------------
 * The `.bb-*` classes are hand-authored CSS. If a preview div references a
 * `.bb-*` class whose CSS rule is MISSING or MISSPELLED (e.g. `class="bb-galery"`
 * or a renamed `.bb-gal` rule), the div silently renders as a zero-height blank —
 * the phone just shows a gap. Nothing catches this: `templates:check-designs`
 * validates the REAL biolink editor/renderer, not this marketing mockup, and the
 * home e2e specs assert section structure, not per-block CSS.
 *
 * What this check enforces (fast, static — parses the Blade file, no server)
 * -------------------------------------------------------------------------
 *   1. Every `.build-list` block type (`data-bl-style="X"`) is a known key in
 *      BLOCK_PREVIEW_MAP below. A new block row added to the build-list must
 *      declare which `.bb-*` preview class represents it (or `null` when it is
 *      intentionally not mirrored in the phone, e.g. the "file / PDF" row).
 *   2. For every mapped (non-null) `.bb-*` class: it is actually USED in the
 *      phone-preview markup AND has a matching CSS rule.
 *   3. Every `.bb-*` class USED anywhere in the preview markup has a matching
 *      CSS rule — this is the direct "renders blank" catch, covering the
 *      structural classes (`.bb-prof`, `.bb-foot`, …) too, not just the mapped
 *      ones.
 *
 * Run:  pnpm --filter @workspace/scripts run check:home-preview-blocks
 */

import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

export const HOME_BLADE_REL = "artifacts/1inme/resources/views/home.blade.php";

/**
 * Maps every `.build-list` block type (`data-bl-style`) to the `.bb-*` CSS class
 * that represents it in the phone preview. `null` means the block type is
 * intentionally NOT mirrored in the phone mockup (documented per entry).
 *
 * Adding a new block row to the build-list? Add its type here pointing at the
 * `.bb-*` preview class you added (or `null` with a reason). The guard fails if a
 * build-list type is missing from this map.
 */
export const BLOCK_PREVIEW_MAP: Record<string, string | null> = {
  image: "bb-hero", // Hero image
  link: "bb-btn", // Plain link button
  shop: "bb-btn", // Shop button (rendered as `.bb-btn.is-accent`)
  video: "bb-video",
  audio: "bb-audio",
  gallery: "bb-gal",
  form: "bb-form",
  calendar: "bb-cal",
  tip: "bb-tip",
  socials: "bb-socials",
  map: "bb-map",
  countdown: "bb-count",
  faq: "bb-faq",
  file: null, // "Media kit · PDF" row has no phone-preview tile by design
  quote: "bb-quote",
  cols: "bb-2col", // Two-column row
};

/** Parse the `data-bl-style="…"` block types listed in the `.build-list`. */
export function parseBuildListStyles(src: string): string[] {
  const out: string[] = [];
  const re = /data-bl-style="([^"]+)"/g;
  let m: RegExpExecArray | null;
  while ((m = re.exec(src)) !== null) out.push(m[1]);
  return out;
}

/**
 * Parse every `bb-*` class TOKEN referenced from a `class="…"` attribute. In
 * home.blade.php these only ever appear inside the phone-preview markup, so this
 * is the set of preview classes the browser will try to style.
 */
export function parseUsedBbClasses(src: string): Set<string> {
  const used = new Set<string>();
  const re = /class="([^"]*)"/g;
  let m: RegExpExecArray | null;
  while ((m = re.exec(src)) !== null) {
    for (const tok of m[1].split(/\s+/)) {
      if (/^bb-[A-Za-z0-9-]+$/.test(tok)) used.add(tok);
    }
  }
  return used;
}

/**
 * Parse every `.bb-*` class DEFINED in CSS — any `bb-*` token that appears
 * preceded by a `.` (a selector), including descendant selectors such as
 * `.bb-prof .bb-av`.
 */
export function parseDefinedBbClasses(src: string): Set<string> {
  const defined = new Set<string>();
  const re = /\.(bb-[A-Za-z0-9-]+)/g;
  let m: RegExpExecArray | null;
  while ((m = re.exec(src)) !== null) defined.add(m[1]);
  return defined;
}

export type PreviewProblem = { kind: string; detail: string };

/** Run all three checks against a home.blade.php source string. */
export function checkHomePreviewBlocks(src: string): PreviewProblem[] {
  const problems: PreviewProblem[] = [];

  const styles = parseBuildListStyles(src);
  const used = parseUsedBbClasses(src);
  const defined = parseDefinedBbClasses(src);

  if (styles.length === 0) {
    problems.push({
      kind: "no-build-list",
      detail:
        'No `data-bl-style="…"` rows found — the .build-list markup moved or changed. Update this guard.',
    });
  }

  // 1. Every build-list type must be declared in BLOCK_PREVIEW_MAP.
  for (const style of styles) {
    if (!(style in BLOCK_PREVIEW_MAP)) {
      problems.push({
        kind: "unmapped-block-type",
        detail: `build-list block type "${style}" is not declared in BLOCK_PREVIEW_MAP — add it pointing at its .bb-* preview class (or null if it has no phone tile).`,
      });
    }
  }

  // 2. Every mapped (non-null) .bb-* class must be used in the preview AND defined in CSS.
  for (const [style, cls] of Object.entries(BLOCK_PREVIEW_MAP)) {
    if (cls === null) continue;
    if (!used.has(cls)) {
      problems.push({
        kind: "mapped-class-not-rendered",
        detail: `block type "${style}" maps to .${cls}, but no phone-preview div uses class "${cls}" — the block will not appear in the mockup.`,
      });
    }
    if (!defined.has(cls)) {
      problems.push({
        kind: "mapped-class-no-css",
        detail: `block type "${style}" maps to .${cls}, but there is no matching .${cls} CSS rule — it would render blank.`,
      });
    }
  }

  // 3. Every .bb-* class used in the preview must have a CSS rule (the direct
  //    "renders blank" catch for missing / misspelled classes).
  for (const cls of [...used].sort()) {
    if (!defined.has(cls)) {
      problems.push({
        kind: "used-class-no-css",
        detail: `preview markup uses class "${cls}" but there is no matching .${cls} CSS rule — it would render blank (missing or misspelled class).`,
      });
    }
  }

  return problems;
}

function main(): void {
  const abs = path.join(REPO_ROOT, HOME_BLADE_REL);
  let src: string;
  try {
    src = fs.readFileSync(abs, "utf8");
  } catch (e) {
    console.error(`home-preview-blocks guard: cannot read ${HOME_BLADE_REL}: ${(e as Error).message}`);
    process.exit(2);
  }

  const problems = checkHomePreviewBlocks(src);

  if (problems.length === 0) {
    console.log(
      "✓ home-preview-blocks guard passed — every .build-list block type maps to a .bb-* preview class and every preview class has matching CSS.",
    );
    process.exit(0);
  }

  console.error("✗ home-preview-blocks guard FAILED:\n");
  for (const p of problems) {
    console.error(`  [${p.kind}] ${p.detail}`);
  }
  console.error(
    `\n${problems.length} problem(s) in ${HOME_BLADE_REL}. A blank/misspelled .bb-* preview class renders as an invisible gap in the home phone mockup.`,
  );
  console.error(
    "Fix the markup/CSS, or (for a new build-list block type) add it to BLOCK_PREVIEW_MAP in scripts/src/check-home-preview-blocks.ts.",
  );
  process.exit(1);
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
