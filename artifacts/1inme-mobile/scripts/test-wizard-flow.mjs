// Source-driven tests for the mobile guided "Link in Bio" wizard.
//
// The wizard (app/links/wizard.tsx) walks four steps entirely in memory off a
// single taxonomy payload, then POSTs every answer at once to
// /links/wizard/generate (lib/api/wizard.ts). The redesigned flow is:
//   1. category  (relabeled "Industry")
//   2. page_type (relabeled "Profile type") — with the OPTIONAL niche
//      refinement folded inline (chips shown only for combos that carry a
//      *specific* industries() list; the taxonomy omits the rest)
//   3. basics    (basic profile & branding fields)
//   4. additional(everything else)
// The basics/additional split is computed server-side and shipped on the
// question-set payload, so the two surfaces stay in lockstep.
//
// Following the convention in test-block-cache.mjs / test-upgrade-hint.mjs we
// avoid a full RN/TS test runner: the step transitions and the inline-niche
// resolver are pure data logic, so we replicate them here AND pin them to the
// real source with wiring guards, so the replica can't silently drift.
//
// Run via `node scripts/test-wizard-flow.mjs` (package script `test:wizard-flow`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const wizardSrc = readFileSync(
  join(root, "app", "links", "wizard.tsx"),
  "utf8",
);
const apiSrc = readFileSync(join(root, "lib", "api", "wizard.ts"), "utf8");

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// Replica of the wizard's pure step logic. Mirrors wizard.tsx exactly:
//   - pickCategory       → step "page_type" (clears pageType/industry/answers)
//   - selectPageType     → STAYS on "page_type" (sets pageType, resets niche)
//   - toggleIndustry     → toggles the inline niche (null when toggled off)
//   - continueFromPageType → step "basics" (requires a pageType)
//   - basics "Continue"  → step "additional"
//   - goBack             → walks one step back; from "category" it exits
//   - resolveIndustries  → combo's taxonomy list (specific-only; may be empty)
//   - stepIndex          → 0..3 for the progress bar
// The wiring guards further down pin this replica to the real source.
// ---------------------------------------------------------------------------
function makeWizard() {
  return {
    step: "category",
    category: null,
    pageType: null,
    industry: null,
    answers: {},
    exited: false,
  };
}
function pickCategory(s, slug) {
  s.category = slug;
  s.pageType = null;
  s.industry = null;
  s.answers = {};
  s.step = "page_type";
}
function selectPageType(s, slug) {
  if (slug === s.pageType) return;
  s.pageType = slug;
  s.industry = null;
  s.answers = {};
  // No step change — niche refinement is inline; Continue advances.
}
function toggleIndustry(s, slug) {
  s.industry = s.industry === slug ? null : slug;
}
function continueFromPageType(s) {
  if (!s.pageType) return;
  s.step = "basics";
}
function continueFromBasics(s) {
  s.step = "additional";
}
function goBack(s) {
  if (s.step === "page_type") s.step = "category";
  else if (s.step === "basics") s.step = "page_type";
  else if (s.step === "additional") s.step = "basics";
  else s.exited = true;
}
// Specific-only: the taxonomy omits combos without a specific industries()
// list, so an empty result means "no inline niche refinement for this combo".
function resolveIndustries(taxonomy, category, pageType) {
  if (!category || !pageType) return [];
  return taxonomy?.industries?.[`${category}.${pageType}`] ?? [];
}
function stepIndex(step) {
  return step === "category"
    ? 0
    : step === "page_type"
      ? 1
      : step === "basics"
        ? 2
        : 3;
}

// ---------------------------------------------------------------------------
// Fixture taxonomy. `business.local_shop` carries a specific industries list
// (inline niche chips show). `personal.developer` deliberately has NO entry —
// the redesigned flow shows no niche chips for it (refinement is optional and
// only appears for combos with a specific list).
// ---------------------------------------------------------------------------
const taxonomy = {
  categories: [
    { slug: "business", label: "Business" },
    { slug: "personal", label: "Personal / Portfolio" },
  ],
  page_types: {
    business: [{ slug: "local_shop", label: "Local Shop / Service" }],
    personal: [{ slug: "developer", label: "Developer / Engineer" }],
  },
  industries: {
    "business.local_shop": [
      { slug: "bakery", label: "Bakery", icon: "fa-bread-slice" },
      { slug: "salon", label: "Hair / Beauty Salon", icon: "fa-scissors" },
    ],
    // personal.developer intentionally absent — no inline niche.
  },
};

// ===========================================================================
// 1. Full advance: category → page_type (select + Continue) → basics →
//    additional. The progress bar walks 0..3.
// ===========================================================================
{
  const s = makeWizard();
  assert.equal(s.step, "category");
  assert.equal(stepIndex(s.step), 0);

  pickCategory(s, "business");
  assert.equal(s.step, "page_type", "picking a category advances to the profile type step");
  assert.equal(s.category, "business");
  assert.equal(stepIndex(s.step), 1);

  // Selecting a profile type does NOT advance — niche refinement is inline.
  selectPageType(s, "local_shop");
  assert.equal(s.step, "page_type", "selecting a profile type stays on the step (inline niche)");
  assert.equal(s.pageType, "local_shop");
  assert.equal(stepIndex(s.step), 1);

  // This combo HAS a specific list, so inline niche chips show.
  const niche = resolveIndustries(taxonomy, s.category, s.pageType);
  assert.equal(niche[0].slug, "bakery", "a combo with a specific list shows inline niche chips");

  // The niche is optional and toggleable.
  toggleIndustry(s, "bakery");
  assert.equal(s.industry, "bakery", "tapping a niche chip selects it");
  toggleIndustry(s, "bakery");
  assert.equal(s.industry, null, "tapping the same niche chip again clears it");
  toggleIndustry(s, "salon");
  assert.equal(s.industry, "salon", "tapping a different niche chip switches the selection");

  continueFromPageType(s);
  assert.equal(s.step, "basics", "Continue advances to the basics step");
  assert.equal(stepIndex(s.step), 2);

  continueFromBasics(s);
  assert.equal(s.step, "additional", "Continue from basics advances to the additional step");
  assert.equal(stepIndex(s.step), 3);
}
ok("category → page_type (select + inline niche + Continue) → basics → additional");

// A combo WITHOUT a specific list shows no inline niche chips.
{
  const niche = resolveIndustries(taxonomy, "personal", "developer");
  assert.deepEqual(niche, [], "a combo without a specific list shows no inline niche chips");
}
ok("a combo without a specific industries list shows no inline niche refinement");

// Continue is gated on having selected a profile type.
{
  const s = makeWizard();
  pickCategory(s, "business");
  continueFromPageType(s);
  assert.equal(s.step, "page_type", "Continue does nothing until a profile type is selected");
}
ok("Continue from the profile-type step requires a selection");

// ===========================================================================
// 2. Back navigation walks the steps in reverse, then exits the screen.
// ===========================================================================
{
  const s = makeWizard();
  pickCategory(s, "business");
  selectPageType(s, "local_shop");
  continueFromPageType(s);
  continueFromBasics(s);
  assert.equal(s.step, "additional");

  goBack(s);
  assert.equal(s.step, "basics", "Back from additional returns to the basics step");
  goBack(s);
  assert.equal(s.step, "page_type", "Back from basics returns to the profile type step");
  goBack(s);
  assert.equal(s.step, "category", "Back from profile type returns to the category step");
  assert.equal(s.exited, false, "still on-screen at the first step");
  goBack(s);
  assert.equal(s.exited, true, "Back from the first step exits the wizard (router.back)");
}
ok("Back walks additional → basics → page_type → category, then exits");

// ===========================================================================
// 3. The niche is optional — advancing without selecting one leaves it null
//    (the server treats null as 'skipped').
// ===========================================================================
{
  const s = makeWizard();
  pickCategory(s, "business");
  selectPageType(s, "local_shop");
  continueFromPageType(s);
  assert.equal(s.step, "basics", "advancing without a niche still works");
  assert.equal(s.industry, null, "leaving the niche unset keeps it null");
}
ok("the inline niche is optional — advancing without one leaves industry = null");

// ===========================================================================
// 4. Source wiring guards — pin the replica above to the real component so the
//    two can't drift, and confirm the generation call is wired correctly.
// ===========================================================================

// 4a. Step transitions in the component route exactly as replicated.
assert.match(
  wizardSrc,
  /function pickCategory\(slug: string\)\s*\{[\s\S]*?reset\("page_type"\);/,
  "pickCategory must advance to the page_type step",
);
{
  const m = wizardSrc.match(/function selectPageType\(slug: string\)\s*\{[\s\S]*?\n\s\s\}/);
  assert.ok(m, "could not find selectPageType()");
  const body = m[0];
  assert.match(body, /setPageType\(slug\);/, "selectPageType must set the page type");
  assert.ok(
    !/reset\(/.test(body),
    "selectPageType must NOT advance the step (niche refinement is inline)",
  );
}
assert.match(
  wizardSrc,
  /function continueFromPageType\(\)\s*\{[\s\S]*?reset\("basics"\);/,
  "continueFromPageType must advance to the basics step",
);
assert.match(
  wizardSrc,
  /function toggleIndustry\(slug: string\)\s*\{[\s\S]*?cur === slug \? null : slug/,
  "toggleIndustry must toggle the niche selection (null when toggled off)",
);
ok("component step transitions match the redesigned 4-step flow");

// 4b. The inline niche is specific-only: no generic fallback remains anywhere.
assert.ok(
  !/GENERIC_INDUSTRIES/.test(wizardSrc),
  "the generic industry fallback must be removed (niche is specific-only now)",
);
assert.match(
  wizardSrc,
  /taxonomyQ\.data\?\.industries\[`\$\{category\}\.\$\{pageType\}`\] \?\? \[\]/,
  "the industries memo must read the combo's specific list with an empty fallback",
);
ok("inline niche is specific-only (no generic fallback in the component)");

// 4c. The niche chips only render for combos that carry a specific list.
assert.match(
  wizardSrc,
  /pageType && industries\.length \?/,
  "the inline niche chips must only render when the combo has a specific list",
);
ok("inline niche chips are gated on a non-empty specific list");

// 4d. The basics/additional steps render from the server-split question set.
assert.match(
  wizardSrc,
  /questionsQ\.data\?\.basics \?\? \[\]/,
  "the basics step must render from the server-provided basics split",
);
assert.match(
  wizardSrc,
  /questionsQ\.data\?\.additional \?\? \[\]/,
  "the additional step must render from the server-provided additional split",
);
assert.match(
  apiSrc,
  /basics: WizardQuestion\[\]/,
  "the question-set type must carry the basics split",
);
assert.match(
  apiSrc,
  /additional: WizardQuestion\[\]/,
  "the question-set type must carry the additional split",
);
ok("basics/additional steps render from the server-provided question split");

// 4e. goBack chains the steps in reverse and exits via router.back().
{
  const m = wizardSrc.match(/function goBack\(\)\s*\{[\s\S]*?\n\s\s\}/);
  assert.ok(m, "could not find goBack()");
  const body = m[0];
  assert.match(body, /step === "page_type"\) reset\("category"\)/);
  assert.match(body, /step === "basics"\) reset\("page_type"\)/);
  assert.match(body, /step === "additional"\) reset\("basics"\)/);
  assert.match(body, /else router\.back\(\)/, "Back from the first step exits via router.back()");
}
ok("component goBack walks the steps in reverse and exits at the first step");

// 4f. Generation wiring: onGenerate posts the full payload and routes to the
//     new link's block editor; the API helper hits the generate endpoint.
{
  const m = wizardSrc.match(/async function onGenerate\(\)\s*\{[\s\S]*?\n\s\s\}/);
  assert.ok(m, "could not find onGenerate()");
  const body = m[0];
  assert.match(
    body,
    /generateWizardPage\(\{[\s\S]*?category,[\s\S]*?page_type: pageType,[\s\S]*?industry,[\s\S]*?answers,[\s\S]*?\}\)/,
    "onGenerate must call generateWizardPage with the collected category/page_type/industry/answers",
  );
  assert.match(
    body,
    /router\.replace\(`\/links\/\$\{link\.id\}\/blocks`/,
    "onGenerate must route to the new link's block editor on success",
  );
}
assert.match(
  apiSrc,
  /"\/links\/wizard\/generate"[\s\S]*?method: "POST"/,
  "generateWizardPage must POST to /links/wizard/generate",
);
assert.match(
  apiSrc,
  /"\/links\/wizard\/taxonomy"/,
  "getWizardTaxonomy must GET /links/wizard/taxonomy",
);
ok("generation wiring: onGenerate posts the payload via generateWizardPage and opens the block editor");

console.log(`\n[test-wizard-flow] all ${passed} checks passed`);
