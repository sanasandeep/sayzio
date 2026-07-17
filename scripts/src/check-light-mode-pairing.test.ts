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
  isThemeSafeColorValue,
  findMissingPairs,
  checkSource,
  checkTarget,
  countInlineThemedDecls,
  countLightOverrides,
  checkPartial,
  readViewsFileMap,
  targetViewRel,
  pageIsSelfContained,
  stripScriptBlocks,
  discoverUnknownStandalonePages,
  extractScriptBlocks,
  findScriptWhiteText,
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

  it("marks props with purely theme-token values as themeSafeProps", () => {
    const rules = parseRules(".a { color:var(--accent); border-color:#111; }");
    expect([...rules[0].props].sort()).toEqual(["border-color", "color"]);
    expect([...rules[0].themeSafeProps]).toEqual(["color"]);
  });

  it("an unsafe re-declaration of the same prop in the same rule wins (not theme-safe)", () => {
    const rules = parseRules(".a { color:var(--accent); color:#111; }");
    expect([...rules[0].themeSafeProps]).toEqual([]);
  });
});

/**
 * Theme-token value acceptance.
 *
 * A base rule whose value is purely `var(--…)` tokens / transparent / inherit /
 * currentColor already flips with (or is neutral to) the theme — it can never
 * wash out, so the guard auto-accepts it with no `html.light-mode` pair and no
 * allowlist entry. Literal colors (and var() with a literal fallback, the
 * undefined-var-dark-fallback trap) must keep failing.
 */
describe("isThemeSafeColorValue", () => {
  it.each([
    "var(--accent)",
    " var(--border-glass-light) ",
    "transparent",
    "inherit",
    "currentColor",
    "CURRENTCOLOR",
    "var(--a) var(--b)", // multi-value border-color
    "var(--accent) !important",
    "var(--a, var(--b))", // fallback that is itself a token
    "var(--a, transparent)",
    "var( --spaced-name )",
  ])("accepts %s", (v) => {
    expect(isThemeSafeColorValue(v)).toBe(true);
  });

  it.each([
    "#111",
    "rgba(255,255,255,.5)",
    "white",
    "var(--x, #0b0e14)", // literal dark fallback — undefined-var trap stays guarded
    "var(--x, rgba(0,0,0,.5))",
    "var(--a) #111", // mixed token + literal
    "{{ $color }}", // blade interpolation — theme-driven literal, not a token
    "var(x)", // not a custom property
    "",
    "   ",
  ])("rejects %s", (v) => {
    expect(isThemeSafeColorValue(v)).toBe(false);
  });
});

describe("findMissingPairs — theme-token values are treated as already paired", () => {
  it("passes a base color rule whose value is a bare var(--…) token", () => {
    expect(missing(".up-badge { color:var(--accent); }")).toEqual([]);
  });

  it("passes a base border-color rule whose value is a var(--…) token", () => {
    expect(missing(".up-feature:hover { border-color:var(--border-glass-light); }")).toEqual([]);
  });

  it("passes color:transparent (background-clip gradient headline pattern)", () => {
    expect(missing(".up-hero { color:transparent; }")).toEqual([]);
  });

  it("passes inherit and currentColor values", () => {
    expect(missing(".a { color:inherit; } .b { border-color:currentColor; }")).toEqual([]);
  });

  it("still flags the literal prop when the other prop is a token", () => {
    expect(missing(".a { color:var(--accent); border-color:#111; }")).toEqual([
      ".a { border-color }",
    ]);
  });

  it("still flags a var() with a literal dark fallback (undefined-var trap)", () => {
    expect(missing(".a { color:var(--maybe-missing, #0b0e14); }")).toEqual([".a { color }"]);
  });

  it("still flags plain literal colors (guard not weakened)", () => {
    expect(missing(".btn { color:#34d399; }")).toEqual([".btn { color }"]);
  });

  it("flags a selector whose prop is token-safe in one rule but literal in another", () => {
    const css = [".a { color:var(--accent); }", ".a { color:#111; }"].join("\n");
    expect(missing(css)).toEqual([".a { color }"]);
  });

  it("applies to checkSource (and therefore the discovery pass) too", () => {
    const src = wrap(".wl-title { color:var(--text-primary); }");
    expect(checkSource(src)).toEqual([]);
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
    // Whole-tree disk scan (memoized in blade-theme-scope, walks once per run);
    // generous timeout so parallel contention with the undefined-css-var guard's
    // live scan never trips the 5s default and flakes CI.
    30_000,
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
  }, 30_000);
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
 * Unknown standalone-page discovery (secondary warning pass).
 *
 * TARGETS only protects configured pages; the discovery pass must flag any
 * OTHER standalone theme-aware page (own <html>/<head> + loads theme-styles or
 * theme-bootstrap) whose <style> sets bare color/border-color without a
 * html.light-mode pair — as a warning steering it into TARGETS. It must NOT
 * flag configured targets, layout shells (@extends targets), self-contained
 * pages, layout-extending pages, fully-paired pages, or document/style markup
 * that only exists inside a <script> JS string (the email-preview srcdoc case).
 */
describe("discoverUnknownStandalonePages", () => {
  const THEME_STYLES = "common/partials/theme-styles.blade.php";
  const THEME_BOOTSTRAP = "common/partials/theme-bootstrap.blade.php";
  const standalone = (body: string, themeInclude = "common.partials.theme-styles") =>
    `<html><head>@include('${themeInclude}')${body}</head><body></body></html>`;
  const baseFiles = (extra: [string, string][]) =>
    new Map<string, string>([
      [THEME_STYLES, ":root{--x:#000}"],
      [THEME_BOOTSTRAP, ":root{--x:#000}"],
      ...extra,
    ]);

  it("flags a standalone theme-aware page with an unpaired base color rule", () => {
    const files = baseFiles([
      ["common/waitlist.blade.php", standalone("<style>.wl-title{color:#fff}</style>")],
    ]);
    const found = discoverUnknownStandalonePages(files, []);
    expect(found).toEqual([
      {
        rel: "common/waitlist.blade.php",
        missing: [{ selector: ".wl-title", property: "color" }],
        scriptWhiteHits: [],
      },
    ]);
  });

  it("flags a standalone theme-aware page whose <script>-built rows hardcode white text", () => {
    const files = baseFiles([
      [
        "common/queue.blade.php",
        standalone(
          `<script>rows.push('<div class="text-white text-sm">' + name + '</div>');</script>`,
        ),
      ],
    ]);
    const found = discoverUnknownStandalonePages(files, []);
    expect(found).toHaveLength(1);
    expect(found[0]!.rel).toBe("common/queue.blade.php");
    expect(found[0]!.missing).toEqual([]);
    expect(found[0]!.scriptWhiteHits).toHaveLength(1);
    expect(found[0]!.scriptWhiteHits[0]!.tokens).toEqual(["text-white"]);
  });

  it("stays quiet when a page's <script>-built rows use themed (non-white) classes", () => {
    const files = baseFiles([
      [
        "common/queue-ok.blade.php",
        standalone(
          `<script>rows.push('<div class="qk-row text-sm">' + name + '</div>');</script>`,
        ),
      ],
    ]);
    expect(discoverUnknownStandalonePages(files, [])).toEqual([]);
  });

  it("treats a theme-bootstrap page (rsvp-form family) as theme-aware too", () => {
    const files = baseFiles([
      [
        "common/invite.blade.php",
        standalone("<style>.inv-note{border-color:#333}</style>", "common.partials.theme-bootstrap"),
      ],
    ]);
    expect(discoverUnknownStandalonePages(files, []).map((f) => f.rel)).toEqual([
      "common/invite.blade.php",
    ]);
  });

  it("stays quiet on a standalone page whose color rules are fully paired", () => {
    const files = baseFiles([
      [
        "common/confirm.blade.php",
        standalone(
          "<style>.c-title{color:#fff} html.light-mode .c-title{color:#111}</style>",
        ),
      ],
    ]);
    expect(discoverUnknownStandalonePages(files, [])).toEqual([]);
  });

  it("skips pages already configured in TARGETS (they get the hard-fail path)", () => {
    const rel = "common/known.blade.php";
    const files = baseFiles([[rel, standalone("<style>.k{color:#fff}</style>")]]);
    const targets = [{ page: `${VIEWS_REL}/${rel}`, label: "known", allowlist: [] }];
    expect(discoverUnknownStandalonePages(files, targets)).toEqual([]);
  });

  it("skips layout shells — views that other views @extends", () => {
    const files = baseFiles([
      ["user/layouts/app.blade.php", standalone("<style>.chrome{color:#fff}</style>")],
      ["user/some-page.blade.php", "@extends('user.layouts.app')\n<div>x</div>"],
    ]);
    expect(discoverUnknownStandalonePages(files, [])).toEqual([]);
  });

  it("skips self-contained pages (own <html> but no theme system — overrides would be dead)", () => {
    const files = baseFiles([
      [
        "common/plain.blade.php",
        "<html><head><style>.p{color:#fff}</style></head><body></body></html>",
      ],
    ]);
    expect(discoverUnknownStandalonePages(files, [])).toEqual([]);
  });

  it("skips layout-extending pages (no own document — covered via TARGETS only)", () => {
    const files = baseFiles([
      [
        "public/extending.blade.php",
        "@extends('public.layouts.site')\n<style>.e{color:#fff}</style>",
      ],
      ["public/layouts/site.blade.php", standalone("")],
    ]);
    expect(discoverUnknownStandalonePages(files, []).map((f) => f.rel)).toEqual([]);
  });

  it("ignores document/style markup that only lives inside a <script> JS string", () => {
    // The email-preview srcdoc case: without stripping <script> blocks, the
    // JS-built '<html><head><style>body{color:#111}…' would make the page look
    // standalone AND contribute garbage unpaired rules.
    const files = baseFiles([
      [
        "admin/compose.blade.php",
        "@extends('admin.layouts.app')\n<script>var d = '<html><head><style>body{color:#111}' + 'a{color:#06c}</style></head><body>';</script>",
      ],
    ]);
    expect(discoverUnknownStandalonePages(files, [])).toEqual([]);
  });

  it("the live views tree currently has NO unknown standalone page with unpaired rules", () => {
    // Every standalone theme-aware page in the repo is either configured in
    // TARGETS or fully paired — the warning list must be empty so a future
    // finding is guaranteed to be NEW (a page that needs a TARGETS entry).
    expect(discoverUnknownStandalonePages(readViewsFileMap())).toEqual([]);
  }, 30_000);
});

describe("stripScriptBlocks", () => {
  it("removes <script> blocks including attributes and multiline bodies", () => {
    const src = `<div>a</div><script type="module">\nvar s = '<style>.x{color:red}</style>';\n</script><p>b</p>`;
    const out = stripScriptBlocks(src);
    expect(out).toContain("<div>a</div>");
    expect(out).toContain("<p>b</p>");
    expect(out).not.toContain("color:red");
  });

  it("leaves real <style> blocks outside scripts untouched", () => {
    const src = `<style>.y{color:blue}</style><script>var x=1;</script>`;
    expect(stripScriptBlocks(src)).toContain(".y{color:blue}");
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

/**
 * Script-built markup white-text check.
 *
 * Markup a page assembles client-side in JS strings never touches a base CSS
 * rule, so hardcoded white utility classes (`text-white`, …) inside <script>
 * blocks slipped past the pairing check — exactly how the event page's
 * "My swaps" rows shipped with invisible names in light mode. Both directions
 * are pinned: a reintroduced `text-white` in a script-built row fails, themed
 * classes (`.ev-strong`) and allowlisted always-dark islands pass.
 */
describe("extractScriptBlocks", () => {
  it("returns each script body with its line offset", () => {
    const src = "line0\n<script>\nvar a=1;\n</script>\n<script>b()</script>";
    const blocks = extractScriptBlocks(src);
    expect(blocks).toHaveLength(2);
    expect(blocks[0]!.body).toContain("var a=1;");
    expect(blocks[0]!.lineOffset).toBe(1);
  });
});

describe("findScriptWhiteText", () => {
  const scriptWrap = (js: string) => `<style>.x{color:#fff}</style>\n<script>\n${js}\n</script>`;

  it("flags text-white in a JS-built class attribute (the swaps regression)", () => {
    const src = scriptWrap(
      `el.innerHTML = '<div class="text-sm font-semibold text-white truncate">' + name + '</div>';`,
    );
    const hits = findScriptWhiteText(src);
    expect(hits).toHaveLength(1);
    expect(hits[0]!.tokens).toEqual(["text-white"]);
    expect(hits[0]!.classValue).toContain("truncate");
  });

  it("flags variant-prefixed, opacity-suffixed, arbitrary-hex and -50 gray tokens", () => {
    const src = scriptWrap(
      `x = '<span class="hover:text-white a">1</span>' +
           '<span class="text-white/80 b">2</span>' +
           '<span class="text-[#fff] c">3</span>' +
           '<span class="text-slate-50 d">4</span>';`,
    );
    const tokens = findScriptWhiteText(src).flatMap((h) => h.tokens);
    expect(tokens).toEqual(["hover:text-white", "text-white/80", "text-[#fff]", "text-slate-50"]);
  });

  it("handles escaped-quote class attributes inside double-quoted JS strings", () => {
    const src = scriptWrap(`el.innerHTML = "<div class=\\"text-white row\\">x</div>";`);
    expect(findScriptWhiteText(src)).toHaveLength(1);
  });

  it("stays quiet on themed classes and non-white utilities", () => {
    const src = scriptWrap(
      `el.innerHTML = '<div class="text-sm font-semibold ev-strong truncate">' + name +
        '<span class="ev-muted-lite text-whitespace whitespace-nowrap text-slate-500">y</span></div>';`,
    );
    expect(findScriptWhiteText(src)).toEqual([]);
  });

  it("ignores text-white in page markup OUTSIDE script blocks (styled by CSS, other guards)", () => {
    const src = `<div class="text-white">badge</div><script>var a = 1;</script>`;
    expect(findScriptWhiteText(src)).toEqual([]);
  });

  it("respects a scriptAllowlist entry matching the class value", () => {
    const src = scriptWrap(`x = '<span class="badge-on-gradient text-white">v</span>';`);
    expect(findScriptWhiteText(src)).toHaveLength(1);
    expect(
      findScriptWhiteText(src, [
        { match: "badge-on-gradient", reason: "white label on saturated gradient badge" },
      ]),
    ).toEqual([]);
  });

  it("reports a usable line number", () => {
    const src = `a\nb\n<script>\nvar x;\nel.innerHTML='<i class="text-white"></i>';\n</script>`;
    expect(findScriptWhiteText(src)[0]!.line).toBe(5);
  });
});

describe("script white-text — live pages", () => {
  it("every configured TARGETS page currently passes (event page uses .ev-strong)", () => {
    for (const t of TARGETS) {
      const src = fs.readFileSync(path.join(REPO_ROOT, t.page), "utf8");
      const hits = findScriptWhiteText(src, t.scriptAllowlist);
      expect(hits, `${t.label} (${t.page})`).toEqual([]);
    }
  });

  it("a reintroduced text-white in the event page swaps renderer would fail via checkTarget shape", () => {
    const eventPage = TARGETS.find((t) => t.page.endsWith("event-page.blade.php"))!;
    const src = fs.readFileSync(path.join(REPO_ROOT, eventPage.page), "utf8");
    // Sanity: the swaps renderer builds rows in a script block with the themed class.
    expect(src).toContain("ev-strong truncate");
    const regressed = src.replace("ev-strong truncate", "text-white truncate");
    const hits = findScriptWhiteText(regressed, eventPage.scriptAllowlist);
    expect(hits).toHaveLength(1);
    expect(hits[0]!.tokens).toEqual(["text-white"]);
  });
});
