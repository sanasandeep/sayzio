import { describe, it, expect } from "vitest";
import fs from "node:fs";
import path from "node:path";
import {
  scanSource,
  blankSafeContexts,
  listFiles,
  REPO_ROOT,
} from "./check-alpine-line-comments.js";
import { VIEWS_REL, readViewsFileMap } from "./lib/blade-theme-scope.js";

/**
 * Regression suite for the alpine-line-comments guard.
 *
 * The guard fires when `//` line comments appear inside a double-quoted
 * Alpine attribute value (x-data / x-init / x-* / @* / :*). When the
 * browser evaluates an attribute value it collapses newlines to a single
 * line, so `//` swallows everything after it on that logical line —
 * including the closing ) / } — causing "Alpine Expression Error:
 * Unexpected token ')'". The component never initialises.
 *
 * Run: pnpm --filter @workspace/scripts run test
 */

const hits = (src: string) =>
  scanSource("test.blade.php", src).map((o) => o.attr);

describe("scanSource — flags // comments inside Alpine double-quoted attributes", () => {
  it("flags // in x-data", () => {
    expect(
      hits('<div x-data="{ /* ok */ // bad comment\n  foo: 1 }"></div>'),
    ).toEqual(["x-data"]);
  });

  it("flags // in x-init", () => {
    expect(
      hits('<div x-init="// init comment\n  setup();"></div>'),
    ).toEqual(["x-init"]);
  });

  it("flags // in an @click handler", () => {
    expect(
      hits('<button @click="// comment\n  doThing()">x</button>'),
    ).toEqual(["@click"]);
  });

  it("flags // in a :class binding", () => {
    expect(
      hits('<div :class="// decides style\n  { active: isActive }"></div>'),
    ).toEqual([":class"]);
  });

  it("reports the correct line number", () => {
    const src = '<div\n  x-data="{\n    // line comment\n    foo: 1\n  }"></div>';
    const [o] = scanSource("f.blade.php", src);
    expect(o?.line).toBe(3);
    expect(o?.attr).toBe("x-data");
  });

  it("flags multiple offenders in one file", () => {
    const src =
      '<div x-data="// a\n  foo: 1"></div>\n<div x-init="// b\n  bar()"></div>';
    expect(hits(src)).toEqual(["x-data", "x-init"]);
  });
});

describe("scanSource — stays quiet on legitimate // usage", () => {
  it("ignores :// URL schemes (https://)", () => {
    expect(
      hits('<div x-data="{ url: \'https://example.com\' }"></div>'),
    ).toEqual([]);
  });

  it("ignores // inside a <script> block body", () => {
    expect(
      hits("<script>// a JS comment\nvar x = 1;</script>"),
    ).toEqual([]);
  });

  it("ignores // inside a Blade {{-- --}} comment", () => {
    expect(
      hits('{{-- <div x-data="// bad"></div> --}}'),
    ).toEqual([]);
  });

  it("ignores // inside an HTML <!-- --> comment", () => {
    expect(
      hits('<!-- <div x-data="// bad"></div> -->'),
    ).toEqual([]);
  });

  it("ignores // inside a @php block", () => {
    expect(
      hits("@php\n// a PHP comment\n$x = 1;\n@endphp\n<div x-data=\"{ a: 1 }\"></div>"),
    ).toEqual([]);
  });

  it("ignores // inside a single-quoted attribute", () => {
    expect(
      hits("<div x-data='{ /* ok */ // still in single quote }'\n></div>"),
    ).toEqual([]);
  });

  it("ignores block comments /* */ safely", () => {
    expect(
      hits('<div x-data="{ /* this is fine */ foo: 1 }"></div>'),
    ).toEqual([]);
  });

  it("ignores // outside any attribute", () => {
    expect(hits("<p>// not in an attribute</p>")).toEqual([]);
  });

  it("does not cross a closing quote into the next attribute", () => {
    const src =
      '<div x-data="{ open: false }"></div>\n<script>// safe</script>';
    expect(hits(src)).toEqual([]);
  });
});

describe("blankSafeContexts", () => {
  it("blanks @php blocks", () => {
    const src = "@php\n// comment\n$x = 1;\n@endphp\nZ";
    const out = blankSafeContexts(src);
    expect(out).not.toContain("// comment");
    expect(out.endsWith("Z")).toBe(true);
    expect(out.length).toBe(src.length);
  });

  it("blanks <script> bodies but keeps the tag", () => {
    const out = blankSafeContexts('<script type="module">// js</script>');
    expect(out).toContain('<script type="module">');
    expect(out).not.toContain("// js");
    expect(out).toContain("</script>");
  });

  it("preserves newlines when blanking", () => {
    const src = "{{-- a\nb --}}Z";
    const out = blankSafeContexts(src);
    expect(out.length).toBe(src.length);
    expect(out.split("\n").length).toBe(src.split("\n").length);
  });
});

describe("live repo", () => {
  it(
    "passes on all real blade files under the main web app views",
    () => {
      for (const [rel, src] of readViewsFileMap()) {
        const relFromRepo = `${VIEWS_REL}/${rel}`;
        const offenders = scanSource(relFromRepo, src);
        expect(
          offenders,
          `${relFromRepo} has // line comments inside an Alpine attribute expression`,
        ).toEqual([]);
      }
    },
    30_000,
  );

  it(
    "passes on every *.blade.php across the whole repo (wider scope)",
    () => {
      const files = listFiles();
      expect(files.length).toBeGreaterThan(0);
      for (const rel of files) {
        let src: string;
        try {
          src = fs.readFileSync(path.join(REPO_ROOT, rel), "utf8");
        } catch {
          continue;
        }
        const offenders = scanSource(rel, src);
        expect(
          offenders,
          `${rel} has // line comments inside an Alpine attribute expression`,
        ).toEqual([]);
      }
    },
    30_000,
  );

  it("scans beyond the main web app views but never third-party vendor code", () => {
    const files = listFiles().map((f) => f.replace(/^\.\//, ""));
    // Composer / npm third-party trees must stay excluded — we can't fix them.
    expect(files.some((f) => f.includes("/vendor/"))).toBe(false);
    expect(files.some((f) => f.includes("/node_modules/"))).toBe(false);
    // The main web app views are still covered.
    expect(files.some((f) => f.startsWith(`${VIEWS_REL}/`))).toBe(true);
  });
});
