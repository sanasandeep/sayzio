import { describe, it, expect } from "vitest";
import {
  NEUTRAL_ACCENT_VARS,
  STANDALONE_FILES,
  FILE_ALLOWLIST,
  stripComments,
  parseCustomPropDecls,
  extractInlineStyleDecls,
  findColorVarRefs,
  classifyScope,
  resolveReference,
  analyze,
  scanRepo,
  type ResolveContext,
  type ScanInput,
} from "./check-undefined-css-var-fallback.js";

/**
 * Regression suite for the undefined-CSS-var dark/white fallback guard.
 *
 * The guard's whole value is that it fires when a `var(--name, <color-literal>)`
 * reference has `--name` UNDECLARED in the scope that renders it (the literal
 * then freezes and breaks the theme toggle), and stays quiet when the token IS
 * declared (theme-styles, the file's own :root, a per-instance component var, or
 * the theme-neutral accent allowlist). Both directions are pinned here so a
 * future refactor can't silently disable the check (false negatives) or start
 * flagging correct references (false positives). The live repo is also asserted
 * to currently pass.
 *
 * Run: pnpm --filter @workspace/scripts run test
 */

const ctx = (over: Partial<ResolveContext> = {}): ResolveContext => ({
  scope: "app",
  localDecls: new Set<string>(),
  themeStylesDecls: new Set<string>(),
  componentVars: new Set<string>(),
  ...over,
});

describe("stripComments", () => {
  it("removes blade {{-- --}} comments so a var() inside is never counted", () => {
    const out = stripComments("{{-- var(--surface, #fff) --}} .a{}");
    expect(out).not.toContain("--surface");
  });

  it("removes HTML <!-- --> comments", () => {
    expect(stripComments("<!-- --x: #000 --> .a{}")).not.toContain("--x");
  });

  it("removes CSS /* */ comments", () => {
    expect(stripComments(".a { /* var(--y, #000) */ color:red; }")).not.toContain("--y");
  });
});

describe("parseCustomPropDecls", () => {
  it("captures a `--x:` declaration but NOT a `var(--x, …)` reference", () => {
    const decls = parseCustomPropDecls(":root { --surface: #101014; } .a { color: var(--text, #fff); }");
    expect(decls.has("--surface")).toBe(true);
    expect(decls.has("--text")).toBe(false);
  });

  it("captures declarations from inline style attributes too", () => {
    expect(parseCustomPropDecls(`<div style="--tile-glow: rgba(0,0,0,.2);"></div>`).has("--tile-glow")).toBe(true);
  });
});

describe("extractInlineStyleDecls", () => {
  it("captures a custom prop set inside a double-quoted style attribute", () => {
    expect([...extractInlineStyleDecls(`<div style="--sc-color:#f00; color:red;"></div>`)]).toEqual(["--sc-color"]);
  });

  it("captures from single-quoted style attributes", () => {
    expect([...extractInlineStyleDecls(`<div style='--c:#0f0;'></div>`)]).toEqual(["--c"]);
  });

  it("does NOT capture a `--x:` that lives in a <style> block (not an inline attr)", () => {
    expect([...extractInlineStyleDecls(`<style>:root{--surface:#111;}</style>`)]).toEqual([]);
  });
});

describe("findColorVarRefs", () => {
  it("captures a hex-fallback reference", () => {
    expect(findColorVarRefs("color: var(--text, #ffffff);")).toEqual([{ name: "--text", kind: "hex" }]);
  });

  it("captures rgb/rgba and hsl/hsla fallbacks and reports their kind", () => {
    const refs = findColorVarRefs(
      "a{color:var(--a, rgba(0,0,0,.2))} b{color:var(--b, hsl(0,0%,0%))}",
    );
    expect(refs).toEqual([
      { name: "--a", kind: "rgb" },
      { name: "--b", kind: "hsl" },
    ]);
  });

  it("ignores a var() whose fallback is NOT a color literal (another var, keyword, gradient, number)", () => {
    const src = [
      "color: var(--x, var(--y));",
      "color: var(--z, transparent);",
      "background: var(--g, linear-gradient(90deg,#000,#fff));",
      "opacity: var(--o, 0.5);",
    ].join("\n");
    expect(findColorVarRefs(src)).toEqual([]);
  });

  it("ignores a bare var() with no fallback", () => {
    expect(findColorVarRefs("color: var(--surface);")).toEqual([]);
  });

  it("collapses duplicate names within a file", () => {
    expect(findColorVarRefs("var(--t, #fff); var(--t, #000);")).toEqual([{ name: "--t", kind: "hex" }]);
  });
});

describe("classifyScope", () => {
  it("classifies the app shell surfaces as `app`", () => {
    expect(classifyScope("user/links/show.blade.php")).toBe("app");
    expect(classifyScope("admin/dashboard.blade.php")).toBe("app");
    expect(classifyScope("components/button.blade.php")).toBe("app");
    expect(classifyScope("common/partials/theme-styles.blade.php")).toBe("app");
  });

  it("classifies the declared standalone pages as `standalone`", () => {
    for (const f of STANDALONE_FILES) expect(classifyScope(f)).toBe("standalone");
  });

  it("classifies separate theming systems (home/public/biolink) as `excluded`", () => {
    expect(classifyScope("home.blade.php")).toBe("excluded");
    expect(classifyScope("welcome.blade.php")).toBe("excluded");
    expect(classifyScope("public/pricing.blade.php")).toBe("excluded");
    expect(classifyScope("common/biolink.blade.php")).toBe("excluded");
  });
});

describe("resolveReference — resolution order", () => {
  it("ok-accent: a theme-neutral accent var is always allowed", () => {
    const name = NEUTRAL_ACCENT_VARS[0]!.name;
    expect(resolveReference(name, ctx())).toBe("ok-accent");
  });

  it("ok-component: a per-instance inline component var is allowed", () => {
    expect(resolveReference("--tile-glow", ctx({ componentVars: new Set(["--tile-glow"]) }))).toBe("ok-component");
  });

  it("ok-local: a var declared in the same file is allowed", () => {
    expect(resolveReference("--cc-bg", ctx({ localDecls: new Set(["--cc-bg"]) }))).toBe("ok-local");
  });

  it("ok-theme: an app-scoped var declared in theme-styles is allowed", () => {
    expect(resolveReference("--surface", ctx({ scope: "app", themeStylesDecls: new Set(["--surface"]) }))).toBe(
      "ok-theme",
    );
  });

  it("violation: an undeclared var in app scope is a violation", () => {
    expect(resolveReference("--nope", ctx({ scope: "app" }))).toBe("violation");
  });

  it("violation: a standalone page does NOT get the theme-styles allowance", () => {
    expect(
      resolveReference("--surface", ctx({ scope: "standalone", themeStylesDecls: new Set(["--surface"]) })),
    ).toBe("violation");
  });
});

describe("analyze — end to end over an in-memory file map", () => {
  const themeStylesSrc = ":root { --surface: #101014; --text: #fff; } html.light-mode { --surface:#fff; --text:#111; }";

  it("passes when an app file references only tokens declared in theme-styles", () => {
    const files = new Map([["user/x.blade.php", "<style>.a{background:var(--surface,#101014);color:var(--text,#fff);}</style>"]]);
    expect(analyze({ files, themeStylesSrc })).toEqual([]);
  });

  it("flags an app file referencing a token declared in NO scope", () => {
    const files = new Map([["user/x.blade.php", "<style>.a{color:var(--ghost,#0a0a0a);}</style>"]]);
    expect(analyze({ files, themeStylesSrc })).toEqual([
      { file: "user/x.blade.php", name: "--ghost", kind: "hex", scope: "app" },
    ]);
  });

  it("does NOT flag an excluded file (separate theming system)", () => {
    const files = new Map([["home.blade.php", "<style>.a{color:var(--ghost,#0a0a0a);}</style>"]]);
    expect(analyze({ files, themeStylesSrc })).toEqual([]);
  });

  it("recognises a component var declared inline in a DIFFERENT file (global set)", () => {
    const files = new Map<string, string>([
      ["user/setter.blade.php", `<div style="--tile-glow: rgba(0,0,0,.2);"></div>`],
      ["user/user.blade.php", "<style>.a{box-shadow:0 0 8px var(--tile-glow, rgba(1,1,1,.3));}</style>"],
    ]);
    expect(analyze({ files, themeStylesSrc })).toEqual([]);
  });

  it("allows a token declared locally in the same file", () => {
    const files = new Map([
      ["user/x.blade.php", "<style>:root{--local:#222;} .a{color:var(--local,#333);}</style>"],
    ]);
    expect(analyze({ files, themeStylesSrc })).toEqual([]);
  });

  it("flags a standalone page whose token is only in theme-styles (not its own :root)", () => {
    const input: ScanInput = {
      files: new Map([[STANDALONE_FILES[0]!, "<style>.a{color:var(--surface,#101014);}</style>"]]),
      themeStylesSrc,
    };
    const out = analyze(input);
    expect(out).toHaveLength(1);
    expect(out[0]!.scope).toBe("standalone");
  });

  it("skips a file in the FILE_ALLOWLIST", () => {
    const allowed = FILE_ALLOWLIST[0]!.file;
    const files = new Map([[allowed, "<style>.a{color:var(--ghost,#0a0a0a);}</style>"]]);
    expect(analyze({ files, themeStylesSrc })).toEqual([]);
  });
});

describe("the live repo", () => {
  it("currently passes the guard (every color-var reference resolves in scope)", () => {
    expect(scanRepo()).toEqual([]);
  });
});
