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
  countInlineThemedDecls,
  countLightOverrides,
  checkPartial,
  readViewsFileMap,
  targetViewRel,
  pageIsSelfContained,
} from "./check-light-mode-pairing.js";
import { VIEWS_REL } from "./lib/blade-theme-scope.js";

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

describe("findMissingPairs — ancestor-prefixed (suffix) matching", () => {
  it("pairs a bare base selector via a light override that adds an ancestor", () => {
    const css = [
      ".hashtag-pill { color:rgba(255,255,255,0.5); }",
      "html.light-mode .events-page-body .hashtag-pill { color:rgba(15,23,42,0.55); }",
    ].join("\n");
    expect(missing(css)).toEqual([]);
  });

  it("pairs a bare base selector for grouped ancestor-prefixed light overrides", () => {
    const css = [
      ".link-accent, .tier-toggle-link { color:#8fa8ff; }",
      "html.light-mode .events-page-body .link-accent, html.light-mode .events-page-body .tier-toggle-link { color:#2342c7; }",
    ].join("\n");
    expect(missing(css)).toEqual([]);
  });

  it("does NOT mask a genuinely-missing pair when only the class suffix coincides", () => {
    // base `.pill` shares a suffix with `.hashtag-pill` but is a different
    // selector — the leading-space boundary must prevent a false pair.
    const css = [
      ".pill { color:#fff; }",
      "html.light-mode .events-page-body .hashtag-pill { color:rgba(15,23,42,0.55); }",
    ].join("\n");
    expect(missing(css)).toEqual([".pill { color }"]);
  });

  it("does NOT pair across a compound-class boundary (no descendant combinator)", () => {
    // `.a.border` is `.a` combined with `.border` (no space) — it must not
    // pair base `.border`.
    const css = [
      ".border { border-color:rgba(255,255,255,0.1); }",
      "html.light-mode .a.border { border-color:rgba(15,23,42,0.1); }",
    ].join("\n");
    expect(missing(css)).toEqual([".border { border-color }"]);
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
    "%s currently has every base color rule paired (or allowlisted) and every inline-styled partial covered",
    (_label, target) => {
      const abs = path.join(REPO_ROOT, target.page);
      expect(fs.existsSync(abs), `${target.page} should exist`).toBe(true);
      const result = checkTarget(target, readViewsFileMap());
      expect(result.error).toBeUndefined();
      expect(result.scopeError).toBeUndefined();
      expect(result.missing).toEqual([]);
      expect(result.partialMismatches).toEqual([]);
    },
  );

  it("every configured target participates in the app theme (loads theme-styles, not self-contained)", () => {
    const files = readViewsFileMap();
    for (const target of TARGETS) {
      const rel = targetViewRel(target.page);
      expect(rel, `${target.page} should live under ${VIEWS_REL}`).not.toBeNull();
      expect(
        pageIsSelfContained(rel as string, files),
        `${target.page} must load theme-styles (extend a themed layout), or the html.light-mode pairing check is meaningless`,
      ).toBe(false);
    }
  });
});

/**
 * Theme-scope blind spot (shared with the undefined-css-var guard).
 *
 * The pairing check only means anything for a page that actually receives the
 * app's `html.light-mode` toggle — i.e. one that loads theme-styles through its
 * layout. A page shipping its own `<html>`/`<head>` and never @including
 * theme-styles never gets that class, so its overrides are dead. The guard must
 * detect that (via the same declaresOwnDocument/includesThemeStyles helpers) and
 * report it as a misconfiguration instead of silently "passing" a page whose
 * light-mode overrides never fire.
 */
describe("theme-scope detection", () => {
  const THEME_STYLES = "common/partials/theme-styles.blade.php";

  it("targetViewRel strips the VIEWS_REL prefix, or returns null when outside the tree", () => {
    expect(targetViewRel(`${VIEWS_REL}/common/event-page.blade.php`)).toBe(
      "common/event-page.blade.php",
    );
    expect(targetViewRel("some/other/place.blade.php")).toBeNull();
  });

  it("a page that @extends a layout is NOT self-contained (no own <html>)", () => {
    const files = new Map<string, string>([
      ["public/some-page.blade.php", "@extends('public.layouts.site')\n@section('content')<style>.a{color:#fff}</style>@endsection"],
    ]);
    expect(pageIsSelfContained("public/some-page.blade.php", files)).toBe(false);
  });

  it("a page with its own <html> that transitively @includes theme-styles is NOT self-contained", () => {
    const files = new Map<string, string>([
      ["common/self.blade.php", "<html><head>@include('common.partials.head')</head><body></body></html>"],
      ["common/partials/head.blade.php", "@include('common.partials.theme-styles')"],
      [THEME_STYLES, ":root{--x:#000}"],
    ]);
    expect(pageIsSelfContained("common/self.blade.php", files)).toBe(false);
  });

  it("a page with its own <html> that never loads theme-styles IS self-contained", () => {
    const files = new Map<string, string>([
      ["common/self.blade.php", "<html><head><style>.a{color:#fff}</style></head><body></body></html>"],
      [THEME_STYLES, ":root{--x:#000}"],
    ]);
    expect(pageIsSelfContained("common/self.blade.php", files)).toBe(true);
  });

  it("checkTarget flags a self-contained target with a scopeError and skips the pairing check", () => {
    const page = `${VIEWS_REL}/common/self.blade.php`;
    // .a sets a base color with NO html.light-mode pair — normally a `missing`,
    // but because the page is self-contained it must short-circuit to scopeError.
    const files = new Map<string, string>([
      ["common/self.blade.php", "<html><head><style>.a{color:#fff}</style></head><body></body></html>"],
      [THEME_STYLES, ":root{--x:#000}"],
    ]);
    const result = checkTarget({ page, label: "self-contained", allowlist: [] }, files);
    expect(result.scopeError).toBeDefined();
    expect(result.scopeError).toContain("theme-styles");
    expect(result.missing).toEqual([]);
    expect(result.partialMismatches).toEqual([]);
  });

  it("checkTarget does NOT short-circuit an in-scope (layout-extending) target — it falls through to the normal disk-read path", () => {
    // In-scope per the map (no own <html>), but the page does not exist on disk.
    // Because it is NOT self-contained, checkTarget must skip the scope screen and
    // proceed to read the real file — surfacing a read `error`, not a `scopeError`.
    const page = `${VIEWS_REL}/common/does-not-exist.blade.php`;
    const files = new Map<string, string>([
      ["common/does-not-exist.blade.php", "@extends('public.layouts.site')\n<style>.a{color:#fff}</style>"],
    ]);
    const result = checkTarget({ page, label: "in-scope", allowlist: [] }, files);
    expect(result.scopeError).toBeUndefined();
    expect(result.error).toBeDefined();
  });

  it("the normal pairing check still runs for a real in-scope target when a files map is passed", () => {
    // A live TARGET is in-scope; passing the map must not disturb its clean pass.
    const result = checkTarget(TARGETS[0]!, readViewsFileMap());
    expect(result.scopeError).toBeUndefined();
    expect(result.error).toBeUndefined();
    expect(result.missing).toEqual([]);
  });

  it("without a files map, checkTarget skips the scope screen (pure-unit backward compat)", () => {
    // Existing unit tests call checkTarget(target) with no map; the scope screen
    // must not run (and must not throw) in that mode.
    const realTarget = TARGETS[0]!;
    const result = checkTarget(realTarget);
    expect(result.scopeError).toBeUndefined();
  });
});

/**
 * Inline-styled partial coverage (structural count proxy).
 *
 * Some partials a page includes bake their dark colors as inline
 * `style="…color:{{ $var }}…"` attributes, so there is no base rule for
 * findMissingPairs to catch. The count check pins that every themed inline
 * color/border has a paired `html.light-mode` override, and that adding/removing
 * one without its pair (the historical washed-out regression on the event
 * page's tips/pairings sections) trips the guard.
 */
describe("countInlineThemedDecls", () => {
  it("counts a themed inline color but ignores literal (inherit) values", () => {
    const src = `<a style="color:{{ $c }}; background:{{ $bg }};"><i style="color:inherit;"></i></a>`;
    expect(countInlineThemedDecls(src)).toEqual({ color: 1, "border-color": 0 });
  });

  it("buckets border shorthand and border-color together, only when themed", () => {
    const src = [
      `<div style="border:1px solid {{ $b }};"></div>`,
      `<div style="border-color:{{ $b2 }};"></div>`,
      `<div style="border:1px solid #fff;"></div>`, // literal — not themed
    ].join("");
    expect(countInlineThemedDecls(src)).toEqual({ color: 0, "border-color": 2 });
  });

  it("does not scan the partial's own <style> block (hover/transition rules)", () => {
    const src = [
      `<span style="color:{{ $c }};">x</span>`,
      `<style>.card:hover { border-color: {{ $accent }}; color: {{ $c }}; }</style>`,
    ].join("");
    // Only the inline attribute counts; the <style> block is ignored.
    expect(countInlineThemedDecls(src)).toEqual({ color: 1, "border-color": 0 });
  });

  it("supports single-quoted style attributes", () => {
    expect(countInlineThemedDecls(`<span style='color:{{ $c }};'>x</span>`)).toEqual({
      color: 1,
      "border-color": 0,
    });
  });
});

describe("countLightOverrides", () => {
  it("counts light-mode color/border-color overrides for the given scope classes", () => {
    const rules = parseRules(
      [
        "html.light-mode .ev-connection-tips { color:#111827; }",
        "html.light-mode .ev-connection-tip-card { border-color:rgba(61,107,255,.16); background:#fff; }",
        "html.light-mode .ev-connection-tip-card > span:last-child { color:#3d6bff; }",
      ].join("\n"),
    );
    expect(countLightOverrides(rules, ["ev-connection-tips", "ev-connection-tip-card"])).toEqual({
      color: 2,
      "border-color": 1,
    });
  });

  it("ignores base (non-light-mode) rules and out-of-scope selectors", () => {
    const rules = parseRules(
      [
        ".ev-connection-tips { color:#f4f4f8; }", // base, not light — ignored
        "html.light-mode .ltp-pairings { color:#111827; }", // different scope
      ].join("\n"),
    );
    expect(countLightOverrides(rules, ["ev-connection-tips"])).toEqual({ color: 0, "border-color": 0 });
  });
});

describe("checkPartial — count parity between inline colors and light overrides", () => {
  const spec = { name: "demo", file: "x", scopeClasses: ["demo-card"] };

  it("passes when every themed inline color has exactly one light override", () => {
    const partial = `<div class="demo-card" style="color:{{ $t }}; border:1px solid {{ $b }};"></div>`;
    const page = [
      "<style>",
      "html.light-mode .demo-card { color:#111; border-color:#ccc; }",
      "</style>",
    ].join("\n");
    expect(checkPartial(partial, page, spec)).toEqual([]);
  });

  it("flags a themed inline color added without a light override (the washout regression)", () => {
    const partial = `<div class="demo-card" style="color:{{ $t }};"></div>`;
    const page = "<style></style>"; // forgot the override
    expect(checkPartial(partial, page, spec)).toEqual([
      { partial: "demo", property: "color", inline: 1, overrides: 0, expected: 1 },
    ]);
  });

  it("flags an orphan light override with no themed inline peer", () => {
    const partial = `<div class="demo-card"></div>`; // no themed inline color
    const page = "<style>html.light-mode .demo-card { color:#111; }</style>";
    expect(checkPartial(partial, page, spec)).toEqual([
      { partial: "demo", property: "color", inline: 0, overrides: 1, expected: 0 },
    ]);
  });

  it("respects a partial allowlist entry that intentionally needs no override", () => {
    const partial = `<div class="demo-card" style="color:{{ $t }};"></div>`;
    const page = "<style></style>";
    const allowed = {
      name: "demo",
      file: "x",
      scopeClasses: ["demo-card"],
      allowlist: [{ property: "color" as const, inlineWithoutOverride: 1, reason: "theme-neutral" }],
    };
    expect(checkPartial(partial, page, allowed)).toEqual([]);
  });
});
