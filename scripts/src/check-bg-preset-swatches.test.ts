import { describe, it, expect } from "vitest";
import fs from "node:fs";
import path from "node:path";
import {
  MIN_PNG_BYTES,
  PNG_SIGNATURE,
  REPO_ROOT,
  SWATCH_DIR_REL,
  checkSwatchFreshness,
  inspectPng,
  loadCatalogCss,
  loadManifest,
  md5,
  type PngInfo,
} from "./check-bg-preset-swatches.js";

/**
 * Regression suite for the bg-preset swatch freshness guard.
 *
 * Pins:
 *   - the LIVE catalog + manifest + PNGs pass (baseline is green),
 *   - a new preset with no manifest entry is flagged,
 *   - a preset whose CSS changed since render (stale md5) is flagged,
 *   - a missing PNG is flagged,
 *   - an empty/truncated PNG (under the minimum size) is flagged,
 *   - a corrupt PNG (bad signature) is flagged,
 *   - a leftover manifest entry for a removed preset is flagged.
 */

const swatchDir = path.join(REPO_ROOT, SWATCH_DIR_REL);
const livePngInfo = (key: string) => inspectPng(path.join(swatchDir, `${key}.png`));

const goodPng: PngInfo = { size: 5000, head: Buffer.from(PNG_SIGNATURE) };

describe("live catalog + manifest", () => {
  it("passes the guard with zero problems", { timeout: 60_000 }, () => {
    const catalog = loadCatalogCss();
    expect(Object.keys(catalog).length).toBeGreaterThan(50);
    expect(checkSwatchFreshness(catalog, loadManifest(), livePngInfo)).toEqual([]);
  });
});

describe("drift detection (synthetic)", () => {
  const catalog = { a: "background: red", b: "background: blue" };
  const freshManifest = { a: md5(catalog.a), b: md5(catalog.b) };
  const allPngs = () => goodPng;

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
    const problems = checkSwatchFreshness(catalog, freshManifest, (k) =>
      k !== "b" ? goodPng : null,
    );
    expect(problems).toHaveLength(1);
    expect(problems[0]).toMatchObject({ kind: "missing-png", key: "b" });
  });

  it("flags a zero-byte PNG as empty", () => {
    const problems = checkSwatchFreshness(catalog, freshManifest, (k) =>
      k === "b" ? { size: 0, head: Buffer.alloc(0) } : goodPng,
    );
    expect(problems).toHaveLength(1);
    expect(problems[0]).toMatchObject({ kind: "empty-png", key: "b" });
    expect(problems[0].detail).toContain(`< ${MIN_PNG_BYTES}`);
  });

  it("flags a truncated PNG below the minimum size even with a valid signature", () => {
    const problems = checkSwatchFreshness(catalog, freshManifest, (k) =>
      k === "b" ? { size: MIN_PNG_BYTES - 1, head: Buffer.from(PNG_SIGNATURE) } : goodPng,
    );
    expect(problems).toHaveLength(1);
    expect(problems[0]).toMatchObject({ kind: "empty-png", key: "b" });
  });

  it("flags a plausibly-sized file with a bad signature as corrupt", () => {
    const problems = checkSwatchFreshness(catalog, freshManifest, (k) =>
      k === "b" ? { size: 5000, head: Buffer.from("GIF89a12") } : goodPng,
    );
    expect(problems).toHaveLength(1);
    expect(problems[0]).toMatchObject({ kind: "corrupt-png", key: "b" });
  });

  it("accepts a file exactly at the minimum size with a valid signature", () => {
    const problems = checkSwatchFreshness(catalog, freshManifest, () => ({
      size: MIN_PNG_BYTES,
      head: Buffer.from(PNG_SIGNATURE),
    }));
    expect(problems).toEqual([]);
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
