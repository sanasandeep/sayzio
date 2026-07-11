// Source-driven guard that the login footer's four /info pages always render
// readable content — even offline (task #4440).
//
// Task #4438 proved the footer links (About/Help/Privacy/Terms) resolve to
// real screen files that default-export a component. But a route can resolve
// to a screen that renders nothing: if a screen's copy were emptied,
// mis-wired, or its state logic broke, a user tapping the link would see a
// blank page — and the route-existence guard (test-login-footer-links.mjs)
// would still pass.
//
// This test reads the shipped screen sources and asserts each one carries the
// content it needs to render something useful:
//
//   * help/privacy/terms — static screens that pass a title + a `sections`
//     array straight into <InfoPage>. We assert a non-empty title and at least
//     one section, and that every section has non-empty body text.
//
//   * about — fetches admin-editable copy at runtime but falls back to a
//     static FALLBACK_SECTIONS array when the backend is offline / returns
//     nothing. We assert FALLBACK_SECTIONS is non-empty with body text AND that
//     it is actually wired as the initial/kept state: the `sections` state is
//     seeded from FALLBACK_SECTIONS, the fetch only overrides it when the
//     backend returns a non-empty list, and it is the array passed to the page.
//
// Source-driven (NOT a headless browser render), following the convention in
// test-about-eefind-sync.mjs / test-login-footer-links.mjs: we read the shipped
// source and eval the real array/object literals, so this exercises what ships.
//
// Run via `node scripts/test-info-pages-content.mjs` (package script
// `test:info-pages`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// String-aware balanced extraction of a bracketed literal. Starting at the
// first `open` char at or after `fromIndex`, walk forward tracking nesting
// depth while skipping over string literals (so a bracket inside a body
// string can't unbalance the match). Returns the literal text or throws.
// ---------------------------------------------------------------------------
function extractBalanced(src, fromIndex, open, close, what) {
  const start = src.indexOf(open, fromIndex);
  assert.ok(start !== -1, `Could not find opening '${open}' for ${what}.`);
  let depth = 0;
  let inStr = null;
  for (let i = start; i < src.length; i++) {
    const ch = src[i];
    if (inStr) {
      if (ch === "\\") {
        i += 1; // skip the escaped char
        continue;
      }
      if (ch === inStr) inStr = null;
      continue;
    }
    if (ch === '"' || ch === "'" || ch === "`") {
      inStr = ch;
      continue;
    }
    if (ch === open) depth += 1;
    else if (ch === close) {
      depth -= 1;
      if (depth === 0) return src.slice(start, i + 1);
    }
  }
  throw new Error(`Unterminated ${what} literal (no matching '${close}').`);
}

// Eval a plain array/object literal (no TS annotations inside the literal).
function evalLiteral(literal, what) {
  try {
    // eslint-disable-next-line no-new-func
    return new Function(`return (${literal});`)();
  } catch (e) {
    throw new Error(`Could not evaluate ${what}: ${e.message}`);
  }
}

// Read a `title="..."` prop (single JSX string attribute).
function readTitle(src, what) {
  const m = src.match(/title="([^"]+)"/);
  assert.ok(m, `Could not find a title="..." prop in ${what}.`);
  return m[1];
}

// Assert a decoded sections array is non-empty and every entry has body text.
function assertSectionsRenderable(sections, what) {
  assert.ok(
    Array.isArray(sections) && sections.length > 0,
    `${what} must have at least one section with content`,
  );
  sections.forEach((s, i) => {
    assert.equal(
      typeof s.body,
      "string",
      `${what} section[${i}] must have a string body`,
    );
    assert.ok(
      s.body.trim().length > 0,
      `${what} section[${i}] body must be non-empty (a blank body renders an empty page)`,
    );
  });
}

// ===========================================================================
// 1. Static screens: help / privacy / terms.
//
// Each passes a literal title + sections array straight into <InfoPage>. If the
// title were dropped or the sections array emptied, the page would render blank.
// ===========================================================================
console.log("[test-info-pages-content] static screens render title + sections");

const STATIC_SCREENS = ["help", "privacy", "terms"];

for (const name of STATIC_SCREENS) {
  const rel = join("app", "info", `${name}.tsx`);
  const src = readFileSync(join(root, rel), "utf8");
  const what = `${rel}`;

  const title = readTitle(src, what);
  assert.ok(
    title.trim().length > 0,
    `${what} must pass a non-empty title to <InfoPage>`,
  );

  const marker = src.indexOf("sections=");
  assert.ok(marker !== -1, `${what} must pass a sections={...} prop to <InfoPage>`);
  const literal = extractBalanced(src, marker, "[", "]", `${what} sections`);
  const sections = evalLiteral(literal, `${what} sections`);
  assertSectionsRenderable(sections, what);

  const withHeading = sections.filter(
    (s) => typeof s.heading === "string" && s.heading.trim().length > 0,
  );
  assert.ok(
    withHeading.length > 0,
    `${what} should have at least one section with a heading`,
  );

  ok(
    `${rel} renders "${title}" + ${sections.length} sections (all with body text)`,
  );
}

// ===========================================================================
// 2. About screen: offline fallback content.
//
// about.tsx fetches admin-editable copy at runtime (fetchAboutContent) but must
// still render usable content when that fetch fails / returns nothing. That
// depends on THREE things staying wired together, all asserted below.
// ===========================================================================
console.log("[test-info-pages-content] about screen has a wired offline fallback");

const aboutRel = join("app", "info", "about.tsx");
const about = readFileSync(join(root, aboutRel), "utf8");

// 2a. FALLBACK_SECTIONS is non-empty and every section has body text.
const fbDecl = about.indexOf("const FALLBACK_SECTIONS");
assert.ok(
  fbDecl !== -1,
  `${aboutRel} must declare a FALLBACK_SECTIONS array for offline rendering`,
);
// Start after the `=` so the `[` in the `: InfoSection[]` type annotation is
// skipped and we extract the actual array initializer.
const fbMarker = about.indexOf("=", fbDecl);
assert.ok(fbMarker !== -1, "FALLBACK_SECTIONS must be assigned an array literal.");
const fbLiteral = extractBalanced(about, fbMarker, "[", "]", "FALLBACK_SECTIONS");
const fallbackSections = evalLiteral(fbLiteral, "FALLBACK_SECTIONS");
assertSectionsRenderable(fallbackSections, "FALLBACK_SECTIONS");
ok(
  `FALLBACK_SECTIONS has ${fallbackSections.length} sections (all with body text)`,
);

// 2b. The `sections` state is SEEDED from FALLBACK_SECTIONS, so the very first
// paint (before any fetch resolves, and forever if offline) shows real copy.
assert.match(
  about,
  /useState<InfoSection\[\]>\(\s*FALLBACK_SECTIONS\s*\)/,
  `${aboutRel} must seed its sections state from FALLBACK_SECTIONS (useState<InfoSection[]>(FALLBACK_SECTIONS)) so offline renders show real copy`,
);
ok("sections state is initialised from FALLBACK_SECTIONS");

// 2c. The fetch only OVERRIDES the fallback when the backend returns a
// non-empty list — so an empty/failed response never blanks the page.
assert.match(
  about,
  /content\.sections\.length\s*>\s*0\s*\)\s*setSections\(content\.sections\)/,
  `${aboutRel} must only call setSections when content.sections.length > 0, or an empty backend response would wipe the fallback copy`,
);
ok("fetched sections replace the fallback only when non-empty");

// 2d. The `sections` state is the array actually handed to the rendered page.
assert.match(
  about,
  /sections=\{sections\}/,
  `${aboutRel} must pass the sections state into <AboutPage sections={sections}>`,
);
const aboutTitle = readTitle(about, aboutRel);
assert.ok(
  aboutTitle.trim().length > 0,
  `${aboutRel} must pass a non-empty title to <AboutPage>`,
);
ok(`AboutPage receives title "${aboutTitle}" + the sections state`);

console.log(`\n[test-info-pages-content] all ${passed} checks passed`);
