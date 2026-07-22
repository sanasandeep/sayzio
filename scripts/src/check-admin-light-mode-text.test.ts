import { describe, it, expect } from "vitest";
import fs from "node:fs";
import path from "node:path";
import {
  scanSource,
  scanRepo,
  readBaseline,
  REPO_ROOT,
  BASELINE_REL,
} from "./check-admin-light-mode-text.js";

/**
 * Regression suite for the admin light-mode dark-only text guard.
 *
 * The guard's value is that it fires when an admin blade element carries a
 * dark-only Tailwind text class (bare/low-opacity text-white, light 100-300
 * tint shades) with NO paired ak-* light-mode helper and NO always-colored
 * surface (solid >=500 bg, gradient, bg-black) — while staying quiet on the
 * documented legitimate cases. Both directions are pinned.
 */

const tokens = (src: string) => scanSource("t.blade.php", src).map((v) => v.token);

describe("scanSource — flags un-paired dark-only text", () => {
  it("flags bare text-white with no ak-* class", () => {
    expect(tokens('<span class="text-sm text-white">Hi</span>')).toEqual(["text-white"]);
  });

  it("flags low-opacity white text", () => {
    expect(tokens('<p class="text-white/40">note</p>')).toEqual(["text-white/40"]);
  });

  it("flags light tint shades (amber-300)", () => {
    expect(tokens('<i class="fa fa-warn text-amber-300"></i>')).toEqual(["text-amber-300"]);
  });

  it("flags a dark token inside a ternary branch lacking ak-*", () => {
    const src = `<span class="{{ $ok ? 'text-emerald-300 ak-green' : 'text-white/50' }}">x</span>`;
    expect(tokens(src)).toEqual(["text-white/50"]);
  });

  it("reports the correct line number", () => {
    const src = `<div>\n\n<span class="text-white/30">x</span>`;
    expect(scanSource("t.blade.php", src)[0]?.line).toBe(3);
  });
});

describe("scanSource — stays quiet on legitimate cases", () => {
  it("accepts a dark token paired with an ak-* helper", () => {
    expect(tokens('<span class="text-white/50 ak-muted">x</span>')).toEqual([]);
  });

  it("accepts white text on a solid >=500 colored button", () => {
    expect(tokens('<button class="bg-blue-600 text-white">Save</button>')).toEqual([]);
  });

  it("accepts white text on a gradient icon tile", () => {
    expect(
      tokens('<div class="bg-gradient-to-br from-blue-500 to-fuchsia-500 text-white">Z</div>'),
    ).toEqual([]);
  });

  it("accepts ternary branches that each carry ak-* or a solid surface", () => {
    const src = `<span class="{{ $ok ? 'text-emerald-300 ak-green' : 'text-white/50 ak-muted' }}">x</span>`;
    expect(tokens(src)).toEqual([]);
  });

  it("ignores prefixed variants (hover:, dark:, group-hover:)", () => {
    expect(
      tokens('<a class="hover:text-white dark:text-gray-300 group-hover:text-white/70">x</a>'),
    ).toEqual([]);
  });

  it("ignores dark shades that are legible on white (e.g. text-gray-700, text-amber-500)", () => {
    expect(tokens('<p class="text-gray-700 text-amber-500">x</p>')).toEqual([]);
  });

  it("ignores Blade-commented markup", () => {
    expect(tokens('{{-- <span class="text-white/40">x</span> --}}')).toEqual([]);
  });

  it("ignores markup without a class attribute", () => {
    expect(tokens("<p>text-white/40 mentioned in prose</p>")).toEqual([]);
  });
});

describe("repo state — ratchet holds", () => {
  it("baseline file exists and no admin blade exceeds its baseline", () => {
    const baseline = readBaseline();
    expect(fs.existsSync(path.join(REPO_ROOT, BASELINE_REL))).toBe(true);
    const byFile = scanRepo();
    const regressions: string[] = [];
    for (const [file, v] of byFile) {
      if (v.length > (baseline[file] ?? 0)) regressions.push(`${file}: ${v.length} > ${baseline[file] ?? 0}`);
    }
    expect(regressions).toEqual([]);
  });
});
