/**
 * Light-mode pairing completeness guard (generalized).
 *
 * Several 1inme blade pages re-theme dark-tuned markup for the light theme by
 * shipping base CSS rules (dark colors) in their own `<style>` block plus
 * `html.light-mode …` counterparts so the same markup stays legible on the
 * white light-mode surface. The invariant, first documented for the event page
 * (.agents/memory/event-page-shared-rich-content-theming.md) and later seen on
 * the standalone pages migrated to the marketing layout
 * (.agents/memory/standalone-page-to-marketing-layout.md,
 *  .agents/memory/marketing-light-mode-legibility.md):
 *
 *   Every base rule that sets a text/border COLOR (`color` / `border-color`)
 *   MUST have a paired `html.light-mode <same-selector>` rule that sets the
 *   SAME property — otherwise that element keeps its DARK-theme color on the
 *   white light-mode card and washes out (illegible / near-invisible).
 *
 * This regressed by hand more than once on the event page before it was guarded
 * (the tips/pairings sections, then the "Interested" `.btn-outline-success`
 * button). Every re-themed page is one hand-edit away from the same failure, so
 * this guard is generalized to scan a CONFIGURED SET of pages (`TARGETS`), not
 * just the event page. Adding a new page is a one-entry change to `TARGETS`.
 *
 * Two matching modes per target (see `Target`):
 *   - whole-page (no `scopes`): every base color rule on the page must be
 *     paired. Best for pages whose entire custom-class CSS is theme-aware.
 *   - scoped (`scopes: [".wrapper", …]`): only base rules under one of the
 *     given wrapper selectors are checked. Best for a page that also has
 *     intentional always-dark islands (e.g. a hero over a dark image) outside
 *     the re-themed region — scope the check to the light surface only.
 *
 * `@keyframes` blocks are stripped before parsing so animation percentages
 * (`0% { … }`) are never mistaken for color-carrying selectors.
 *
 * Intentional un-paired rules (a theme-neutral accent that reads correctly in
 * both modes, e.g. a blue focus ring, or a white label on a colored badge) go
 * in the target's `allowlist` with a reason — never by weakening the parser.
 *
 * Run:  pnpm --filter @workspace/scripts run check:light-mode-pairing
 *       (add `--explain` to print what is checked and exit 0)
 */

import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

/** The `html.light-mode ` selector prefix a light-mode override carries. */
const LIGHT_PREFIX = "html.light-mode ";

/**
 * The color-carrying properties whose dark value washes out on the white
 * light-mode card if left un-paired. `background` is deliberately NOT here: a
 * dark background block on the light card is not the washed-out-TEXT failure
 * this guard targets, and many rules set only a subtle translucent background
 * that reads fine in both themes.
 */
export const COLOR_PROPS = ["color", "border-color"] as const;
export type ColorProp = (typeof COLOR_PROPS)[number];

/** A selector+property that intentionally has no light-mode counterpart. */
export type AllowEntry = { selector: string; property: ColorProp; reason: string };

export interface Target {
  /** Repo-relative path to the blade page. */
  page: string;
  /** Human label used in output. */
  label: string;
  /**
   * Optional wrapper selectors to scope the check to. When omitted/empty the
   * whole page is checked (every base color rule must be paired).
   */
  scopes?: string[];
  /** Intentionally un-paired selector+property rules, each with a reason. */
  allowlist: AllowEntry[];
}

/**
 * The configured set of pages this guard protects. Add a page by appending an
 * entry here (and, if it re-themes a shared partial under a wrapper class, list
 * that wrapper in `scopes`). Keep the `allowlist` reasons specific.
 */
export const TARGETS: Target[] = [
  {
    page: "artifacts/1inme/resources/views/common/event-page.blade.php",
    label: "event page",
    allowlist: [
      {
        selector: ".ev-input:focus",
        property: "border-color",
        reason:
          "blue focus-ring accent (#3d6bff) — the brand accent reads clearly on both the dark and the white input surface, so the focus border is intentionally theme-neutral.",
      },
    ],
  },
  {
    page: "artifacts/1inme/resources/views/public/faqs.blade.php",
    label: "FAQs page",
    allowlist: [
      {
        selector: ".faq-card:hover",
        property: "border-color",
        reason: "blue accent hover border (rgba(61,107,255,.35)) — legible on both themes.",
      },
      {
        selector: ".faq-card[open]",
        property: "border-color",
        reason: "blue accent open border (rgba(61,107,255,.5)) — legible on both themes.",
      },
      {
        selector: ".faq-chip.is-active",
        property: "border-color",
        reason:
          "active chip sits on a solid blue background with border-color:transparent — theme-neutral (the paired html.light-mode rule keeps its white text).",
      },
    ],
  },
];

function makeIsAllowed(allowlist: AllowEntry[]) {
  return (selector: string, property: ColorProp): boolean =>
    allowlist.some((e) => e.selector === selector && e.property === property);
}

/** Concatenate the contents of every `<style>…</style>` block in the source. */
export function extractStyleBlocks(src: string): string {
  const re = /<style\b[^>]*>([\s\S]*?)<\/style>/gi;
  const parts: string[] = [];
  let m: RegExpExecArray | null;
  while ((m = re.exec(src)) !== null) parts.push(m[1] ?? "");
  return parts.join("\n");
}

/** Strip `/* *\/` CSS comments (blade `{{-- --}}` never appears inside CSS). */
export function stripCssComments(css: string): string {
  return css.replace(/\/\*[\s\S]*?\*\//g, " ");
}

/**
 * Remove `@keyframes` (and vendor-prefixed) blocks — including their nested
 * `0% { … }` steps — so animation percentages are never parsed as
 * color-carrying selectors. Brace-matched so nested braces are handled.
 */
export function stripKeyframes(css: string): string {
  const re = /@(?:-webkit-|-moz-|-o-|-ms-)?keyframes\b/gi;
  let out = "";
  let last = 0;
  let m: RegExpExecArray | null;
  while ((m = re.exec(css)) !== null) {
    out += css.slice(last, m.index);
    const open = css.indexOf("{", m.index);
    if (open === -1) {
      last = m.index;
      break;
    }
    let depth = 1;
    let j = open + 1;
    while (j < css.length && depth > 0) {
      const ch = css[j];
      if (ch === "{") depth++;
      else if (ch === "}") depth--;
      j++;
    }
    last = j;
    re.lastIndex = j;
  }
  out += css.slice(last);
  return out;
}

/** Collapse whitespace in a selector so grouping is stable. */
function normalizeSelector(sel: string): string {
  return sel.replace(/\s+/g, " ").trim();
}

export interface CssRule {
  selectors: string[];
  /** The color-carrying properties this rule declares. */
  props: Set<ColorProp>;
}

/**
 * Parse the flat (un-nested) rules out of a CSS string into
 * `{ selectors, props }`, where `props` is the subset of COLOR_PROPS the rule
 * declares. Comments and `@keyframes` blocks are stripped first. Robust enough
 * for these hand-written `<style>` blocks (nested at-rules other than
 * `@keyframes` — e.g. a bare `@media` wrapper — leave their inner rules intact
 * and only contribute a harmless prop-less wrapper).
 */
export function parseRules(css: string): CssRule[] {
  const clean = stripKeyframes(stripCssComments(css));
  const rules: CssRule[] = [];
  const re = /([^{}]+)\{([^{}]*)\}/g;
  let m: RegExpExecArray | null;
  while ((m = re.exec(clean)) !== null) {
    const selectorList = (m[1] ?? "").trim();
    if (!selectorList) continue;
    const selectors = selectorList.split(",").map(normalizeSelector).filter(Boolean);

    const props = new Set<ColorProp>();
    for (const decl of (m[2] ?? "").split(";")) {
      const colon = decl.indexOf(":");
      if (colon === -1) continue;
      const prop = decl.slice(0, colon).trim().toLowerCase();
      if ((COLOR_PROPS as readonly string[]).includes(prop)) {
        props.add(prop as ColorProp);
      }
    }
    rules.push({ selectors, props });
  }
  return rules;
}

export interface MissingPair {
  /** The base selector (light-prefix stripped for base comparison). */
  selector: string;
  property: ColorProp;
}

interface FindOptions {
  /** Wrapper selectors to scope to; empty/undefined = whole page. */
  scopes?: string[];
  isAllowed?: (selector: string, property: ColorProp) => boolean;
}

/** Is `sel` in scope for the given wrapper list (empty list = whole page)? */
function inScope(sel: string, scopes: string[]): boolean {
  if (scopes.length === 0) return true;
  return scopes.some((s) => sel === s || sel.startsWith(s + " "));
}

/**
 * Analyze parsed rules for base color declarations that lack a paired
 * `html.light-mode <same-selector>` override of the SAME property.
 *
 * For each individual selector in each rule:
 *   - `html.light-mode …`  → record its props under the stripped key (if in scope).
 *   - non-light selector    → record its props under that key (if in scope).
 * Then every in-scope (baseSelector, property) must appear in the light map too.
 */
export function findMissingPairs(rules: CssRule[], options: FindOptions = {}): MissingPair[] {
  const scopes = options.scopes ?? [];
  const isAllowed = options.isAllowed ?? (() => false);
  const base = new Map<string, Set<ColorProp>>();
  const light = new Map<string, Set<ColorProp>>();

  const add = (map: Map<string, Set<ColorProp>>, key: string, props: Set<ColorProp>) => {
    const set = map.get(key) ?? new Set<ColorProp>();
    for (const p of props) set.add(p);
    map.set(key, set);
  };

  for (const rule of rules) {
    for (const sel of rule.selectors) {
      if (sel.startsWith(LIGHT_PREFIX)) {
        const stripped = sel.slice(LIGHT_PREFIX.length).trim();
        if (inScope(stripped, scopes)) add(light, stripped, rule.props);
      } else if (inScope(sel, scopes)) {
        add(base, sel, rule.props);
      }
    }
  }

  const missing: MissingPair[] = [];
  for (const [sel, props] of [...base.entries()].sort((a, b) => a[0].localeCompare(b[0]))) {
    const lightProps = light.get(sel);
    for (const prop of COLOR_PROPS) {
      if (!props.has(prop)) continue;
      if (isAllowed(sel, prop)) continue;
      if (!lightProps || !lightProps.has(prop)) {
        missing.push({ selector: sel, property: prop });
      }
    }
  }
  return missing;
}

/** Full pipeline over a blade source string for one target's options. */
export function checkSource(src: string, options: FindOptions = {}): MissingPair[] {
  return findMissingPairs(parseRules(extractStyleBlocks(src)), options);
}

export interface TargetResult {
  target: Target;
  missing: MissingPair[];
  error?: string;
}

/** Read + check a single configured target. */
export function checkTarget(target: Target): TargetResult {
  const abs = path.join(REPO_ROOT, target.page);
  let src: string;
  try {
    src = fs.readFileSync(abs, "utf8");
  } catch (e) {
    return { target, missing: [], error: (e as Error).message };
  }
  const missing = checkSource(src, {
    scopes: target.scopes,
    isAllowed: makeIsAllowed(target.allowlist),
  });
  return { target, missing };
}

function describeMode(t: Target): string {
  return t.scopes && t.scopes.length ? `scoped to ${t.scopes.join(", ")}` : "whole page";
}

function printExplain(): void {
  console.log("Light-mode pairing completeness guard\n");
  console.log(
    `Rule:  every base color rule (${COLOR_PROPS.map((p) => `"${p}"`).join(" / ")}) on a`,
  );
  console.log(`       checked page must have a paired "${LIGHT_PREFIX}<same-selector>" rule`);
  console.log("       for the SAME property, or that element keeps its dark color and");
  console.log("       washes out on the white light-mode card.\n");
  console.log("Checked pages:");
  for (const t of TARGETS) {
    console.log(`  • ${t.label} — ${t.page} (${describeMode(t)})`);
    for (const a of t.allowlist) {
      console.log(`      allow: ${a.selector} { ${a.property} } — ${a.reason}`);
    }
  }
  console.log("\nAdd a page: append a { page, label, allowlist } entry to TARGETS in");
  console.log("  scripts/src/check-light-mode-pairing.ts (add `scopes` if the page also");
  console.log("  has intentional always-dark islands outside the re-themed region).");
}

function main(): void {
  if (process.argv.includes("--explain")) {
    printExplain();
    process.exit(0);
  }

  const results = TARGETS.map(checkTarget);
  const readErrors = results.filter((r) => r.error);
  if (readErrors.length) {
    for (const r of readErrors) {
      console.error(`✗ light-mode-pairing guard FAILED — cannot read ${r.target.page}: ${r.error}`);
    }
    process.exit(2);
  }

  const failed = results.filter((r) => r.missing.length > 0);
  if (failed.length === 0) {
    console.log(
      `✓ light-mode-pairing guard passed — every base color rule across ${TARGETS.length} checked page(s) has its paired "${LIGHT_PREFIX}" override.`,
    );
    process.exit(0);
  }

  console.error("✗ light-mode-pairing guard FAILED — base color rule(s) missing a light-mode override:\n");
  for (const r of failed) {
    console.error(`  ${r.target.label} (${r.target.page}):`);
    for (const m of r.missing) {
      console.error(`    ${m.selector} { ${m.property} }`);
      console.error(`        add:  ${LIGHT_PREFIX}${m.selector} { ${m.property}: <light value>; }`);
    }
  }
  console.error(
    `\nThe rule(s) above set a dark-theme ${COLOR_PROPS.join("/")} with no paired ` +
      `"${LIGHT_PREFIX}" override — they will wash out on the white light-mode card.`,
  );
  console.error(
    "Add the matching html.light-mode override, or (if genuinely theme-neutral) add the selector+property to that page's `allowlist` in scripts/src/check-light-mode-pairing.ts with a reason.",
  );
  console.error("Run `pnpm --filter @workspace/scripts run check:light-mode-pairing -- --explain` for details.");
  process.exit(1);
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
