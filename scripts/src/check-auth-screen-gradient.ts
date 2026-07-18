/**
 * Auth-screen branded-background guard.
 *
 * Fails (exit 1) when any mobile auth-flow screen stops rendering the shared,
 * theme-aware brand gradient wash — or ships a flat / hardcoded background
 * instead.
 *
 * Why this exists
 * ---------------
 * The mobile auth funnel — login (`app/(auth)/index.tsx`), OTP verify
 * (`app/(auth)/verify.tsx`), the OAuth return screen (`app/oauth-callback.tsx`)
 * and the cancel-sensitive-change deep link (`app/(auth)/cancel-change.tsx`) —
 * all share one visual treatment: a diagonal `LinearGradient` built from the
 * theme-aware `colors.brandGradient` tokens, tinted with the shared bgAlpha
 * treatment (0x40 in dark mode, 0x2e in light mode). Until now that
 * consistency was only enforced by manual review: a future edit could silently
 * drop the gradient, hardcode a flat colour, or lose the dark/light opacity
 * treatment on one screen and no test would catch it.
 *
 * What it checks
 * -------------
 * For every auth-flow screen it asserts the source contains all of:
 *   - a `<LinearGradient>` element;
 *   - a wash derived from `colors.brandGradient.map(...)`;
 *   - the shared bgAlpha treatment `colors.scheme === "dark" ? "40" : "2e"`;
 *   - each stop tinted with that alpha (`` `${c}${bgAlpha}` ``);
 *   - a diagonal gradient (`start` at top-left {x:0,y:0}, `end` at
 *     bottom-right {x:1,y:1}).
 * Any screen missing one of these — e.g. one that swaps the wash for a
 * hardcoded `colors={["#0b0e1a", ...]}` background — is reported as a drift.
 *
 * Run:  pnpm --filter @workspace/scripts run check:auth-screen-gradient
 */

import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

/** Every auth-flow screen that must keep the shared branded background. */
export const AUTH_SCREENS = [
  "artifacts/1inme-mobile/app/(auth)/index.tsx",
  "artifacts/1inme-mobile/app/(auth)/verify.tsx",
  "artifacts/1inme-mobile/app/(auth)/cancel-change.tsx",
  "artifacts/1inme-mobile/app/oauth-callback.tsx",
] as const;

export type Requirement = {
  /** Short id used in failure messages. */
  id: string;
  /** Human-readable description of what must be present. */
  label: string;
  /** True when the source satisfies the requirement. */
  test: (src: string) => boolean;
};

/**
 * The five things that together make up the shared branded background. Each is
 * matched leniently on whitespace so reformatting (prettier, line wraps) does
 * not produce false failures, but the semantic shape is pinned.
 */
export const REQUIREMENTS: Requirement[] = [
  {
    id: "linear-gradient",
    label: "renders a <LinearGradient> element",
    test: (src) => /<LinearGradient\b/.test(src),
  },
  {
    id: "brand-gradient-wash",
    label: "derives the wash from colors.brandGradient.map(...)",
    test: (src) => /colors\.brandGradient\.map\s*\(/.test(src),
  },
  {
    id: "bg-alpha-treatment",
    label:
      'uses the shared bgAlpha treatment (colors.scheme === "dark" ? "40" : "2e")',
    test: (src) =>
      /\.scheme\s*===\s*["']dark["']\s*\?\s*["']40["']\s*:\s*["']2e["']/.test(
        src,
      ),
  },
  {
    id: "alpha-applied",
    label: "tints each brand stop with the bgAlpha value (`${c}${bgAlpha}`)",
    test: (src) => /\$\{c\}\$\{bgAlpha\}/.test(src),
  },
  {
    id: "diagonal-start",
    label: "starts the gradient at the top-left corner (x:0, y:0)",
    test: (src) =>
      /start=\{\{\s*x:\s*0(?:\.0)?\s*,\s*y:\s*0(?:\.0)?\s*,?\s*\}\}/.test(src),
  },
  {
    id: "diagonal-end",
    label: "ends the gradient at the bottom-right corner (x:1, y:1)",
    test: (src) =>
      /end=\{\{\s*x:\s*1(?:\.0)?\s*,\s*y:\s*1(?:\.0)?\s*,?\s*\}\}/.test(src),
  },
];

/**
 * Pure analysis: return the ids of every requirement the source is missing. An
 * empty array means the screen keeps the full shared branded background.
 */
export function missingRequirements(src: string): string[] {
  return REQUIREMENTS.filter((r) => !r.test(src)).map((r) => r.id);
}

const labelFor = (id: string): string =>
  REQUIREMENTS.find((r) => r.id === id)?.label ?? id;

export type ScreenResult = { file: string; missing: string[] };

/** Analyse every auth screen using the provided file reader. */
export function analyzeScreens(
  read: (file: string) => string,
  files: readonly string[] = AUTH_SCREENS,
): ScreenResult[] {
  return files.map((file) => ({ file, missing: missingRequirements(read(file)) }));
}

function main(): void {
  const read = (file: string): string =>
    fs.readFileSync(path.join(REPO_ROOT, file), "utf8");

  let results: ScreenResult[];
  try {
    results = analyzeScreens(read);
  } catch (e) {
    console.error(
      `✗ auth-screen-gradient guard: cannot read a screen: ${(e as Error).message}`,
    );
    process.exit(2);
  }

  const broken = results.filter((r) => r.missing.length > 0);
  if (broken.length === 0) {
    console.log(
      `✓ auth-screen-gradient guard passed — all ${results.length} auth-flow ` +
        "screens keep the shared theme-aware brand gradient wash.",
    );
    process.exit(0);
  }

  console.error(
    "✗ auth-screen-gradient guard FAILED — one or more auth-flow screens " +
      "dropped the shared branded background:\n",
  );
  for (const r of broken) {
    console.error(`  ${r.file}`);
    for (const id of r.missing) {
      console.error(`    - missing: ${labelFor(id)}`);
    }
    console.error("");
  }
  console.error(
    "Fix: restore the shared wash on the screen above. Compute\n" +
      '  const bgAlpha = colors.scheme === "dark" ? "40" : "2e";\n' +
      "  const bgGradientColors = colors.brandGradient.map((c) => `${c}${bgAlpha}`) ...;\n" +
      "and render <LinearGradient colors={bgGradientColors} start={{ x: 0.0, y: 0.0 }} " +
      "end={{ x: 1.0, y: 1.0 }} style={StyleSheet.absoluteFill} />. Do not ship a " +
      "flat or hardcoded background on an auth screen.",
  );
  process.exit(1);
}

// Only run when invoked directly (helpers above are imported by the test suite).
if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
