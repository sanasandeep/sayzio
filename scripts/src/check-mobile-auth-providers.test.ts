import { describe, it, expect } from "vitest";
import fs from "node:fs";
import path from "node:path";
import {
  parseAppProviders,
  parseTestProviders,
  diffProviders,
  APP_FILE,
  TEST_FILE,
  REPO_ROOT,
} from "./check-mobile-auth-providers.js";

/**
 * Regression suite for the mobile-auth-providers lockstep guard.
 *
 * The guard fails when the auth-flow e2e's hardcoded provider lists
 * (REQUIRED_SOCIAL_LABELS / WEB_OAUTH_PROVIDERS) drift from the login screen's
 * SOCIALS + WEB_BROWSER_PROVIDERS. See task #4314 and
 * .agents/memory/e2e-mobile-main-social-button-staleness.md.
 *
 * Run: pnpm --filter @workspace/scripts run test
 */

const APP = `
type SocialProvider = "google" | "linkedin";
const SOCIALS: {
  id: SocialProvider;
  label: string;
  icon: string;
  color: string;
}[] = [
  { id: "google", label: "Google", icon: "logo-google", color: "#ea4335" },
  { id: "linkedin", label: "LinkedIn", icon: "logo-linkedin", color: "#0a66c2" },
];
const WEB_BROWSER_PROVIDERS = new Set<SocialProvider>(["google", "linkedin"]);
// ...
        accessibilityLabel={\`Log in with \${s.label}\`}
`;

const TEST = `
const REQUIRED_SOCIAL_LABELS = [
  "Log in with Google",
  "Log in with LinkedIn",
];
const OPTIONAL_SOCIAL_LABELS = [];
const WEB_OAUTH_PROVIDERS = [
  { id: "google", label: "Log in with Google" },
  { id: "linkedin", label: "Log in with LinkedIn" },
];
`;

describe("parseAppProviders", () => {
  it("derives the ordered web-browser providers with the Log in with prefix", () => {
    const app = parseAppProviders(APP);
    expect(app.labelPrefix).toBe("Log in with ");
    expect(app.webProviders).toEqual([
      { id: "google", label: "Log in with Google" },
      { id: "linkedin", label: "Log in with LinkedIn" },
    ]);
  });

  it("excludes SOCIALS entries not in WEB_BROWSER_PROVIDERS", () => {
    const src = APP.replace(
      'new Set<SocialProvider>(["google", "linkedin"])',
      'new Set<SocialProvider>(["linkedin"])',
    );
    const app = parseAppProviders(src);
    expect(app.webProviders).toEqual([
      { id: "linkedin", label: "Log in with LinkedIn" },
    ]);
  });

  it("throws when SOCIALS is missing", () => {
    expect(() => parseAppProviders(WEB_BROWSER_ONLY)).toThrow(/SOCIALS/);
  });

  it("throws when WEB_BROWSER_PROVIDERS references an unknown id", () => {
    const src = APP.replace(
      'new Set<SocialProvider>(["google", "linkedin"])',
      'new Set<SocialProvider>(["google", "apple"])',
    );
    expect(() => parseAppProviders(src)).toThrow(/apple/);
  });
});

const WEB_BROWSER_ONLY = `
const WEB_BROWSER_PROVIDERS = new Set(["google"]);
accessibilityLabel={\`Log in with \${s.label}\`}
`;

describe("parseTestProviders", () => {
  it("reads REQUIRED_SOCIAL_LABELS and WEB_OAUTH_PROVIDERS", () => {
    const t = parseTestProviders(TEST);
    expect(t.required).toEqual(["Log in with Google", "Log in with LinkedIn"]);
    expect(t.optional).toEqual([]);
    expect(t.webOauth).toEqual([
      { id: "google", label: "Log in with Google" },
      { id: "linkedin", label: "Log in with LinkedIn" },
    ]);
  });
});

describe("diffProviders", () => {
  it("passes when app and test are in lockstep", () => {
    expect(diffProviders(parseAppProviders(APP), parseTestProviders(TEST))).toEqual([]);
  });

  it("flags a provider the app added but the test did not (the historic RED)", () => {
    const app = parseAppProviders(
      APP.replace(
        '{ id: "linkedin", label: "LinkedIn", icon: "logo-linkedin", color: "#0a66c2" },',
        '{ id: "linkedin", label: "LinkedIn", icon: "logo-linkedin", color: "#0a66c2" },\n  { id: "instagram", label: "Instagram", icon: "logo-instagram", color: "#e4405f" },',
      ).replace(
        'new Set<SocialProvider>(["google", "linkedin"])',
        'new Set<SocialProvider>(["google", "linkedin", "instagram"])',
      ),
    );
    const errors = diffProviders(app, parseTestProviders(TEST));
    expect(errors.length).toBe(2);
    expect(errors.join("\n")).toMatch(/WEB_OAUTH_PROVIDERS/);
    expect(errors.join("\n")).toMatch(/REQUIRED_SOCIAL_LABELS/);
    expect(errors.join("\n")).toMatch(/Instagram/);
  });

  it("flags a provider the test still lists but the app removed", () => {
    const test = parseTestProviders(TEST);
    const app = parseAppProviders(
      APP.replace(
        '  { id: "linkedin", label: "LinkedIn", icon: "logo-linkedin", color: "#0a66c2" },',
        "",
      ).replace(
        'new Set<SocialProvider>(["google", "linkedin"])',
        'new Set<SocialProvider>(["google"])',
      ),
    );
    const errors = diffProviders(app, test);
    expect(errors.length).toBe(2);
  });

  it("flags a relabelled provider", () => {
    const test = parseTestProviders(
      TEST.replace(/Log in with LinkedIn/g, "Log in with LinkedIn Inc."),
    );
    const errors = diffProviders(parseAppProviders(APP), test);
    expect(errors.length).toBe(2);
  });

  it("flags a changed accessibilityLabel prefix", () => {
    const app = parseAppProviders(APP.replace(/Log in with /g, "Sign in with "));
    const errors = diffProviders(app, parseTestProviders(TEST));
    expect(errors.length).toBe(2);
  });

  it("ignores WEB_OAUTH_PROVIDERS ordering only for REQUIRED labels, not for the ordered list", () => {
    const test = parseTestProviders(
      TEST.replace(
        `const WEB_OAUTH_PROVIDERS = [
  { id: "google", label: "Log in with Google" },
  { id: "linkedin", label: "Log in with LinkedIn" },
];`,
        `const WEB_OAUTH_PROVIDERS = [
  { id: "linkedin", label: "Log in with LinkedIn" },
  { id: "google", label: "Log in with Google" },
];`,
      ),
    );
    const errors = diffProviders(parseAppProviders(APP), test);
    // Order matters for WEB_OAUTH_PROVIDERS (drives the ordered loops), so this
    // reordering is flagged; REQUIRED_SOCIAL_LABELS is a set so it is not.
    expect(errors.length).toBe(1);
    expect(errors[0]).toMatch(/WEB_OAUTH_PROVIDERS/);
  });
});

describe("live repo", () => {
  it("the real auth-flow e2e is in lockstep with the real login screen", () => {
    const appSrc = fs.readFileSync(path.join(REPO_ROOT, APP_FILE), "utf8");
    const testSrc = fs.readFileSync(path.join(REPO_ROOT, TEST_FILE), "utf8");
    const app = parseAppProviders(appSrc);
    const test = parseTestProviders(testSrc);
    expect(app.webProviders.length).toBeGreaterThan(0);
    expect(diffProviders(app, test)).toEqual([]);
  });
});
