import { describe, it, expect } from "vitest";
import {
  scanSource,
  scanConsentText,
  isAllowed,
  blankComments,
  hexPatternWithAlpha,
  rgbPatternFor,
  BANNED_HEX_PATTERN,
} from "./check-brand-color.js";

/**
 * Regression suite for the brand-color guard.
 *
 * The retired purple ramp must never creep back into a PRIMARY UI surface (the
 * brand accent is now blue). The delicate exception logic is pinned here so a
 * future refactor can't silently disable an exception (missing real drift) or
 * start flagging an allow-listed categorical palette (blocking valid changes):
 *   - scanSource()      — the banned-token detection (hex / rgb / rgba /
 *     violet-<shade> / purple-<shade>) shared with the ripgrep scan in main(),
 *     the comment-blanking pass, the ALLOWLIST short-circuit, and the
 *     intentional 8-digit-alpha miss.
 *   - scanConsentText() — the alpha-aware cookie-consent defaults scan.
 *   - isAllowed()       — the intentional categorical-palette allow-list.
 *   - blankComments() / hexPatternWithAlpha() / rgbPatternFor() /
 *     BANNED_HEX_PATTERN — the shared building blocks also used by the
 *     post-build (compiled-CSS) guard.
 * Run: pnpm --filter @workspace/scripts run test
 */

const UI_FILE = "artifacts/1inme/resources/views/user/dashboard.blade.php";

const cols = (relFile: string, src: string) => scanSource(relFile, src).map((o) => o.col);
const flagged = (src: string, relFile = UI_FILE) => scanSource(relFile, src).length > 0;

describe("scanSource — retired purple is flagged", () => {
  it("flags a bare retired hex (with #)", () => {
    expect(flagged("color: #7c3aed;")).toBe(true);
  });

  it("flags a retired hex without the leading '#' (Tailwind arbitrary value body)", () => {
    expect(flagged("bg-[#8b5cf6]")).toBe(true);
  });

  it("flags each of the three retired hexes", () => {
    for (const hex of ["#7c3aed", "#8b5cf6", "#a78bfa"]) {
      expect(flagged(`x { color: ${hex}; }`)).toBe(true);
    }
  });

  it("flags an rgb() form of a retired color", () => {
    expect(flagged("color: rgb(124, 58, 237);")).toBe(true);
  });

  it("flags a space-separated rgba() form (Tailwind v4 channel syntax)", () => {
    expect(flagged("--x: rgba(139 92 246 / 0.5);")).toBe(true);
  });

  it("flags a violet-<shade> Tailwind class", () => {
    expect(flagged(`<div class="bg-violet-500">`)).toBe(true);
  });

  it("flags a purple-<shade> Tailwind class on any utility", () => {
    for (const cls of ["text-purple-700", "from-purple-950", "ring-violet-50"]) {
      expect(flagged(`<div class="${cls}">`)).toBe(true);
    }
  });

  it("is case-insensitive (matches uppercase hex)", () => {
    expect(flagged("color: #7C3AED;")).toBe(true);
  });

  it("reports the 1-based column of the offending token", () => {
    // "  #a78bfa" — token starts at column 3.
    expect(cols(UI_FILE, "  #a78bfa")).toEqual([3]);
  });

  it("flags every offending line, not just the first", () => {
    const lines = scanSource(UI_FILE, `a: #7c3aed;\nb: rgb(139,92,246);\nc: purple-300;`).map(
      (o) => o.line,
    );
    expect(lines).toEqual([1, 2, 3]);
  });
});

describe("scanSource — non-retired colors are NOT flagged", () => {
  it("passes the brand-blue accent hex", () => {
    expect(flagged("color: #3d6bff;")).toBe(false);
  });

  it("passes brand-primary Tailwind utilities", () => {
    expect(flagged(`<div class="bg-primary-600 text-primary-500 border-blue-500">`)).toBe(false);
  });

  it("intentionally MISSES an 8-digit translucent purple hex (build guard owns it)", () => {
    // `#7c3aed2e` has no word boundary before the alpha pair, so the source-level
    // \b-anchored pattern skips it by design; the alpha-aware post-build guard
    // catches it in compiled CSS.
    expect(flagged("--wash: #7c3aed2e;")).toBe(false);
  });

  it("does not flag a violet/purple word that is not a Tailwind shade class", () => {
    expect(flagged("const violetName = 'grape';")).toBe(false);
    expect(flagged("<h1>Purple mountains</h1>")).toBe(false);
  });

  it("does not flag an out-of-range violet shade", () => {
    expect(flagged(`<div class="bg-violet-1000">`)).toBe(false);
  });

  it("passes a non-retired hex", () => {
    expect(flagged("color: #123456;")).toBe(false);
  });
});

describe("scanSource — comments are blanked (retired purple in a comment is not drift)", () => {
  it("ignores retired purple inside a C-style block comment", () => {
    expect(flagged("/* was #7c3aed / violet-500 */")).toBe(false);
  });

  it("ignores retired purple inside a blade comment", () => {
    expect(flagged("{{-- old bg-[#8b5cf6] --}}")).toBe(false);
  });

  it("ignores retired purple inside an HTML comment", () => {
    expect(flagged("<!-- legacy text-purple-700 -->")).toBe(false);
  });

  it("ignores retired purple inside a // line comment", () => {
    expect(flagged("const x = 1; // TODO drop purple-500")).toBe(false);
  });

  it("does NOT treat a URL's '//' as a comment (purple after a URL still fails)", () => {
    // The `//` in https:// is preceded by ':' so it is not blanked; a retired
    // token later on the same line must still be reported.
    expect(flagged(`background: url("https://cdn/x.png"); color: #7c3aed;`)).toBe(true);
  });

  it("still flags retired purple in real code on a line that also has a comment", () => {
    expect(flagged("color: #7c3aed; /* keep */")).toBe(true);
  });
});

describe("scanSource — allow-listed categorical palettes are ignored", () => {
  it("ignores retired purple in an allow-listed file", () => {
    expect(flagged("color: #7c3aed;", "artifacts/1inme-mobile/lib/blockVariants.ts")).toBe(false);
  });

  it("ignores retired purple anywhere under an allow-listed directory", () => {
    expect(flagged("color: #7c3aed;", "artifacts/1inme-deck/src/pages/intro/Hero.tsx")).toBe(false);
  });

  it("still flags retired purple in a NON-allow-listed primary UI file", () => {
    expect(flagged("color: #7c3aed;", UI_FILE)).toBe(true);
  });
});

describe("isAllowed — allow-list membership", () => {
  it("matches an allow-listed file exactly", () => {
    expect(isAllowed("artifacts/1inme/resources/views/welcome.blade.php")).toBe(true);
  });

  it("matches any path under an allow-listed directory", () => {
    expect(isAllowed("artifacts/1inme-deck/src/pages/deep/nested/Slide.tsx")).toBe(true);
    expect(isAllowed("artifacts/1inme-deck/src/pages")).toBe(true);
  });

  it("does NOT match a directory allow-list entry as a filename prefix", () => {
    // The dir entry is "…/src/pages"; a sibling like "…/src/pages-extra" must not match.
    expect(isAllowed("artifacts/1inme-deck/src/pages-extra/Slide.tsx")).toBe(false);
  });

  it("does not match an ordinary primary UI file", () => {
    expect(isAllowed(UI_FILE)).toBe(false);
  });
});

describe("scanConsentText — cookie-consent defaults are a real brand surface", () => {
  const CONSENT = "artifacts/1inme/app/Modules/Common/Support/CookieConsentConfig.php";
  const consentFlagged = (src: string) => scanConsentText(CONSENT, src).length > 0;

  it("flags a solid retired hex", () => {
    expect(consentFlagged("'accent' => '#7c3aed',")).toBe(true);
  });

  it("flags an 8-digit translucent retired hex (alpha-aware, unlike scanSource)", () => {
    expect(consentFlagged("'accent' => '#7c3aed2e',")).toBe(true);
  });

  it("flags an rgb() form", () => {
    expect(consentFlagged("'accent' => 'rgb(167, 139, 250)',")).toBe(true);
  });

  it("passes the brand-blue accent", () => {
    expect(consentFlagged("'accent' => '#3d6bff',")).toBe(false);
  });

  it("ignores retired purple inside a PHP comment (comment-blanked like the source scan)", () => {
    expect(consentFlagged("// legacy '#7c3aed'")).toBe(false);
  });
});

describe("blankComments — preserves line/column geometry", () => {
  it("removes comment bodies while preserving newlines", () => {
    const src = `a\n/* #7c3aed */\nb`;
    const out = blankComments(src);
    expect(out.split("\n").length).toBe(src.split("\n").length);
    expect(out.includes("7c3aed")).toBe(false);
    expect(out.split("\n")[0]).toBe("a");
    expect(out.split("\n")[2]).toBe("b");
  });

  it("leaves non-comment code on the same line intact", () => {
    const out = blankComments(`color: #7c3aed; /* note */`);
    expect(out.includes("#7c3aed")).toBe(true);
    expect(out.includes("note")).toBe(false);
  });
});

describe("shared pattern builders (also used by the post-build guard)", () => {
  it("hexPatternWithAlpha matches the 6-digit and 8-digit-alpha forms", () => {
    const re = new RegExp(hexPatternWithAlpha("7c3aed"), "i");
    expect(re.test("#7c3aed")).toBe(true);
    expect(re.test("7c3aed")).toBe(true);
    expect(re.test("#7c3aed2e")).toBe(true); // translucent form in compiled CSS
  });

  it("hexPatternWithAlpha does not match a 7-hex-digit run (no valid boundary)", () => {
    const re = new RegExp(hexPatternWithAlpha("7c3aed"), "i");
    expect(re.test("#7c3aed2")).toBe(false);
  });

  it("BANNED_HEX_PATTERN (source-level) misses the 8-digit-alpha form", () => {
    // Documents WHY the build guard needs hexPatternWithAlpha: the \b-terminated
    // source pattern cannot see translucent purple in compiled output.
    const re = new RegExp(BANNED_HEX_PATTERN, "i");
    expect(re.test("#7c3aed2e")).toBe(false);
  });

  it("rgbPatternFor tolerates comma- and space-separated channels", () => {
    const re = new RegExp(rgbPatternFor([124, 58, 237]), "i");
    expect(re.test("rgb(124, 58, 237)")).toBe(true);
    expect(re.test("rgba(124 58 237 / .3)")).toBe(true);
    expect(re.test("rgb(124,58,237)")).toBe(true);
  });
});
