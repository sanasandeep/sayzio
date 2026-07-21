import { describe, it, expect } from "vitest";
import fs from "node:fs";
import {
  BLUE_TOKEN_REGEXES,
  INTENTIONAL_SURFACES,
  baselinePath,
  blankCommentsPreserveLines,
  diffAgainstBaseline,
  findOccurrences,
  isThemedUserAdminView,
  readBaseline,
  scanCounts,
} from "./check-hardcoded-blue-accents.js";
import { readViewsFileMap } from "./lib/blade-theme-scope.js";

/**
 * Regression suite for the hardcoded-blue-accent ratchet guard.
 *
 * The guard's value is that it fires when a NEW fixed-blue token (raw #3d6bff,
 * rgba(61,107,255,...), or the non-flipping --color-primary-500) is added to a
 * themed user/admin blade view, and stays quiet on the checked-in baseline.
 * Both directions are pinned so a refactor can't silently disable the check or
 * start flagging the baselined footprint.
 */

const matchesAny = (s: string) =>
  BLUE_TOKEN_REGEXES.some((re) => {
    re.lastIndex = 0;
    return re.test(s);
  });

describe("token detection", () => {
  it("matches the raw hex, case-insensitively", () => {
    expect(matchesAny('style="color:#3d6bff"')).toBe(true);
    expect(matchesAny('style="color:#3D6BFF"')).toBe(true);
  });

  it("matches the hex with an 8-digit alpha suffix", () => {
    expect(matchesAny("bg-[#3d6bff1f]")).toBe(true);
  });

  it("does not match a longer hex that merely contains the digits", () => {
    expect(matchesAny("#3d6bffa")).toBe(false);
  });

  it("matches rgba()/rgb() forms with commas, spaces, and slash alpha", () => {
    expect(matchesAny("background: rgba(61,107,255,0.12)")).toBe(true);
    expect(matchesAny("background: rgba( 61 , 107 , 255 , .5 )")).toBe(true);
    expect(matchesAny("background: rgb(61 107 255 / 50%)")).toBe(true);
  });

  it("does not match other rgb triplets", () => {
    expect(matchesAny("rgba(61,107,254,0.5)")).toBe(false);
    expect(matchesAny("rgba(161,107,255,0.5)")).toBe(false);
  });

  it("matches the non-flipping --color-primary-500 token but not its siblings", () => {
    expect(matchesAny("var(--color-primary-500)")).toBe(true);
    expect(matchesAny("ring-[var(--color-primary-500,#anything)]")).toBe(true);
    expect(matchesAny("var(--color-primary-5000)")).toBe(false);
  });
});

describe("findOccurrences", () => {
  it("counts every occurrence on a line with correct line numbers", () => {
    const occ = findOccurrences('a\nstyle="background:#3d6bff;border-color:rgba(61,107,255,.2)"\n');
    expect(occ.length).toBe(2);
    expect(occ.every((o) => o.line === 2)).toBe(true);
  });

  it("ignores tokens inside blade, HTML, CSS and // comments", () => {
    const src = [
      "{{-- color:#3d6bff --}}",
      "<!-- rgba(61,107,255,1) -->",
      "/* var(--color-primary-500) */",
      "// #3d6bff",
      "https://example.com/x // keeps urls intact #3d6bff",
    ].join("\n");
    // The last line's token sits inside a `//` comment after a URL.
    expect(findOccurrences(src).length).toBe(0);
  });

  it("blankCommentsPreserveLines preserves line offsets", () => {
    const src = "a\n/* x\ny */\nb";
    expect(blankCommentsPreserveLines(src).split("\n").length).toBe(4);
  });
});

describe("scope: themed user/admin views only", () => {
  const files = readViewsFileMap();

  it("includes ordinary layout-extending user and admin pages", () => {
    expect(isThemedUserAdminView("user/dashboard/index.blade.php", files)).toBe(true);
    expect(isThemedUserAdminView("admin/partials/sidebar.blade.php", files)).toBe(true);
  });

  it("excludes views outside user/ and admin/", () => {
    expect(isThemedUserAdminView("common/biolink.blade.php", files)).toBe(false);
  });

  it("exempts the standalone dark complete-profile page (own document, no theme system)", () => {
    expect(isThemedUserAdminView("user/auth/complete-profile.blade.php", files)).toBe(false);
  });

  it("exempts the print-oriented invoice PDF document", () => {
    expect(isThemedUserAdminView("user/invoices/pdf.blade.php", files)).toBe(false);
  });
});

describe("diffAgainstBaseline", () => {
  const occ = (n: number) => Array.from({ length: n }, (_, i) => ({ line: i + 1, text: "x" }));

  it("flags an increase and a brand-new file", () => {
    const counts = new Map([
      ["user/a.blade.php", occ(3)],
      ["user/new.blade.php", occ(1)],
    ]);
    const problems = diffAgainstBaseline(counts, { "user/a.blade.php": 2 });
    expect(problems.map((p) => `${p.file}:${p.kind}`).sort()).toEqual([
      "user/a.blade.php:increase",
      "user/new.blade.php:new-file",
    ]);
  });

  it("flags decreases and stale entries so the baseline ratchets down", () => {
    const counts = new Map([["user/a.blade.php", occ(1)]]);
    const problems = diffAgainstBaseline(counts, { "user/a.blade.php": 2, "user/gone.blade.php": 4 });
    expect(problems.map((p) => `${p.file}:${p.kind}`).sort()).toEqual([
      "user/a.blade.php:decrease",
      "user/gone.blade.php:stale-entry",
    ]);
  });

  it("is quiet when counts match exactly", () => {
    const counts = new Map([["user/a.blade.php", occ(2)]]);
    expect(diffAgainstBaseline(counts, { "user/a.blade.php": 2 })).toEqual([]);
  });
});

describe("live repo", () => {
  it("has a checked-in baseline", () => {
    expect(fs.existsSync(baselinePath())).toBe(true);
  });

  it("current tree matches the baseline (the guard passes)", () => {
    const baseline = readBaseline();
    expect(baseline).not.toBeNull();
    const problems = diffAgainstBaseline(scanCounts(readViewsFileMap()), baseline as Record<string, number>);
    expect(problems).toEqual([]);
  });

  it("every documented intentional surface actually appears in the baseline", () => {
    const baseline = readBaseline() as Record<string, number>;
    for (const file of Object.keys(INTENTIONAL_SURFACES)) {
      expect(baseline[file], `${file} should be baselined`).toBeGreaterThan(0);
    }
  });
});
