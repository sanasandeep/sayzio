/**
 * Brand-color guard.
 *
 * Fails (exit 1) if the RETIRED purple brand palette is reintroduced into a
 * PRIMARY UI surface of any of the four product artifacts. The product brand
 * accent is now blue (`--color-primary-*`); the old purple ramp must not creep
 * back into the app chrome, slide chrome, or mobile UI.
 *
 * Banned tokens
 * -------------
 *   - Hex:  #7c3aed / #8b5cf6 / #a78bfa  (with or without the leading '#',
 *           e.g. inside a Tailwind arbitrary value `bg-[#7c3aed]`)
 *   - RGB:  rgb()/rgba() forms of the same three colors
 *   - Tailwind classes: `violet-<shade>` / `purple-<shade>` (any utility:
 *           bg/text/border/from/via/to/ring/fill/... and the `[id]` shades)
 *
 * What is scanned (primary UI surfaces)
 * -------------------------------------
 *   1inme        resources/ (blade + css), public/css, public/js
 *   1inme-mobile app/, components/, constants/, lib/
 *
 * What is NOT scanned, and WHY (intentional categorical palettes)
 * --------------------------------------------------------------
 * The retired purple is legitimately allowed in places that render a
 * MULTI-COLOR categorical palette (block-style pickers, template galleries)
 * or that store palette/content DATA rather than
 * the brand accent. Re-coloring those would break the intended rainbow of
 * choices, not fix a brand regression. Each exclusion below is deliberate:
 *
 *   - Build output / deps: node_modules, build, dist, .expo
 *   - vendor/ blades            : third-party (do-not-touch) views
 *   - PHP backend (artifacts/1inme/app/**) and database/** are not scanned:
 *     the template catalogs (BlockVariantCatalog, PaidPageTemplates,
 *     QrCodeCatalog, GradientCatalog, ...) and content/demo seeders store
 *     categorical palette + seed DATA, not rendered brand accents.
 *   - See the ALLOWLIST array for the per-file categorical surfaces.
 *
 * Run:  pnpm --filter @workspace/scripts run check:brand-color
 *       (add `--explain` to print the exclusion rationale and exit 0)
 */

import { spawnSync } from "node:child_process";
import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

/**
 * Banned RETIRED-purple HEX forms, with or without '#'. \b keeps us off longer
 * hex strings. Shared with the post-build guard (`check-brand-color-build.ts`),
 * which greps the COMPILED stylesheet where these survive verbatim.
 */
export const BANNED_HEX_PATTERN = String.raw`#?\b(7c3aed|8b5cf6|a78bfa)\b`;

/**
 * Banned rgb()/rgba() forms of the same three colors. Whitespace-tolerant and
 * accepts both comma- and space-separated channels (Tailwind v4 can emit either
 * in the compiled CSS).
 */
export const BANNED_RGB_PATTERNS: string[] = [
  String.raw`rgba?\(\s*124\s*[,\s]\s*58\s*[,\s]\s*237`,
  String.raw`rgba?\(\s*139\s*[,\s]\s*92\s*[,\s]\s*246`,
  String.raw`rgba?\(\s*167\s*[,\s]\s*139\s*[,\s]\s*250`,
];

/**
 * Per-color descriptors of the retired purple ramp, used by the post-build
 * guard (`check-brand-color-build.ts`) to attribute a compiled-CSS match back
 * to one canonical color.
 */
export const RETIRED_COLORS: { base: string; rgb: [number, number, number] }[] = [
  { base: "7c3aed", rgb: [124, 58, 237] },
  { base: "8b5cf6", rgb: [139, 92, 246] },
  { base: "a78bfa", rgb: [167, 139, 250] },
];

/**
 * Hex form of a retired color, allowing an optional 8-digit alpha suffix.
 *
 * Tailwind v4 normalizes arbitrary `rgba(124,58,237,0.18)` values into a
 * `#rrggbbaa` hex in the COMPILED CSS (e.g. `#7c3aed2e`). The source-level
 * `BANNED_HEX_PATTERN` ends in `\b`, which never matches an 8-digit hex (no
 * word boundary between the 6th and 7th hex digit), so it silently misses
 * translucent purple in built output. This variant catches both the 6-digit
 * solid and the 8-digit translucent forms.
 */
export function hexPatternWithAlpha(base: string): string {
  return String.raw`#?\b${base}([0-9a-f]{2})?\b`;
}

/** rgb()/rgba() form of a retired color (whitespace/comma tolerant). */
export function rgbPatternFor([r, g, b]: [number, number, number]): string {
  return String.raw`rgba?\(\s*${r}\s*[,\s]\s*${g}\s*[,\s]\s*${b}`;
}

/** Banned-token regexes passed to ripgrep (case-insensitive). */
export const BANNED_PATTERNS: string[] = [
  BANNED_HEX_PATTERN,
  ...BANNED_RGB_PATTERNS,
  // Tailwind utility classes for the violet / purple ramps (source-only — these
  // compile away to color values, so they never reach the built stylesheet).
  String.raw`\b(violet|purple)-(50|100|200|300|400|500|600|700|800|900|950)\b`,
];

/**
 * Case-insensitive compiled forms of the source-level banned tokens. This is the
 * exact set of matchers the CLI runs against every scanned line (via `scanSource`
 * below), exposed so the delicate hex/rgb/violet-/purple- detection can be
 * unit-tested directly rather than only through ripgrep.
 */
export const BANNED_REGEXES: RegExp[] = BANNED_PATTERNS.map((p) => new RegExp(p, "i"));

/** A retired-purple offender: 1-based line/col of the first match on that line. */
export type Offender = { file: string; line: number; col: number; text: string };

/**
 * Blank out comment spans so their contents are never flagged, while preserving
 * newlines (and column offsets) so reported line/column numbers still point at
 * the real source. A retired-purple hex/class inside a comment does not render,
 * so it is not a brand regression (mirrors the AI tool-name guard). Handles:
 *   - C-style block comments `/* *\/` (CSS, JS/TS/JSX, PHP)
 *   - blade `{{-- --}}` and HTML `<!-- -->`
 *   - `//` line comments (not the `//` in `https://`, so URLs survive)
 */
export function blankComments(src: string): string {
  const blank = (m: string) => m.replace(/[^\n]/g, " ");
  let out = src.replace(/\/\*[\s\S]*?\*\//g, blank);
  out = out.replace(/\{\{--[\s\S]*?--\}\}|<!--[\s\S]*?-->/g, blank);
  out = out.replace(/(^|[^:])(\/\/[^\n]*)/gm, (_m, pre: string, cmt: string) => pre + " ".repeat(cmt.length));
  return out;
}

/**
 * Core line matcher: return an offender for the FIRST banned token on each line
 * of `src` (comments blanked first). Shared by the source-level scan and the
 * cookie-consent config scan, which pass different regex sets.
 */
export function matchLines(relFile: string, src: string, regexes: RegExp[]): Offender[] {
  const cleaned = blankComments(src);
  const lines = cleaned.split("\n");
  const rawLines = src.split("\n");
  const offenders: Offender[] = [];
  for (let i = 0; i < lines.length; i++) {
    const text = lines[i] ?? "";
    for (const re of regexes) {
      const m = re.exec(text);
      if (m) {
        offenders.push({ file: relFile, line: i + 1, col: m.index + 1, text: rawLines[i] ?? "" });
        break;
      }
    }
  }
  return offenders;
}

/**
 * Cookie-consent config defaults guard (PHP backend exception).
 * --------------------------------------------------------------
 * The PHP backend (`artifacts/1inme/app/**`) is deliberately OUT of the
 * SCAN_ROOTS above, because its catalogs store multi-color categorical palette
 * DATA. But ONE PHP file is a genuine PRIMARY brand surface: the cookie-consent
 * widget's default accent / button colors. Those defaults render as the brand
 * accent on every visitor's consent prompt, so retired purple must never creep
 * back in there. We scan just this single file for the retired HEX/rgb forms
 * (Tailwind utility classes never appear in PHP config), keeping the scope
 * narrow so the categorical palette catalogs elsewhere in the backend are not
 * touched.
 */
const COOKIE_CONSENT_CONFIG = "artifacts/1inme/app/Modules/Common/Support/CookieConsentConfig.php";

/** Build the HEX (incl. 8-digit alpha) + rgb() regexes for the consent scan. */
function consentBannedRegexes(): RegExp[] {
  const pats = [
    ...RETIRED_COLORS.map((c) => hexPatternWithAlpha(c.base)),
    ...RETIRED_COLORS.map((c) => rgbPatternFor(c.rgb)),
  ];
  return pats.map((p) => new RegExp(p, "i"));
}

/**
 * Pure scan of arbitrary text for the cookie-consent retired purple. Unlike the
 * source-level `scanSource`, this uses the ALPHA-AWARE hex pattern (via
 * `consentBannedRegexes`) because the consent defaults render the brand accent
 * directly, so a translucent `#7c3aedNN` here IS a real brand regression. Reuses
 * the shared `matchLines` matcher (comments blanked) so it stays in lockstep with
 * the source scan, and is exercised by both the file scan below and the
 * regression suite. Returns 1-based line/col offenders.
 */
export function scanConsentText(relFile: string, text: string): Offender[] {
  return matchLines(relFile, text, consentBannedRegexes());
}

/**
 * Scan the cookie-consent config defaults file for retired purple. Delegates to
 * `scanConsentText` (alpha-aware + comment-blanked) so the exported helper stays
 * on the real guard path, not just under test.
 */
function scanCookieConsentConfig(): Offender[] {
  const abs = path.join(REPO_ROOT, COOKIE_CONSENT_CONFIG);
  let src: string;
  try {
    src = fs.readFileSync(abs, "utf8");
  } catch {
    // Missing file is not this guard's concern (e.g. relocated/renamed).
    return [];
  }
  return scanConsentText(COOKIE_CONSENT_CONFIG, src);
}

/** Primary UI surfaces to scan (relative to repo root). */
const SCAN_ROOTS: string[] = [
  "artifacts/1inme/resources",
  "artifacts/1inme/public/css",
  "artifacts/1inme/public/js",
  "artifacts/1inme-mobile/app",
  "artifacts/1inme-mobile/components",
  "artifacts/1inme-mobile/constants",
  "artifacts/1inme-mobile/lib",
];

type AllowEntry = { path: string; kind: "file" | "dir"; reason: string };

/**
 * Explicit allow-list of intentional categorical palettes / non-product files
 * within the scanned roots. Each entry documents WHY purple is acceptable
 * there so future agents do not "fix" a deliberate multi-color surface.
 */
const ALLOWLIST: AllowEntry[] = [
  {
    path: "artifacts/1inme/resources/views/welcome.blade.php",
    kind: "file",
    reason:
      "Stock Laravel fallback page (inline Tailwind theme ramp); only rendered when the Vite build manifest is missing, not a product surface.",
  },
  {
    path: "artifacts/1inme/resources/views/common/partials/biolink-block-render.blade.php",
    kind: "file",
    reason:
      "Public biolink block renderer carries the multi-color categorical block/template palette, not the brand accent.",
  },
  {
    path: "artifacts/1inme/public/css/marketing-anim.css",
    kind: "file",
    reason:
      "Defensive light-mode overrides that REFERENCE violet/purple class names only to remap stray instances back to brand blue.",
  },
  {
    path: "artifacts/1inme-mobile/lib/blockVariants.ts",
    kind: "file",
    reason: "Categorical block-variant palette mirror (multi-color choices).",
  },
  {
    path: "artifacts/1inme-mobile/app/calendars/edit.tsx",
    kind: "file",
    reason:
      "Calendar accent-color picker is a multi-color categorical user-choice palette (not the brand accent); the default stays brand blue #3d6bff.",
  },
  {
    path: "artifacts/1inme-mobile/lib/paidPage.ts",
    kind: "file",
    reason: "Categorical paid-page template palette mirror (multi-color choices).",
  },
  {
    path: "artifacts/1inme-mobile/app/projects.tsx",
    kind: "file",
    reason: "Categorical project-color palette used to tag/group projects.",
  },
  {
    path: "artifacts/1inme-mobile/app/links/[id]/settings/themes.tsx",
    kind: "file",
    reason: "Theme PICKER — presents the full multi-color theme palette to choose from.",
  },
  {
    path: "artifacts/1inme-mobile/app/links/[id]/blocks/[blockId].tsx",
    kind: "file",
    reason: "Block-style PICKER — presents the full multi-color style palette to choose from.",
  },
  {
    path: "artifacts/1inme-mobile/components/PreviewBlueprint.tsx",
    kind: "file",
    reason: "Template-preview component renders the categorical template palette.",
  },
];

export function isAllowed(file: string): boolean {
  const norm = file.split(path.sep).join("/");
  return ALLOWLIST.some((e) =>
    e.kind === "file" ? norm === e.path : norm === e.path || norm.startsWith(e.path + "/"),
  );
}

/**
 * Pure source-level matcher: return retired-purple offenders in `src`, honoring
 * the ALLOWLIST (an allow-listed file — an intentional categorical palette —
 * yields no offenders). This is exactly what the CLI runs against every scanned
 * file, exposed for direct unit testing so a well-meaning tweak to the delicate
 * hex/rgb/violet-/purple- matching or the allow-list can't silently weaken the
 * guard without a test noticing.
 */
export function scanSource(relFile: string, src: string): Offender[] {
  if (isAllowed(relFile)) return [];
  return matchLines(relFile, src, BANNED_REGEXES);
}

function printExclusions(): void {
  console.log("Brand-color guard — intentional exclusions:");
  console.log("  (build output, node_modules, vendor/, PHP backend & database/ are out of scope)\n");
  for (const e of ALLOWLIST) {
    console.log(`  • ${e.path}${e.kind === "dir" ? "/**" : ""}`);
    console.log(`      ${e.reason}`);
  }
}

/**
 * List every file under SCAN_ROOTS (build output / vendor / deck decorative
 * slides excluded up-front) so each can be read and passed to `scanSource`.
 */
function listFiles(): string[] {
  const args = ["--files"];
  for (const g of [
    "!**/node_modules/**",
    "!**/build/**",
    "!**/dist/**",
    "!**/.expo/**",
    "!**/vendor/**",
  ]) {
    args.push("-g", g);
  }
  args.push(...SCAN_ROOTS);

  const res = spawnSync("rg", args, {
    cwd: REPO_ROOT,
    encoding: "utf8",
    maxBuffer: 64 * 1024 * 1024,
  });
  if (res.error) {
    console.error("brand-color guard: failed to list files:", res.error.message);
    process.exit(2);
  }
  if (res.status === 2) {
    console.error("brand-color guard: ripgrep error:\n" + res.stderr);
    process.exit(2);
  }
  return res.stdout.split("\n").map((l) => l.trim()).filter(Boolean);
}

function main(): void {
  if (process.argv.includes("--explain")) {
    printExclusions();
    process.exit(0);
  }

  const offenders: Offender[] = [];
  for (const rel of listFiles()) {
    let src: string;
    try {
      src = fs.readFileSync(path.join(REPO_ROOT, rel), "utf8");
    } catch {
      continue;
    }
    offenders.push(...scanSource(rel, src));
  }

  // The PHP backend is otherwise out of scope, but the cookie-consent config
  // defaults are a genuine brand surface — scan that single file too.
  offenders.push(...scanCookieConsentConfig());

  if (offenders.length === 0) {
    console.log("✓ brand-color guard passed — purple only in intentional categorical palettes.");
    process.exit(0);
  }

  console.error("✗ brand-color guard FAILED — retired purple palette found in primary UI surfaces:\n");
  for (const o of offenders) console.error(`  ${o.file}:${o.line}:${o.col}:${o.text}`);
  console.error(
    `\n${offenders.length} match(es). The brand accent is blue (use --color-primary-* / *-primary-<shade>).`,
  );
  console.error(
    "If this is an INTENTIONAL multi-color categorical palette, add it to ALLOWLIST in scripts/src/check-brand-color.ts with a reason.",
  );
  console.error("Run `pnpm --filter @workspace/scripts run check:brand-color -- --explain` to see current exclusions.");
  process.exit(1);
}

// Only run when invoked directly (the patterns above are imported by the
// post-build guard, which must not trigger this source-level scan on import).
if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
