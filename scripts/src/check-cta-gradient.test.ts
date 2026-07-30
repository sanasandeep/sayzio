import { describe, it, expect } from "vitest";
import fs from "node:fs";
import path from "node:path";
import {
  normalizeHex,
  parseWebCtaStops,
  parseMobileCtaGradients,
  isOrderedSubsequence,
  findDrift,
  REPO_ROOT,
  WEB_CSS_FILE,
  MOBILE_COLORS_FILE,
} from "./check-cta-gradient.js";

/**
 * Regression suite for the CTA gradient drift guard.
 *
 * The guard fails when the web `.btn-cta` gradient and the mobile `ctaGradient`
 * stops no longer correspond (light mobile stops must be an ordered
 * subsequence of the web stops; dark keeps the light structure).
 *
 * Run: pnpm --filter @workspace/scripts run test
 */

const GOOD_CSS = `
.other { color: red; }
.btn-cta {
    background: linear-gradient(115deg, #2342c7 0%, #3d6bff 28%, #6e61ff 58%, #22d3ee 100%);
    color: #fff !important;
}
`;

const GOOD_COLORS = `
const brand = {
  blue600: "#3d6bff",
  blue400: "#7d9bff",
  indigo: "#6e61ff",
  indigoLight: "#9c92ff",
  cyan400: "#22d3ee",
  cyan300: "#67e8f9",
};
const colors = {
  light: {
    ctaGradient: [brand.blue600, brand.indigo, brand.cyan400] as const,
  },
  dark: {
    ctaGradient: [brand.blue400, brand.indigoLight, brand.cyan300] as const,
  },
};
`;

describe("normalizeHex", () => {
  it("lowercases and expands short hex", () => {
    expect(normalizeHex("#ABC")).toBe("#aabbcc");
    expect(normalizeHex("3D6BFF")).toBe("#3d6bff");
  });
});

describe("parseWebCtaStops", () => {
  it("extracts ordered stops from the .btn-cta gradient", () => {
    expect(parseWebCtaStops(GOOD_CSS)).toEqual(["#2342c7", "#3d6bff", "#6e61ff", "#22d3ee"]);
  });

  it("throws when the .btn-cta rule is missing", () => {
    expect(() => parseWebCtaStops(".foo { color: red; }")).toThrow(/no \.btn-cta rule/);
  });

  it("throws when .btn-cta lost its gradient", () => {
    expect(() => parseWebCtaStops(".btn-cta { background: #3d6bff; }")).toThrow(/no linear-gradient/);
  });
});

describe("parseMobileCtaGradients", () => {
  it("resolves brand token references to hex, light then dark", () => {
    const g = parseMobileCtaGradients(GOOD_COLORS);
    expect(g.light).toEqual(["#3d6bff", "#6e61ff", "#22d3ee"]);
    expect(g.dark).toEqual(["#7d9bff", "#9c92ff", "#67e8f9"]);
  });

  it("accepts literal hex stops", () => {
    const src = GOOD_COLORS.replace("brand.cyan400", '"#22d3ee"');
    expect(parseMobileCtaGradients(src).light[2]).toBe("#22d3ee");
  });

  it("throws on an unknown brand token", () => {
    const src = GOOD_COLORS.replace("brand.cyan400", "brand.nonexistent");
    expect(() => parseMobileCtaGradients(src)).toThrow(/unknown brand token/);
  });

  it("throws when a ctaGradient definition disappears", () => {
    const src = GOOD_COLORS.replace(/ctaGradient: \[brand\.blue400[^\]]*\] as const,/, "");
    expect(() => parseMobileCtaGradients(src)).toThrow(/expected exactly 2/);
  });
});

describe("isOrderedSubsequence", () => {
  it("accepts in-order subsets and rejects reordered ones", () => {
    expect(isOrderedSubsequence(["a", "c"], ["a", "b", "c"])).toBe(true);
    expect(isOrderedSubsequence(["c", "a"], ["a", "b", "c"])).toBe(false);
    expect(isOrderedSubsequence(["a", "d"], ["a", "b", "c"])).toBe(false);
  });
});

describe("findDrift", () => {
  it("passes when web and mobile correspond", () => {
    expect(findDrift(GOOD_CSS, GOOD_COLORS).problems).toEqual([]);
  });

  it("flags a web-side stop change the mobile side did not follow", () => {
    const css = GOOD_CSS.replace("#6e61ff", "#8b5cf6");
    const { problems } = findDrift(css, GOOD_COLORS);
    expect(problems.length).toBeGreaterThan(0);
    expect(problems[0]).toMatch(/not an ordered subsequence/);
  });

  it("flags a mobile-side stop change the web side did not follow", () => {
    const colors = GOOD_COLORS.replace('cyan400: "#22d3ee"', 'cyan400: "#00ffcc"');
    const { problems } = findDrift(GOOD_CSS, colors);
    expect(problems.length).toBeGreaterThan(0);
  });

  it("flags a reordered mobile gradient", () => {
    const colors = GOOD_COLORS.replace(
      "[brand.blue600, brand.indigo, brand.cyan400]",
      "[brand.cyan400, brand.indigo, brand.blue600]",
    );
    expect(findDrift(GOOD_CSS, colors).problems.length).toBeGreaterThan(0);
  });

  it("flags a dark variant that drops a stop", () => {
    const colors = GOOD_COLORS.replace(
      "[brand.blue400, brand.indigoLight, brand.cyan300]",
      "[brand.blue400, brand.cyan300]",
    );
    const { problems } = findDrift(GOOD_CSS, colors);
    expect(problems.some((p) => /dark ctaGradient/.test(p))).toBe(true);
  });
});

describe("live repo", () => {
  const read = (file: string): string => fs.readFileSync(path.join(REPO_ROOT, file), "utf8");

  it("the real web .btn-cta and mobile ctaGradient are in sync", () => {
    const result = findDrift(read(WEB_CSS_FILE), read(MOBILE_COLORS_FILE));
    expect(result.problems, result.problems.join("\n")).toEqual([]);
  });
});
