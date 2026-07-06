/**
 * Undefined-CSS-var dark/white fallback guard.
 *
 * The failure class (see .agents/memory/undefined-css-var-dark-fallback.md):
 *
 *   `var(--name, <#hex | rgb() | rgba() | hsl() literal>)` looks theme-aware —
 *   the literal is "just a fallback". But if `--name` is NEVER DECLARED in the
 *   scope that renders the element, the fallback is the ONLY value the browser
 *   ever sees. A dark literal then freezes on the white light-mode surface (and
 *   a white literal freezes on the dark surface): the element silently stops
 *   responding to the theme toggle and washes out.
 *
 * This regressed by hand repeatedly on the 1inme inner pages (analytics,
 * billing, api-keys, resume/dialer/settings editors, …): whole families of
 * `var(--surface, …)` / `var(--text, …)` / `var(--border, …)` references whose
 * token was declared only in a DIFFERENT render scope (e.g. the standalone
 * embed/splash pages' own `:root`) and so was undefined on the app pages. The
 * fix each time was to DECLARE the token in the correct scope
 * (common/partials/theme-styles.blade.php for the app shell). This guard makes
 * that invariant enforceable so it can't silently rot back in.
 *
 * What it does: scan every non-vendor blade under
 * `artifacts/1inme/resources/views`, find every `var(--name, <color-literal>)`
 * reference, and resolve whether `--name` is DECLARED in the scope that renders
 * that file:
 *
 *   - app-scoped views (user/admin app shell + auth pages + the shared
 *     common/partials/* that render inside it): the token must be declared in
 *     common/partials/theme-styles.blade.php (the app's `:root` / `html.light-mode`
 *     source of truth) — OR locally in the file itself.
 *   - standalone pages that ship their own `:root` (common/embed/card,
 *     common/splash): the token must be declared in that same file.
 *   - separate theming systems (marketing site, home/welcome landing, the
 *     public biolink-family pages, community blocks) are NOT the app light/dark
 *     toggle and are skipped (see EXCLUDED_SCOPE).
 *
 * A per-instance COMPONENT var set inline via `style="--x: …"` (e.g. `--tile-glow`,
 * `--sc-color`, `--rbp-c`) is legitimately declared at the element and its
 * fallback is a real default — those are recognised (COMPONENT vars) and never
 * flagged.
 *
 * Intentional theme-NEUTRAL accents (a blue/red that reads correctly in both
 * modes, deliberately frozen) and intentional standalone always-one-theme pages
 * go in NEUTRAL_ACCENT_VARS / FILE_ALLOWLIST with a reason — never by weakening
 * the parser.
 *
 * Run:  pnpm --filter @workspace/scripts run check:undefined-css-var
 *       (add `--explain` to print what is checked and exit 0)
 */

import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

/** Blade views root this guard scans (repo-relative). */
export const VIEWS_REL = "artifacts/1inme/resources/views";
/** The app shell's canonical `:root` / `html.light-mode` token source. */
export const THEME_STYLES_REL = `${VIEWS_REL}/common/partials/theme-styles.blade.php`;

export type Scope = "app" | "standalone" | "excluded";

/**
 * Standalone pages that ship their OWN `:root` block (they do NOT load
 * theme-styles), so their `var(--x, …)` tokens must be declared in the SAME
 * file. Listed explicitly (paths relative to VIEWS_REL) because the theme-styles
 * allowance must NOT leak to them: a token defined only in theme-styles is
 * undefined here and would freeze on the fallback.
 */
export const STANDALONE_FILES: readonly string[] = [
  "common/embed/card.blade.php",
  "common/splash.blade.php",
];

/**
 * Whole files intentionally skipped, each with a reason. Not the app light/dark
 * bug this guard targets.
 */
export const FILE_ALLOWLIST: readonly { file: string; reason: string }[] = [];

/**
 * Custom-property names whose color-literal fallback is an INTENTIONAL,
 * theme-neutral accent (reads correctly on both the dark and the white surface)
 * or a widget-local accent deliberately frozen. Never declared in theme-styles
 * on purpose — so exempt everywhere, with a reason.
 */
export const NEUTRAL_ACCENT_VARS: readonly { name: string; reason: string }[] = [
  {
    name: "--sa-accent",
    reason:
      "site-assistant (\"Zio Bot\") widget accent — a brand-blue accent that reads clearly on both themes; the widget ships no light/dark variant for it by design.",
  },
  {
    name: "--cc-accent",
    reason:
      "cookie-consent widget accent — brand-blue accent, theme-neutral (the widget's --cc-bg/--cc-fg/--cc-muted/--cc-border ARE declared locally; only the accent is intentionally frozen).",
  },
  {
    name: "--danger",
    reason:
      "danger/error text accent (fallback #ef4444) — red reads correctly on both the dark and the white surface, deliberately theme-neutral.",
  },
  {
    name: "--accent-danger",
    reason:
      "form/editor validation-error text accent (fallback #f87171) — red reads correctly on both themes, deliberately theme-neutral.",
  },
  {
    name: "--color-primary-500",
    reason:
      "brand-primary blue accent (fallback #3b82f6) used as a small label / pill text on a translucent blue tint — reads correctly on both themes, deliberately theme-neutral.",
  },
  {
    name: "--color-primary-600",
    reason:
      "brand-primary blue accent (fallback #2563eb) used as a solid button BACKGROUND with white text — the button is legible in both themes, deliberately theme-neutral.",
  },
  {
    name: "--tile-bg-from",
    reason:
      "per-instance stat-tile gradient START tint (fallback rgba(61,107,255,0.10)) — a low-alpha blue tint layered over the theme-flipping --bg-glass in `linear-gradient(160deg, var(--tile-bg-from,…) 0%, var(--bg-glass) 70%)`; reads correctly on both themes and no current instance overrides it.",
  },
  {
    name: "--tile-border",
    reason:
      "per-instance stat-tile border tint (fallback rgba(61,107,255,0.22)) — a low-alpha blue tint that reads correctly on both the dark and the white surface; the tile background itself flips via --bg-glass, so the tint is deliberately theme-neutral.",
  },
];

const NEUTRAL_ACCENT_SET = new Set(NEUTRAL_ACCENT_VARS.map((e) => e.name));
const FILE_ALLOWLIST_SET = new Set(FILE_ALLOWLIST.map((e) => e.file));

/**
 * Strip blade `{{-- --}}`, HTML `<!-- -->` and CSS `/* *\/` comments so a
 * `var(--x, #hex)` or a `--x:` declaration that only appears INSIDE a comment is
 * never counted as a real reference or declaration.
 */
export function stripComments(src: string): string {
  return src
    .replace(/\{\{--[\s\S]*?--\}\}/g, " ")
    .replace(/<!--[\s\S]*?-->/g, " ")
    .replace(/\/\*[\s\S]*?\*\//g, " ");
}

/**
 * All CUSTOM-PROPERTY DECLARATIONS (`--x:`) in the source — from `<style>`
 * blocks AND inline `style="…"` attributes alike. A declaration is `--name`
 * immediately followed by a colon; a `var(--name, …)` REFERENCE has `--name`
 * followed by `,` or `)`, so it never matches here.
 */
export function parseCustomPropDecls(src: string): Set<string> {
  const out = new Set<string>();
  const re = /(--[A-Za-z0-9_-]+)\s*:/g;
  let m: RegExpExecArray | null;
  while ((m = re.exec(src)) !== null) out.add(m[1] as string);
  return out;
}

/**
 * Custom-property declarations that appear INSIDE an inline `style="…"` /
 * `style='…'` attribute — i.e. per-instance COMPONENT vars set at the element
 * (`style="--tile-glow: …"`). Their `var(--x, default)` reference elsewhere is a
 * genuine per-instance default, not the undefined-token bug.
 */
export function extractInlineStyleDecls(src: string): Set<string> {
  const out = new Set<string>();
  const attrRe = /\bstyle\s*=\s*"([^"]*)"|\bstyle\s*=\s*'([^']*)'/gi;
  let m: RegExpExecArray | null;
  while ((m = attrRe.exec(src)) !== null) {
    const body = m[1] ?? m[2] ?? "";
    const declRe = /(--[A-Za-z0-9_-]+)\s*:/g;
    let d: RegExpExecArray | null;
    while ((d = declRe.exec(body)) !== null) out.add(d[1] as string);
  }
  return out;
}

export interface VarRef {
  name: string;
  /** The color-literal kind detected in the fallback slot. */
  kind: "hex" | "rgb" | "hsl";
}

/**
 * Every `var(--name, <color-literal>)` reference whose FALLBACK slot begins with
 * a color literal — `#hex`, `rgb(`/`rgba(`, or `hsl(`/`hsla(`. Only the literal
 * KIND is captured (not the full value), which sidesteps the nested `)` in
 * `rgba(…)`. References with a non-color fallback (another `var(…)`, a keyword
 * like `transparent`/`currentColor`, a number, a gradient) are NOT this bug and
 * are ignored. Duplicate names within a file are collapsed.
 */
export function findColorVarRefs(src: string): VarRef[] {
  const re =
    /var\(\s*(--[A-Za-z0-9_-]+)\s*,\s*(#[0-9a-fA-F]{3,8}\b|rgba?\(|hsla?\()/gi;
  const seen = new Map<string, VarRef>();
  let m: RegExpExecArray | null;
  while ((m = re.exec(src)) !== null) {
    const name = m[1] as string;
    if (seen.has(name)) continue;
    const marker = (m[2] as string).toLowerCase();
    const kind: VarRef["kind"] = marker.startsWith("#")
      ? "hex"
      : marker.startsWith("rgb")
        ? "rgb"
        : "hsl";
    seen.set(name, { name, kind });
  }
  return [...seen.values()];
}

/**
 * Which render scope a blade file belongs to (path relative to VIEWS_REL):
 *
 *   - `standalone` — an explicit page shipping its own `:root` (STANDALONE_FILES).
 *   - `app` — the user/admin app shell (user/**, admin/**), shared components
 *     (components/**) and the common/partials/** that render inside the shell.
 *     These load theme-styles, so a token declared there is defined here.
 *   - `excluded` — everything else: the marketing landing (home*, welcome), the
 *     marketing/public pages (public/**), the public biolink-family pages
 *     (other common/** non-partials), community blocks (partials/community/**),
 *     the customer portal (portal/**) and error/maintenance shells. These are
 *     SEPARATE theming systems, not the app light/dark toggle.
 */
export function classifyScope(rel: string): Scope {
  if (STANDALONE_FILES.includes(rel)) return "standalone";
  if (
    rel.startsWith("user/") ||
    rel.startsWith("admin/") ||
    rel.startsWith("components/") ||
    rel.startsWith("common/partials/")
  ) {
    return "app";
  }
  return "excluded";
}

export type Resolution =
  | "ok-accent"
  | "ok-component"
  | "ok-local"
  | "ok-theme"
  | "violation";

export interface ResolveContext {
  scope: Scope;
  localDecls: Set<string>;
  themeStylesDecls: Set<string>;
  componentVars: Set<string>;
}

/**
 * Resolve a single `var(--name, …)` reference. Order:
 *   1. theme-neutral accent allowlist → intentional, ok.
 *   2. per-instance component var (set inline `style="--name:"` anywhere) → ok.
 *   3. declared locally in this file (own `<style>`/`:root`/inline) → ok.
 *   4. app-scoped AND declared in theme-styles (the shell's token source) → ok.
 *   5. otherwise → the token is undefined in this render scope → violation.
 */
export function resolveReference(name: string, ctx: ResolveContext): Resolution {
  if (NEUTRAL_ACCENT_SET.has(name)) return "ok-accent";
  if (ctx.componentVars.has(name)) return "ok-component";
  if (ctx.localDecls.has(name)) return "ok-local";
  if (ctx.scope === "app" && ctx.themeStylesDecls.has(name)) return "ok-theme";
  return "violation";
}

export interface Violation {
  file: string;
  name: string;
  kind: VarRef["kind"];
  scope: Scope;
}

export interface ScanInput {
  /** rel path (under VIEWS_REL) → raw blade source. */
  files: Map<string, string>;
  /** raw theme-styles source. */
  themeStylesSrc: string;
}

/**
 * Pure analysis over an in-memory file map (used by tests and by scanRepo).
 * Builds the global COMPONENT var set (any var ever declared via an inline
 * `style="--x:"` attribute across all files) once, then evaluates each file's
 * color-var references in its own scope.
 */
export function analyze(input: ScanInput): Violation[] {
  const themeStylesDecls = parseCustomPropDecls(stripComments(input.themeStylesSrc));

  const componentVars = new Set<string>();
  for (const src of input.files.values()) {
    for (const v of extractInlineStyleDecls(src)) componentVars.add(v);
  }

  const violations: Violation[] = [];
  for (const [rel, raw] of [...input.files.entries()].sort((a, b) => a[0].localeCompare(b[0]))) {
    if (FILE_ALLOWLIST_SET.has(rel)) continue;
    const scope = classifyScope(rel);
    if (scope === "excluded") continue;

    const src = stripComments(raw);
    const refs = findColorVarRefs(src);
    if (refs.length === 0) continue;

    const localDecls = parseCustomPropDecls(src);
    const ctx: ResolveContext = { scope, localDecls, themeStylesDecls, componentVars };
    for (const ref of refs) {
      if (resolveReference(ref.name, ctx) === "violation") {
        violations.push({ file: rel, name: ref.name, kind: ref.kind, scope });
      }
    }
  }
  return violations;
}

/** Recursively list `*.blade.php` under `dir`, skipping any `vendor/` directory. */
export function listBladeFiles(dir: string): string[] {
  const out: string[] = [];
  const walk = (abs: string) => {
    for (const entry of fs.readdirSync(abs, { withFileTypes: true })) {
      if (entry.name === "vendor") continue;
      const child = path.join(abs, entry.name);
      if (entry.isDirectory()) walk(child);
      else if (entry.isFile() && entry.name.endsWith(".blade.php")) out.push(child);
    }
  };
  walk(dir);
  return out;
}

/** Read the whole views tree (minus vendor) and analyze it. */
export function scanRepo(): Violation[] {
  const viewsAbs = path.join(REPO_ROOT, VIEWS_REL);
  const files = new Map<string, string>();
  for (const abs of listBladeFiles(viewsAbs)) {
    const rel = path.relative(viewsAbs, abs).split(path.sep).join("/");
    files.set(rel, fs.readFileSync(abs, "utf8"));
  }
  const themeStylesSrc = fs.readFileSync(path.join(REPO_ROOT, THEME_STYLES_REL), "utf8");
  return analyze({ files, themeStylesSrc });
}

function printExplain(): void {
  console.log("Undefined-CSS-var dark/white fallback guard\n");
  console.log("Rule:  every `var(--name, <#hex | rgb() | hsl() literal>)` reference in a");
  console.log("       checked blade must have `--name` DECLARED in the scope that renders it,");
  console.log("       or the literal fallback is the only value the browser sees — a dark");
  console.log("       literal then freezes on the white light-mode card (and a white literal");
  console.log("       on the dark surface).\n");
  console.log(`Scanned:  every non-vendor *.blade.php under ${VIEWS_REL}\n`);
  console.log("Resolution per reference (first match wins):");
  console.log("  1. theme-neutral accent allowlist (NEUTRAL_ACCENT_VARS) — intentional");
  console.log("  2. per-instance component var set inline via style=\"--x: …\"");
  console.log("  3. declared locally in the same file (own <style>/:root/inline)");
  console.log("  4. app-scoped file AND declared in common/partials/theme-styles.blade.php");
  console.log("  else → undefined-in-scope → FAIL\n");
  console.log("Scopes:");
  console.log("  • app        — user/**, admin/**, components/**, common/partials/**");
  console.log("                 (load theme-styles → its tokens count)");
  console.log("  • standalone — own :root, checked against its OWN file:");
  for (const f of STANDALONE_FILES) console.log(`                 ${f}`);
  console.log("  • excluded   — home*/welcome, public/**, other common/** biolink pages,");
  console.log("                 partials/community/**, portal/** (separate theming systems)\n");
  console.log("Neutral-accent allowlist:");
  for (const e of NEUTRAL_ACCENT_VARS) console.log(`  allow ${e.name} — ${e.reason}`);
  console.log("\nFile allowlist:");
  for (const e of FILE_ALLOWLIST) console.log(`  allow ${e.file} — ${e.reason}`);
  console.log(
    "\nFix a failure by DECLARING the token in the correct scope (theme-styles for the",
  );
  console.log("app shell, or the page's own :root), or — if genuinely theme-neutral — add it");
  console.log("to NEUTRAL_ACCENT_VARS / FILE_ALLOWLIST in");
  console.log("scripts/src/check-undefined-css-var-fallback.ts with a reason.");
}

function main(): void {
  if (process.argv.includes("--explain")) {
    printExplain();
    process.exit(0);
  }

  let violations: Violation[];
  try {
    violations = scanRepo();
  } catch (e) {
    console.error(`✗ undefined-css-var guard FAILED — cannot scan views: ${(e as Error).message}`);
    process.exit(2);
    return;
  }

  if (violations.length === 0) {
    console.log(
      "✓ undefined-css-var guard passed — every `var(--name, <color-literal>)` reference in the checked blade views resolves to a token DECLARED in its render scope (theme-styles, its own :root, a component var, or the neutral-accent allowlist).",
    );
    process.exit(0);
  }

  console.error(
    "✗ undefined-css-var guard FAILED — color-literal fallback(s) for a token that is NEVER declared in the render scope (the literal freezes and breaks the theme toggle):\n",
  );
  const byFile = new Map<string, Violation[]>();
  for (const v of violations) {
    const list = byFile.get(v.file) ?? [];
    list.push(v);
    byFile.set(v.file, list);
  }
  for (const [file, list] of byFile) {
    console.error(`  ${VIEWS_REL}/${file}  (${list[0]!.scope} scope):`);
    for (const v of list) {
      console.error(`    var(${v.name}, <${v.kind} literal>) — ${v.name} is not declared in scope`);
    }
    const target =
      list[0]!.scope === "app"
        ? "common/partials/theme-styles.blade.php (:root + html.light-mode)"
        : "this page's own :root";
    console.error(`        declare ${list.map((v) => v.name).join(", ")} in ${target}`);
  }
  console.error(
    "\nEither DECLARE the token in the correct scope so it flips with the theme, or — if the",
  );
  console.error(
    "fallback is a genuinely theme-neutral accent / an intentional single-theme page — add it to",
  );
  console.error(
    "NEUTRAL_ACCENT_VARS / FILE_ALLOWLIST in scripts/src/check-undefined-css-var-fallback.ts with a reason.",
  );
  console.error(
    "Run `pnpm --filter @workspace/scripts run check:undefined-css-var -- --explain` for details.",
  );
  process.exit(1);
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
