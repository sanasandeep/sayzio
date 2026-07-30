/**
 * CTA gradient drift guard.
 *
 * Fails (exit 1) when the highlight CTA gradient defined in the web CSS
 * (`.btn-cta` in artifacts/1inme/public/css/marketing-anim.css) and the mobile
 * theme (`ctaGradient` in artifacts/1inme-mobile/constants/colors.ts) no
 * longer correspond. Today the only thing keeping the two matching is a code
 * comment; this guard parses BOTH sources and asserts the color stops line up,
 * the same way the brand-color guard mechanically enforces the retired-purple
 * ban.
 *
 * What "correspond" means
 * -----------------------
 * The web `.btn-cta` gradient may carry extra intermediate stops (it does — a
 * darker lead-in blue), but the mobile LIGHT-mode `ctaGradient` stops must all
 * appear in the web gradient, in the same order (an ordered subsequence).
 * The mobile DARK-mode `ctaGradient` is a lighter-tint variant, so it is not
 * compared hex-for-hex against the web; instead it must keep the same number
 * of stops as the light variant so the two mobile themes can't drift apart
 * structurally.
 *
 * Run:  pnpm --filter @workspace/scripts run check:cta-gradient
 */

import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

export const WEB_CSS_FILE = "artifacts/1inme/public/css/marketing-anim.css";
export const MOBILE_COLORS_FILE = "artifacts/1inme-mobile/constants/colors.ts";

/** Normalize a hex color to lowercase 6-digit `#rrggbb` (expands #abc). */
export function normalizeHex(hex: string): string {
  let h = hex.trim().toLowerCase();
  if (!h.startsWith("#")) h = `#${h}`;
  if (/^#[0-9a-f]{3}$/.test(h)) {
    h = `#${h[1]}${h[1]}${h[2]}${h[2]}${h[3]}${h[3]}`;
  }
  return h;
}

/**
 * Extract the ordered hex color stops of the `.btn-cta` linear-gradient from
 * the web stylesheet source. Throws when the rule or gradient can't be found —
 * a silently-passing guard would be worse than a loud failure.
 */
export function parseWebCtaStops(css: string): string[] {
  const rule = css.match(/^\s*\.btn-cta\s*\{([^}]*)\}/m);
  if (!rule) throw new Error(`no .btn-cta rule found in ${WEB_CSS_FILE}`);
  const gradient = rule[1].match(/linear-gradient\s*\(([^)]*)\)/);
  if (!gradient) throw new Error(".btn-cta rule has no linear-gradient()");
  const stops = gradient[1].match(/#[0-9a-fA-F]{3,8}\b/g) ?? [];
  if (stops.length < 2) {
    throw new Error(".btn-cta linear-gradient has fewer than 2 hex stops");
  }
  return stops.map(normalizeHex);
}

export type MobileCtaGradients = { light: string[]; dark: string[] };

/**
 * Extract the resolved hex stops of the light and dark `ctaGradient` arrays
 * from the mobile colors module source. Resolves `brand.xyz` references via
 * the `const brand = { ... }` map in the same file; literal hex strings inside
 * the arrays are also accepted.
 */
export function parseMobileCtaGradients(src: string): MobileCtaGradients {
  const brandBlock = src.match(/const\s+brand\s*=\s*\{([\s\S]*?)\}/);
  if (!brandBlock) throw new Error(`no \`const brand = {...}\` map found in ${MOBILE_COLORS_FILE}`);
  const brand = new Map<string, string>();
  for (const m of brandBlock[1].matchAll(/(\w+)\s*:\s*["'](#[0-9a-fA-F]{3,8})["']/g)) {
    brand.set(m[1], normalizeHex(m[2]));
  }

  const gradients: string[][] = [];
  for (const m of src.matchAll(/ctaGradient\s*:\s*\[([^\]]*)\]/g)) {
    const stops: string[] = [];
    for (const tok of m[1].matchAll(/brand\.(\w+)|["'](#[0-9a-fA-F]{3,8})["']/g)) {
      if (tok[1]) {
        const hex = brand.get(tok[1]);
        if (!hex) throw new Error(`ctaGradient references unknown brand token: brand.${tok[1]}`);
        stops.push(hex);
      } else {
        stops.push(normalizeHex(tok[2]));
      }
    }
    if (stops.length < 2) throw new Error("a ctaGradient array has fewer than 2 stops");
    gradients.push(stops);
  }
  if (gradients.length !== 2) {
    throw new Error(
      `expected exactly 2 ctaGradient definitions (light + dark) in ${MOBILE_COLORS_FILE}, found ${gradients.length}`,
    );
  }
  return { light: gradients[0], dark: gradients[1] };
}

/** True when `needle` appears within `haystack` as an ordered subsequence. */
export function isOrderedSubsequence(needle: string[], haystack: string[]): boolean {
  let i = 0;
  for (const h of haystack) {
    if (i < needle.length && h === needle[i]) i++;
  }
  return i === needle.length;
}

export type DriftResult = {
  webStops: string[];
  mobile: MobileCtaGradients;
  problems: string[];
};

/** Pure comparison: returns human-readable drift problems (empty = in sync). */
export function findDrift(css: string, colorsSrc: string): DriftResult {
  const webStops = parseWebCtaStops(css);
  const mobile = parseMobileCtaGradients(colorsSrc);
  const problems: string[] = [];

  if (!isOrderedSubsequence(mobile.light, webStops)) {
    problems.push(
      `mobile light ctaGradient [${mobile.light.join(", ")}] is not an ordered ` +
        `subsequence of the web .btn-cta stops [${webStops.join(", ")}]`,
    );
  }
  if (mobile.dark.length !== mobile.light.length) {
    problems.push(
      `mobile dark ctaGradient has ${mobile.dark.length} stops but light has ` +
        `${mobile.light.length} — the two themes must keep the same structure`,
    );
  }
  return { webStops, mobile, problems };
}

function main(): void {
  let result: DriftResult;
  try {
    result = findDrift(
      fs.readFileSync(path.join(REPO_ROOT, WEB_CSS_FILE), "utf8"),
      fs.readFileSync(path.join(REPO_ROOT, MOBILE_COLORS_FILE), "utf8"),
    );
  } catch (e) {
    console.error(`✗ cta-gradient guard: cannot analyze sources: ${(e as Error).message}`);
    process.exit(2);
  }

  if (result.problems.length === 0) {
    console.log(
      `✓ cta-gradient guard passed — web .btn-cta [${result.webStops.join(", ")}] and ` +
        `mobile ctaGradient (light [${result.mobile.light.join(", ")}]) correspond.`,
    );
    process.exit(0);
  }

  console.error("✗ cta-gradient guard FAILED — the highlight CTA gradient drifted between web and mobile:\n");
  for (const p of result.problems) console.error(`  - ${p}`);
  console.error(
    `\nFix: keep the .btn-cta gradient in ${WEB_CSS_FILE} and the ctaGradient\n` +
      `stops in ${MOBILE_COLORS_FILE} in lockstep. Every light-mode mobile stop\n` +
      "must appear in the web gradient, in the same order; the dark-mode variant\n" +
      "keeps the same number of stops (as lighter tints of the same hues).",
  );
  process.exit(1);
}

// Only run when invoked directly (helpers above are imported by the test suite).
if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
