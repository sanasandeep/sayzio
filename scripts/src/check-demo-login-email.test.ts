import { describe, it, expect } from "vitest";
import { spawnSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
import { scanSource, blankComments, SCAN_ROOTS, REPO_ROOT } from "./check-demo-login-email.js";

/**
 * Regression suite for the demo-login-email guard.
 *
 * The guard fires when a browser spec hardcodes the demo-login account email
 * (the current `DEMO_LOGIN_EMAIL` value or the legacy `demo@1inme.com`) in seed
 * code instead of interpolating the shared constant — a mismatch trips the
 * controller owner guard and silently 403s the seeded page (see task #4290 /
 * .agents/memory/e2e-demo-user-banned-bypass.md). It must stay quiet on the
 * email inside comments and on `${DEMO_LOGIN_EMAIL}` interpolation.
 *
 * Run: pnpm --filter @workspace/scripts run test
 */

const emails = (src: string) => scanSource("x.spec.ts", src).map((o) => o.email);

describe("scanSource — flags hardcoded demo-login emails in code", () => {
  it("flags the current constant value pasted into a seed string", () => {
    expect(emails(`$u = User::where('email', 'sazioapp@gmail.com')->first();`)).toEqual([
      "sazioapp@gmail.com",
    ]);
  });

  it("flags the legacy wrong account", () => {
    expect(emails(`$u = User::where('email', 'demo@1inme.com')->first();`)).toEqual([
      "demo@1inme.com",
    ]);
  });

  it("flags a double-quoted literal", () => {
    expect(emails(`const e = "demo@1inme.com";`)).toEqual(["demo@1inme.com"]);
  });

  it("reports the correct line for a multi-line seed string", () => {
    const src = "const php = `\nuse App;\n$u = User::create(['email' => 'sazioapp@gmail.com']);\n`;";
    const [o] = scanSource("f.spec.ts", src);
    expect(o?.line).toBe(3);
    expect(o?.email).toBe("sazioapp@gmail.com");
  });

  it("flags multiple offenders in one file", () => {
    const src = "'sazioapp@gmail.com'\n'demo@1inme.com'";
    expect(emails(src)).toEqual(["sazioapp@gmail.com", "demo@1inme.com"]);
  });
});

describe("scanSource — stays quiet on allowed usage", () => {
  it("ignores the email inside a JS // line comment", () => {
    expect(emails(`// AuthController::demoLogin -> sazioapp@gmail.com`)).toEqual([]);
  });

  it("ignores the email inside a PHP // comment embedded in a seed string", () => {
    const src = "const php = `\n// demoLogin -> demo@1inme.com is the wrong account\n`;";
    expect(emails(src)).toEqual([]);
  });

  it("ignores the email inside a block comment", () => {
    expect(emails(`/* seed as sazioapp@gmail.com */`)).toEqual([]);
  });

  it("ignores the required ${DEMO_LOGIN_EMAIL} interpolation", () => {
    expect(emails("$u = User::where('email', '${DEMO_LOGIN_EMAIL}')->first();")).toEqual([]);
  });

  it("does not treat // inside an https:// URL as a comment", () => {
    // The email after the URL on the SAME line must still be caught.
    const src = `const u = 'https://example.com'; const e = 'demo@1inme.com';`;
    expect(emails(src)).toEqual(["demo@1inme.com"]);
  });

  it("blanks a comment but keeps code before it on the same line", () => {
    const src = `const e = 'demo@1inme.com'; // ok to mention sazioapp@gmail.com here`;
    expect(emails(src)).toEqual(["demo@1inme.com"]);
  });
});

describe("blankComments", () => {
  it("preserves length and newlines when blanking", () => {
    const src = "a // c\nb";
    const out = blankComments(src);
    expect(out.length).toBe(src.length);
    expect(out.split("\n").length).toBe(src.split("\n").length);
    expect(out.startsWith("a ")).toBe(true);
  });
});

describe("live repo", () => {
  it("passes on all real browser spec files under the scan roots", () => {
    const res = spawnSync("rg", ["--files", "-g", "*.spec.ts", ...SCAN_ROOTS], {
      cwd: REPO_ROOT,
      encoding: "utf8",
    });
    const files = res.stdout.split("\n").map((l) => l.trim()).filter(Boolean);
    expect(files.length).toBeGreaterThan(0);
    for (const rel of files) {
      const src = fs.readFileSync(path.join(REPO_ROOT, rel), "utf8");
      const offenders = scanSource(rel, src);
      expect(offenders, `${rel} hardcodes a demo-login email in seed code`).toEqual([]);
    }
  });
});
