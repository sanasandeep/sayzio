import { describe, it, expect } from "vitest";
import fs from "node:fs";
import path from "node:path";
import {
  missingRequirements,
  analyzeScreens,
  REQUIREMENTS,
  AUTH_SCREENS,
  REPO_ROOT,
} from "./check-auth-screen-gradient.js";

/**
 * Regression suite for the auth-screen branded-background guard.
 *
 * The guard fails when a mobile auth-flow screen drops the shared, theme-aware
 * brand gradient wash (login, OTP verify, OAuth return, cancel-change) or ships
 * a flat / hardcoded background instead.
 *
 * Run: pnpm --filter @workspace/scripts run test
 */

// A minimal screen that satisfies every requirement, mirroring the real shape.
const GOOD = `
  const bgAlpha = colors.scheme === "dark" ? "40" : "2e";
  const bgGradientColors = colors.brandGradient.map(
    (c) => \`\${c}\${bgAlpha}\`,
  ) as unknown as [string, string, string];

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <LinearGradient
        colors={bgGradientColors}
        start={{ x: 0.0, y: 0.0 }}
        end={{ x: 1.0, y: 1.0 }}
        style={StyleSheet.absoluteFill}
      />
    </View>
  );
`;

// The historical cancel-change shape: a hardcoded flat/dark gradient, no wash.
const HARDCODED = `
  return (
    <View style={{ flex: 1, backgroundColor: "#0b0e1a" }}>
      <LinearGradient
        colors={["#0b0e1a", "#080b14", "#070a12"]}
        style={StyleSheet.absoluteFill}
      />
    </View>
  );
`;

describe("missingRequirements", () => {
  it("passes a screen with the full shared branded background", () => {
    expect(missingRequirements(GOOD)).toEqual([]);
  });

  it("flags a hardcoded flat background (the historic cancel-change shape)", () => {
    const missing = missingRequirements(HARDCODED);
    expect(missing).toContain("brand-gradient-wash");
    expect(missing).toContain("bg-alpha-treatment");
    expect(missing).toContain("alpha-applied");
    expect(missing).toContain("diagonal-start");
    expect(missing).toContain("diagonal-end");
  });

  it("flags dropping the LinearGradient entirely", () => {
    const src = GOOD.replace(/<LinearGradient[\s\S]*?\/>/, "");
    expect(missingRequirements(src)).toContain("linear-gradient");
  });

  it("flags losing the theme-aware opacity treatment (always 0x40)", () => {
    const src = GOOD.replace(
      'colors.scheme === "dark" ? "40" : "2e"',
      '"40"',
    ).replace("${c}${bgAlpha}", "${c}40");
    const missing = missingRequirements(src);
    expect(missing).toContain("bg-alpha-treatment");
    expect(missing).toContain("alpha-applied");
  });

  it("flags a non-brand (hardcoded) gradient wash", () => {
    const src = GOOD.replace("colors.brandGradient.map(", "somethingElse.map(");
    expect(missingRequirements(src)).toContain("brand-gradient-wash");
  });

  it("flags a non-diagonal gradient (top-to-bottom)", () => {
    const src = GOOD.replace(
      "end={{ x: 1.0, y: 1.0 }}",
      "end={{ x: 0.0, y: 1.0 }}",
    );
    expect(missingRequirements(src)).toContain("diagonal-end");
  });

  it("tolerates integer corner coordinates and reflowed whitespace", () => {
    const src = GOOD.replace("start={{ x: 0.0, y: 0.0 }}", "start={{ x: 0, y: 0 }}")
      .replace("end={{ x: 1.0, y: 1.0 }}", "end={{\n  x: 1,\n  y: 1,\n}}");
    expect(missingRequirements(src)).toEqual([]);
  });
});

describe("REQUIREMENTS", () => {
  it("has stable, unique ids", () => {
    const ids = REQUIREMENTS.map((r) => r.id);
    expect(new Set(ids).size).toBe(ids.length);
  });
});

describe("live repo", () => {
  const read = (file: string): string =>
    fs.readFileSync(path.join(REPO_ROOT, file), "utf8");

  it("guards a non-empty list of auth screens", () => {
    expect(AUTH_SCREENS.length).toBeGreaterThan(0);
  });

  it("every real auth-flow screen keeps the shared branded background", () => {
    const results = analyzeScreens(read);
    const broken = results.filter((r) => r.missing.length > 0);
    expect(
      broken,
      broken
        .map((b) => `${b.file} is missing: ${b.missing.join(", ")}`)
        .join("\n"),
    ).toEqual([]);
  });
});
