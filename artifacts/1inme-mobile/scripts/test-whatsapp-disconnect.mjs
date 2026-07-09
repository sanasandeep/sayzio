// Source-driven test for the mobile WhatsApp DISCONNECT flow (Task #2780).
//
// Removing a WhatsApp number must turn its dependent alerts OFF immediately,
// because every WhatsApp toggle is gated on hasWhatsappNumber() server-side:
//   - the account-level "WhatsApp payment alerts" switch
//   - each form's "Notify me on WhatsApp" toggle
// The backend is covered by WhatsAppDisconnectApiTest, but the *mobile UI
// wiring* — invalidating the whatsapp-status / whatsapp-payment-alerts /
// per-form whatsapp-alert queries after a disconnect, steering the verify
// screen back to the "add" state, and the notification-preferences screen
// flipping the switch to disabled + showing the "Verify a WhatsApp number"
// CTA — had no automated coverage. A regression here would silently leave a
// user believing their alerts are still on after they removed their number.
//
// Following the convention in test-auth-flow.mjs / test-wizard-flow.mjs this
// is a source-driven test (NOT a headless browser click-through). It lifts the
// REAL functions / expressions out of the screens and runs them, plus pins the
// JSX gating to the source with wiring guards so the replica can't drift:
//   1. The REAL disconnectWhatsapp from lib/api/whatsapp.ts → DELETE /me/whatsapp.
//   2. The REAL invalidateDependents from whatsapp-verify.tsx — proving a
//      disconnect busts all three dependent caches (status, payment-alerts,
//      and every per-form whatsapp-alert query via its predicate).
//   3. The REAL "Remove" onPress — proving a successful disconnect invalidates
//      the dependents, refreshes auth, and steers the screen to the "enter"
//      (add) step so the verify CTA reappears.
//   4. The REAL initial-steering effect — manage when connected, enter when not.
//   5. The REAL notification-preferences gating expressions — after disconnect
//      the payment-alert switch reads disabled, the verify CTA shows, and the
//      "Manage" row disappears.
//
// Run via `node scripts/test-whatsapp-disconnect.mjs` (script `test:whatsapp-disconnect`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { runExtractedCall, runExtractedStatements } from "./lib/extract.mjs";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const whatsappApiSrc = readFileSync(
  join(root, "lib", "api", "whatsapp.ts"),
  "utf8",
);
const verifySrc = readFileSync(
  join(root, "app", "whatsapp-verify.tsx"),
  "utf8",
);
const prefsSrc = readFileSync(
  join(root, "app", "notification-preferences.tsx"),
  "utf8",
);

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ===========================================================================
// 1. The REAL disconnectWhatsapp hits DELETE /me/whatsapp (the wire the whole
//    flow rides on). Lift the function out of lib/api/whatsapp.ts, strip the
//    generic type arg, and run it against a captured apiFetch.
// ===========================================================================
console.log("[test-whatsapp-disconnect] disconnect endpoint");

{
  const m = whatsappApiSrc.match(
    /export async function disconnectWhatsapp\([\s\S]*?\n\}/m,
  );
  assert.ok(m, "could not find disconnectWhatsapp in lib/api/whatsapp.ts");
  const js = m[0]
    .replace(
      /export async function disconnectWhatsapp\(\): Promise<[^>]*> \{/,
      "async function disconnectWhatsapp() {",
    )
    .replace(/apiFetch<[\s\S]*?>\(/g, "apiFetch(");

  const calls = [];
  const apiFetch = async (path, init) => {
    calls.push({ path, init });
    return { data: { has_whatsapp_number: false, mobile_masked: null } };
  };
  // eslint-disable-next-line no-new-func
  const fn = new Function(
    "apiFetch",
    `${js}\n return disconnectWhatsapp;`,
  )(apiFetch);

  const res = await fn();
  assert.equal(calls.length, 1, "disconnect must make exactly one request");
  assert.equal(calls[0].path, "/me/whatsapp", "disconnect endpoint");
  assert.equal(calls[0].init.method, "DELETE", "disconnect must use DELETE");
  assert.equal(
    res.has_whatsapp_number,
    false,
    "disconnect must unwrap data.has_whatsapp_number = false",
  );
}
ok("disconnectWhatsapp issues DELETE /me/whatsapp and reports no number");

// ===========================================================================
// 2. The REAL invalidateDependents busts all three dependent caches. This is
//    the wiring that makes the alert toggles re-read their (now-gated-off)
//    state the moment the user returns from a disconnect.
// ===========================================================================
console.log("[test-whatsapp-disconnect] dependent cache invalidation");

function loadInvalidateDependents(qc) {
  const m = verifySrc.match(
    /const invalidateDependents = \(\) => \{[\s\S]*?\n  \};/m,
  );
  assert.ok(m, "could not find invalidateDependents in whatsapp-verify.tsx");
  return runExtractedStatements(m[0], "invalidateDependents", { qc },
    "invalidateDependents", { test: "test-whatsapp-disconnect" });
}

{
  const invalidations = [];
  const qc = {
    invalidateQueries: (arg) => {
      invalidations.push(arg);
    },
  };
  const invalidateDependents = loadInvalidateDependents(qc);
  invalidateDependents();

  const byKey = invalidations
    .filter((i) => Array.isArray(i.queryKey))
    .map((i) => i.queryKey[0]);
  assert.ok(
    byKey.includes("whatsapp-status"),
    "must invalidate the whatsapp-status query",
  );
  assert.ok(
    byKey.includes("whatsapp-payment-alerts"),
    "must invalidate the account-level whatsapp-payment-alerts query",
  );

  // The per-form "Notify me on WhatsApp" toggles are invalidated by predicate:
  // any query keyed ["form", <id>, "whatsapp-alert"]. Prove the predicate
  // matches a per-form alert query and ignores unrelated ones.
  const predicates = invalidations.filter(
    (i) => typeof i.predicate === "function",
  );
  assert.equal(
    predicates.length,
    1,
    "must invalidate per-form alerts via a single predicate",
  );
  const pred = predicates[0].predicate;
  assert.equal(
    pred({ queryKey: ["form", 42, "whatsapp-alert"] }),
    true,
    "predicate must match a per-form whatsapp-alert query",
  );
  assert.equal(
    pred({ queryKey: ["form", 42, "submissions"] }),
    false,
    "predicate must NOT match an unrelated per-form query",
  );
  assert.equal(
    pred({ queryKey: ["notifications"] }),
    false,
    "predicate must NOT match a non-form query",
  );
}
ok("invalidateDependents busts status, payment-alerts, and per-form whatsapp-alert caches");

// ===========================================================================
// 3. The REAL "Remove" onPress: a successful disconnect invalidates the
//    dependents, refreshes auth, and steers the screen back to the add flow so
//    the user can connect a new number (and the verify CTA reappears).
// ===========================================================================
console.log("[test-whatsapp-disconnect] remove handler steers to add flow");

{
  const m = verifySrc.match(
    /onPress: async \(\) => \{([\s\S]*?)\n          \},/m,
  );
  assert.ok(m, "could not find the Remove onPress in whatsapp-verify.tsx");
  const body = m[1].replace(/ as ApiError/g, "");

  const events = [];
  const stubSetter = (name) => (v) => events.push([name, v]);
  let invalidated = false;
  let refreshed = false;
  let step = "manage";

  const env = {
    setRemoving: stubSetter("setRemoving"),
    disconnectWhatsapp: async () => {
      events.push(["disconnect"]);
      return { has_whatsapp_number: false, mobile_masked: null };
    },
    invalidateDependents: () => {
      invalidated = true;
    },
    refresh: async () => {
      refreshed = true;
    },
    setMobile: stubSetter("setMobile"),
    setCode: stubSetter("setCode"),
    setDemoReveal: stubSetter("setDemoReveal"),
    setResentAt: stubSetter("setResentAt"),
    cooldown: { clear: () => events.push(["cooldown.clear"]) },
    setError: stubSetter("setError"),
    setStep: (s) => {
      step = s;
    },
    Alert: { alert: () => events.push(["Alert.alert"]) },
    // The screen now routes alerts through the web-safe showAlert shim
    // (lib/webAlert.ts) instead of the react-native Alert directly.
    showAlert: () => events.push(["Alert.alert"]),
  };

  // Shared resilient helper: a NEW free variable in the onPress body warns
  // actionably instead of hard-crashing with a raw ReferenceError.
  const onPress = runExtractedCall(`(async () => {${body}})`, env,
    "Remove onPress", { test: "test-whatsapp-disconnect" });

  await onPress();

  const order = events.map((e) => e[0]);
  assert.ok(order.includes("disconnect"), "onPress must call disconnectWhatsapp");
  assert.ok(invalidated, "onPress must invalidate the dependent alert queries");
  assert.ok(refreshed, "onPress must refresh the auth/session state");
  assert.equal(
    step,
    "enter",
    "after a disconnect the screen must steer to the add flow (so the verify CTA reappears)",
  );
  assert.ok(
    order.indexOf("disconnect") < order.lastIndexOf("setRemoving"),
    "the busy flag must clear after the disconnect resolves",
  );
}
ok("Remove onPress disconnects, invalidates, refreshes, and drops into the add flow");

// ===========================================================================
// 4. The REAL initial-steering effect: land on manage when a number is
//    connected, on the add (enter) flow when not — and only steer once.
// ===========================================================================
console.log("[test-whatsapp-disconnect] initial screen steering");

function loadSteerEffect() {
  const m = verifySrc.match(
    /useEffect\(\(\) => \{([\s\S]*?)\n  \}, \[status\.data, steered\]\);/m,
  );
  assert.ok(m, "could not find the steering effect in whatsapp-verify.tsx");
  const body = m[1];
  return (steered, statusData) => {
    let step = null;
    let nextSteered = steered;
    const env = {
      steered,
      status: { data: statusData },
      setStep: (s) => {
        step = s;
      },
      setSteered: (b) => {
        nextSteered = b;
      },
    };
    runExtractedStatements(body, "undefined", env, "steering effect", {
      test: "test-whatsapp-disconnect",
    });
    return { step, steered: nextSteered };
  };
}

{
  const run = loadSteerEffect();
  assert.deepEqual(
    run(false, { has_whatsapp_number: true }),
    { step: "manage", steered: true },
    "a connected number must land on the manage view",
  );
  assert.deepEqual(
    run(false, { has_whatsapp_number: false }),
    { step: "enter", steered: true },
    "no number must land on the add flow",
  );
  assert.deepEqual(
    run(true, { has_whatsapp_number: true }),
    { step: null, steered: true },
    "an in-progress add/verify must not be yanked back once steered",
  );
  assert.deepEqual(
    run(false, null),
    { step: null, steered: false },
    "must wait for the status query before steering",
  );
}
ok("the verify screen steers to manage when connected and add when not (once)");

// ===========================================================================
// 5. The REAL notification-preferences gating: extract the payment-alert
//    switch's value/disabled expressions and the verify-CTA / manage-row
//    conditions verbatim, then evaluate them across connected vs disconnected.
// ===========================================================================
console.log("[test-whatsapp-disconnect] notification-preferences switch + CTA gating");

function extractExpr(re, label) {
  const m = prefsSrc.match(re);
  assert.ok(m, `could not find ${label} in notification-preferences.tsx`);
  return m[1];
}

{
  const valueExpr = extractExpr(/value=\{(!!waq\.data\?\.enabled)\}/, "switch value");
  const disabledExpr = extractExpr(
    /disabled=\{\s*(!waq\.data\?\.has_whatsapp_number \|\| waMutation\.isPending)\s*\}/,
    "switch disabled",
  );
  const verifyExpr = extractExpr(
    /\{(waq\.data && !waq\.data\.has_whatsapp_number) \?/,
    "verify CTA condition",
  );
  const manageExpr = extractExpr(
    /\{(waq\.data && waq\.data\.has_whatsapp_number) \?/,
    "manage row condition",
  );

  const gate = (waq, waMutation) =>
    runExtractedCall(
      `{
         value: !!(${valueExpr}),
         disabled: !!(${disabledExpr}),
         showVerify: !!(${verifyExpr}),
         showManage: !!(${manageExpr}),
       }`,
      { waq, waMutation },
      "notification-preferences gating",
      { test: "test-whatsapp-disconnect" },
    );

  // Connected: alerts ON, switch enabled, manage row shown, no verify CTA.
  const connected = gate(
    { data: { enabled: true, has_whatsapp_number: true, mobile_masked: "••• 4567" } },
    { isPending: false },
  );
  assert.deepEqual(
    connected,
    { value: true, disabled: false, showVerify: false, showManage: true },
    "while connected: switch on + enabled, manage row, no verify CTA",
  );

  // After disconnect: the payment-alerts query reports has_whatsapp_number
  // false and enabled false → switch reads OFF + disabled, verify CTA returns,
  // manage row gone. This is the core regression guard.
  const disconnected = gate(
    { data: { enabled: false, has_whatsapp_number: false, mobile_masked: null } },
    { isPending: false },
  );
  assert.deepEqual(
    disconnected,
    { value: false, disabled: true, showVerify: true, showManage: false },
    "after disconnect: switch off + DISABLED, verify CTA reappears, manage row gone",
  );

  // Even if a stale `enabled: true` lingers for a frame, the missing number
  // still hard-disables the switch (the gate is has_whatsapp_number, not enabled).
  const stale = gate(
    { data: { enabled: true, has_whatsapp_number: false, mobile_masked: null } },
    { isPending: false },
  );
  assert.equal(
    stale.disabled,
    true,
    "a missing number must disable the switch regardless of a stale enabled flag",
  );
  assert.equal(stale.showVerify, true, "a missing number must show the verify CTA");
}
ok("payment-alert switch reads disabled + verify CTA returns once the number is gone");

// The manage row's tap target must route to the verify/manage screen so the
// disconnect flow is reachable from notification preferences.
assert.match(
  prefsSrc,
  /onPress=\{\(\) => router\.push\("\/whatsapp-verify"\)\}/,
  "the WhatsApp rows must route to the /whatsapp-verify screen",
);
ok("the notification-preferences WhatsApp rows link to the verify/manage screen");

console.log(`\n[test-whatsapp-disconnect] all ${passed} checks passed`);
