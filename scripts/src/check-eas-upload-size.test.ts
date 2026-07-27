import { describe, expect, it } from "vitest";
import fs from "node:fs";
import path from "node:path";
import { REPO_ROOT, isIgnored, parseEasignore, scan } from "./check-eas-upload-size";

const RULES = parseEasignore(
  [
    "**/node_modules",
    "**/.expo",
    ".git",
    ".local",
    "docs",
    "exports",
    "screenshots",
    "artifacts/1inme",
    "artifacts/1inme-mobile/test-results",
  ].join("\n"),
);

describe("parseEasignore / isIgnored", () => {
  it("ignores root-anchored directories and everything beneath them", () => {
    expect(isIgnored("exports", true, RULES)).toBe(true);
    expect(isIgnored("exports/foo/bar.png", false, RULES)).toBe(true);
    expect(isIgnored("docs/readme.md", false, RULES)).toBe(true);
  });

  it("ignores slash-containing patterns only at that path", () => {
    expect(isIgnored("artifacts/1inme/app/Models/User.php", false, RULES)).toBe(true);
    expect(isIgnored("artifacts/1inme-mobile/app/index.tsx", false, RULES)).toBe(false);
    expect(isIgnored("artifacts/1inme-mobile/test-results/a.png", false, RULES)).toBe(true);
  });

  it("matches **/ patterns at any depth", () => {
    expect(isIgnored("node_modules/x/y.js", false, RULES)).toBe(true);
    expect(isIgnored("artifacts/1inme-mobile/node_modules/react/index.js", false, RULES)).toBe(true);
    expect(isIgnored("artifacts/1inme-mobile/.expo/types/router.d.ts", false, RULES)).toBe(true);
  });

  it("does not ignore prefix-similar names", () => {
    expect(isIgnored("exports-helper.ts", false, RULES)).toBe(false);
    expect(isIgnored("artifacts/1inme-com/index.html", false, RULES)).toBe(false);
  });

  it("skips comments and blank lines", () => {
    const rules = parseEasignore("# comment\n\nfoo\n");
    expect(rules).toHaveLength(1);
    expect(isIgnored("foo", true, rules)).toBe(true);
  });

  it("supports negation (last match wins)", () => {
    const rules = parseEasignore("dist\n!dist/keep.txt\n");
    expect(isIgnored("dist/other.txt", false, rules)).toBe(true);
    expect(isIgnored("dist/keep.txt", false, rules)).toBe(false);
  });

  it("dir-only patterns do not ignore a same-named plain file", () => {
    const rules = parseEasignore("build/\n");
    expect(isIgnored("build", true, rules)).toBe(true);
    expect(isIgnored("build/out.js", false, rules)).toBe(true);
    expect(isIgnored("build", false, rules)).toBe(false);
  });
});

describe("scan against the real workspace", () => {
  it("estimates the upload well under the historical 564 MB bloat", () => {
    const easignore = fs.readFileSync(path.join(REPO_ROOT, ".easignore"), "utf8");
    const result = scan(REPO_ROOT, parseEasignore(easignore));
    expect(result.fileCount).toBeGreaterThan(100);
    // The trimmed upload is ~43 MB; anything approaching the old 564 MB
    // means the ignore rules or this scanner regressed.
    expect(result.totalBytes).toBeLessThan(200 * 1024 * 1024);
    // Known-excluded trees must contribute nothing.
    for (const entry of result.topLevel) {
      expect([".git", ".local", "docs", "exports", "screenshots"]).not.toContain(entry.path);
    }
  });
});
