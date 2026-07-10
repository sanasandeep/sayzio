/**
 * Mobile sign-in provider-list lockstep guard.
 *
 * Fails (exit 1) when the social-provider lists hardcoded in the mobile
 * auth-flow e2e test drift out of sync with what the login screen actually
 * renders.
 *
 * Why this exists
 * ---------------
 * The mobile auth-flow e2e (`artifacts/1inme-mobile/scripts/test-auth-flow-e2e.mjs`)
 * hardcodes the social-provider list twice — `REQUIRED_SOCIAL_LABELS` (the
 * "Log in with {label}" buttons that must be tappable) and `WEB_OAUTH_PROVIDERS`
 * (the {id, label} pairs it drives end to end). Both must exactly mirror the
 * app's login screen (`app/(auth)/index.tsx`): its `SOCIALS` catalog filtered by
 * `WEB_BROWSER_PROVIDERS`, rendered with the `accessibilityLabel`
 * "Log in with {label}".
 *
 * When these drift, coverage silently rots: the test has already shipped a
 * deterministic RED once (expecting a different count than the app rendered),
 * and — worse — later assertions only run if the earlier button-list assertion
 * passes (e.g. Google's web-fallback round-trip), so a stale list can hide real
 * regressions instead of catching them. Nothing in the test itself notices the
 * two lists diverging. This guard makes that drift a loud, fast CI failure.
 *
 * What it checks
 * -------------
 * From the app it derives the ordered web-browser providers = every `SOCIALS`
 * entry whose `id` is in `WEB_BROWSER_PROVIDERS`, each with label
 * `${accessibilityLabelPrefix}${social.label}` (e.g. "Log in with Google").
 * Then it asserts:
 *   - the test's `WEB_OAUTH_PROVIDERS` deep-equals the app's derived list
 *     (same ids, labels AND order);
 *   - the test's `REQUIRED_SOCIAL_LABELS` (as a set) equals the app's derived
 *     labels — the always-rendered web providers are exactly the minimum set
 *     the test requires to be tappable.
 *
 * Run:  pnpm --filter @workspace/scripts run check:mobile-auth-providers
 */

import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

/** The login screen that is the single source of truth (relative to repo root). */
export const APP_FILE = "artifacts/1inme-mobile/app/(auth)/index.tsx";
/** The e2e test whose provider lists must mirror the app (relative to repo root). */
export const TEST_FILE = "artifacts/1inme-mobile/scripts/test-auth-flow-e2e.mjs";

export type Provider = { id: string; label: string };

export type AppProviders = {
  /** Full SOCIALS catalog (id -> visible label, before the "Log in with" prefix). */
  socials: Provider[];
  /** ids in WEB_BROWSER_PROVIDERS. */
  webBrowserIds: string[];
  /** The accessibilityLabel prefix, e.g. "Log in with ". */
  labelPrefix: string;
  /** Derived, ordered web providers: {id, "Log in with {label}"}. */
  webProviders: Provider[];
};

export type TestProviders = {
  required: string[];
  optional: string[];
  webOauth: Provider[];
};

/** Extract every "id"/"label" pair, in order, from an object-array body. */
function extractIdLabelPairs(body: string): Provider[] {
  const out: Provider[] = [];
  const re = /\bid:\s*["']([^"']+)["']\s*,\s*label:\s*["']([^"']+)["']/g;
  for (const m of body.matchAll(re)) out.push({ id: m[1]!, label: m[2]! });
  return out;
}

/** Extract every quoted string literal, in order, from a fragment. */
function extractStringLiterals(body: string): string[] {
  const out: string[] = [];
  for (const m of body.matchAll(/["']([^"']*)["']/g)) out.push(m[1]!);
  return out;
}

/**
 * Parse the app login screen. Throws with a clear message if a required
 * declaration can't be found (a structural change the guard must not silently
 * pass through).
 */
export function parseAppProviders(src: string): AppProviders {
  const socialsMatch = src.match(/const\s+SOCIALS\b[\s\S]*?=\s*\[([\s\S]*?)\];/);
  if (!socialsMatch) {
    throw new Error(
      `could not find the SOCIALS array in ${APP_FILE} — the guard's parser ` +
        `needs updating to match a refactor of the login screen`,
    );
  }
  const socials = extractIdLabelPairs(socialsMatch[1]!);
  if (socials.length === 0) {
    throw new Error(`SOCIALS in ${APP_FILE} parsed to zero providers`);
  }

  const setMatch = src.match(
    /const\s+WEB_BROWSER_PROVIDERS\b[\s\S]*?=\s*new Set<[^>]*>\(\s*\[([\s\S]*?)\]\s*\)/,
  );
  if (!setMatch) {
    throw new Error(
      `could not find WEB_BROWSER_PROVIDERS in ${APP_FILE} — the guard's ` +
        `parser needs updating`,
    );
  }
  const webBrowserIds = extractStringLiterals(setMatch[1]!);
  if (webBrowserIds.length === 0) {
    throw new Error(`WEB_BROWSER_PROVIDERS in ${APP_FILE} parsed to zero ids`);
  }

  const labelMatch = src.match(
    /accessibilityLabel=\{`([^`$]*)\$\{s\.label\}`\}/,
  );
  if (!labelMatch) {
    throw new Error(
      `could not find the \`Log in with \${s.label}\` accessibilityLabel in ` +
        `${APP_FILE} — the guard's parser needs updating`,
    );
  }
  const labelPrefix = labelMatch[1]!;

  const socialById = new Map(socials.map((s) => [s.id, s]));
  const webProviders: Provider[] = [];
  for (const id of webBrowserIds) {
    const social = socialById.get(id);
    if (!social) {
      throw new Error(
        `WEB_BROWSER_PROVIDERS lists "${id}" but there is no SOCIALS entry ` +
          `with that id in ${APP_FILE}`,
      );
    }
    webProviders.push({ id, label: `${labelPrefix}${social.label}` });
  }

  return { socials, webBrowserIds, labelPrefix, webProviders };
}

/** Parse the e2e test's hardcoded provider lists. */
export function parseTestProviders(src: string): TestProviders {
  const requiredMatch = src.match(
    /const\s+REQUIRED_SOCIAL_LABELS\s*=\s*\[([\s\S]*?)\]/,
  );
  if (!requiredMatch) {
    throw new Error(`could not find REQUIRED_SOCIAL_LABELS in ${TEST_FILE}`);
  }
  const required = extractStringLiterals(requiredMatch[1]!);

  const optionalMatch = src.match(
    /const\s+OPTIONAL_SOCIAL_LABELS\s*=\s*\[([\s\S]*?)\]/,
  );
  const optional = optionalMatch
    ? extractStringLiterals(optionalMatch[1]!)
    : [];

  const webOauthMatch = src.match(
    /const\s+WEB_OAUTH_PROVIDERS\s*=\s*\[([\s\S]*?)\];/,
  );
  if (!webOauthMatch) {
    throw new Error(`could not find WEB_OAUTH_PROVIDERS in ${TEST_FILE}`);
  }
  const webOauth = extractIdLabelPairs(webOauthMatch[1]!);

  return { required, optional, webOauth };
}

const asKeys = (ps: Provider[]): string[] =>
  ps.map((p) => `${p.id}=${p.label}`);

/**
 * Pure comparison: return every drift as a human-readable message. An empty
 * array means the test is in lockstep with the app.
 */
export function diffProviders(
  app: AppProviders,
  test: TestProviders,
): string[] {
  const errors: string[] = [];

  // WEB_OAUTH_PROVIDERS must match the app's derived list exactly, in order.
  const appKeys = asKeys(app.webProviders);
  const testKeys = asKeys(test.webOauth);
  if (JSON.stringify(appKeys) !== JSON.stringify(testKeys)) {
    errors.push(
      "WEB_OAUTH_PROVIDERS drifted from the login screen.\n" +
        `    app renders (id=label, in order): ${appKeys.join(", ")}\n` +
        `    test WEB_OAUTH_PROVIDERS:          ${testKeys.join(", ")}`,
    );
  }

  // REQUIRED_SOCIAL_LABELS (order-independent) must equal the app's derived
  // web-provider labels: exactly the always-rendered buttons.
  const appLabels = [...app.webProviders.map((p) => p.label)].sort();
  const testRequired = [...test.required].sort();
  if (JSON.stringify(appLabels) !== JSON.stringify(testRequired)) {
    errors.push(
      "REQUIRED_SOCIAL_LABELS drifted from the login screen.\n" +
        `    app renders: ${appLabels.join(", ")}\n` +
        `    test REQUIRED_SOCIAL_LABELS: ${testRequired.join(", ")}`,
    );
  }

  return errors;
}

function main(): void {
  let appSrc: string;
  let testSrc: string;
  try {
    appSrc = fs.readFileSync(path.join(REPO_ROOT, APP_FILE), "utf8");
  } catch (e) {
    console.error(
      `✗ mobile-auth-providers guard: cannot read ${APP_FILE}: ${(e as Error).message}`,
    );
    process.exit(2);
  }
  try {
    testSrc = fs.readFileSync(path.join(REPO_ROOT, TEST_FILE), "utf8");
  } catch (e) {
    console.error(
      `✗ mobile-auth-providers guard: cannot read ${TEST_FILE}: ${(e as Error).message}`,
    );
    process.exit(2);
  }

  let app: AppProviders;
  let test: TestProviders;
  try {
    app = parseAppProviders(appSrc);
    test = parseTestProviders(testSrc);
  } catch (e) {
    console.error(`✗ mobile-auth-providers guard FAILED: ${(e as Error).message}`);
    process.exit(1);
  }

  const errors = diffProviders(app, test);
  if (errors.length === 0) {
    console.log(
      `✓ mobile-auth-providers guard passed — the auth-flow e2e mirrors the ` +
        `${app.webProviders.length} web-browser provider(s) rendered by the login screen.`,
    );
    process.exit(0);
  }

  console.error(
    "✗ mobile-auth-providers guard FAILED — the auth-flow e2e provider lists " +
      "have drifted from the login screen:\n",
  );
  for (const err of errors) console.error(`  - ${err}\n`);
  console.error(
    `Fix: update REQUIRED_SOCIAL_LABELS / WEB_OAUTH_PROVIDERS in ${TEST_FILE} ` +
      `to mirror SOCIALS (filtered by WEB_BROWSER_PROVIDERS) in ${APP_FILE}. ` +
      "A stale list makes the test's button-list assertion RED, or worse, " +
      "silently skips the provider round-trips gated behind it.",
  );
  process.exit(1);
}

// Only run when invoked directly (helpers above are imported by the test suite).
if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
