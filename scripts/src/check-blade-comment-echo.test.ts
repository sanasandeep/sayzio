import { describe, it, expect } from "vitest";
import {
  scanSource,
  blankSafeSpans,
  findBlockComments,
  commentSpans,
} from "./check-blade-comment-echo.js";
import { VIEWS_REL, readViewsFileMap } from "./lib/blade-theme-scope.js";

/**
 * Regression suite for the blade-comment-echo guard.
 *
 * The guard's value is that it fires when a live Blade echo (`{{ }}` / `{!! !!}`)
 * hides inside a plain HTML (`<!-- -->`) or C-style (`/* *\/`) comment — which
 * still compiles to a live PHP echo and can 500 the page — while staying quiet
 * on the many benign look-alikes (`{{-- --}}` Blade comments, `@verbatim`
 * blocks, `image/*` attribute values, `/*` inside JS/CSS strings, and echoes
 * that are simply outside any comment). Both directions are pinned so a future
 * refactor can neither disable the check (false negatives) nor start flagging
 * correct templating (false positives).
 *
 * Run: pnpm --filter @workspace/scripts run test
 */

const tokens = (src: string) => scanSource("test.blade.php", src).map((o) => o.token);

describe("scanSource — flags live echoes inside plain comments", () => {
  it("flags {{ }} inside an HTML comment", () => {
    expect(tokens("<!-- example: {{ $var }} -->")).toEqual(["{{"]);
  });

  it("flags {!! !!} inside an HTML comment", () => {
    expect(tokens("<!-- {!! $html !!} -->")).toEqual(["{!!"]);
  });

  it("flags {{ }} inside a CSS comment within <style>", () => {
    const src = "<style>\n.a { color: red; /* fallback {{ $color }} */ }\n</style>";
    expect(tokens(src)).toEqual(["{{"]);
  });

  it("flags {{ }} inside a JS comment within <script>", () => {
    const src = "<script>\n// setup\n/* route: {{ route('x') }} */\nvar a = 1;\n</script>";
    expect(tokens(src)).toEqual(["{{"]);
  });

  it("flags a multi-line HTML comment echo and reports its real line/col", () => {
    const src = "line1\n<!--\n  {{ $v }}\n-->\nline5";
    const [o] = scanSource("f.blade.php", src);
    expect(o?.line).toBe(3);
    expect(o?.col).toBe(3);
  });

  it("flags an echo even when the HTML comment wraps a whole <script>", () => {
    const src = "<!-- <script>{{ $x }}</script> -->";
    expect(tokens(src)).toEqual(["{{"]);
  });
});

describe("scanSource — stays quiet on benign patterns", () => {
  it("ignores a normal echo outside any comment", () => {
    expect(tokens("<p>{{ $name }}</p>")).toEqual([]);
  });

  it("ignores a Blade comment {{-- --}} (even with a nested echo)", () => {
    expect(tokens("{{-- a real blade comment {{ $x }} --}}")).toEqual([]);
  });

  it("ignores echoes inside an @verbatim block", () => {
    expect(tokens("@verbatim <!-- {{ $x }} --> @endverbatim")).toEqual([]);
  });

  it("ignores an escaped @{{ ... }} inside a comment (renders literal braces)", () => {
    expect(tokens("<!-- example: @{{ $x }} -->")).toEqual([]);
  });

  it("ignores an escaped @{!! ... !!} inside a comment", () => {
    expect(tokens("<!-- @{!! $x !!} -->")).toEqual([]);
  });

  it("still flags a live echo after a doubled @@{{ (literal @ + real echo)", () => {
    expect(tokens("<!-- @@{{ $x }} -->")).toEqual(["{{"]);
  });

  it("does not treat image/* in an HTML attribute as a comment", () => {
    const src = '<input accept="image/*">\n<label>{{ $label }}</label>\n<span>{{ $x }}</span>';
    expect(tokens(src)).toEqual([]);
  });

  it("does not treat /* inside a JS string as a comment opener", () => {
    const src = "<script>\nif (t.endsWith('/*')) return;\nvar u = '{{ route(\"a\") }}';\n</script>";
    expect(tokens(src)).toEqual([]);
  });

  it("does not treat */* wildcard mime strings as comment delimiters", () => {
    const src = "<script>\nvar accept = '*/*';\nfetch('{{ route(\"x\") }}');\n</script>";
    expect(tokens(src)).toEqual([]);
  });

  it("ignores /* */ outside <style>/<script> (plain HTML text)", () => {
    // A stray /* */ in HTML body text is not a comment to the browser or Blade.
    const src = "<p>a /* b */ {{ $x }}</p>";
    expect(tokens(src)).toEqual([]);
  });
});

describe("findBlockComments — string-aware /* */ detection", () => {
  it("finds a real block comment", () => {
    expect(findBlockComments("a /* c */ b")).toEqual([{ start: 2, end: 9 }]);
  });

  it("skips /* that lives inside a quoted string", () => {
    expect(findBlockComments("var x = '/*';")).toEqual([]);
  });

  it("treats an unterminated comment as running to end of input", () => {
    const [span] = findBlockComments("a /* unterminated");
    expect(span?.start).toBe(2);
    expect(span?.end).toBe(17);
  });
});

describe("blankSafeSpans", () => {
  it("blanks blade comments but preserves newlines/length", () => {
    const src = "{{-- x\ny --}}Z";
    const out = blankSafeSpans(src);
    expect(out).toBe("      \n      Z");
  });
});

describe("commentSpans — no double counting across kinds", () => {
  it("does not re-scan a <script> already inside an HTML comment", () => {
    const src = "<!-- <script>/* {{ $x }} */</script> -->";
    // One comment span (the HTML comment) covers everything; the script's
    // /* */ is blanked, so the echo is counted exactly once.
    expect(tokens(src)).toEqual(["{{"]);
  });
});

describe("live repo", () => {
  // Reuses the shared, MEMOIZED whole-tree read (`readViewsFileMap`) rather than
  // re-walking the blade views tree with its own scan. The guard's SCAN_ROOTS is
  // exactly `[VIEWS_REL]`, so this covers the same files, while sharing the single
  // cached walk with the theme guards. A generous explicit timeout is kept as a
  // belt-and-suspenders against residual cross-file disk contention under vitest
  // parallelism (see .agents/memory/parallel-disk-scan-vitest-flake.md).
  it(
    "passes on all real blade files under the scan roots",
    () => {
      for (const [rel, src] of readViewsFileMap()) {
        const relFromRepo = `${VIEWS_REL}/${rel}`;
        const offenders = scanSource(relFromRepo, src);
        expect(offenders, `${relFromRepo} has stray {{ }}/{!! !!} in a plain comment`).toEqual([]);
      }
    },
    30_000,
  );
});
