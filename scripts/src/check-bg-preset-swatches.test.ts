import { describe, it, expect } from "vitest";
import fs from "node:fs";
import path from "node:path";
import {
  REPO_ROOT,
  SWATCH_DIR_REL,
  checkSwatchFreshness,
  loadCatalogCss,
  loadManifest,
  md5,
} from "./check-bg-preset-swatches.js";

/**
 * Regression suite for the bg-preset swatch freshness guard.
 *
 * Pins:
 *   - the LIVE catalog + manifest + PNGs pass (baseline is green),
 *   - a new preset with no manifest entry is flagged,
 *   - a preset whose CSS changed since render (stale md5) is flagged,
 *   - a missing PNG is flagged,
 *   - a leftover manifest entry for a removed preset is flagged.
 */

const swatchDir = path.join(REPO_ROOT, SWATCH_DIR_REL);
const livePngExists = (key: string) => fs.existsSync(path.join(swatchDir, `${key}.png`));

describe("live catalog + manifest", () => {
  it("passes the guard with zero problems", () => {
    const catalog = loadCatalogCss();
    expect(Object.keys(catalog).length).toBeGreaterThan(50);
    expect(checkSwatchFreshness(catalog, loadManifest(), livePngExists)).toEqual([]);
  });
});

describe("drift detection (synthetic)", () => {
  const catalog = { a: "background: red", b: "background: blue" };
  const freshManifest = { a: md5(catalog.a), b: md5(catalog.b) };
  const allPngs = () => true;

  it("green when everything matches", () => {
    expect(checkSwatchFreshness(catalog, freshManifest, allPngs)).toEqual([]);
  });

  it("flags a new preset with no manifest entry", () => {
    const problems = checkSwatchFreshness(catalog, { a: freshManifest.a }, allPngs);
    expect(problems).toHaveLength(1);
    expect(problems[0]).toMatchObject({ kind: "missing-manifest-entry", key: "b" });
  });

  it("flags a stale md5 after a CSS edit", () => {
    const problems = checkSwatchFreshness(
      { ...catalog, a: "background: green" },
      freshManifest,
      allPngs,
    );
    expect(problems).toHaveLength(1);
    expect(problems[0]).toMatchObject({ kind: "stale-manifest-hash", key: "a" });
  });

  it("flags a missing PNG even when the hash matches", () => {
    const problems = checkSwatchFreshness(catalog, freshManifest, (k) => k !== "b");
    expect(problems).toHaveLength(1);
    expect(problems[0]).toMatchObject({ kind: "missing-png", key: "b" });
  });

  it("flags a leftover manifest entry for a removed preset", () => {
    const problems = checkSwatchFreshness(
      { a: catalog.a },
      freshManifest,
      allPngs,
    );
    expect(problems).toHaveLength(1);
    expect(problems[0]).toMatchObject({ kind: "orphan-manifest-entry", key: "b" });
  });

  it("fails loudly on an empty catalog dump", () => {
    const problems = checkSwatchFreshness({}, freshManifest, allPngs);
    expect(problems).toHaveLength(1);
    expect(problems[0].kind).toBe("empty-catalog");
  });
});
