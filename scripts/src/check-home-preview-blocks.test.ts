import { describe, it, expect } from "vitest";
import fs from "node:fs";
import path from "node:path";
import {
  REPO_ROOT,
  HOME_BLADE_REL,
  BLOCK_PREVIEW_MAP,
  parseBuildListStyles,
  parseUsedBbClasses,
  parseDefinedBbClasses,
  checkHomePreviewBlocks,
} from "./check-home-preview-blocks.js";

/**
 * Regression suite for the home-page biolink-preview block guard.
 *
 * Pins the three behaviours that matter:
 *   - the LIVE home.blade.php passes (baseline is green),
 *   - a preview div referencing a .bb-* class with no CSS rule is flagged
 *     (the "renders blank" catch),
 *   - a new build-list block type with no BLOCK_PREVIEW_MAP entry is flagged.
 */

const liveSrc = () => fs.readFileSync(path.join(REPO_ROOT, HOME_BLADE_REL), "utf8");

describe("live home.blade.php", () => {
  it("passes the guard with zero problems", () => {
    expect(checkHomePreviewBlocks(liveSrc())).toEqual([]);
  });

  it("every build-list block type is declared in BLOCK_PREVIEW_MAP", () => {
    for (const style of parseBuildListStyles(liveSrc())) {
      expect(BLOCK_PREVIEW_MAP).toHaveProperty(style);
    }
  });

  it("every non-null mapped .bb-* class is both used and defined", () => {
    const src = liveSrc();
    const used = parseUsedBbClasses(src);
    const defined = parseDefinedBbClasses(src);
    for (const cls of Object.values(BLOCK_PREVIEW_MAP)) {
      if (cls === null) continue;
      expect(used.has(cls)).toBe(true);
      expect(defined.has(cls)).toBe(true);
    }
  });
});

describe("parsers", () => {
  it("parseBuildListStyles reads data-bl-style tokens in order", () => {
    const src = `
      <div data-bl-style="image"></div>
      <div data-bl-style="video"></div>
    `;
    expect(parseBuildListStyles(src)).toEqual(["image", "video"]);
  });

  it("parseUsedBbClasses collects only bb-* class tokens", () => {
    const src = `<div class="bb-hero lift"></div><div class="build-row bb-video"></div>`;
    expect([...parseUsedBbClasses(src)].sort()).toEqual(["bb-hero", "bb-video"]);
  });

  it("parseDefinedBbClasses collects dot-prefixed bb-* selectors incl. descendants", () => {
    const src = `.bb-prof { } .bb-prof .bb-av { } .bb-video i { }`;
    expect([...parseDefinedBbClasses(src)].sort()).toEqual(["bb-av", "bb-prof", "bb-video"]);
  });
});

describe("failure modes", () => {
  it("flags a used preview class with no CSS rule (missing/misspelled)", () => {
    // .bb-hero is used but only .bb-video has CSS -> hero has no rule.
    const src = `
      <div data-bl-style="image"></div>
      <style>.bb-video { color: red; }</style>
      <div class="bb-phone"><div class="bb-hero"></div><div class="bb-video"></div></div>
    `;
    const problems = checkHomePreviewBlocks(src);
    expect(problems.some((p) => p.kind === "used-class-no-css" && p.detail.includes("bb-hero"))).toBe(
      true,
    );
  });

  it("flags a build-list type that is not in BLOCK_PREVIEW_MAP", () => {
    const src = `
      <div data-bl-style="brandnewtype"></div>
      <style>.bb-hero{}</style>
      <div class="bb-phone"><div class="bb-hero"></div></div>
    `;
    const problems = checkHomePreviewBlocks(src);
    expect(
      problems.some(
        (p) => p.kind === "unmapped-block-type" && p.detail.includes("brandnewtype"),
      ),
    ).toBe(true);
  });

  it("flags a mapped class that CSS defines but the preview never renders", () => {
    // gallery -> bb-gal has CSS, but the preview markup omits the bb-gal div.
    const src = `
      <div data-bl-style="gallery"></div>
      <style>.bb-gal{}</style>
      <div class="bb-phone"></div>
    `;
    const problems = checkHomePreviewBlocks(src);
    expect(
      problems.some((p) => p.kind === "mapped-class-not-rendered" && p.detail.includes("bb-gal")),
    ).toBe(true);
  });
});
