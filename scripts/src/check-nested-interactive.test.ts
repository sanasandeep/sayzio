import { describe, it, expect } from "vitest";
import { scanSource, blankNonMarkupContexts } from "./check-nested-interactive.js";
import { VIEWS_REL, readViewsFileMap } from "./lib/blade-theme-scope.js";

/**
 * Regression suite for the nested-interactive guard.
 *
 * The guard fires when an interactive element (<button>, <a>, <input>, ...)
 * is nested inside an open <button> or <a>. The HTML parser force-closes
 * the outer element at the nested tag, ejecting later markup from its layout
 * column (see .agents/memory/nested-button-parser-ejection.md — this broke
 * the admin Block Defaults editor).
 *
 * Run: pnpm --filter @workspace/scripts run test
 */

const hits = (src: string) =>
  scanSource("test.blade.php", src).map((o) => `${o.tag} in ${o.inside}`);

describe("scanSource — flags nested interactive elements", () => {
  it("flags <button> inside <button>", () => {
    expect(hits("<button><span>x</span><button>y</button></button>")).toEqual([
      "button in button",
    ]);
  });

  it("flags <button> inside <a>", () => {
    expect(hits('<a href="/x"><button type="button">copy</button></a>')).toEqual(["button in a"]);
  });

  it("flags <a> inside <button>", () => {
    expect(hits('<button><a href="/x">go</a></button>')).toEqual(["a in button"]);
  });

  it("flags <a> inside <a>", () => {
    expect(hits('<a href="/x"><a href="/y">y</a></a>')).toEqual(["a in a"]);
  });

  it("flags <input> inside <button>", () => {
    expect(hits('<button><input type="checkbox"></button>')).toEqual(["input in button"]);
  });

  it("flags <label>, <select>, <textarea> inside <a>", () => {
    expect(hits('<a href="/x"><label>l</label><select></select><textarea></textarea></a>')).toEqual(
      ["label in a", "select in a", "textarea in a"],
    );
  });

  it("flags across intervening non-interactive wrappers", () => {
    expect(hits('<a href="/x"><div><span><button>b</button></span></div></a>')).toEqual([
      "button in a",
    ]);
  });

  it("flags across multiple lines and reports the nested tag line", () => {
    const src = '<a href="/x"\n   class="c">\n  <div>\n    <button>b</button>\n  </div>\n</a>';
    const [o] = scanSource("f.blade.php", src);
    expect(o?.line).toBe(4);
    expect(o?.tag).toBe("button");
    expect(o?.inside).toBe("a");
    expect(o?.insideLine).toBe(1);
  });

  it("flags multiple offenders in one file", () => {
    const src = "<button><button>a</button></button>\n<a href='/'><input></a>";
    expect(hits(src)).toEqual(["button in button", "input in a"]);
  });
});

describe("scanSource — stays quiet on valid markup", () => {
  it("ignores sibling buttons", () => {
    expect(hits("<button>a</button><button>b</button>")).toEqual([]);
  });

  it("ignores a button after an anchor closes", () => {
    expect(hits('<a href="/x">go</a><button>b</button>')).toEqual([]);
  });

  it("ignores buttons inside labels/divs (containers we don't police)", () => {
    expect(hits('<label><input type="checkbox"><span>x</span></label>')).toEqual([]);
  });

  it("ignores tag-like text inside quoted attribute values", () => {
    expect(hits(`<button @click="open = a < b" x-html="'<a href=x>y</a>'">t</button>`)).toEqual([]);
  });

  it("ignores markup inside Blade comments", () => {
    expect(hits("{{-- <button><button>x</button></button> --}}")).toEqual([]);
  });

  it("ignores markup inside HTML comments", () => {
    expect(hits("<!-- <a href='/'><button>x</button></a> -->")).toEqual([]);
  });

  it("ignores markup inside @verbatim blocks", () => {
    expect(hits("@verbatim <button><button>x</button></button> @endverbatim")).toEqual([]);
  });

  it("ignores markup inside <script> bodies", () => {
    expect(hits("<script>el.innerHTML = '<button><button>x</button></button>';</script>")).toEqual(
      [],
    );
  });

  it("ignores tag-like text inside Blade echoes", () => {
    expect(hits("<button>{{ $a < $b ? 'x' : 'y' }}</button>")).toEqual([]);
  });

  it("does not treat @if/@else branch reopenings as nesting", () => {
    const src = "@if($x)\n<button>a\n@else\n<button>b\n@endif\n</button>";
    expect(hits(src)).toEqual([]);
  });

  it("does not treat @forelse @empty branches as nesting", () => {
    const src = "@forelse($xs as $x)\n<a href='/'>x</a>\n@empty\n<a href='/'>none</a>\n@endforelse";
    expect(hits(src)).toEqual([]);
  });

  it("ignores span with role=button inside <a> (the sanctioned fix)", () => {
    expect(hits('<a href="/x"><span role="button" tabindex="0">copy</span></a>')).toEqual([]);
  });
});

describe("blankNonMarkupContexts", () => {
  it("blanks script bodies but keeps the tag", () => {
    const out = blankNonMarkupContexts('<script x-a="1">var b = 2;</script>');
    expect(out).toContain('<script x-a="1">');
    expect(out).not.toContain("var b");
    expect(out).toContain("</script>");
  });

  it("preserves newlines/length when blanking", () => {
    const src = "{{-- a\nb --}}Z";
    const out = blankNonMarkupContexts(src);
    expect(out.length).toBe(src.length);
    expect(out.endsWith("Z")).toBe(true);
    expect(out.split("\n").length).toBe(src.split("\n").length);
  });
});

describe("live repo", () => {
  it(
    "passes on all real blade files under the scan roots",
    () => {
      for (const [rel, src] of readViewsFileMap()) {
        const relFromRepo = `${VIEWS_REL}/${rel}`;
        const offenders = scanSource(relFromRepo, src).map(
          (o) => `${o.file}:${o.line} <${o.tag}> inside <${o.inside}>`,
        );
        expect(offenders, `${relFromRepo} nests interactive elements`).toEqual([]);
      }
    },
    30_000,
  );
});
