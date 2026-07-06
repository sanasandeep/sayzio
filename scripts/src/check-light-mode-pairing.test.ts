import { describe, it, expect } from "vitest";
import fs from "node:fs";
import path from "node:path";
import {
  REPO_ROOT,
  TARGETS,
  extractStyleBlocks,
  stripCssComments,
  stripKeyframes,
  parseRules,
  findMissingPairs,
  checkSource,
  checkTarget,
} from "./check-light-mode-pairing.js";

/**
 * Regression suite for the generalized light-mode pairing guard.
 *
 * The guard's whole value is that it fires when a base color rule ships without
 * its `html.light-mode <same-selector>` peer, and stays quiet on correct,
 * fully-paired CSS. Both directions are pinned here (whole-page and scoped
 * modes) so a future refactor can't silently disable the check (false
 * negatives) or start flagging correct pairs (false positives). Every live
 * configured page is also asserted to currently pass.
 *
 * Run: pnpm --filter @workspace/scripts run test
 */

const wrap = (css: string) => `<style>\n${css}\n</style>`;
const missing = (css: string, scopes?: string[]) =>
  findMissingPairs(parseRules(css), { scopes }).map((m) => `${m.selector} { ${m.property} }`);

describe("extractStyleBlocks", () => {
  it("pulls the contents of a <style> block", () => {
    expect(extractStyleBlocks("<style>.a{color:red}</style>")).toContain(".a{color:red}");
  });

  it("concatenates multiple <style> blocks and ignores markup outside them", () => {
    const src = "<div>x</div><style>.a{color:red}</style><p>y</p><style>.b{color:blue}</style>";
    const out = extractStyleBlocks(src);
    expect(out).toContain(".a{color:red}");
    expect(out).toContain(".b{color:blue}");
    expect(out).not.toContain("<div>");
  });
});

describe("stripCssComments", () => {
  it("removes /* */ comments so a color inside a comment is never parsed", () => {
    const stripped = stripCssComments(".a { color:#fff; /* html.light-mode .a { color:#000 } */ }");
    expect(stripped).not.toContain("light-mode");
  });
});

describe("stripKeyframes", () => {
  it("removes @keyframes blocks so percentage steps are never parsed as selectors", () => {
    const css = "@keyframes spin { 0% { border-color:#fff; } 100% { border-color:#000; } } .a { color:#fff; }";
    const stripped = stripKeyframes(css);
    expect(stripped).not.toContain("0%");
    expect(stripped).toContain(".a");
  });

  it("handles vendor-prefixed keyframes and nested braces", () => {
    const css = "@-webkit-keyframes p { 50% { color:red; } } .b{color:blue}";
    expect(stripKeyframes(css)).not.toContain("50%");
  });

  it("means parseRules ignores keyframe percentage steps entirely", () => {
    const rules = parseRules("@keyframes k { 0% { color:#fff; } } .real { color:#000; }");
    const selectors = rules.flatMap((r) => r.selectors);
    expect(selectors).toContain(".real");
    expect(selectors).not.toContain("0%");
  });
});

describe("parseRules", () => {
  it("captures only the color-carrying properties", () => {
    const rules = parseRules(".a { color:#fff; background:#000; border-color:#111; font-weight:600; }");
    expect(rules).toHaveLength(1);
    expect([...rules[0].props].sort()).toEqual(["border-color", "color"]);
  });

  it("splits grouped selectors", () => {
    const rules = parseRules(".a, .b { color:#fff; }");
    expect(rules[0].selectors).toEqual([".a", ".b"]);
  });

  it("does not treat background-color as color", () => {
    const rules = parseRules(".a { background-color:#000; }");
    expect([...rules[0].props]).toEqual([]);
  });
});

describe("findMissingPairs — whole-page mode flags unpaired base color rules", () => {
  it("flags a base color rule with no light override", () => {
    expect(missing(".btn { color:#34d399; }")).toEqual([".btn { color }"]);
  });

  it("flags a base border-color rule with no light override", () => {
    expect(missing(".card { border-color:rgba(255,255,255,0.1); }")).toEqual([".card { border-color }"]);
  });

  it("flags the property that is missing even when the other is paired", () => {
    const css = [".btn { color:#34d399; border-color:#34d399; }", "html.light-mode .btn { color:#059669; }"].join("\n");
    expect(missing(css)).toEqual([".btn { border-color }"]);
  });
});

describe("findMissingPairs — stays quiet on correct CSS", () => {
  it("passes when color and border-color are both paired", () => {
    const css = [
      ".btn { color:#34d399; border-color:rgba(52,211,153,0.45); }",
      "html.light-mode .btn { color:#059669; border-color:rgba(5,150,105,0.5); }",
    ].join("\n");
    expect(missing(css)).toEqual([]);
  });

  it("passes when the pair lives in a grouped light-mode selector", () => {
    const css = [
      ".a.border:hover { border-color:rgba(61,107,255,0.4); }",
      "html.light-mode .a.border, html.light-mode .a.border:hover { border-color:rgba(61,107,255,0.4); }",
    ].join("\n");
    expect(missing(css)).toEqual([]);
  });

  it("ignores base rules that set no color/border-color (layout/background only)", () => {
    const css = [".row { display:flex; }", ".a { background:rgba(255,255,255,0.03); }", ".b { font-weight:600; }"].join("\n");
    expect(missing(css)).toEqual([]);
  });
});

describe("findMissingPairs — scoped mode", () => {
  it("only checks base rules under the configured wrapper", () => {
    const css = [
      ".ev-rich .text-dark { color:#e8eaf0; }", // in scope, unpaired
      ".hero-title { color:#fff; }", // out of scope (intentional dark island)
    ].join("\n");
    expect(missing(css, [".ev-rich"])).toEqual([".ev-rich .text-dark { color }"]);
  });

  it("passes when the scoped base rule is paired", () => {
    const css = [
      ".ev-rich .text-dark { color:#e8eaf0; }",
      "html.light-mode .ev-rich .text-dark { color:#111827; }",
      ".hero-title { color:#fff; }",
    ].join("\n");
    expect(missing(css, [".ev-rich"])).toEqual([]);
  });
});

describe("checkSource — end to end over a blade string", () => {
  it("passes on a fully-paired <style> block", () => {
    const src = wrap([".text-dark { color:#e8eaf0; }", "html.light-mode .text-dark { color:#111827; }"].join("\n"));
    expect(checkSource(src)).toEqual([]);
  });

  it("catches the historical regression: btn-outline-success with no light pair", () => {
    const src = wrap(".btn-outline-success { color:#34d399; border-color:rgba(52,211,153,0.45); }");
    expect(checkSource(src).map((m) => m.property).sort()).toEqual(["border-color", "color"]);
  });

  it("respects the allowlist via checkSource options", () => {
    const src = wrap(".ev-input:focus { border-color:#3d6bff; }");
    expect(
      checkSource(src, {
        isAllowed: (sel, prop) => sel === ".ev-input:focus" && prop === "border-color",
      }),
    ).toEqual([]);
  });
});

describe("the live configured TARGETS", () => {
  it("has at least the event page and one other page configured", () => {
    expect(TARGETS.length).toBeGreaterThanOrEqual(2);
    expect(TARGETS.map((t) => t.page)).toContain(
      "artifacts/1inme/resources/views/common/event-page.blade.php",
    );
  });

  it.each(TARGETS.map((t) => [t.label, t] as const))(
    "%s currently has every base color rule paired (or allowlisted)",
    (_label, target) => {
      const abs = path.join(REPO_ROOT, target.page);
      expect(fs.existsSync(abs), `${target.page} should exist`).toBe(true);
      const result = checkTarget(target);
      expect(result.error).toBeUndefined();
      expect(result.missing).toEqual([]);
    },
  );
});
