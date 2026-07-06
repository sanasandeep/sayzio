/**
 * Event-page light-mode completeness guard.
 *
 * The public event page
 *   artifacts/1inme/resources/views/common/event-page.blade.php
 * re-themes a shared, Bootstrap-styled partial (event-rich-content) for its
 * Tailwind dark-glass theme by scoping overrides under a `.ev-rich` wrapper,
 * and then provides `html.light-mode .ev-rich …` counterparts so the same
 * markup reads correctly on the WHITE light-mode card.
 *
 * The invariant (documented in
 * .agents/memory/event-page-shared-rich-content-theming.md):
 *
 *   Every base `.ev-rich <selector>` rule that sets a text/border COLOR
 *   (`color` / `border-color`) MUST have a paired
 *   `html.light-mode .ev-rich <selector>` rule that sets the SAME property —
 *   otherwise that element keeps its DARK-theme color on the white light-mode
 *   card and washes out (illegible / near-invisible).
 *
 * This has already regressed twice by hand (the tips/pairings sections, then
 * the "Interested" `.btn-outline-success` button): a base color rule shipped
 * without its `html.light-mode` counterpart and nothing caught it. This guard
 * closes that gap — it parses the page's `<style>` block, collects every base
 * `.ev-rich` color declaration, and fails (exit 1) if any lacks its paired
 * light-mode override for the same property.
 *
 * Scope
 * -----
 * Only `.ev-rich`-scoped rules are checked (that is the shared-partial re-theme
 * surface described in the task). The other inline-styled partials
 * (`.ev-connection-tips`, `.ltp-pairings`) bake their dark colors as inline
 * styles inside the partials themselves — there is no base rule in this
 * `<style>` block to pair against — so they are intentionally out of scope
 * here (their light overrides live in the same block but have no base peer).
 *
 * Run:  pnpm --filter @workspace/scripts run check:event-light-mode
 *       (add `--explain` to print what is checked and exit 0)
 */

import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

/** The single page this guard protects. */
export const EVENT_PAGE = "artifacts/1inme/resources/views/common/event-page.blade.php";

/** The `html.light-mode ` selector prefix a light-mode override carries. */
const LIGHT_PREFIX = "html.light-mode ";

/** The wrapper class every checked rule is scoped under. */
const SCOPE = ".ev-rich";

/**
 * The color-carrying properties whose dark value washes out on the white
 * light-mode card if left un-paired. `background` is deliberately NOT here: a
 * dark background block on the light card is not the washed-out-TEXT failure
 * this guard targets, and several `.ev-rich` rules set only a subtle
 * translucent background that reads fine in both themes.
 */
export const COLOR_PROPS = ["color", "border-color"] as const;
type ColorProp = (typeof COLOR_PROPS)[number];

type AllowEntry = { selector: string; property: ColorProp; reason: string };

/**
 * Selector+property pairs that intentionally have no light-mode counterpart
 * (e.g. a theme-neutral color that reads correctly in both modes). Empty today
 * — every base `.ev-rich` color rule currently has its pair. Documented here so
 * a future intentional exception is recorded with a reason instead of weakening
 * the parser.
 */
const ALLOWLIST: AllowEntry[] = [];

function isAllowed(selector: string, property: ColorProp): boolean {
  return ALLOWLIST.some((e) => e.selector === selector && e.property === property);
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
 * declares. Comments are stripped first. Robust enough for this hand-written
 * `<style>` block (no nested at-rules are used here).
 */
export function parseRules(css: string): CssRule[] {
  const clean = stripCssComments(css);
  const rules: CssRule[] = [];
  const re = /([^{}]+)\{([^{}]*)\}/g;
  let m: RegExpExecArray | null;
  while ((m = re.exec(clean)) !== null) {
    const selectorList = (m[1] ?? "").trim();
    if (!selectorList) continue;
    const selectors = selectorList
      .split(",")
      .map(normalizeSelector)
      .filter(Boolean);

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
  /** The `.ev-rich …` selector (light-prefix stripped for base comparison). */
  selector: string;
  property: ColorProp;
}

/**
 * Analyze the parsed rules for base `.ev-rich` color declarations that lack a
 * paired `html.light-mode .ev-rich` override of the SAME property.
 *
 * For each individual selector in each rule:
 *   - `html.light-mode .ev-rich …`  → record its props under the stripped key.
 *   - `.ev-rich …` (not light)       → record its props under that key.
 * Then every (baseSelector, property) must appear in the light map too.
 */
export function findMissingPairs(rules: CssRule[]): MissingPair[] {
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
        if (stripped === SCOPE || stripped.startsWith(SCOPE + " ")) {
          add(light, stripped, rule.props);
        }
      } else if (sel === SCOPE || sel.startsWith(SCOPE + " ")) {
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

/** Full pipeline over a blade source string (exported for tests). */
export function checkSource(src: string): MissingPair[] {
  return findMissingPairs(parseRules(extractStyleBlocks(src)));
}

function printExplain(): void {
  console.log("Event-page light-mode completeness guard\n");
  console.log(`Page:  ${EVENT_PAGE}`);
  console.log(`Scope: rules scoped under "${SCOPE}" in the page's <style> block.`);
  console.log(`Rule:  every base "${SCOPE} <selector>" rule that sets`);
  console.log(`       ${COLOR_PROPS.map((p) => `"${p}"`).join(" or ")} must have a paired`);
  console.log(`       "${LIGHT_PREFIX}${SCOPE} <selector>" rule setting the SAME property,`);
  console.log("       or that element keeps its dark color and washes out on the white");
  console.log("       light-mode card.");
  console.log("\nOut of scope (no base rule in this <style> block to pair against):");
  console.log("  • .ev-connection-tips / .ltp-pairings — dark colors are inline styles");
  console.log("    baked into the shared partials; only their light overrides live here.");
  if (ALLOWLIST.length) {
    console.log("\nAllow-listed (intentionally un-paired):");
    for (const e of ALLOWLIST) console.log(`  • ${e.selector} { ${e.property} } — ${e.reason}`);
  }
}

function main(): void {
  if (process.argv.includes("--explain")) {
    printExplain();
    process.exit(0);
  }

  const abs = path.join(REPO_ROOT, EVENT_PAGE);
  let src: string;
  try {
    src = fs.readFileSync(abs, "utf8");
  } catch (e) {
    console.error(`✗ event-light-mode guard FAILED — cannot read ${EVENT_PAGE}: ${(e as Error).message}`);
    process.exit(2);
  }

  const missing = checkSource(src);

  if (missing.length === 0) {
    console.log(
      `✓ event-light-mode guard passed — every base "${SCOPE}" color rule has its paired "${LIGHT_PREFIX}${SCOPE}" override.`,
    );
    process.exit(0);
  }

  console.error("✗ event-light-mode guard FAILED — base color rule(s) missing a light-mode override:\n");
  for (const m of missing) {
    console.error(`  ${m.selector} { ${m.property} }`);
    console.error(`      add:  ${LIGHT_PREFIX}${m.selector} { ${m.property}: <light value>; }`);
  }
  console.error(
    `\n${missing.length} rule(s) in ${EVENT_PAGE} set a dark-theme ${COLOR_PROPS.join("/")} with no paired ` +
      `"${LIGHT_PREFIX}${SCOPE}" override — they will wash out on the white light-mode card.`,
  );
  console.error(
    "Add the matching html.light-mode override, or (if genuinely theme-neutral) add the selector+property to ALLOWLIST in scripts/src/check-event-page-light-mode.ts with a reason.",
  );
  console.error(
    "Run `pnpm --filter @workspace/scripts run check:event-light-mode -- --explain` for details.",
  );
  process.exit(1);
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
