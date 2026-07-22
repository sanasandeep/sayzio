// Web ⇄ mobile parity guard for the profile-card socials row (Task #5680).
//
// The web side has ProfileCardLayoutSocialsCoverageTest asserting exactly
// which `_profile_layout` tokens render the shared socials row. The mobile
// renderer in app/biolink/[handle].tsx mirrors those layouts, dispatching on
// the same token — but nothing guarded that its layout→socials mapping
// matches the web. This test parses BOTH sources:
//
//  1. the web test's LAYOUTS_WITH_SOCIALS / LAYOUTS_WITHOUT_SOCIALS constants
//     (the web's declared source of truth, itself runtime-verified against
//     the blade renderer), and
//  2. the mobile ProfileCardView source, splitting it into per-layout
//     branches and checking which ones render <ProfileSocialsRow>,
//
// then fails if any layout shows socials on one platform but not the other.
//
// Run via `node scripts/test-profile-socials-parity.mjs` (package script
// `test:profile-socials-parity`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const mobileRoot = join(__dirname, "..");
const repoRoot = join(mobileRoot, "..", "..");

// ---------------------------------------------------------------------------
// 1. Web source of truth: the coverage test's classification constants.
// ---------------------------------------------------------------------------
const webTestPath = join(
  repoRoot,
  "artifacts",
  "1inme",
  "tests",
  "Feature",
  "ProfileCardLayoutSocialsCoverageTest.php",
);
const webSrc = readFileSync(webTestPath, "utf8");

function phpConstArray(name) {
  const m = webSrc.match(new RegExp(`const ${name}\\s*=\\s*\\[([\\s\\S]*?)\\];`));
  if (!m) throw new Error(`could not find const ${name} in ${webTestPath}`);
  const items = [...m[1].matchAll(/'([^']+)'/g)].map((x) => x[1]);
  if (items.length === 0) throw new Error(`const ${name} parsed empty — parser drift?`);
  return items;
}

const webWith = phpConstArray("LAYOUTS_WITH_SOCIALS");
const webWithout = phpConstArray("LAYOUTS_WITHOUT_SOCIALS");
const webAll = new Set([...webWith, ...webWithout]);

// ---------------------------------------------------------------------------
// 2. Mobile renderer: split ProfileCardView into per-layout branches and
//    record which contain <ProfileSocialsRow.
// ---------------------------------------------------------------------------
const mobilePath = join(mobileRoot, "app", "biolink", "[handle].tsx");
const mobileSrc = readFileSync(mobilePath, "utf8");

const viewStart = mobileSrc.indexOf("function ProfileCardView(");
assert.ok(viewStart !== -1, "ProfileCardView not found in [handle].tsx");
// The renderer section ends where the slides viewer section begins; fall back
// to end-of-file if that marker ever moves.
const viewEndMarker = mobileSrc.indexOf("function SlidesViewer(", viewStart);
const viewSrc = mobileSrc.slice(viewStart, viewEndMarker === -1 ? undefined : viewEndMarker);

// Branch boundaries: every `if (layout === "x")` plus the trailing fallback.
const branchRe = /if\s*\(\s*layout\s*===\s*"([^"]+)"\s*\)/g;
const marks = [];
let m;
while ((m = branchRe.exec(viewSrc)) !== null) {
  marks.push({ layout: m[1], start: m.index });
}
assert.ok(marks.length >= 5, `only ${marks.length} layout branches found — parser drift?`);

const mobileWith = new Set();
const mobileBranches = new Set();
for (let i = 0; i < marks.length; i++) {
  const chunk = viewSrc.slice(marks[i].start, i + 1 < marks.length ? marks[i + 1].start : undefined);
  // Only consider the branch body up to its closing `return (...)`; since
  // branches are contiguous `if (...) { return (...); }` blocks, the chunk
  // between two marks is exactly one branch.
  mobileBranches.add(marks[i].layout);
  if (chunk.includes("<ProfileSocialsRow")) mobileWith.add(marks[i].layout);
}

// The fallback (badges & everything unlisted) must NOT render socials —
// otherwise every unmatched layout would silently show socials on mobile.
const lastChunk = viewSrc.slice(marks[marks.length - 1].start);
const fallbackStart = lastChunk.indexOf("LEGACY: BADGES");
assert.ok(fallbackStart !== -1, "badges fallback marker not found");
assert.ok(
  !lastChunk.slice(fallbackStart).includes("<ProfileSocialsRow"),
  "mobile fallback branch renders ProfileSocialsRow — unlisted layouts would all show socials",
);

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

console.log("[test-profile-socials-parity] web ⇄ mobile socials-row parity");

// ---------------------------------------------------------------------------
// 3. Parity assertions.
// ---------------------------------------------------------------------------

// Every layout the web renders socials for must have an explicit mobile
// branch that renders ProfileSocialsRow.
for (const layout of webWith) {
  assert.ok(
    mobileBranches.has(layout),
    `layout '${layout}' shows socials on web but has NO explicit mobile branch (falls to socials-less fallback)`,
  );
  assert.ok(
    mobileWith.has(layout),
    `layout '${layout}' shows socials on web but its mobile branch does not render ProfileSocialsRow`,
  );
}
ok(`all ${webWith.length} web socials layouts render ProfileSocialsRow on mobile (${webWith.join(", ")})`);

// Every layout the web hides socials for must not render them on mobile
// (either an explicit socials-less branch or the socials-less fallback).
for (const layout of webWithout) {
  assert.ok(
    !mobileWith.has(layout),
    `layout '${layout}' hides socials on web but the mobile branch renders ProfileSocialsRow`,
  );
}
ok(`all ${webWithout.length} web socials-less layouts stay socials-less on mobile`);

// No mobile branch may render socials for a layout the web test doesn't
// classify as WITH — catches mobile-side additions the web hides, and new
// mobile layouts the web test has never heard of.
for (const layout of mobileWith) {
  assert.ok(
    webWith.includes(layout),
    `mobile layout '${layout}' renders ProfileSocialsRow but the web classifies it as socials-less (or not at all)`,
  );
}
ok("no mobile branch shows socials the web hides");

// Every explicit mobile branch must be a layout the web test knows about, so
// a renamed/new token can't silently bypass the guard on either side.
for (const layout of mobileBranches) {
  assert.ok(
    webAll.has(layout),
    `mobile has a branch for layout '${layout}' that the web coverage test does not classify — update both in lockstep`,
  );
}
ok(`all ${mobileBranches.size} explicit mobile branches are classified by the web test`);

console.log(`\n[test-profile-socials-parity] PASS — ${passed} checks`);
