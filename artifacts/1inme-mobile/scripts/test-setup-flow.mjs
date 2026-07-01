// Source-driven tests for the mobile first-run staged setup hand-off.
//
// The staged setup (app/setup.tsx) is the mobile mirror of the web onboarding
// wizard: Welcome → Persona → Template → (WhatsApp, optional) → Done. It owns
// the single most important moment of the first-run experience — where a
// brand-new user lands once setup finishes:
//   - applying a template creates the first biolink and captures its `Link` id
//     (createdLinkId); finishing drops the user straight into that page's
//     editor (`/links/{id}/edit`), mirroring the web hand-off.
//   - skipping the template creates no page (createdLinkId stays null); finishing
//     lands the user on the dashboard (`/(tabs)`).
//
// Unlike the guided wizard (app/links/wizard.tsx, covered by
// test-wizard-flow.mjs) this hand-off had no source-driven test — a silent
// regression (e.g. always routing to the dashboard again) would quietly undo
// the parity fix and no test would notice.
//
// Following the convention in test-wizard-flow.mjs / test-block-cache.mjs we
// avoid a full RN/TS test runner: the hand-off is pure state logic, so we
// replicate it here AND pin the replica to the real source with wiring guards,
// so the replica can't silently drift.
//
// Run via `node scripts/test-setup-flow.mjs` (package script `test:setup-flow`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const setupSrc = readFileSync(join(root, "app", "setup.tsx"), "utf8");

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// Replica of setup.tsx's hand-off logic. Mirrors it exactly:
//   - applyDesign(link) → records the created biolink's id (createdLinkId).
//   - skipTemplate()    → creates no page; createdLinkId stays null.
//   - finishToApp()     → routes to `/links/{id}/edit` when a page was created,
//                         otherwise to `/(tabs)` (the dashboard).
// The wiring guards further down pin this replica to the real source.
// ---------------------------------------------------------------------------
function makeSetup() {
  return { createdLinkId: null, route: null };
}
function applyDesign(s, link) {
  // Mirrors setup.tsx: on a successful generateWizardPage() the created link's
  // id is stored so finishing opens its editor.
  s.createdLinkId = link.id;
}
function skipTemplate(s) {
  // Mirrors setup.tsx: skipping never creates a page — createdLinkId untouched.
}
function finishToApp(s) {
  s.route =
    s.createdLinkId != null ? `/links/${s.createdLinkId}/edit` : "/(tabs)";
  return s.route;
}

// ===========================================================================
// 1. Applying a template captures the created Link id and finishing routes
//    straight into that page's editor.
// ===========================================================================
{
  const s = makeSetup();
  assert.equal(s.createdLinkId, null, "no page created yet");

  applyDesign(s, { id: 77 });
  assert.equal(s.createdLinkId, 77, "applying a template records the created Link id");

  finishToApp(s);
  assert.equal(
    s.route,
    "/links/77/edit",
    "finishing after a template routes to the created page's editor",
  );
}
ok("applying a template captures the Link id and finishing opens /links/{id}/edit");

// ===========================================================================
// 2. Skipping the template creates no page — finishing lands on the dashboard.
// ===========================================================================
{
  const s = makeSetup();
  skipTemplate(s);
  assert.equal(s.createdLinkId, null, "skipping the template creates no page");

  finishToApp(s);
  assert.equal(s.route, "/(tabs)", "finishing after a skip routes to the dashboard");
}
ok("skipping the template routes to the dashboard /(tabs)");

// A page id of 0 is still a real id — the hand-off must not treat it as skipped.
{
  const s = makeSetup();
  applyDesign(s, { id: 0 });
  finishToApp(s);
  assert.equal(
    s.route,
    "/links/0/edit",
    "a zero Link id is still a created page (uses != null, not falsy)",
  );
}
ok("a zero Link id is treated as a created page, not a skip");

// ===========================================================================
// 3. Source wiring guards — pin the replica above to the real component so the
//    two can't drift.
// ===========================================================================

// 3a. applyDesign stores the created link's id from generateWizardPage.
{
  const m = setupSrc.match(/const applyDesign = async \([\s\S]*?\n {2}\};/);
  assert.ok(m, "could not find applyDesign()");
  const body = m[0];
  assert.match(
    body,
    /const link = await generateWizardPage\(\{/,
    "applyDesign must create the page via generateWizardPage",
  );
  assert.match(
    body,
    /setCreatedLinkId\(link\.id\)/,
    "applyDesign must capture the created Link id",
  );
}
ok("component applyDesign captures the created Link id");

// 3b. skipTemplate never sets a created link id (it stays null → dashboard).
{
  const m = setupSrc.match(/const skipTemplate = async \([\s\S]*?\n {2}\};/);
  assert.ok(m, "could not find skipTemplate()");
  const body = m[0];
  assert.ok(
    !/setCreatedLinkId/.test(body),
    "skipTemplate must NOT set a created Link id (stays null → dashboard)",
  );
}
ok("component skipTemplate never records a created Link id");

// 3c. finishToApp branches on createdLinkId: editor when set, dashboard when null.
{
  const m = setupSrc.match(/const finishToApp = \(\) => \{[\s\S]*?\n {2}\};/);
  assert.ok(m, "could not find finishToApp()");
  const body = m[0];
  assert.match(
    body,
    /createdLinkId != null/,
    "finishToApp must branch on createdLinkId != null (a real id, incl. 0, opens the editor)",
  );
  assert.match(
    body,
    /router\.replace\(`\/links\/\$\{createdLinkId\}\/edit`/,
    "finishToApp must open the created page's editor when a page was created",
  );
  assert.match(
    body,
    /router\.replace\("\/\(tabs\)"\)/,
    "finishToApp must land on the dashboard when no page was created",
  );
}
ok("component finishToApp routes to the editor or the dashboard on createdLinkId");

// 3d. createdLinkId is component state seeded null (skip is the default outcome).
assert.match(
  setupSrc,
  /const \[createdLinkId, setCreatedLinkId\] = useState<number \| null>\(null\)/,
  "createdLinkId must be number|null state initialised to null",
);
ok("createdLinkId is number|null state initialised to null");

console.log(`\n[test-setup-flow] all ${passed} checks passed`);
