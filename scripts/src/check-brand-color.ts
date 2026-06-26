/**
 * Brand-color guard.
 *
 * Fails (exit 1) if the RETIRED purple brand palette is reintroduced into a
 * PRIMARY UI surface of any of the four product artifacts. The product brand
 * accent is now blue (`--color-primary-*`); the old purple ramp must not creep
 * back into the app chrome, marketing site, slide chrome, or mobile UI.
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
 *   1inme-com    src/
 *   1inme-deck   src/   (slide CHROME only — see exclusions)
 *   1inme-mobile app/, components/, constants/, lib/
 *
 * What is NOT scanned, and WHY (intentional categorical palettes)
 * --------------------------------------------------------------
 * The retired purple is legitimately allowed in places that render a
 * MULTI-COLOR categorical palette (block-style pickers, template galleries,
 * decorative pitch-deck slides) or that store palette/content DATA rather than
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

/** Banned-token regexes passed to ripgrep (case-insensitive). */
const BANNED_PATTERNS: string[] = [
  BANNED_HEX_PATTERN,
  ...BANNED_RGB_PATTERNS,
  // Tailwind utility classes for the violet / purple ramps (source-only — these
  // compile away to color values, so they never reach the built stylesheet).
  String.raw`\b(violet|purple)-(50|100|200|300|400|500|600|700|800|900|950)\b`,
];

/** Primary UI surfaces to scan (relative to repo root). */
const SCAN_ROOTS: string[] = [
  "artifacts/1inme/resources",
  "artifacts/1inme/public/css",
  "artifacts/1inme/public/js",
  "artifacts/1inme-com/src",
  "artifacts/1inme-deck/src",
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
    path: "artifacts/1inme-deck/src/pages",
    kind: "dir",
    reason:
      "Pitch-deck slides use intentional per-slide decorative palettes (multi-color), not the product brand accent. Slide CHROME outside pages/ is still scanned.",
  },
  {
    path: "artifacts/1inme-mobile/lib/blockVariants.ts",
    kind: "file",
    reason: "Categorical block-variant palette mirror (multi-color choices).",
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

function isAllowed(file: string): boolean {
  const norm = file.split(path.sep).join("/");
  return ALLOWLIST.some((e) =>
    e.kind === "file" ? norm === e.path : norm === e.path || norm.startsWith(e.path + "/"),
  );
}

function printExclusions(): void {
  console.log("Brand-color guard — intentional exclusions:");
  console.log("  (build output, node_modules, vendor/, PHP backend & database/ are out of scope)\n");
  for (const e of ALLOWLIST) {
    console.log(`  • ${e.path}${e.kind === "dir" ? "/**" : ""}`);
    console.log(`      ${e.reason}`);
  }
}

function main(): void {
  if (process.argv.includes("--explain")) {
    printExclusions();
    process.exit(0);
  }

  const rgArgs = ["-i", "--vimgrep", "--no-heading"];
  for (const p of BANNED_PATTERNS) rgArgs.push("-e", p);
  for (const g of [
    "!**/node_modules/**",
    "!**/build/**",
    "!**/dist/**",
    "!**/.expo/**",
    "!**/vendor/**",
    // Cut the bulk up-front: the deck's decorative slides are allow-listed below
    // and would otherwise flood ripgrep's output. The remaining ALLOWLIST entries
    // are filtered in JS so their rationale stays documented in one place.
    "!artifacts/1inme-deck/src/pages/**",
  ]) {
    rgArgs.push("-g", g);
  }
  rgArgs.push(...SCAN_ROOTS);

  const res = spawnSync("rg", rgArgs, {
    cwd: REPO_ROOT,
    encoding: "utf8",
    maxBuffer: 64 * 1024 * 1024,
  });

  if (res.error) {
    console.error("brand-color guard: failed to run ripgrep:", res.error.message);
    process.exit(2);
  }
  // rg exit codes: 0 = matches found, 1 = no matches, 2 = error.
  if (res.status === 1) {
    console.log("✓ brand-color guard passed — no retired purple in primary UI surfaces.");
    process.exit(0);
  }
  if (res.status === 2) {
    console.error("brand-color guard: ripgrep error:\n" + res.stderr);
    process.exit(2);
  }

  const offenders: string[] = [];
  for (const line of res.stdout.split("\n")) {
    if (!line.trim()) continue;
    // vimgrep format: path:line:col:text
    const file = line.slice(0, line.indexOf(":"));
    if (!isAllowed(file)) offenders.push(line);
  }

  if (offenders.length === 0) {
    console.log("✓ brand-color guard passed — purple only in intentional categorical palettes.");
    process.exit(0);
  }

  console.error("✗ brand-color guard FAILED — retired purple palette found in primary UI surfaces:\n");
  for (const o of offenders) console.error("  " + o);
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
