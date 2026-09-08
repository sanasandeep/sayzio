import { describe, it, expect } from "vitest";
import { scanSource } from "./check-blade-php.js";

/**
 * Regression suite for the blade-php guard.
 *
 * The guard exists because Blade's raw-block pass matches `@php(.*?)@endphp`
 * lazily and BEFORE any directive is compiled, so a one-line
 * `@php(...)` pairs with an unrelated `@endphp` further down and silently
 * stops compiling everything in between. Both directions are pinned: the
 * shadowed one-liner must fire (it was a live 500), and the many correct
 * inline uses in this repo — files with no `@endphp` after them — must stay
 * quiet, or the guard gets switched off as noise.
 *
 * Run: pnpm --filter @workspace/scripts run test
 */

const lines = (src: string) => scanSource("t.blade.php", src).map((o) => o.line);

describe("scanSource — fires on a shadowed inline @php", () => {
  it("flags an inline @php( with an @endphp later in the file", () => {
    const src = ["@php($a = 1)", "@if($a)x@endif", "@php", "$b = 2;", "@endphp"].join("\n");
    expect(lines(src)).toEqual([1]);
  });

  it("reports the @endphp it pairs with, not the last one in the file", () => {
    const src = ["@php($a = 1)", "@php $b = 1; @endphp", "@php", "$c = 2;", "@endphp"].join("\n");
    const [first] = scanSource("t.blade.php", src);
    expect(first.endphpLine).toBe(2);
  });

  it("flags an inline @php( written inside a Blade comment", () => {
    // Comment stripping also runs after raw-block extraction, so a directive
    // inside {{-- --}} arms the same trap.
    const src = ["{{-- like @php($a = 1) --}}", "@php", "$b = 2;", "@endphp"].join("\n");
    expect(lines(src)).toEqual([1]);
  });

  it("flags every shadowed one-liner, not just the first", () => {
    const src = ["@php($a = 1)", "@php($b = 2)", "@php", "$c = 3;", "@endphp"].join("\n");
    expect(lines(src)).toEqual([1, 2]);
  });

  it("allows whitespace between the directive and its parenthesis, as Blade does", () => {
    const src = ["@php ($a = 1)", "@php", "$b = 2;", "@endphp"].join("\n");
    expect(lines(src)).toEqual([1]);
  });
});

describe("scanSource — stays quiet on correct templating", () => {
  it("ignores an inline @php( with no @endphp anywhere after it", () => {
    expect(lines("@php($a = 1)\n@if($a)x@endif")).toEqual([]);
  });

  it("ignores an inline @php( that comes after the file's only @endphp", () => {
    const src = ["@php", "$a = 1;", "@endphp", "@php($b = 2)"].join("\n");
    expect(lines(src)).toEqual([]);
  });

  it("ignores balanced block form", () => {
    expect(lines("@php\n$a = 1;\n@endphp\n@php\n$b = 2;\n@endphp")).toEqual([]);
  });

  it("ignores an escaped @@php( literal", () => {
    expect(lines("@@php($a = 1)\n@php\n$b = 2;\n@endphp")).toEqual([]);
  });

  it("ignores a bare @php block opener followed by @endphp", () => {
    expect(lines("@php $a = 1; @endphp\n@php\n$b = 2;\n@endphp")).toEqual([]);
  });
});
