import { describe, it, expect } from "vitest";
import fs from "node:fs";
import path from "node:path";
import {
  REPO_ROOT,
  SCAN_FILES,
  classTokens,
  findMulticolItemClasses,
  parseCss,
  styleBlocks,
  subjectCompound,
  subjectIsPseudoElement,
  subjectClasses,
  subjectTag,
  scanSource,
} from "./check-multicol-transform.js";

/**
 * Regression suite for the multicol-item transform guard (the "vanishing
 * showcase card" hover bug). Per the poisoned-fixture meta-test pattern, both
 * directions are pinned:
 *   - the guard PASSES on a clean fixture and on the live home.blade.php;
 *   - the guard FAILS when a hover transform (or will-change: transform, or a
 *     transform-carrying keyframe animation) is re-added to the multicol card.
 *
 * Run: pnpm --filter @workspace/scripts run test
 */

/** A minimal clean fixture mirroring the real showcase structure. */
const CLEAN_FIXTURE = `
<style>
    .reveal { opacity: 1; transform: none; transition: opacity .7s, transform .7s; }
    @media (min-width: 1024px) {
        .reveal { animation: revealAuto .8s both; }
        @keyframes revealAuto {
            from { opacity: 0; transform: translateY(40px) scale(.94); }
            to   { opacity: 1; transform: none; }
        }
    }
    .showcase-card { transition: box-shadow .4s; }
    .showcase-inner { transition: transform .45s; will-change: transform; }
    .showcase-card:hover .showcase-inner { transform: translateY(-4px); }
    .showcase-card::before { transform: scale(1.02); opacity: 0; }
    .showcase-card.reveal.visible { animation: showcaseReveal .85s backwards; }
    @keyframes showcaseReveal {
        0%   { opacity: 0; filter: blur(5px); }
        100% { opacity: 1; filter: none; }
    }
    @media (prefers-reduced-motion: reduce) {
        .showcase-card { animation: none !important; transition: none !important; transform: none !important; }
        .showcase-card:hover .showcase-inner { transform: none !important; }
    }
</style>
<div class="showcase-field columns-2 sm:columns-3 gap-3">
    <article class="reveal showcase-card glass p-4 break-inside-avoid">
        <div class="showcase-inner relative"></div>
    </article>
</div>
<p class="reveal">reveal is also used OUTSIDE the multicol grid</p>
<div class="glass">so is glass</div>
`;

const poison = (extraCss: string) =>
  CLEAN_FIXTURE.replace("</style>", `${extraCss}\n</style>`);

describe("classTokens", () => {
  it("keeps plain classes and drops blade interpolation + variant tokens", () => {
    expect(classTokens("reveal rd-{{ ($i % 5) + 1 }} showcase-card sm:mb-4 p-4")).toEqual([
      "reveal",
      "showcase-card",
      "p-4",
    ]);
  });
});

describe("findMulticolItemClasses", () => {
  it("collects the direct-child classes of a columns-* container", () => {
    const { itemClasses } = findMulticolItemClasses(CLEAN_FIXTURE);
    expect(itemClasses.has("showcase-card")).toBe(true);
    expect(itemClasses.has("reveal")).toBe(true);
    // inner wrapper is NOT a direct child
    expect(itemClasses.has("showcase-inner")).toBe(false);
  });

  it("computes exclusivity: showcase-card is exclusive, reveal/glass are not", () => {
    const { exclusiveItemClasses } = findMulticolItemClasses(CLEAN_FIXTURE);
    expect(exclusiveItemClasses.has("showcase-card")).toBe(true);
    expect(exclusiveItemClasses.has("reveal")).toBe(false);
    expect(exclusiveItemClasses.has("glass")).toBe(false);
  });

  it("collects direct-child tag names and their exclusivity", () => {
    const { itemTags, exclusiveItemTags } = findMulticolItemClasses(CLEAN_FIXTURE);
    // <article> is only used as the multicol card in the fixture.
    expect(itemTags.has("article")).toBe(true);
    expect(exclusiveItemTags.has("article")).toBe(true);
    // <div>/<p> appear elsewhere in the file too.
    expect(itemTags.has("div")).toBe(false);
    expect(exclusiveItemTags.has("p")).toBe(false);
  });

  it("returns nothing when there is no columns-* container", () => {
    const { itemClasses } = findMulticolItemClasses(
      `<div class="grid grid-cols-3"><div class="card"></div></div>`,
    );
    expect(itemClasses.size).toBe(0);
  });

  it("only counts DIRECT children, not grandchildren", () => {
    const { itemClasses } = findMulticolItemClasses(
      `<div class="columns-2"><div class="kid"><span class="grandkid"></span></div></div>`,
    );
    expect(itemClasses.has("kid")).toBe(true);
    expect(itemClasses.has("grandkid")).toBe(false);
  });

  it("handles void/self-closing tags without corrupting depth tracking", () => {
    const { itemClasses } = findMulticolItemClasses(
      `<div class="columns-2"><img class="pic"><div class="kid"><br><input class="deep"></div></div>`,
    );
    expect(itemClasses.has("pic")).toBe(true);
    expect(itemClasses.has("kid")).toBe(true);
    expect(itemClasses.has("deep")).toBe(false);
  });
});

describe("parseCss", () => {
  it("tracks media context through nesting", () => {
    const { rules } = parseCss(
      `@media (min-width: 1024px) { @media (prefers-reduced-motion: reduce) { .a { transform: scale(2); } } }`,
    );
    expect(rules).toHaveLength(1);
    expect(rules[0]!.media.some((m) => /prefers-reduced-motion/.test(m))).toBe(true);
  });

  it("registers keyframes that declare a real transform", () => {
    const { transformKeyframes } = parseCss(
      `@keyframes moves { from { transform: translateY(10px); } } @keyframes fades { from { opacity: 0; } }`,
    );
    expect(transformKeyframes.has("moves")).toBe(true);
    expect(transformKeyframes.has("fades")).toBe(false);
  });

  it("does not register keyframes whose only transform is none", () => {
    const { transformKeyframes } = parseCss(`@keyframes still { to { transform: none; } }`);
    expect(transformKeyframes.has("still")).toBe(false);
  });

  it("ignores transforms inside CSS comments", () => {
    const { rules } = parseCss(`.a { /* transform: scale(2); */ color: red; }`);
    expect(rules[0]!.decls.some((d) => d.prop === "transform")).toBe(false);
  });
});

describe("subject analysis", () => {
  it("subjectCompound returns the last compound", () => {
    expect(subjectCompound(".showcase-card:hover .showcase-inner")).toBe(".showcase-inner");
    expect(subjectCompound(".a > .b + .c")).toBe(".c");
    expect(subjectCompound(".showcase-card.reveal:not(.visible)")).toBe(
      ".showcase-card.reveal:not(.visible)",
    );
  });

  it("subjectIsPseudoElement detects ::before/::after (and legacy :before)", () => {
    expect(subjectIsPseudoElement(".showcase-card::before")).toBe(true);
    expect(subjectIsPseudoElement(".showcase-card:before")).toBe(true);
    expect(subjectIsPseudoElement(".showcase-card:hover")).toBe(false);
  });

  it("subjectClasses excludes classes inside :not()/:is() arguments", () => {
    expect(subjectClasses(".showcase-card.reveal:not(.visible)")).toEqual([
      "showcase-card",
      "reveal",
    ]);
  });

  it("subjectTag extracts a leading tag or *, and null otherwise", () => {
    expect(subjectTag("article:hover")).toBe("article");
    expect(subjectTag("ARTICLE")).toBe("article");
    expect(subjectTag("*")).toBe("*");
    expect(subjectTag(".showcase-card:hover")).toBeNull();
    expect(subjectTag(":hover")).toBeNull();
  });
});

describe("scanSource — clean fixture (false-positive protection)", () => {
  it("passes the clean fixture outright", () => {
    expect(scanSource("fixture.blade.php", CLEAN_FIXTURE)).toEqual([]);
  });

  it("allows transform: none on the card and on shared utilities", () => {
    expect(scanSource("f", poison(`.showcase-card { transform: none; }`))).toEqual([]);
  });

  it("allows transforms on descendants of the card (the sanctioned fix)", () => {
    expect(
      scanSource("f", poison(`.showcase-card:hover .showcase-inner { transform: scale(1.1); }`)),
    ).toEqual([]);
  });

  it("allows transforms on the card's pseudo-elements", () => {
    expect(
      scanSource("f", poison(`.showcase-card:hover::after { transform: rotate(2deg); }`)),
    ).toEqual([]);
  });

  it("allows transforms inside the reduced-motion kill-switch block", () => {
    expect(
      scanSource(
        "f",
        poison(
          `@media (prefers-reduced-motion: reduce) { .showcase-card { transform: none !important; will-change: auto; } }`,
        ),
      ),
    ).toEqual([]);
  });

  it("does not flag transition: transform on the card", () => {
    expect(
      scanSource("f", poison(`.showcase-card { transition: transform .3s ease; }`)),
    ).toEqual([]);
  });

  it("does not flag shared utilities animating transform keyframes on non-exclusive classes", () => {
    // `.reveal { animation: revealAuto }` is already in the clean fixture and passes.
    expect(scanSource("f", CLEAN_FIXTURE)).toEqual([]);
  });
});

describe("scanSource — poisoned fixtures (the guard must FIRE)", () => {
  it("fails when a hover transform is re-added to .showcase-card", () => {
    const offenders = scanSource(
      "f",
      poison(`.showcase-card:hover { transform: translateY(-6px) scale(1.02); }`),
    );
    expect(offenders).toHaveLength(1);
    expect(offenders[0]!.selector).toBe(".showcase-card:hover");
    expect(offenders[0]!.property).toBe("transform");
  });

  it("fails when will-change: transform is re-added to .showcase-card", () => {
    const offenders = scanSource("f", poison(`.showcase-card { will-change: transform; }`));
    expect(offenders).toHaveLength(1);
    expect(offenders[0]!.reason).toContain("will-change");
  });

  it("fails on will-change lists that include transform", () => {
    expect(scanSource("f", poison(`.showcase-card { will-change: opacity, transform; }`))).toHaveLength(1);
  });

  it("fails on a vendor-prefixed transform", () => {
    expect(scanSource("f", poison(`.showcase-card:hover { -webkit-transform: scale(1.05); }`))).toHaveLength(1);
  });

  it("fails when a base (non-hover) transform lands on the card", () => {
    expect(scanSource("f", poison(`.showcase-card.reveal:not(.visible) { transform: translateY(46px); }`))).toHaveLength(1);
  });

  it("fails when a transform hits the card through a shared utility class (.reveal)", () => {
    // A transform on `.reveal` lands on every showcase card too — same bug.
    expect(scanSource("f", poison(`.reveal:not(.visible) { transform: translateY(40px); }`))).toHaveLength(1);
  });

  it("fails when the card's own animation points at transform-carrying keyframes", () => {
    const offenders = scanSource(
      "f",
      poison(
        `@keyframes cardPop { 50% { transform: scale(1.06); } }
         .showcase-card.reveal.visible { animation: cardPop .8s ease both; }`,
      ),
    );
    expect(offenders).toHaveLength(1);
    expect(offenders[0]!.reason).toContain("cardPop");
  });

  it("fails on a class-less TAG subject targeting the card (`article:hover`)", () => {
    const offenders = scanSource("f", poison(`article:hover { transform: translateY(-6px); }`));
    expect(offenders).toHaveLength(1);
    expect(offenders[0]!.selector).toBe("article:hover");
  });

  it("fails on a parent>child combinator with a tag subject (`.showcase-field > article:hover`)", () => {
    expect(
      scanSource("f", poison(`.showcase-field > article:hover { transform: scale(1.03); }`)),
    ).toHaveLength(1);
  });

  it("fails on a universal subject (`.showcase-field > *`)", () => {
    expect(
      scanSource("f", poison(`.showcase-field > * { will-change: transform; }`)),
    ).toHaveLength(1);
  });

  it("fails when an EXCLUSIVE tag subject's animation points at transform keyframes", () => {
    const offenders = scanSource(
      "f",
      poison(
        `@keyframes tagPop { 50% { transform: scale(1.04); } }
         .showcase-field > article { animation: tagPop .6s both; }`,
      ),
    );
    expect(offenders).toHaveLength(1);
    expect(offenders[0]!.reason).toContain("tagPop");
  });

  it("does NOT flag a tag subject that never appears as a multicol child", () => {
    expect(scanSource("f", poison(`section:hover { transform: scale(1.1); }`))).toEqual([]);
  });

  it("fails inside a non-reduced-motion media query", () => {
    expect(
      scanSource(
        "f",
        poison(`@media (min-width: 1024px) { .showcase-card:hover { transform: translateY(-4px); } }`),
      ),
    ).toHaveLength(1);
  });

  it("reports a plausible line number for the offender", () => {
    const src = poison(`.showcase-card:hover { transform: translateY(-6px); }`);
    const [o] = scanSource("f", src);
    const lines = src.split("\n");
    expect(lines[o!.line - 1]).toContain(".showcase-card:hover");
  });
});

describe("live codebase", () => {
  it("every scanned file currently passes the guard", () => {
    for (const rel of SCAN_FILES) {
      const src = fs.readFileSync(path.join(REPO_ROOT, rel), "utf8");
      expect(scanSource(rel, src)).toEqual([]);
    }
  });

  it("home.blade.php actually contains a multicol grid with an exclusive card class (guard is armed)", () => {
    const src = fs.readFileSync(path.join(REPO_ROOT, SCAN_FILES[0]!), "utf8");
    const { itemClasses, exclusiveItemClasses } = findMulticolItemClasses(src);
    expect(itemClasses.has("showcase-card")).toBe(true);
    expect(exclusiveItemClasses.has("showcase-card")).toBe(true);
    // styleBlocks must find the page CSS or the scan would be a silent no-op.
    expect(styleBlocks(src).length).toBeGreaterThan(0);
  });

  it("the live file poisoned with the ORIGINAL bug (hover transform on .showcase-card) fails", () => {
    const src = fs.readFileSync(path.join(REPO_ROOT, SCAN_FILES[0]!), "utf8");
    const poisoned = src.replace(
      "</style>",
      `.showcase-card:hover { transform: translateY(-6px); will-change: transform; }\n</style>`,
    );
    const offenders = scanSource("home.blade.php", poisoned);
    expect(offenders.length).toBeGreaterThanOrEqual(2); // transform + will-change
  });
});
