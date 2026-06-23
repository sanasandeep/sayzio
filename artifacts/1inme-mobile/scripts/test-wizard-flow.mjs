// Source-driven tests for the mobile guided "Link in Bio" wizard.
//
// The wizard (app/links/wizard.tsx) walks four steps entirely in memory —
// category → page type → industry → questions — off a single taxonomy
// payload, then POSTs every answer at once to /links/wizard/generate
// (lib/api/wizard.ts). The recent refresh made the industry step ALWAYS-ON
// (a generic fallback fills in for combos with no specific industry list) and
// gave each step an icon-led look. None of that had automated coverage.
//
// Following the convention in test-block-cache.mjs / test-upgrade-hint.mjs we
// avoid a full RN/TS test runner: the step transitions and the `industries`
// resolver are pure data logic, so we replicate them here AND pin them to the
// real source with wiring guards, so the replica can't silently drift. The
// generic-fallback list is read straight out of the component source and is
// asserted to match the server's genericIndustries() (the parity that keeps
// the always-on industry step from ever rendering blank).
//
// Run via `node scripts/test-wizard-flow.mjs` (package script `test:wizard-flow`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");
const repo = join(root, "..", "..");

const wizardSrc = readFileSync(
  join(root, "app", "links", "wizard.tsx"),
  "utf8",
);
const apiSrc = readFileSync(join(root, "lib", "api", "wizard.ts"), "utf8");
const phpSrc = readFileSync(
  join(
    repo,
    "artifacts",
    "1inme",
    "app",
    "Modules",
    "User",
    "Services",
    "BiolinkWizardQuestions.php",
  ),
  "utf8",
);

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// Pull the GENERIC_INDUSTRIES array out of the component source and eval it as
// plain JS (it's a literal of {slug,label,icon} objects). This is the exact
// list the wizard falls back to when the taxonomy is missing a combo's
// industries — testing against the real literal keeps the test honest.
// ---------------------------------------------------------------------------
function extractArrayLiteral(src, name) {
  const start = src.indexOf(`const ${name}`);
  if (start === -1) throw new Error(`could not find ${name}`);
  // Anchor on the assignment so a `Type[]` annotation's bracket isn't grabbed.
  const eq = src.indexOf("=", start);
  const open = src.indexOf("[", eq);
  let depth = 0;
  let end = -1;
  for (let i = open; i < src.length; i += 1) {
    if (src[i] === "[") depth += 1;
    else if (src[i] === "]") {
      depth -= 1;
      if (depth === 0) {
        end = i + 1;
        break;
      }
    }
  }
  if (end === -1) throw new Error(`unterminated array for ${name}`);
  // eslint-disable-next-line no-new-func
  return new Function(`return ${src.slice(open, end)};`)();
}

const GENERIC_INDUSTRIES = extractArrayLiteral(wizardSrc, "GENERIC_INDUSTRIES");

assert.ok(
  Array.isArray(GENERIC_INDUSTRIES) && GENERIC_INDUSTRIES.length > 0,
  "GENERIC_INDUSTRIES must be a non-empty list so the industry step is never blank",
);
for (const ind of GENERIC_INDUSTRIES) {
  assert.ok(ind.slug && ind.label, "every generic industry needs a slug + label");
}
ok("GENERIC_INDUSTRIES is a non-empty {slug,label,icon} fallback list");

// Parity guard: the mobile generic fallback must mirror the server's
// genericIndustries() (same slugs + labels) so the always-on industry step
// stays consistent across surfaces. We parse the PHP array body loosely.
{
  const m = phpSrc.match(
    /function genericIndustries\(\): array\s*\{[\s\S]*?return\s*\[([\s\S]*?)\];/,
  );
  assert.ok(m, "could not find genericIndustries() in the PHP source");
  const pairs = [...m[1].matchAll(/'slug'\s*=>\s*'([^']+)'\s*,\s*'label'\s*=>\s*'([^']+)'/g)];
  const serverSlugs = pairs.map((p) => p[1]);
  const serverLabels = pairs.map((p) => p[2]);
  assert.deepEqual(
    GENERIC_INDUSTRIES.map((i) => i.slug),
    serverSlugs,
    "mobile generic industry slugs must match the server genericIndustries()",
  );
  assert.deepEqual(
    GENERIC_INDUSTRIES.map((i) => i.label),
    serverLabels,
    "mobile generic industry labels must match the server genericIndustries()",
  );
}
ok("mobile GENERIC_INDUSTRIES matches the server genericIndustries() (slug+label parity)");

// ---------------------------------------------------------------------------
// Replica of the wizard's pure step logic. Mirrors wizard.tsx exactly:
//   - pickCategory  → step "page_type" (clears pageType/industry/answers)
//   - pickPageType  → step "industry"  (ALWAYS — generic fallback when needed)
//   - pickIndustry  → step "questions" (slug may be null = skipped)
//   - goBack        → walks one step back; from "category" it exits (router.back)
//   - resolveIndustries → combo's taxonomy list, else GENERIC_INDUSTRIES
//   - stepIndex     → 0..3 for the progress bar
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
function pickPageType(s, slug) {
  s.pageType = slug;
  s.industry = null;
  s.answers = {};
  s.step = "industry";
}
function pickIndustry(s, slug) {
  s.industry = slug;
  s.answers = {};
  s.step = "questions";
}
function goBack(s) {
  if (s.step === "page_type") s.step = "category";
  else if (s.step === "industry") s.step = "page_type";
  else if (s.step === "questions") s.step = "industry";
  else s.exited = true;
}
function resolveIndustries(taxonomy, category, pageType) {
  if (!category || !pageType) return [];
  const fromTaxonomy = taxonomy?.industries?.[`${category}.${pageType}`] ?? [];
  return fromTaxonomy.length ? fromTaxonomy : GENERIC_INDUSTRIES;
}
function stepIndex(step) {
  return step === "category"
    ? 0
    : step === "page_type"
      ? 1
      : step === "industry"
        ? 2
        : 3;
}

// ---------------------------------------------------------------------------
// Fixture taxonomy. `personal.developer` deliberately has NO specific industry
// list — on the server effectiveIndustries() substitutes the generic set, so
// here the taxonomy carries that generic list for the combo. We ALSO leave the
// combo out of `industries` entirely in a second fixture to prove the
// client-side GENERIC_INDUSTRIES const kicks in if the data is missing.
// ---------------------------------------------------------------------------
const taxonomyWithGeneric = {
  categories: [
    { slug: "business", label: "Business" },
    { slug: "personal", label: "Personal / Portfolio" },
  ],
  page_types: {
    business: [{ slug: "local_shop", label: "Local Shop / Service" }],
    personal: [{ slug: "developer", label: "Developer / Engineer" }],
  },
  industries: {
    // A combo WITH a specific list.
    "business.local_shop": [
      { slug: "bakery", label: "Bakery", icon: "fa-bread-slice" },
      { slug: "salon", label: "Hair / Beauty Salon", icon: "fa-scissors" },
    ],
    // A combo WITHOUT a specific list — the server already filled it with the
    // generic set (this is what the always-on industry step renders).
    "personal.developer": GENERIC_INDUSTRIES,
  },
};

// Same taxonomy but with the personal.developer industries omitted entirely,
// exercising the component's defensive GENERIC_INDUSTRIES fallback.
const taxonomyMissingCombo = {
  ...taxonomyWithGeneric,
  industries: { "business.local_shop": taxonomyWithGeneric.industries["business.local_shop"] },
};

// ===========================================================================
// 1. Full advance: category → page_type → industry → questions for a combo
//    WITHOUT specific industries (generic fallback shows, step never skipped).
// ===========================================================================
{
  const s = makeWizard();
  assert.equal(s.step, "category");
  assert.equal(stepIndex(s.step), 0);

  pickCategory(s, "personal");
  assert.equal(s.step, "page_type", "picking a category advances to page type");
  assert.equal(s.category, "personal");
  assert.equal(stepIndex(s.step), 1);
  // No page type chosen yet → no industries resolved.
  assert.deepEqual(resolveIndustries(taxonomyWithGeneric, s.category, s.pageType), []);

  pickPageType(s, "developer");
  assert.equal(
    s.step,
    "industry",
    "picking a page type ALWAYS advances to the industry step (never skipped)",
  );
  assert.equal(stepIndex(s.step), 2);

  // The combo has no specific list, so the industry step shows the generic set
  // — proving the always-on step is populated, not blank.
  const shown = resolveIndustries(taxonomyWithGeneric, s.category, s.pageType);
  assert.deepEqual(
    shown,
    GENERIC_INDUSTRIES,
    "a combo without specific industries shows the generic fallback list",
  );
  assert.ok(shown.length > 0, "the industry step is never blank");

  pickIndustry(s, shown[0].slug);
  assert.equal(s.step, "questions", "picking an industry advances to the Q&A step");
  assert.equal(s.industry, GENERIC_INDUSTRIES[0].slug);
  assert.equal(stepIndex(s.step), 3);
}
ok("category → page_type → industry → questions for a no-specific-industry combo (generic fallback shows)");

// A combo WITH a specific list shows that list (not the generic fallback).
{
  const shown = resolveIndustries(taxonomyWithGeneric, "business", "local_shop");
  assert.equal(shown[0].slug, "bakery", "specific combos keep their own industry list");
  assert.notDeepEqual(shown, GENERIC_INDUSTRIES);
}
ok("a combo WITH specific industries shows its own list, not the generic fallback");

// Client-side defensive fallback: if the taxonomy is missing the combo, the
// GENERIC_INDUSTRIES const still fills the step so it can't render blank.
{
  const shown = resolveIndustries(taxonomyMissingCombo, "personal", "developer");
  assert.deepEqual(
    shown,
    GENERIC_INDUSTRIES,
    "missing taxonomy industries fall back to the GENERIC_INDUSTRIES const",
  );
}
ok("missing taxonomy data falls back to the GENERIC_INDUSTRIES const (step never blank)");

// ===========================================================================
// 2. Back navigation walks the steps in reverse, then exits the screen.
// ===========================================================================
{
  const s = makeWizard();
  pickCategory(s, "personal");
  pickPageType(s, "developer");
  pickIndustry(s, "online");
  assert.equal(s.step, "questions");

  goBack(s);
  assert.equal(s.step, "industry", "Back from questions returns to the industry step");
  goBack(s);
  assert.equal(s.step, "page_type", "Back from industry returns to page type");
  goBack(s);
  assert.equal(s.step, "category", "Back from page type returns to category");
  assert.equal(s.exited, false, "still on-screen at the first step");
  goBack(s);
  assert.equal(s.exited, true, "Back from the first step exits the wizard (router.back)");
}
ok("Back walks questions → industry → page_type → category, then exits");

// ===========================================================================
// 3. Skip: the industry step is optional — skipping lands on questions with a
//    null industry (the server treats null as 'skipped').
// ===========================================================================
{
  const s = makeWizard();
  pickCategory(s, "personal");
  pickPageType(s, "developer");
  assert.equal(s.step, "industry");

  // The "Skip this step" control calls pickIndustry(null).
  pickIndustry(s, null);
  assert.equal(s.step, "questions", "Skip advances straight to the Q&A step");
  assert.equal(s.industry, null, "skipping leaves the industry unset (null)");
}
ok("Skip advances to questions with industry = null");

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
assert.match(
  wizardSrc,
  /function pickPageType\(slug: string\)\s*\{[\s\S]*?reset\("industry"\);/,
  "pickPageType must ALWAYS advance to the industry step (always-on)",
);
assert.match(
  wizardSrc,
  /function pickIndustry\(slug: string \| null\)\s*\{[\s\S]*?reset\("questions"\);/,
  "pickIndustry must advance to the questions step",
);
ok("component pickCategory/pickPageType/pickIndustry advance to the expected steps");

// 4b. The always-on industry step uses the taxonomy list with a GENERIC fallback.
assert.match(
  wizardSrc,
  /fromTaxonomy\.length \? fromTaxonomy : GENERIC_INDUSTRIES/,
  "the industries memo must fall back to GENERIC_INDUSTRIES when the combo list is empty",
);
ok("industries memo falls back to GENERIC_INDUSTRIES (always-on industry step)");

// 4c. goBack chains the steps in reverse and exits via router.back().
{
  const m = wizardSrc.match(/function goBack\(\)\s*\{[\s\S]*?\n\s\s\}/);
  assert.ok(m, "could not find goBack()");
  const body = m[0];
  assert.match(body, /step === "page_type"\) reset\("category"\)/);
  assert.match(body, /step === "industry"\) reset\("page_type"\)/);
  assert.match(body, /step === "questions"\) reset\("industry"\)/);
  assert.match(body, /else router\.back\(\)/, "Back from the first step exits via router.back()");
}
ok("component goBack walks the steps in reverse and exits at the first step");

// 4d. The Skip control calls pickIndustry(null).
assert.match(
  wizardSrc,
  /onPress=\{\(\) => pickIndustry\(null\)\}/,
  "the Skip control must call pickIndustry(null)",
);
ok("component Skip control calls pickIndustry(null)");

// 4e. Generation wiring: onGenerate posts the full payload and routes to the
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
