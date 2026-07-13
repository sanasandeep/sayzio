import { describe, it, expect } from "vitest";
import { spawnSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
import { scanSource, blankComments, REPO_ROOT, SCAN_ROOTS } from "./check-demo-login-button-pattern.js";

/**
 * Regression suite for the demo-login-button guard.
 *
 * The guard fires when a `tests/Browser/*.spec.ts` re-introduces the fragile,
 * button-first demo sign-in pattern (an inline `loginAsDemo`, the
 * `form[action$="/user/demo-login"]` selector, or the misleading
 * "demo-login form not found" error) INSTEAD of importing the shared
 * token-POST helper (`./login-as-demo`). It must stay quiet on specs that
 * import the sanctioned helper and on the admin-login variant.
 *
 * Run: pnpm --filter @workspace/scripts run test
 */

const kinds = (src: string) => scanSource("test.spec.ts", src).map((o) => o.kind);

const SHARED_IMPORT = `import { loginAsDemo } from "./login-as-demo";\n`;

describe("scanSource — flags the button-first pattern (no shared import)", () => {
  it("flags an inline `function loginAsDemo` definition", () => {
    expect(kinds("async function loginAsDemo(page) {}")).toContain(
      "inline loginAsDemo definition",
    );
  });

  it("flags an inline `const loginAsDemo = ...` arrow definition", () => {
    expect(kinds("const loginAsDemo = async (page) => {};")).toContain(
      "inline loginAsDemo definition",
    );
  });

  it('flags the form[action$="/user/demo-login"] selector', () => {
    expect(kinds(`page.locator('form[action$="/user/demo-login"]').evaluate(f => f.submit());`)).toContain(
      'form[action$="/user/demo-login"] selector',
    );
  });

  it('flags the "demo-login form not found" error string', () => {
    expect(kinds(`if (!form) throw new Error("demo-login form not found");`)).toContain(
      '"demo-login form not found" error',
    );
  });

  it("flags a full reintroduced button-first helper", () => {
    const src = [
      `async function loginAsDemo(page) {`,
      `  await page.goto("/user/login");`,
      `  const form = document.querySelector('form[action$="/user/demo-login"]');`,
      `  if (!form) throw new Error("demo-login form not found");`,
      `  form.submit();`,
      `}`,
    ].join("\n");
    expect(kinds(src)).toEqual([
      "inline loginAsDemo definition",
      'form[action$="/user/demo-login"] selector',
      '"demo-login form not found" error',
    ]);
  });
});

describe("scanSource — stays quiet on compliant / out-of-scope code", () => {
  it("ignores everything once the shared helper is imported", () => {
    const src =
      SHARED_IMPORT +
      `page.locator('form[action$="/user/demo-login"]').toBeVisible();\n` +
      `await loginAsDemo(page);`;
    expect(kinds(src)).toEqual([]);
  });

  it("does not flag the admin-login variant selector", () => {
    expect(kinds(`document.querySelector('form[action$="/admin/demo-login"]');`)).toEqual([]);
  });

  it('does not flag the admin "admin demo-login form not found" error', () => {
    expect(kinds(`if (!form) throw new Error("admin demo-login form not found");`)).toEqual([]);
  });

  it("does not flag `loginAsDemoAdmin` (different helper name)", () => {
    expect(kinds("async function loginAsDemoAdmin(page) {}")).toEqual([]);
  });

  it("does not flag calling or importing loginAsDemo", () => {
    expect(kinds(`await loginAsDemo(page);`)).toEqual([]);
    expect(kinds(`import { loginAsDemo } from "./login-as-demo";`)).toEqual([]);
  });

  it("ignores the patterns inside a line comment", () => {
    expect(kinds(`// we no longer use form[action$="/user/demo-login"] here`)).toEqual([]);
  });

  it("ignores the patterns inside a block comment", () => {
    const src = `/**\n * function loginAsDemo used to grab the demo-login form not found\n */`;
    expect(kinds(src)).toEqual([]);
  });
});

describe("blankComments", () => {
  it("preserves newlines/length when blanking", () => {
    const src = "// a\ncode\n/* b */Z";
    const out = blankComments(src);
    expect(out.length).toBe(src.length);
    expect(out.endsWith("Z")).toBe(true);
    expect(out.split("\n").length).toBe(src.split("\n").length);
  });

  it("does not treat `https://` as a line comment", () => {
    const out = blankComments("const u = 'https://x/user/demo-login';");
    expect(out).toContain("demo-login");
  });
});

describe("live repo", () => {
  it("passes on all real browser spec files under the scan roots", () => {
    const res = spawnSync(
      "rg",
      ["--files", "-g", "*.spec.ts", "!**/node_modules/**", ...SCAN_ROOTS],
      { cwd: REPO_ROOT, encoding: "utf8", maxBuffer: 64 * 1024 * 1024 },
    );
    const files = res.stdout.split("\n").map((l) => l.trim()).filter(Boolean);
    expect(files.length).toBeGreaterThan(0);
    for (const rel of files) {
      const src = fs.readFileSync(path.join(REPO_ROOT, rel), "utf8");
      const offenders = scanSource(rel, src);
      expect(offenders, `${rel} re-introduces the button-first demo-login pattern`).toEqual([]);
    }
  });
});
