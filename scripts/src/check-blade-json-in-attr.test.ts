import { describe, it, expect } from "vitest";
import { scanSource, blankNonAttributeContexts } from "./check-blade-json-in-attr.js";
import { VIEWS_REL, readViewsFileMap } from "./lib/blade-theme-scope.js";

/**
 * Regression suite for the blade-json-in-attr guard.
 *
 * The guard fires when `@json(...)` sits inside a double-quoted HTML
 * attribute (x-data / @click / x-show / :style ...), where its literal `"`
 * output truncates the attribute and silently dead-clicks Alpine components
 * (see .agents/memory/blade-json-in-double-quoted-attr.md). It must stay
 * quiet on legitimate `@json` usage: inside <script> blocks, single-quoted
 * attributes, Blade comments, @verbatim, and the escaped `@@json`.
 *
 * Run: pnpm --filter @workspace/scripts run test
 */

const attrs = (src: string) => scanSource("test.blade.php", src).map((o) => o.attr);

describe("scanSource — flags @json inside double-quoted attributes", () => {
  it("flags @json in x-data", () => {
    expect(attrs('<div x-data="{ items: @json($items) }"></div>')).toEqual(["x-data"]);
  });

  it("flags @json in an Alpine @click handler", () => {
    expect(attrs('<button @click="pick(@json($opt))">x</button>')).toEqual(["@click"]);
  });

  it("flags @json in x-show", () => {
    expect(attrs('<div x-show="@json($flag)"></div>')).toEqual(["x-show"]);
  });

  it("flags @json in a bound :style attribute", () => {
    expect(attrs('<div :style="@json($style)"></div>')).toEqual([":style"]);
  });

  it("flags @json in a modifier-suffixed handler (@click.prevent)", () => {
    expect(attrs('<a @click.prevent="go(@json($to))">x</a>')).toEqual(["@click.prevent"]);
  });

  it("flags @json in a plain data- attribute", () => {
    expect(attrs('<div data-config="@json($cfg)"></div>')).toEqual(["data-config"]);
  });

  it("flags a multi-line attribute value and reports the @json line", () => {
    const src = '<div\n  x-data="{\n    items: @json($items)\n  }"></div>';
    const [o] = scanSource("f.blade.php", src);
    expect(o?.line).toBe(3);
    expect(o?.attr).toBe("x-data");
  });

  it("flags @json on a <script> TAG attribute (not its body)", () => {
    expect(attrs('<script x-data="@json($x)">var a = 1;</script>')).toEqual(["x-data"]);
  });

  it("flags multiple offenders in one file", () => {
    const src = '<div x-data="@json($a)"></div>\n<div @click="f(@json($b))"></div>';
    expect(attrs(src)).toEqual(["x-data", "@click"]);
  });
});

describe("scanSource — stays quiet on legitimate @json usage", () => {
  it("ignores @json inside a <script> block", () => {
    expect(attrs("<script>var items = @json($items);</script>")).toEqual([]);
  });

  it("ignores @json inside a <script> block that also has attributes", () => {
    expect(attrs('<script type="module">var x = @json($x);</script>')).toEqual([]);
  });

  it("ignores @json inside a single-quoted attribute", () => {
    expect(attrs("<div x-data='{ items: @json($items) }'></div>")).toEqual([]);
  });

  it("ignores @js(...) in a double-quoted attribute (the correct fix)", () => {
    expect(attrs('<div x-data="{ items: @js($items) }"></div>')).toEqual([]);
  });

  it("ignores @json outside any attribute", () => {
    expect(attrs("<p>@json($x)</p>")).toEqual([]);
  });

  it("ignores the escaped @@json (renders literally)", () => {
    expect(attrs('<div x-data="@@json($x)"></div>')).toEqual([]);
  });

  it("ignores @json inside a Blade comment", () => {
    expect(attrs('{{-- <div x-data="@json($x)"></div> --}}')).toEqual([]);
  });

  it("ignores @json inside an @verbatim block", () => {
    expect(attrs('@verbatim <div x-data="@json($x)"></div> @endverbatim')).toEqual([]);
  });

  it("ignores @json inside an HTML comment", () => {
    expect(attrs('<!-- <div x-data="@json($x)"></div> -->')).toEqual([]);
  });

  it("does not cross a closing quote into the next attribute", () => {
    // First attribute closes cleanly; @json is in a <script> after it.
    const src = '<div x-data="{ open: false }"></div><script>var a = @json($a);</script>';
    expect(attrs(src)).toEqual([]);
  });
});

describe("blankNonAttributeContexts", () => {
  it("blanks script bodies but keeps the tag (and its attributes)", () => {
    const out = blankNonAttributeContexts('<script x-a="1">var b = 2;</script>');
    expect(out).toContain('<script x-a="1">');
    expect(out).not.toContain("var b");
    expect(out).toContain("</script>");
  });

  it("preserves newlines/length when blanking", () => {
    const src = "{{-- a\nb --}}Z";
    const out = blankNonAttributeContexts(src);
    expect(out.length).toBe(src.length);
    expect(out.endsWith("Z")).toBe(true);
    expect(out.split("\n").length).toBe(src.split("\n").length);
  });
});

describe("live repo", () => {
  // Reuses the shared, memoized whole-tree read (`readViewsFileMap`); the
  // guard's SCAN_ROOTS is exactly `[VIEWS_REL]`, so this covers the same files.
  it(
    "passes on all real blade files under the scan roots",
    () => {
      for (const [rel, src] of readViewsFileMap()) {
        const relFromRepo = `${VIEWS_REL}/${rel}`;
        const offenders = scanSource(relFromRepo, src);
        expect(offenders, `${relFromRepo} has @json( inside a double-quoted attribute`).toEqual([]);
      }
    },
    30_000,
  );
});
