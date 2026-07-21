/**
 * Hardcoded-blue-accent ratchet guard.
 *
 * Fixed-blue accents keep creeping into the THEMED user/admin blade views:
 *   - the raw brand hex `#3d6bff`
 *   - its rgb()/rgba() form `rgba(61,107,255,…)`
 *   - the non-flipping `--color-primary-500` token
 *
 * Each hand-added occurrence is a potential light-mode legibility bug that was
 * previously only caught by manual sweeps (two full sweeps and counting — see
 * .agents/memory/marketing-light-mode-legibility.md and
 * .agents/memory/light-mode-textwhite-exemption-gap.md). These tokens do NOT
 * flip with the `html.light-mode` toggle, so a dark-tuned blue accent pasted
 * into a themed view renders unchanged on the white light-mode surface.
 *
 * How it works (baseline ratchet, like the pairing guard's partial COUNT proxy)
 * -----------------------------------------------------------------------------
 * The existing footprint is large and mostly intentional/benign (translucent
 * rgba() washes, gradient CTAs, focus rings, palette custom-props), so a
 * zero-tolerance ban would drown signal in noise. Instead the guard:
 *
 *   1. scans every THEMED blade view under `user/` and `admin/`
 *      (self-contained pages that never receive the `html.light-mode` class —
 *      e.g. the standalone dark `user/auth/complete-profile.blade.php` or the
 *      print-oriented `user/invoices/pdf.blade.php` — are exempt: the theme
 *      toggle never reaches them, so a fixed blue there cannot be a light-mode
 *      bug; detection shared with the sibling theme guards via
 *      lib/blade-theme-scope),
 *   2. counts fixed-blue token occurrences per file (comments blanked), and
 *   3. compares the counts against the checked-in BASELINE
 *      (`data/hardcoded-blue-accents-baseline.json`).
 *
 * Any INCREASE over the baseline fails the build: a NEW hardcoded blue was
 * added to a themed view. The offending lines are printed. Fix by using the
 * theme-flipping tokens (`var(--accent)`, `var(--accent-light)`,
 * `var(--text-*)`, `var(--border-glass)`, …) or by adding an explicit
 * `html.light-mode` override — or, if the new usage is genuinely brand-fixed
 * (see INTENTIONAL_SURFACES), re-baseline with `--update-baseline`.
 *
 * Any DECREASE also fails, with a self-service fix: run `--update-baseline`
 * to ratchet the count DOWN so the removed usages can never silently return.
 *
 * Intentional brand-fixed surfaces are documented in INTENTIONAL_SURFACES with
 * reasons; they are part of the baseline like everything else, and the reason
 * is echoed whenever such a file is reported.
 *
 * Run:  pnpm --filter @workspace/scripts run check:hardcoded-blue
 *       (add `--explain` to print scope/rationale and exit 0;
 *        add `--update-baseline` to rewrite the baseline after a deliberate
 *        change)
 */

import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";
import {
  VIEWS_REL,
  declaresOwnDocument,
  includesThemeStyles,
  readViewsFileMap,
  stripComments,
} from "./lib/blade-theme-scope.js";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

/** Checked-in per-file occurrence baseline (path relative to VIEWS_REL → count). */
export const BASELINE_REL = "scripts/src/data/hardcoded-blue-accents-baseline.json";

/**
 * The fixed-blue tokens that never flip with the light/dark theme toggle.
 * Case-insensitive; global so every occurrence on a line is counted.
 *
 *   - `#3d6bff` with an optional 2-digit alpha suffix (Tailwind arbitrary
 *     values and compiled forms can carry `#3d6bff1f`-style translucency).
 *   - `rgb()`/`rgba()` of 61,107,255 — whitespace- and separator-tolerant
 *     (`rgba(61, 107, 255, .5)`, `rgb(61 107 255 / 50%)`).
 *   - the `--color-primary-500` custom property (referenced via `var(...)` or
 *     used as a Tailwind arbitrary token) — it is defined once and does NOT
 *     have an `html.light-mode` counterpart, so styling text/surfaces with it
 *     is exactly as fixed as the raw hex.
 */
export const BLUE_TOKEN_PATTERNS: string[] = [
  String.raw`#3d6bff([0-9a-f]{2})?\b`,
  String.raw`rgba?\(\s*61\s*[,\s]\s*107\s*[,\s]\s*255`,
  String.raw`--color-primary-500\b`,
];

export const BLUE_TOKEN_REGEXES: RegExp[] = BLUE_TOKEN_PATTERNS.map((p) => new RegExp(p, "gi"));

/**
 * Known intentional brand-fixed surfaces, with the reason each keeps its raw
 * blue. These are NOT exclusions — their occurrences are counted in the
 * baseline like every other file — but the reason is echoed whenever the file
 * shows up in a report so the next editor knows what is deliberate there.
 */
export const INTENTIONAL_SURFACES: Record<string, string> = {
  "user/dashboard/index.blade.php":
    "Gradient CTA tiles: --tile-accent/--tile-glow blue gradient accents and the upgrade CTA are " +
    "deliberately brand-fixed in both themes (saturated gradient surfaces carry their own contrast).",
  "admin/partials/sidebar.blade.php":
    "Admin sidebar --nav-tint palette: each nav group gets a fixed categorical tint (blue is one of " +
    "several); the count badge and avatar gradient sit on those tinted/gradient surfaces by design.",
  "admin/layouts/app.blade.php":
    "Admin chrome badges (e.g. the 'Admin' count badge) and the sidebar edge toggle use the fixed " +
    "brand blue on translucent blue washes that read correctly in both themes.",
  "user/layouts/app.blade.php":
    "App shell hero/nav accents: avatar/wordmark gradients, focus outlines and translucent blue " +
    "washes, most of which already ship explicit html.light-mode pairs in the same style block.",
};

/**
 * Blank comment spans (blade, HTML, CSS/JS block, `//` line) while PRESERVING
 * newlines and column offsets, so occurrence counts skip commented-out code but
 * reported line numbers still point at the real source. `https://` survives.
 */
export function blankCommentsPreserveLines(src: string): string {
  const blank = (m: string) => m.replace(/[^\n]/g, " ");
  let out = src.replace(/\/\*[\s\S]*?\*\//g, blank);
  out = out.replace(/\{\{--[\s\S]*?--\}\}|<!--[\s\S]*?-->/g, blank);
  out = out.replace(/(^|[^:])(\/\/[^\n]*)/gm, (_m, pre: string, cmt: string) => pre + " ".repeat(cmt.length));
  return out;
}

/** One fixed-blue occurrence: 1-based line number plus the raw line text. */
export type Occurrence = { line: number; text: string };

/** Count and locate every fixed-blue token occurrence in a blade source. */
export function findOccurrences(src: string): Occurrence[] {
  const cleaned = blankCommentsPreserveLines(src);
  const cleanedLines = cleaned.split("\n");
  const rawLines = src.split("\n");
  const out: Occurrence[] = [];
  for (let i = 0; i < cleanedLines.length; i++) {
    const line = cleanedLines[i] ?? "";
    let hits = 0;
    for (const re of BLUE_TOKEN_REGEXES) {
      re.lastIndex = 0;
      while (re.exec(line) !== null) hits++;
    }
    for (let h = 0; h < hits; h++) out.push({ line: i + 1, text: (rawLines[i] ?? "").trim() });
  }
  return out;
}

/**
 * Is this view IN SCOPE? Only themed views under `user/` or `admin/` are:
 * a page that ships its own `<html>`/`<head>` document and never pulls in the
 * shared theme system (theme-styles or theme-bootstrap, directly or through
 * its layout/includes/components) never receives the `html.light-mode` class,
 * so a fixed blue there is not a light-mode hazard.
 */
export function isThemedUserAdminView(rel: string, files: Map<string, string>): boolean {
  if (!rel.startsWith("user/") && !rel.startsWith("admin/")) return false;
  const raw = files.get(rel);
  if (raw === undefined) return false;
  const src = stripComments(raw);
  if (declaresOwnDocument(src) && !includesThemeStyles(rel, files)) return false;
  return true;
}

/** Current per-file occurrence counts across all in-scope themed views. */
export function scanCounts(files: Map<string, string>): Map<string, Occurrence[]> {
  const out = new Map<string, Occurrence[]>();
  for (const rel of [...files.keys()].sort()) {
    if (!isThemedUserAdminView(rel, files)) continue;
    const occ = findOccurrences(files.get(rel) as string);
    if (occ.length > 0) out.set(rel, occ);
  }
  return out;
}

export type Baseline = Record<string, number>;

export function baselinePath(): string {
  return path.join(REPO_ROOT, BASELINE_REL);
}

export function readBaseline(): Baseline | null {
  const p = baselinePath();
  if (!fs.existsSync(p)) return null;
  return JSON.parse(fs.readFileSync(p, "utf8")) as Baseline;
}

export function writeBaseline(counts: Map<string, Occurrence[]>): void {
  const obj: Baseline = {};
  for (const [rel, occ] of counts) obj[rel] = occ.length;
  const p = baselinePath();
  fs.mkdirSync(path.dirname(p), { recursive: true });
  fs.writeFileSync(p, `${JSON.stringify(obj, null, 2)}\n`);
}

export type Problem = {
  file: string;
  kind: "new-file" | "increase" | "decrease" | "stale-entry";
  baseline: number;
  found: number;
  occurrences: Occurrence[];
};

/** Diff current counts against the baseline. */
export function diffAgainstBaseline(counts: Map<string, Occurrence[]>, baseline: Baseline): Problem[] {
  const problems: Problem[] = [];
  for (const [rel, occ] of counts) {
    const base = baseline[rel];
    if (base === undefined) {
      problems.push({ file: rel, kind: "new-file", baseline: 0, found: occ.length, occurrences: occ });
    } else if (occ.length > base) {
      problems.push({ file: rel, kind: "increase", baseline: base, found: occ.length, occurrences: occ });
    } else if (occ.length < base) {
      problems.push({ file: rel, kind: "decrease", baseline: base, found: occ.length, occurrences: occ });
    }
  }
  for (const rel of Object.keys(baseline)) {
    if (!counts.has(rel)) {
      problems.push({ file: rel, kind: "stale-entry", baseline: baseline[rel] as number, found: 0, occurrences: [] });
    }
  }
  return problems.sort((a, b) => a.file.localeCompare(b.file));
}

/** The exact self-service command that rewrites the baseline. */
export const UPDATE_BASELINE_COMMAND =
  "pnpm --filter @workspace/scripts run check:hardcoded-blue -- --update-baseline";

/**
 * One-paragraph, copy-paste-ready explanation of a baseline mismatch. Used as
 * the vitest live-repo assertion message so a task blocked by SOMEONE ELSE'S
 * stale baseline (or its own forgotten update) sees the fix immediately
 * instead of a bare array diff.
 */
export function formatProblemsSummary(problems: Problem[]): string {
  const lines: string[] = [];
  const added = problems.filter((p) => p.kind === "increase" || p.kind === "new-file");
  const stale = problems.filter((p) => p.kind === "decrease" || p.kind === "stale-entry");
  lines.push(
    `hardcoded-blue-accents baseline is out of sync with the tree (${problems.length} file(s)).`,
  );
  for (const p of problems) {
    lines.push(`  - ${p.file}: baseline ${p.baseline}, found ${p.found} (${p.kind})`);
  }
  if (stale.length > 0 && added.length === 0) {
    lines.push(
      "STALE BASELINE: fixed-blue occurrences were REMOVED without ratcheting the baseline down.",
      "This is safe to self-heal — no new hardcoded blues were added. Run:",
      `  ${UPDATE_BASELINE_COMMAND}`,
      `and commit the updated ${BASELINE_REL}.`,
      "If this decrease came from a DIFFERENT task's cleanup, re-baselining here unblocks you;",
      "the removed usages stay ratcheted out either way.",
    );
  } else {
    if (added.length > 0) {
      lines.push(
        "NEW fixed-blue accents in themed views are potential light-mode legibility bugs.",
        "Prefer theme-flipping tokens (var(--accent), var(--accent-light), var(--text-*), ...)",
        "or pair the rule with an html.light-mode override. If genuinely brand-fixed, re-baseline:",
      );
    }
    if (stale.length > 0) {
      lines.push("Removed occurrences must also ratchet the baseline DOWN:");
    }
    lines.push(`  ${UPDATE_BASELINE_COMMAND}`);
  }
  return lines.join("\n");
}

function printProblem(p: Problem): void {
  const label =
    p.kind === "increase" || p.kind === "new-file"
      ? "NEW hardcoded blue accent(s)"
      : p.kind === "decrease"
        ? "fewer occurrences than the baseline (stale baseline)"
        : "baseline entry for a file with no occurrences (stale baseline)";
  console.error(`\n  ${VIEWS_REL}/${p.file}`);
  console.error(`    ${label}: baseline ${p.baseline}, found ${p.found}`);
  const reason = INTENTIONAL_SURFACES[p.file];
  if (reason) console.error(`    note (intentional surface): ${reason}`);
  if (p.kind === "increase" || p.kind === "new-file") {
    for (const o of p.occurrences) {
      const text = o.text.length > 160 ? `${o.text.slice(0, 157)}...` : o.text;
      console.error(`      L${o.line}: ${text}`);
    }
  }
}

function explain(): void {
  console.log("Hardcoded-blue-accent ratchet guard");
  console.log("");
  console.log("Tokens flagged (never flip with html.light-mode):");
  for (const p of BLUE_TOKEN_PATTERNS) console.log(`  - /${p}/i`);
  console.log("");
  console.log(`Scope: themed blade views under ${VIEWS_REL}/{user,admin}/ (vendor/ excluded).`);
  console.log("Self-contained pages (own <html>/<head>, no theme-styles/theme-bootstrap include)");
  console.log("never receive the html.light-mode toggle and are exempt, e.g.:");
  console.log("  - user/auth/complete-profile.blade.php (standalone dark page by design)");
  console.log("  - user/invoices/pdf.blade.php (print-oriented document)");
  console.log("");
  console.log(`Baseline: ${BASELINE_REL} (per-file occurrence counts).`);
  console.log("  count above baseline  -> FAIL (a new fixed blue was added; use theme tokens");
  console.log("                           like var(--accent)/var(--accent-light) or add an");
  console.log("                           html.light-mode override, or re-baseline if intentional)");
  console.log("  count below baseline  -> FAIL (ratchet down: run --update-baseline)");
  console.log("");
  console.log("Intentional brand-fixed surfaces (counted in the baseline, reason echoed in reports):");
  for (const [file, reason] of Object.entries(INTENTIONAL_SURFACES)) {
    console.log(`  - ${file}`);
    console.log(`      ${reason}`);
  }
}

export function main(argv: string[]): number {
  if (argv.includes("--explain")) {
    explain();
    return 0;
  }
  const files = readViewsFileMap();
  const counts = scanCounts(files);

  if (argv.includes("--update-baseline")) {
    writeBaseline(counts);
    console.log(`hardcoded-blue-accents: baseline rewritten (${counts.size} files) at ${BASELINE_REL}`);
    return 0;
  }

  const baseline = readBaseline();
  if (baseline === null) {
    console.error(`hardcoded-blue-accents: baseline file missing at ${BASELINE_REL}.`);
    console.error("Run: pnpm --filter @workspace/scripts run check:hardcoded-blue -- --update-baseline");
    return 1;
  }

  const problems = diffAgainstBaseline(counts, baseline);
  if (problems.length === 0) {
    let total = 0;
    for (const occ of counts.values()) total += occ.length;
    console.log(
      `hardcoded-blue-accents: OK — ${total} baselined occurrence(s) across ${counts.size} themed user/admin view(s), none added.`,
    );
    return 0;
  }

  const added = problems.filter((p) => p.kind === "increase" || p.kind === "new-file");
  const stale = problems.filter((p) => p.kind === "decrease" || p.kind === "stale-entry");
  console.error(`hardcoded-blue-accents: FAIL — ${problems.length} file(s) out of sync with the baseline.`);
  for (const p of problems) printProblem(p);
  console.error("");
  if (added.length > 0) {
    console.error("New fixed-blue accents in themed views are potential light-mode legibility bugs.");
    console.error("Prefer theme-flipping tokens (var(--accent), var(--accent-light), var(--text-*),");
    console.error("var(--border-glass), ...) or pair the rule with an html.light-mode override.");
    console.error("If the new usage is genuinely brand-fixed (gradient CTA, categorical nav tint,");
    console.error("always-dark island), re-baseline it deliberately:");
  }
  if (stale.length > 0) {
    console.error("Occurrences were removed — ratchet the baseline DOWN so they cannot return:");
  }
  console.error(`  ${UPDATE_BASELINE_COMMAND}`);
  return 1;
}

const isDirectRun =
  typeof process.argv[1] === "string" && import.meta.url === pathToFileURL(process.argv[1]).href;
if (isDirectRun) {
  process.exit(main(process.argv.slice(2)));
}
