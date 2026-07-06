import { describe, it, expect } from "vitest";
import fs from "node:fs";
import path from "node:path";
import {
  REPO_ROOT,
  EVENT_PAGE,
  extractStyleBlocks,
  stripCssComments,
  parseRules,
  findMissingPairs,
  checkSource,
} from "./check-event-page-light-mode.js";

/**
 * Regression suite for the event-page light-mode completeness guard.
 *
 * The guard's whole value is that it fires when a base `.ev-rich <selector>`
 * color rule ships without its `html.light-mode .ev-rich <selector>` peer, and
 * stays quiet on correct, fully-paired CSS. Both directions are pinned here so a
 * future refactor can't silently disable the check (false negatives) or start
 * flagging correct pairs (false positives).
 *
 * Run: pnpm --filter @workspace/scripts run test
 */

const wrap = (css: string) => `<style>\n${css}\n</style>`;
const missing = (css: string) =>
  findMissingPairs(parseRules(css)).map((m) => `${m.selector} { ${m.property} }`);

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
    const stripped = stripCssComments(".ev-rich { color:#fff; /* html.light-mode .ev-rich { color:#000 } */ }");
    expect(stripped).not.toContain("light-mode");
  });
});

describe("parseRules", () => {
  it("captures only the color-carrying properties", () => {
    const rules = parseRules(".ev-rich a { color:#fff; background:#000; border-color:#111; font-weight:600; }");
    expect(rules).toHaveLength(1);
    expect([...rules[0].props].sort()).toEqual(["border-color", "color"]);
  });

  it("splits grouped selectors", () => {
    const rules = parseRules(".ev-rich a, .ev-rich b { color:#fff; }");
    expect(rules[0].selectors).toEqual([".ev-rich a", ".ev-rich b"]);
  });

  it("does not treat background-color as color", () => {
    const rules = parseRules(".ev-rich a { background-color:#000; }");
    expect([...rules[0].props]).toEqual([]);
  });
});

describe("findMissingPairs — flags unpaired base color rules", () => {
  it("flags a base color rule with no light override", () => {
    expect(missing(".ev-rich .btn { color:#34d399; }")).toEqual([".ev-rich .btn { color }"]);
  });

  it("flags a base border-color rule with no light override", () => {
    expect(missing(".ev-rich .card { border-color:rgba(255,255,255,0.1); }")).toEqual([
      ".ev-rich .card { border-color }",
    ]);
  });

  it("flags the property that is missing even when the other is paired", () => {
    // color paired, border-color not.
    const css = [
      ".ev-rich .btn { color:#34d399; border-color:#34d399; }",
      "html.light-mode .ev-rich .btn { color:#059669; }",
    ].join("\n");
    expect(missing(css)).toEqual([".ev-rich .btn { border-color }"]);
  });

  it("flags the bare .ev-rich wrapper color when unpaired", () => {
    expect(missing(".ev-rich { color:#e8eaf0; }")).toEqual([".ev-rich { color }"]);
  });
});

describe("findMissingPairs — stays quiet on correct CSS", () => {
  it("passes when color and border-color are both paired", () => {
    const css = [
      ".ev-rich .btn { color:#34d399; border-color:rgba(52,211,153,0.45); }",
      "html.light-mode .ev-rich .btn { color:#059669; border-color:rgba(5,150,105,0.5); }",
    ].join("\n");
    expect(missing(css)).toEqual([]);
  });

  it("passes when the pair lives in a grouped light-mode selector", () => {
    const css = [
      ".ev-rich a.border:hover { border-color:rgba(61,107,255,0.4); }",
      "html.light-mode .ev-rich a.border, html.light-mode .ev-rich a.border:hover { border-color:rgba(61,107,255,0.4); }",
    ].join("\n");
    expect(missing(css)).toEqual([]);
  });

  it("ignores base rules that set no color/border-color (layout/background only)", () => {
    const css = [
      ".ev-rich .row.g-2 { display:flex; }",
      ".ev-rich a.border { background:rgba(255,255,255,0.03); }",
      ".ev-rich .fw-semibold { font-weight:600; }",
    ].join("\n");
    expect(missing(css)).toEqual([]);
  });

  it("ignores non-.ev-rich selectors (out of scope)", () => {
    // .ev-connection-tips only has a light override here, no base peer.
    const css = [
      ".ev-card { color:#fff; }",
      "html.light-mode .ev-connection-tips { color:#111827; }",
    ].join("\n");
    expect(missing(css)).toEqual([]);
  });
});

describe("checkSource — end to end over a blade string", () => {
  it("passes on a fully-paired <style> block", () => {
    const src = wrap(
      [
        ".ev-rich .text-dark { color:#e8eaf0; }",
        "html.light-mode .ev-rich .text-dark { color:#111827; }",
      ].join("\n"),
    );
    expect(checkSource(src)).toEqual([]);
  });

  it("catches the historical regression: btn-outline-success with no light pair", () => {
    const src = wrap(".ev-rich .btn-outline-success { color:#34d399; border-color:rgba(52,211,153,0.45); }");
    expect(checkSource(src).map((m) => m.property).sort()).toEqual(["border-color", "color"]);
  });
});

describe("the live event-page.blade.php", () => {
  it("currently has every base .ev-rich color rule paired", () => {
    const src = fs.readFileSync(path.join(REPO_ROOT, EVENT_PAGE), "utf8");
    expect(checkSource(src)).toEqual([]);
  });
});
