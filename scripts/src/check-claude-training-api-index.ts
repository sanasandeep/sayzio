/**
 * Claude-training ⇄ api.md cross-link drift guard.
 *
 * `artifacts/1inme/docs/claude-training.md` §12 ("REST API surface") is a
 * route → notes index that mirrors the authoritative endpoint contract in
 * `artifacts/1inme/docs/api.md` and cross-links each group to that file's
 * section anchors (e.g. `[Profile](./api.md#profile)`). Because it *duplicates*
 * route/anchor data, it silently rots the moment api.md renames or removes a
 * heading: the anchor stops resolving but nothing errors — a reader just lands
 * at the top of api.md instead of the intended section.
 *
 * This guard makes that drift a CI failure instead of a silent stale doc.
 *
 * Hard fail (exit 1):
 *   Every `](./api.md#anchor)` (or `](api.md#anchor)`) cross-link found in
 *   claude-training.md must resolve to a real heading in api.md. Anchors are
 *   compared against the GitHub heading-slug of every api.md heading (all
 *   levels), computed with the same algorithm GitHub/`github-slugger` uses:
 *     lower-case → strip everything except letters/numbers/marks/`_`/`-`/space
 *     → spaces to hyphens → de-duplicate repeated slugs with `-1`, `-2`, …
 *   Headings inside fenced code blocks (```` ```bash ````) are NOT anchor
 *   targets on GitHub, so they are skipped (api.md's curl examples contain
 *   `# Register`-style shell comments that must never count as headings).
 *
 * Soft warning (never fails the build):
 *   Once the §12 index actually uses `api.md#` cross-links, any top-level
 *   (`##`) api.md group with no corresponding cross-link is flagged so a newly
 *   added endpoint group does not go undocumented. This coverage pass is
 *   skipped entirely while the index has zero `api.md#` cross-links (nothing to
 *   be inconsistent with yet), keeping the guard quiet until the index format
 *   is adopted.
 *
 * Run:  pnpm --filter @workspace/scripts run check:claude-training-api-index
 *       (add `--strict` to also FAIL on uncovered groups)
 */

import fs from "node:fs";
import path from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

export const REPO_ROOT = path.resolve(fileURLToPath(import.meta.url), "../../..");

export const API_MD_REL = "artifacts/1inme/docs/api.md";
export const CLAUDE_TRAINING_REL = "artifacts/1inme/docs/claude-training.md";

/**
 * `##` groups in api.md that are structural, not endpoint groups, and are never
 * expected to be cross-linked from the §12 index. Compared by slug.
 */
export const COVERAGE_IGNORE_SLUGS = new Set<string>([
  "contents",
  "error-codes",
  "pagination-shape",
]);

export interface Heading {
  level: number;
  text: string;
  slug: string;
  line: number;
}

export interface CrossLink {
  anchor: string;
  line: number;
  raw: string;
}

/**
 * GitHub heading-slug for a single heading's text (no de-duplication). Mirrors
 * `github-slugger`: lower-case, then drop every character that is not a Unicode
 * letter / number / mark / connector-punctuation (`_`) / hyphen / space, then
 * turn each remaining space into a hyphen.
 */
export function githubSlug(text: string): string {
  return text
    .trim()
    .toLowerCase()
    .replace(/[^\p{L}\p{N}\p{M}\p{Pc}\- ]/gu, "")
    .replace(/ /g, "-");
}

/**
 * Blank every line that lives inside a fenced code block (```` ``` ```` or
 * `~~~`), preserving newlines so 1-based line numbers stay accurate. The fence
 * lines themselves are blanked too.
 */
export function stripFencedCodeBlocks(markdown: string): string {
  const lines = markdown.split("\n");
  let inFence = false;
  let fenceMarker = "";
  const out = lines.map((line) => {
    const m = line.match(/^\s*(`{3,}|~{3,})/);
    if (m) {
      const marker = m[1][0];
      if (!inFence) {
        inFence = true;
        fenceMarker = marker;
        return "";
      }
      if (marker === fenceMarker) {
        inFence = false;
        fenceMarker = "";
        return "";
      }
      // A different fence char while already inside a fence: still code, blank it.
      return "";
    }
    return inFence ? "" : line;
  });
  return out.join("\n");
}

/**
 * Extract all ATX headings (`#`..`######`) from markdown, skipping fenced code
 * blocks, and assign each a GitHub slug with duplicate-suffixing so repeated
 * heading texts resolve to `slug`, `slug-1`, `slug-2`, … exactly like GitHub.
 */
export function extractHeadings(markdown: string): Heading[] {
  const stripped = stripFencedCodeBlocks(markdown);
  const lines = stripped.split("\n");
  const occurrences = new Map<string, number>();
  const headings: Heading[] = [];
  lines.forEach((line, i) => {
    const m = line.match(/^(#{1,6})\s+(.*?)\s*#*\s*$/);
    if (!m) return;
    const level = m[1].length;
    const text = m[2].trim();
    if (text === "") return;
    const base = githubSlug(text);
    let slug = base;
    if (occurrences.has(base)) {
      const next = (occurrences.get(base) ?? 0) + 1;
      occurrences.set(base, next);
      slug = `${base}-${next}`;
    } else {
      occurrences.set(base, 0);
    }
    headings.push({ level, text, slug, line: i + 1 });
  });
  return headings;
}

/**
 * Extract every markdown link that targets `api.md` with an `#anchor` fragment,
 * skipping fenced code blocks. Matches both `](./api.md#x)` and `](api.md#x)`.
 */
export function extractApiCrossLinks(markdown: string): CrossLink[] {
  const stripped = stripFencedCodeBlocks(markdown);
  const lines = stripped.split("\n");
  const re = /\]\((?:\.\/)?api\.md#([^)\s]+)\)/g;
  const links: CrossLink[] = [];
  lines.forEach((line, i) => {
    let m: RegExpExecArray | null;
    re.lastIndex = 0;
    while ((m = re.exec(line)) !== null) {
      links.push({ anchor: m[1], line: i + 1, raw: m[0] });
    }
  });
  return links;
}

export interface CheckResult {
  broken: CrossLink[];
  uncovered: Heading[];
  totalLinks: number;
  validSlugs: Set<string>;
}

/**
 * Run the guard against two markdown sources. Pure (no I/O) so it is trivially
 * unit-testable with fixture strings.
 */
export function checkDocs(apiMd: string, claudeTraining: string): CheckResult {
  const apiHeadings = extractHeadings(apiMd);
  const validSlugs = new Set(apiHeadings.map((h) => h.slug));

  const links = extractApiCrossLinks(claudeTraining);
  const broken = links.filter((l) => !validSlugs.has(l.anchor));

  // Coverage: which `##` groups have no cross-link pointing at them?
  const linkedAnchors = new Set(links.map((l) => l.anchor));
  const uncovered =
    links.length === 0
      ? []
      : apiHeadings.filter(
          (h) =>
            h.level === 2 &&
            !COVERAGE_IGNORE_SLUGS.has(h.slug) &&
            !linkedAnchors.has(h.slug),
        );

  return { broken, uncovered, totalLinks: links.length, validSlugs };
}

function main(): void {
  const strict = process.argv.includes("--strict");

  const apiPath = path.join(REPO_ROOT, API_MD_REL);
  const claudePath = path.join(REPO_ROOT, CLAUDE_TRAINING_REL);

  let apiMd: string;
  let claudeTraining: string;
  try {
    apiMd = fs.readFileSync(apiPath, "utf8");
    claudeTraining = fs.readFileSync(claudePath, "utf8");
  } catch (err) {
    console.error(`✗ claude-training-api-index guard: could not read docs: ${String(err)}`);
    process.exit(2);
  }

  const { broken, uncovered, totalLinks } = checkDocs(apiMd, claudeTraining);

  if (uncovered.length > 0) {
    console.warn(
      `⚠ ${uncovered.length} api.md group(s) have no cross-link in claude-training.md §12:`,
    );
    for (const h of uncovered) {
      console.warn(`    "${h.text}"  →  add [text](./api.md#${h.slug})`);
    }
    console.warn(
      "  (warning only — add a §12 index row for each so new groups don't go undocumented)\n",
    );
  }

  if (broken.length > 0) {
    console.error(
      `✗ claude-training-api-index guard FAILED — ${broken.length} of ${totalLinks} ` +
        `api.md cross-link(s) in claude-training.md point at a heading that no longer ` +
        `exists in api.md:\n`,
    );
    for (const l of broken) {
      console.error(
        `  ${CLAUDE_TRAINING_REL}:${l.line}: ${l.raw}  →  no api.md heading slugs to "#${l.anchor}"`,
      );
    }
    console.error(
      "\nFix: update the anchor to the heading's current GitHub slug in api.md, or drop the " +
        "stale index row. (Anchor = api.md heading lower-cased, punctuation stripped, spaces → hyphens.)",
    );
    process.exit(1);
  }

  if (strict && uncovered.length > 0) {
    console.error(
      `✗ claude-training-api-index guard FAILED (--strict) — ${uncovered.length} api.md ` +
        `group(s) are not cross-linked from the §12 index (see warnings above).`,
    );
    process.exit(1);
  }

  console.log(
    `✓ claude-training-api-index guard passed — all ${totalLinks} api.md cross-link(s) resolve` +
      (uncovered.length > 0 ? ` (${uncovered.length} group(s) uncovered, see warnings).` : "."),
  );
  process.exit(0);
}

// Only run when invoked directly (helpers above are imported by the test suite).
if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
