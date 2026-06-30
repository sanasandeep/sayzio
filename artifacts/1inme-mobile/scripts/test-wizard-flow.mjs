// Source-driven tests for the mobile guided "Link in Bio" wizard.
//
// The wizard (app/links/wizard.tsx) walks five steps entirely in memory off a
// single taxonomy payload (PersonaCatalog), then POSTs every answer at once to
// /links/wizard/generate (lib/api/wizard.ts). The flow is:
//   1. group   (persona groups)
//   2. persona (41 personas) — with the OPTIONAL niche refinement folded inline
//      (chips shown only for personas that carry a *specific* industries() list;
//      the taxonomy omits the rest)
//   3. design  (persona-tagged starting designs + "Start from scratch")
//   4. basics  (basic profile & branding fields)
//   5. additional (everything else)
// The basics/additional split is computed server-side and shipped on the
// question-set payload, so the two surfaces stay in lockstep. A selected
// persona carries the legacy (category, page_type) combo that drives the
// (unchanged) questions/generate endpoints.
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
const createSrc = readFileSync(
  join(root, "app", "(tabs)", "create.tsx"),
  "utf8",
);

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// Replica of the wizard's pure step logic. Mirrors wizard.tsx exactly:
//   - pickGroup          → step "persona" (clears persona/industry/template/answers)
//   - selectPersona      → STAYS on "persona" (sets persona, resets niche/template)
//   - toggleIndustry     → toggles the inline niche (null when toggled off)
//   - continueFromPersona→ step "design" (requires a persona)
//   - pickTemplate       → step "basics" (locked design prompts upgrade instead)
//   - basics "Continue"  → step "additional"
//   - goBack             → walks one step back; from "group" it exits
//   - resolveIndustries  → persona's taxonomy list (specific-only; may be empty)
//   - stepIndex          → 0..4 for the progress bar
// The wiring guards further down pin this replica to the real source.
// ---------------------------------------------------------------------------
const STEP_ORDER = ["group", "persona", "design", "basics", "additional"];

function makeWizard() {
  return {
    step: "group",
    group: null,
    persona: null,
    industry: null,
    templateId: null,
    answers: {},
    exited: false,
  };
}
function pickGroup(s, key) {
  s.group = key;
  s.persona = null;
  s.industry = null;
  s.templateId = null;
  s.answers = {};
  s.step = "persona";
}
function selectPersona(s, slug) {
  if (slug === s.persona) return;
  s.persona = slug;
  s.industry = null;
  s.templateId = null;
  s.answers = {};
  // No step change — niche refinement is inline; Continue advances.
}
function toggleIndustry(s, slug) {
  s.industry = s.industry === slug ? null : slug;
}
function continueFromPersona(s) {
  if (!s.persona) return;
  s.step = "design";
}
function pickTemplate(s, design) {
  if (design?.locked) return; // locked prompts upgrade, no advance
  s.templateId = design ? design.id : null;
  s.step = "basics";
}
function continueFromBasics(s) {
  s.step = "additional";
}
function goBack(s) {
  if (s.step === "persona") s.step = "group";
  else if (s.step === "design") s.step = "persona";
  else if (s.step === "basics") s.step = "design";
  else if (s.step === "additional") s.step = "basics";
  else s.exited = true;
}
// Specific-only: the taxonomy omits personas without a specific industries()
// list, so an empty result means "no inline niche refinement for this persona".
function resolveIndustries(taxonomy, persona) {
  if (!persona) return [];
  return taxonomy?.industries_by_persona?.[persona] ?? [];
}
function stepIndex(step) {
  return STEP_ORDER.indexOf(step);
}

// ---------------------------------------------------------------------------
// Fixture taxonomy. The `local_shop_owner` persona carries a specific
// industries list (inline niche chips show). `developer` deliberately has NO
// entry — the flow shows no niche chips for it (refinement is optional and only
// appears for personas with a specific list).
// ---------------------------------------------------------------------------
const taxonomy = {
  groups: [
    { key: "business", label: "Business" },
    { key: "personal", label: "Personal / Portfolio" },
  ],
  personas: {
    business: [
      {
        slug: "local_shop_owner",
        label: "Local Shop / Service",
        category: "business",
        page_type: "local_shop",
      },
    ],
    personal: [
      {
        slug: "developer",
        label: "Developer / Engineer",
        category: "personal",
        page_type: "developer",
      },
    ],
  },
  industries_by_persona: {
    local_shop_owner: [
      { slug: "bakery", label: "Bakery", icon: "fa-bread-slice" },
      { slug: "salon", label: "Hair / Beauty Salon", icon: "fa-scissors" },
    ],
    // developer intentionally absent — no inline niche.
  },
};

// ===========================================================================
// 1. Full advance: group → persona (select + Continue) → design → basics →
//    additional. The progress bar walks 0..4.
// ===========================================================================
{
  const s = makeWizard();
  assert.equal(s.step, "group");
  assert.equal(stepIndex(s.step), 0);

  pickGroup(s, "business");
  assert.equal(s.step, "persona", "picking a group advances to the persona step");
  assert.equal(s.group, "business");
  assert.equal(stepIndex(s.step), 1);

  // Selecting a persona does NOT advance — niche refinement is inline.
  selectPersona(s, "local_shop_owner");
  assert.equal(s.step, "persona", "selecting a persona stays on the step (inline niche)");
  assert.equal(s.persona, "local_shop_owner");
  assert.equal(stepIndex(s.step), 1);

  // This persona HAS a specific list, so inline niche chips show.
  const niche = resolveIndustries(taxonomy, s.persona);
  assert.equal(niche[0].slug, "bakery", "a persona with a specific list shows inline niche chips");

  // The niche is optional and toggleable.
  toggleIndustry(s, "bakery");
  assert.equal(s.industry, "bakery", "tapping a niche chip selects it");
  toggleIndustry(s, "bakery");
  assert.equal(s.industry, null, "tapping the same niche chip again clears it");
  toggleIndustry(s, "salon");
  assert.equal(s.industry, "salon", "tapping a different niche chip switches the selection");

  continueFromPersona(s);
  assert.equal(s.step, "design", "Continue advances to the starting-design step");
  assert.equal(stepIndex(s.step), 2);

  // Pick a (non-locked) template → advances to basics, records the id.
  pickTemplate(s, { id: 42, locked: false });
  assert.equal(s.step, "basics", "picking a design advances to the basics step");
  assert.equal(s.templateId, 42, "picking a design records its template id");
  assert.equal(stepIndex(s.step), 3);

  continueFromBasics(s);
  assert.equal(s.step, "additional", "Continue from basics advances to the additional step");
  assert.equal(stepIndex(s.step), 4);
}
ok("group → persona (select + inline niche + Continue) → design → basics → additional");

// A persona WITHOUT a specific list shows no inline niche chips.
{
  const niche = resolveIndustries(taxonomy, "developer");
  assert.deepEqual(niche, [], "a persona without a specific list shows no inline niche chips");
}
ok("a persona without a specific industries list shows no inline niche refinement");

// Continue is gated on having selected a persona.
{
  const s = makeWizard();
  pickGroup(s, "business");
  continueFromPersona(s);
  assert.equal(s.step, "persona", "Continue does nothing until a persona is selected");
}
ok("Continue from the persona step requires a selection");

// "Start from scratch" advances with a null template id.
{
  const s = makeWizard();
  pickGroup(s, "business");
  selectPersona(s, "local_shop_owner");
  continueFromPersona(s);
  pickTemplate(s, null);
  assert.equal(s.step, "basics", "Start from scratch advances to the basics step");
  assert.equal(s.templateId, null, "Start from scratch leaves the template id null");
}
ok("Start from scratch advances with a null template id");

// A locked design can't be selected — it does not advance.
{
  const s = makeWizard();
  pickGroup(s, "business");
  selectPersona(s, "local_shop_owner");
  continueFromPersona(s);
  pickTemplate(s, { id: 7, locked: true });
  assert.equal(s.step, "design", "a locked design must not advance (prompts upgrade)");
  assert.equal(s.templateId, null, "a locked design is not recorded as the template id");
}
ok("a locked starting design prompts an upgrade instead of advancing");

// ===========================================================================
// 2. Back navigation walks the steps in reverse, then exits the screen.
// ===========================================================================
{
  const s = makeWizard();
  pickGroup(s, "business");
  selectPersona(s, "local_shop_owner");
  continueFromPersona(s);
  pickTemplate(s, null);
  continueFromBasics(s);
  assert.equal(s.step, "additional");

  goBack(s);
  assert.equal(s.step, "basics", "Back from additional returns to the basics step");
  goBack(s);
  assert.equal(s.step, "design", "Back from basics returns to the design step");
  goBack(s);
  assert.equal(s.step, "persona", "Back from design returns to the persona step");
  goBack(s);
  assert.equal(s.step, "group", "Back from persona returns to the group step");
  assert.equal(s.exited, false, "still on-screen at the first step");
  goBack(s);
  assert.equal(s.exited, true, "Back from the first step exits the wizard (router.back)");
}
ok("Back walks additional → basics → design → persona → group, then exits");

// ===========================================================================
// 3. The niche is optional — advancing without selecting one leaves it null
//    (the server treats null as 'skipped').
// ===========================================================================
{
  const s = makeWizard();
  pickGroup(s, "business");
  selectPersona(s, "local_shop_owner");
  continueFromPersona(s);
  pickTemplate(s, null);
  assert.equal(s.step, "basics", "advancing without a niche still works");
  assert.equal(s.industry, null, "leaving the niche unset keeps it null");
}
ok("the inline niche is optional — advancing without one leaves industry = null");

// ===========================================================================
// 3.5. One-tap goal prefill from the Create Link screen. `prefillGroup` seeds
//      the persona group (lands on the persona step); an optional
//      `prefillPersona` (sent only for goals that map to exactly one persona)
//      ALSO seeds the persona and jumps straight to the starting-design step.
//      A foreign/unknown persona, or an invalid group, falls back safely.
// ===========================================================================

// Replica of the wizard.tsx prefill effect's group/persona branch (the
// category-match branch is covered elsewhere). Mirrors it exactly; the wiring
// guards in 4i pin this replica to the real source so it can't drift.
function applyPrefill(taxo, prm) {
  const s = makeWizard();
  const grp = prm.prefillGroup;
  if (
    typeof grp === "string" &&
    grp &&
    taxo?.groups.some((g) => g.key === grp)
  ) {
    s.group = grp;
    const pers = prm.prefillPersona;
    const inGroup =
      typeof pers === "string" &&
      !!pers &&
      (taxo?.personas[grp] ?? []).some((p) => p.slug === pers);
    if (inGroup) {
      s.persona = pers;
      s.step = "design";
    } else {
      s.step = "persona";
    }
  }
  return s;
}

// group only → lands on the persona step, persona unset.
{
  const s = applyPrefill(taxonomy, { prefillGroup: "business" });
  assert.equal(s.group, "business", "prefillGroup seeds the persona group");
  assert.equal(s.persona, null, "no prefillPersona leaves the persona unset");
  assert.equal(s.step, "persona", "group-only prefill lands on the persona step");
}
ok("prefillGroup seeds the group and lands on the persona step");

// group + valid persona → seeds the persona and jumps to the design step.
{
  const s = applyPrefill(taxonomy, {
    prefillGroup: "business",
    prefillPersona: "local_shop_owner",
  });
  assert.equal(s.group, "business");
  assert.equal(s.persona, "local_shop_owner", "a valid prefillPersona is seeded");
  assert.equal(s.step, "design", "persona prefill jumps to the starting-design step");
}
ok("prefillGroup + prefillPersona seeds the persona and jumps to the design step");

// group + foreign persona (belongs to another group) → ignored, persona step.
{
  const s = applyPrefill(taxonomy, {
    prefillGroup: "business",
    prefillPersona: "developer", // a Personal persona, not Business
  });
  assert.equal(s.group, "business", "the valid group still seeds");
  assert.equal(s.persona, null, "a foreign persona is not seeded");
  assert.equal(s.step, "persona", "a foreign persona falls back to the persona step");
}
ok("a foreign prefillPersona is ignored and falls back to the persona step");

// invalid group → nothing seeded, stays on the group step.
{
  const s = applyPrefill(taxonomy, {
    prefillGroup: "not-a-real-group",
    prefillPersona: "local_shop_owner",
  });
  assert.equal(s.group, null, "an invalid group seeds nothing");
  assert.equal(s.persona, null, "an invalid group never seeds a persona");
  assert.equal(s.step, "group", "an invalid group leaves the wizard on the group step");
}
ok("an invalid prefillGroup is ignored entirely");

// ===========================================================================
// 4. Source wiring guards — pin the replica above to the real component so the
//    two can't drift, and confirm the generation call is wired correctly.
// ===========================================================================

// 4a. Step order is the canonical 5-step list driving the progress bar.
assert.match(
  wizardSrc,
  /STEP_ORDER:\s*Step\[\]\s*=\s*\[\s*"group",\s*"persona",\s*"design",\s*"basics",\s*"additional",?\s*\]/,
  "STEP_ORDER must be the canonical 5-step list",
);
assert.match(
  wizardSrc,
  /const stepIndex = STEP_ORDER\.indexOf\(step\)/,
  "stepIndex must be derived from STEP_ORDER",
);
ok("component declares the canonical 5-step order");

// 4b. Step transitions in the component route exactly as replicated.
assert.match(
  wizardSrc,
  /function pickGroup\(key: string\)\s*\{[\s\S]*?reset\("persona"\);/,
  "pickGroup must advance to the persona step",
);
{
  const m = wizardSrc.match(/function selectPersona\(slug: string\)\s*\{[\s\S]*?\n\s\s\}/);
  assert.ok(m, "could not find selectPersona()");
  const body = m[0];
  assert.match(body, /setPersona\(slug\);/, "selectPersona must set the persona");
  assert.ok(
    !/reset\(/.test(body),
    "selectPersona must NOT advance the step (niche refinement is inline)",
  );
}
assert.match(
  wizardSrc,
  /function continueFromPersona\(\)\s*\{[\s\S]*?reset\("design"\);/,
  "continueFromPersona must advance to the design step",
);
{
  const m = wizardSrc.match(/function pickTemplate\([\s\S]*?\n\s\s\}/);
  assert.ok(m, "could not find pickTemplate()");
  const body = m[0];
  assert.match(body, /design\?\.locked/, "pickTemplate must guard against locked designs");
  assert.match(body, /reset\("basics"\);/, "pickTemplate must advance to the basics step");
}
assert.match(
  wizardSrc,
  /function toggleIndustry\(slug: string\)\s*\{[\s\S]*?cur === slug \? null : slug/,
  "toggleIndustry must toggle the niche selection (null when toggled off)",
);
ok("component step transitions match the redesigned 5-step flow");

// 4c. The inline niche is specific-only: keyed by persona, with an empty fallback.
assert.ok(
  !/GENERIC_INDUSTRIES/.test(wizardSrc),
  "the generic industry fallback must be removed (niche is specific-only now)",
);
assert.match(
  wizardSrc,
  /taxonomyQ\.data\?\.industries_by_persona\[persona\] \?\? \[\]/,
  "the industries memo must read the persona's specific list with an empty fallback",
);
ok("inline niche is specific-only, keyed by persona (no generic fallback)");

// 4d. The niche chips only render for personas that carry a specific list.
assert.match(
  wizardSrc,
  /persona && industries\.length \?/,
  "the inline niche chips must only render when the persona has a specific list",
);
ok("inline niche chips are gated on a non-empty specific list");

// 4e. The design step loads persona-tagged starting designs and offers
//     "Start from scratch".
assert.match(
  apiSrc,
  /`\/links\/wizard\/starting-designs\?/,
  "getWizardStartingDesigns must GET /links/wizard/starting-designs",
);
assert.match(
  wizardSrc,
  /getWizardStartingDesigns\(\{ persona: persona! \}\)/,
  "the design step must fetch persona-tagged starting designs",
);
assert.match(
  wizardSrc,
  /Start from scratch/,
  "the design step must offer a Start-from-scratch option",
);
ok("design step loads persona-tagged designs and offers Start from scratch");

// 4f. The basics/additional steps render from the server-split question set.
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

// 4g. goBack chains the steps in reverse and exits via router.back().
{
  const m = wizardSrc.match(/function goBack\(\)\s*\{[\s\S]*?\n\s\s\}/);
  assert.ok(m, "could not find goBack()");
  const body = m[0];
  assert.match(body, /step === "persona"\) reset\("group"\)/);
  assert.match(body, /step === "design"\) reset\("persona"\)/);
  assert.match(body, /step === "basics"\) reset\("design"\)/);
  assert.match(body, /step === "additional"\) reset\("basics"\)/);
  assert.match(body, /else router\.back\(\)/, "Back from the first step exits via router.back()");
}
ok("component goBack walks the steps in reverse and exits at the first step");

// 4h. Generation wiring: onGenerate posts the persona/template payload and
//     routes to the new link's block editor; the API helper hits the endpoint.
{
  const m = wizardSrc.match(/async function onGenerate\(\)\s*\{[\s\S]*?\n\s\s\}/);
  assert.ok(m, "could not find onGenerate()");
  const body = m[0];
  assert.match(
    body,
    /generateWizardPage\(\{[\s\S]*?persona,[\s\S]*?industry,[\s\S]*?template_id: templateId,[\s\S]*?answers,[\s\S]*?\}\)/,
    "onGenerate must call generateWizardPage with persona/industry/template_id/answers",
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
ok("generation wiring: onGenerate posts persona/template payload and opens the block editor");

// 4i. Goal-prefill wiring — pin the applyPrefill replica (section 3.5) to the
//     real source so the prefill effect can't silently drift.
{
  // The wizard reads an optional prefillPersona param alongside prefillGroup.
  assert.match(
    wizardSrc,
    /prefillPersona\?: string;/,
    "the wizard must accept an optional prefillPersona param",
  );
  // The prefill effect seeds the persona + jumps to the design step only when
  // the persona belongs to the prefilled group; otherwise it stops on persona.
  assert.match(
    wizardSrc,
    /\(taxonomyQ\.data\?\.personas\[grp\] \?\? \[\]\)\.some\(\(p\) => p\.slug === pers\)/,
    "the prefill must validate the persona belongs to the prefilled group",
  );
  assert.match(
    wizardSrc,
    /if \(personaValid\) \{[\s\S]*?setPersona\(pers\);[\s\S]*?setStep\("design"\);[\s\S]*?\} else \{[\s\S]*?setStep\("persona"\);/,
    "a valid prefillPersona must jump to design; a foreign one falls back to persona",
  );
}
// The create screen's openGuided builds the deep link with prefillGroup and,
// for single-persona goals, prefillPersona — and the taxonomy type carries the
// {group, persona} shape both sides read.
assert.match(
  createSrc,
  /const \{ group, persona \} = wizardGroups\[apiType\];/,
  "openGuided must destructure { group, persona } from the goal map",
);
assert.match(
  createSrc,
  /new URLSearchParams\(\{ prefillGroup: group \}\)/,
  "openGuided must seed prefillGroup on the deep link query string",
);
assert.match(
  createSrc,
  /if \(persona\) qs\.set\("prefillPersona", persona\)/,
  "openGuided must add prefillPersona to the deep link for single-persona goals",
);
assert.match(
  apiSrc,
  /wizard_groups: Record<\s*string,\s*\{ group: string \| null; persona: string \| null \}\s*>;/,
  "the taxonomy type must model wizard_groups as a {group, persona} map",
);
ok("goal-prefill wiring: deep link carries prefillGroup/prefillPersona and seeds the right step");

console.log(`\n[test-wizard-flow] all ${passed} checks passed`);
