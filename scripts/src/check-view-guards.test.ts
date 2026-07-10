import { describe, it, expect } from "vitest";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { GUARDS } from "./check-view-guards.js";

const REPO_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

/**
 * Wiring regression for the combined view-guard runner.
 *
 * The value of `check:view-guards` is that it stays in lockstep with the three
 * individual guards that post-merge.sh enforces. If someone adds/removes a
 * guard from post-merge but forgets the combined runner (or vice versa), the
 * local pre-push hook silently stops mirroring the merge gate. These tests pin
 * that contract.
 */
describe("check:view-guards wiring", () => {
  it("runs exactly the three post-merge Blade/Alpine guards", () => {
    expect([...GUARDS]).toEqual([
      "check:alpine-line-comments",
      "check:blade-json-in-attr",
      "check:blade-comment-echo",
    ]);
  });

  it("exposes every guard as a real npm script in scripts/package.json", () => {
    const pkg = JSON.parse(
      fs.readFileSync(path.join(REPO_ROOT, "scripts/package.json"), "utf8"),
    ) as { scripts: Record<string, string> };
    expect(pkg.scripts["check:view-guards"]).toBe(
      "tsx ./src/check-view-guards.ts",
    );
    for (const g of GUARDS) {
      expect(pkg.scripts[g], `missing npm script ${g}`).toBeTruthy();
    }
  });

  it("is mirrored by post-merge.sh (both run the same three guards)", () => {
    const postMerge = fs.readFileSync(
      path.join(REPO_ROOT, "scripts/post-merge.sh"),
      "utf8",
    );
    for (const g of GUARDS) {
      expect(
        postMerge.includes(`run ${g}`),
        `post-merge.sh no longer runs ${g}`,
      ).toBe(true);
    }
  });

  it("ships an executable pre-push hook that invokes the combined check", () => {
    const hookPath = path.join(REPO_ROOT, ".githooks/pre-push");
    expect(fs.existsSync(hookPath)).toBe(true);
    const hook = fs.readFileSync(hookPath, "utf8");
    expect(hook).toContain("check:view-guards");
    const mode = fs.statSync(hookPath).mode;
    expect(mode & 0o111, "pre-push hook is not executable").toBeTruthy();
  });
});
