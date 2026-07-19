import { describe, expect, it } from "vitest";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { diffContexts, loadManifestContexts, MANIFEST_PATH, REPO_ROOT } from "./sync-branch-protection";

function writeManifest(json: unknown): string {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), "sync-bp-"));
  fs.mkdirSync(path.join(root, path.dirname(MANIFEST_PATH)), { recursive: true });
  fs.writeFileSync(path.join(root, MANIFEST_PATH), JSON.stringify(json));
  return root;
}

describe("loadManifestContexts", () => {
  it("loads the real committed manifest", () => {
    const contexts = loadManifestContexts(REPO_ROOT);
    expect(contexts.length).toBeGreaterThan(0);
    expect(contexts).toContain("php artisan test against PostgreSQL");
  });

  it("rejects a manifest without a requiredChecks string array", () => {
    const root = writeManifest({ requiredChecks: [1, 2] });
    expect(() => loadManifestContexts(root)).toThrow(/array of strings/);
  });

  it("refuses an empty requiredChecks list (never wipes protection)", () => {
    const root = writeManifest({ requiredChecks: [] });
    expect(() => loadManifestContexts(root)).toThrow(/refusing to wipe/);
  });

  it("rejects duplicate entries", () => {
    const root = writeManifest({ requiredChecks: ["a", "a"] });
    expect(() => loadManifestContexts(root)).toThrow(/twice/);
  });
});

describe("diffContexts", () => {
  it("reports added, removed, and unchanged contexts sorted", () => {
    const diff = diffContexts(["b", "a", "new"], ["b", "a", "stale"]);
    expect(diff.added).toEqual(["new"]);
    expect(diff.removed).toEqual(["stale"]);
    expect(diff.unchanged).toEqual(["a", "b"]);
  });

  it("is empty when in sync regardless of order", () => {
    const diff = diffContexts(["x", "y"], ["y", "x"]);
    expect(diff.added).toEqual([]);
    expect(diff.removed).toEqual([]);
  });
});
