// Source-driven test for the phone-contact import flow — the promise that a
// real device build can never SILENTLY fail an import.
//
// The flow is native-only (expo-contacts, hidden on web), so web e2e can't
// exercise it. Following test-contact-details.mjs / scripts/lib/extract.mjs we
// lift the REAL shipped code:
//
//   1. importDeviceContacts (lib/deviceContacts.ts) — outcome mapping:
//      - missing native module        → { ok:false, reason:"unavailable" }
//      - permission denied            → { ok:false, reason:"denied" }
//        (requestPermission:false must NOT re-prompt; true prompts once and
//        proceeds when the prompt grants)
//      - no importable contacts       → { ok:false, reason:"empty" }
//        (contacts with neither name nor email nor phone are filtered out,
//        and NO network call is made)
//      - success                      → { ok:true, result, imported } with the
//        payload mapped field-by-field (names/company/emails/phones + labels)
//      - a failing bulkImportContacts REJECTS (never swallowed).
//   2. The contacts screen's onSuccess/onError handlers (app/contacts.tsx) —
//      every outcome branch surfaces a user-visible alert, the summary line
//      shows created/updated/skipped, duplicates add a "Review duplicates"
//      button that routes to /contact-duplicates, and onError alerts too.
//
// Runs with node only — expo-contacts and the API client are mocked.
// Run via `node scripts/test-device-contacts-import.mjs`
// (package script `test:device-contacts-import`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { runExtractedStatements } from "./lib/extract.mjs";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const libSrc = readFileSync(join(root, "lib", "deviceContacts.ts"), "utf8");
const screenSrc = readFileSync(join(root, "app", "contacts.tsx"), "utf8");

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// Lift the REAL importDeviceContacts out of lib/deviceContacts.ts, strip its
// (simple) TS annotations, and inject mock expo-contacts + bulkImportContacts.
// ---------------------------------------------------------------------------
function extractImportFn(src) {
  const start = src.indexOf("export async function importDeviceContacts");
  if (start === -1) throw new Error("could not find importDeviceContacts");
  // The multi-line `opts?: { ... }` parameter puts a column-0 `}` inside the
  // signature, so scan for the body's closing brace AFTER the return type.
  const sigEnd = src.indexOf("Promise<DeviceImportOutcome>", start);
  if (sigEnd === -1) throw new Error("could not find importDeviceContacts return type");
  const end = src.indexOf("\n}", sigEnd);
  if (end === -1) throw new Error("unterminated importDeviceContacts body");
  return src.slice(start, end + 2);
}

const importFnSrc = extractImportFn(libSrc)
  .replace(
    /export async function importDeviceContacts\(opts\?: \{[\s\S]*?\}\): Promise<DeviceImportOutcome> \{/,
    "async function importDeviceContacts(opts) {",
  )
  .replace('await import("expo-contacts")', "await __importExpoContacts()")
  .replace("const payload: ContactImportPayload[] =", "const payload =")
  .replace(/: any\)/g, ")");

assert.ok(
  !/[:?]\s*(Promise|ContactImportPayload|DeviceImportOutcome)/.test(importFnSrc),
  "TS annotations were fully stripped from the lifted importDeviceContacts",
);

// eslint-disable-next-line no-new-func
const makeImporter = new Function(
  "__importExpoContacts",
  "bulkImportContacts",
  `${importFnSrc}\nreturn importDeviceContacts;`,
);

// Minimal expo-contacts mock. Field constants mirror the real module enough
// for the lifted code to build its `fields` array.
function makeContactsModule({
  permission = "granted",
  promptResult = null, // status returned by requestPermissionsAsync
  contacts = [],
  log = [],
} = {}) {
  return {
    Fields: {
      FirstName: "firstName",
      LastName: "lastName",
      Name: "name",
      Company: "company",
      Emails: "emails",
      PhoneNumbers: "phoneNumbers",
    },
    getPermissionsAsync: async () => {
      log.push("getPermissions");
      return { status: permission };
    },
    requestPermissionsAsync: async () => {
      log.push("requestPermissions");
      return { status: promptResult ?? permission };
    },
    getContactsAsync: async (query) => {
      log.push({ getContacts: query });
      return { data: contacts };
    },
  };
}

// ===========================================================================
// 1. Missing native module → "unavailable" (web / stripped builds degrade
//    loudly, not with a crash and not silently).
// ===========================================================================
{
  const apiCalls = [];
  const importDeviceContacts = makeImporter(
    () => Promise.reject(new Error("Cannot find module 'expo-contacts'")),
    async (p) => (apiCalls.push(p), { created: 0, updated: 0, skipped: 0 }),
  );
  const out = await importDeviceContacts({ requestPermission: true });
  assert.deepEqual(out, { ok: false, reason: "unavailable" });
  assert.equal(apiCalls.length, 0, "no API call when the module is missing");
}
ok('missing expo-contacts module → { ok:false, reason:"unavailable" }, no API call');

// ===========================================================================
// 2. Permission handling.
// ===========================================================================
{
  // Denied, requestPermission:false → NO prompt, reason "denied".
  const log = [];
  const mod = makeContactsModule({ permission: "denied", log });
  const importDeviceContacts = makeImporter(
    () => Promise.resolve(mod),
    async () => assert.fail("must not call the API when denied"),
  );
  const out = await importDeviceContacts();
  assert.deepEqual(out, { ok: false, reason: "denied" });
  assert.ok(
    !log.includes("requestPermissions"),
    "silent re-sync (requestPermission unset) never re-prompts the user",
  );
}
{
  // Denied, prompt also denies → "denied" after ONE prompt.
  const log = [];
  const mod = makeContactsModule({ permission: "denied", promptResult: "denied", log });
  const importDeviceContacts = makeImporter(
    () => Promise.resolve(mod),
    async () => assert.fail("must not call the API when denied"),
  );
  const out = await importDeviceContacts({ requestPermission: true });
  assert.deepEqual(out, { ok: false, reason: "denied" });
  assert.equal(
    log.filter((l) => l === "requestPermissions").length,
    1,
    "prompts exactly once",
  );
}
{
  // Denied, prompt grants → proceeds to read contacts.
  const log = [];
  const mod = makeContactsModule({
    permission: "denied",
    promptResult: "granted",
    contacts: [{ name: "Ada", phoneNumbers: [{ number: "+1 555", label: "mobile" }] }],
    log,
  });
  const importDeviceContacts = makeImporter(
    () => Promise.resolve(mod),
    async () => ({ created: 1, updated: 0, skipped: 0, duplicates_found: 0 }),
  );
  const out = await importDeviceContacts({ requestPermission: true });
  assert.equal(out.ok, true, "a granted prompt lets the import proceed");
  const q = log.find((l) => typeof l === "object" && l.getContacts);
  assert.ok(q, "contacts were actually read");
  assert.deepEqual(
    [...q.getContacts.fields].sort(),
    ["company", "emails", "firstName", "lastName", "name", "phoneNumbers"].sort(),
    "all mapped fields are requested from the address book",
  );
}
ok("permission branches: no silent re-prompt, single prompt, grant proceeds");

// ===========================================================================
// 3. Empty / unusable address book → "empty", no API call.
// ===========================================================================
{
  const apiCalls = [];
  const mod = makeContactsModule({
    contacts: [
      {}, // nothing at all
      { emails: [{}, { email: "" }], phoneNumbers: [{ number: null }] }, // junk entries
    ],
  });
  const importDeviceContacts = makeImporter(
    () => Promise.resolve(mod),
    async (p) => (apiCalls.push(p), {}),
  );
  const out = await importDeviceContacts({ requestPermission: true });
  assert.deepEqual(out, { ok: false, reason: "empty" });
  assert.equal(apiCalls.length, 0, "no bulk POST for an unusable address book");
}
ok('empty / junk-only address book → { ok:false, reason:"empty" }, no API call');

// ===========================================================================
// 4. Success: field-by-field payload mapping + result/imported passthrough.
// ===========================================================================
{
  const apiCalls = [];
  const mod = makeContactsModule({
    contacts: [
      {
        name: "Ada Lovelace",
        firstName: "Ada",
        lastName: "Lovelace",
        company: "Analytical Engines",
        emails: [{ email: "ada@example.com", label: "work" }, { email: null }],
        phoneNumbers: [{ number: "+44 1", label: null }, { number: "" }],
      },
      { name: "Name Only" }, // kept: has a display name
      { emails: [{ email: "x@y.z" }] }, // kept: has an email
      {}, // dropped
    ],
  });
  const serverResult = { created: 2, updated: 1, skipped: 0, duplicates_found: 1 };
  const importDeviceContacts = makeImporter(
    () => Promise.resolve(mod),
    async (payload) => (apiCalls.push(payload), serverResult),
  );
  const out = await importDeviceContacts({ requestPermission: true });
  assert.equal(apiCalls.length, 1, "exactly one bulk POST");
  const payload = apiCalls[0];
  assert.equal(payload.length, 3, "only importable contacts are sent");
  assert.deepEqual(payload[0], {
    display_name: "Ada Lovelace",
    given_name: "Ada",
    family_name: "Lovelace",
    organization: "Analytical Engines",
    emails: [{ value: "ada@example.com", label: "work" }],
    phones: [{ value: "+44 1", label: null }],
  });
  assert.deepEqual(payload[1], {
    display_name: "Name Only",
    given_name: null,
    family_name: null,
    organization: null,
    emails: [],
    phones: [],
  });
  assert.equal(out.ok, true);
  assert.deepEqual(out.result, serverResult);
  assert.equal(out.imported, 3);
  assert.ok(
    typeof out.fingerprint === "string" && out.fingerprint.length > 0,
    "a successful import must return the payload fingerprint",
  );
}
ok("success maps fields (junk emails/phones filtered) and returns result + imported + fingerprint");

// ===========================================================================
// 4b. Change detection: an unchanged address book skips the bulk POST when
//     the caller passes back the last fingerprint; a changed book still POSTs.
// ===========================================================================
{
  const contacts = [{ name: "Ada", emails: [{ email: "ada@example.com" }] }];
  const apiCalls = [];
  const makeIt = (list) =>
    makeImporter(
      () => Promise.resolve(makeContactsModule({ contacts: list })),
      async (p) => (apiCalls.push(p), { created: 1, updated: 0, skipped: 0 }),
    );

  const first = await makeIt(contacts)({ requestPermission: false });
  assert.equal(first.ok, true);
  assert.equal(apiCalls.length, 1, "first sync POSTs");

  // Same book + last fingerprint → no POST, "unchanged" outcome that still
  // carries the fingerprint so callers keep remembering it.
  const second = await makeIt(contacts)({
    requestPermission: false,
    unchangedFingerprint: first.fingerprint,
  });
  assert.deepEqual(second, {
    ok: false,
    reason: "unchanged",
    fingerprint: first.fingerprint,
  });
  assert.equal(apiCalls.length, 1, "unchanged address book must skip the bulk POST");

  // A stale/other fingerprint (changed book) still POSTs and returns the new one.
  const changed = [{ name: "Ada B", emails: [{ email: "ada@example.com" }] }];
  const third = await makeIt(changed)({
    requestPermission: false,
    unchangedFingerprint: first.fingerprint,
  });
  assert.equal(third.ok, true, "a changed book must still import");
  assert.notEqual(third.fingerprint, first.fingerprint, "fingerprint must change with the payload");
  assert.equal(apiCalls.length, 2, "changed address book POSTs again");
}
ok("unchanged fingerprint skips the bulk POST; changed payloads still import with a new fingerprint");

// ===========================================================================
// 5. A failing bulk POST REJECTS — never swallowed into a fake outcome.
// ===========================================================================
{
  const mod = makeContactsModule({ contacts: [{ name: "A" }] });
  const importDeviceContacts = makeImporter(
    () => Promise.resolve(mod),
    async () => {
      throw new Error("HTTP 500");
    },
  );
  await assert.rejects(
    importDeviceContacts({ requestPermission: true }),
    /HTTP 500/,
    "API failures propagate (the screen's onError alerts on them)",
  );
}
ok("bulkImportContacts failure rejects — never silently mapped to a success/empty outcome");

// ===========================================================================
// Lift the screen's REAL onSuccess / onError handlers out of contacts.tsx.
// ===========================================================================
function sliceBetween(src, startMarker, endMarker, label) {
  const start = src.indexOf(startMarker);
  if (start === -1) throw new Error(`could not find ${label} start`);
  const end = src.indexOf(endMarker, start);
  if (end === -1) throw new Error(`could not find ${label} end`);
  return src.slice(start, end);
}

// The screen now has multiple mutations with onSuccess/onError handlers (e.g.
// the Google-sync mutation added later sits ABOVE the device import). Anchor
// on the import mutation's mutationFn so we lift the right handlers.
const importMutationSrc = sliceBetween(
  screenSrc,
  "mutationFn: async () =>",
  "\n  });",
  "device import mutation",
);

// The manual import must read the stored per-user fingerprint and hand it to
// importDeviceContacts as unchangedFingerprint, so an unchanged phone book
// skips the bulk POST entirely.
assert.ok(
  /unchangedFingerprint:/.test(importMutationSrc) &&
    /getStoredContactSyncFingerprint\(user\.id\)/.test(importMutationSrc),
  "manual import passes getStoredContactSyncFingerprint(user.id) as unchangedFingerprint",
);
assert.ok(
  /user\?\.id != null\s*\?\s*await getStoredContactSyncFingerprint\(user\.id\)\s*:\s*null/.test(
    importMutationSrc,
  ),
  "no stored-fingerprint read without a signed-in user id (falls back to null)",
);
ok("manual import wires the stored fingerprint into importDeviceContacts");

const onSuccessSrc = sliceBetween(
  importMutationSrc,
  "onSuccess: (out) => {",
  "\n    onError:",
  "onSuccess handler",
)
  .replace("onSuccess: (out) => {", "const onSuccess = (out) => {")
  .replace(/,\s*$/, ";")
  .replace(/ as any\)/g, ")");

const onErrorSrc = sliceBetween(
  `${importMutationSrc}\n  });`,
  "onError: (e: any) => {",
  "\n  });",
  "onError handler",
)
  .replace("onError: (e: any) => {", "const onError = (e) => {")
  .replace(/,\s*$/, ";");

function makeScreenHarness({ user = { id: 42 } } = {}) {
  const alerts = [];
  const pushes = [];
  const invalidated = [];
  const persisted = [];
  const scope = {
    showAlert: (title, message, buttons) => alerts.push({ title, message, buttons }),
    router: { push: (path) => pushes.push(path) },
    qc: { invalidateQueries: (q) => invalidated.push(q.queryKey) },
    user,
    setStoredContactSyncFingerprint: (userId, fingerprint) => {
      persisted.push({ userId, fingerprint });
      return Promise.resolve();
    },
  };
  const handlers = runExtractedStatements(
    `${onSuccessSrc}\n${onErrorSrc}`,
    "({ onSuccess, onError })",
    scope,
    "contacts import handlers",
    { test: "test-device-contacts-import" },
  );
  return { ...handlers, alerts, pushes, invalidated, persisted };
}

// ===========================================================================
// 6. Every failure reason produces a user-visible alert (nothing silent).
// ===========================================================================
{
  const cases = [
    ["unavailable", "Not available"],
    ["denied", "Permission needed"],
    ["empty", "Nothing to import"],
  ];
  for (const [reason, title] of cases) {
    const h = makeScreenHarness();
    h.onSuccess({ ok: false, reason });
    assert.equal(h.alerts.length, 1, `${reason} alerts exactly once`);
    assert.equal(h.alerts[0].title, title, `${reason} → "${title}" alert`);
    assert.equal(h.invalidated.length, 0, `${reason} does not invalidate queries`);
    assert.equal(h.pushes.length, 0, `${reason} does not navigate`);
    assert.equal(h.persisted.length, 0, `${reason} does not persist a fingerprint`);
  }
}
ok("unavailable / denied / empty each raise a distinct visible alert");

// ===========================================================================
// 6b. "unchanged" (re-tapping Import with an unchanged phone book) shows a
//     friendly "Already up to date" alert, refreshes nothing, and re-persists
//     the fingerprint it carries (harmless same-value write).
// ===========================================================================
{
  const h = makeScreenHarness({ user: { id: 42 } });
  h.onSuccess({ ok: false, reason: "unchanged", fingerprint: "123:abcd" });
  assert.equal(h.alerts.length, 1, "unchanged alerts exactly once");
  assert.equal(h.alerts[0].title, "Already up to date", 'unchanged → "Already up to date" alert');
  assert.equal(h.invalidated.length, 0, "unchanged does not invalidate queries");
  assert.equal(h.pushes.length, 0, "unchanged does not navigate");
  assert.deepEqual(
    h.persisted,
    [{ userId: 42, fingerprint: "123:abcd" }],
    "unchanged still (re)persists the carried fingerprint per-user",
  );
}
ok('unchanged phone book shows "Already up to date" with no query refresh');

// ===========================================================================
// 7. Success without duplicates: summary alert + query invalidation.
// ===========================================================================
{
  const h = makeScreenHarness();
  h.onSuccess({
    ok: true,
    imported: 5,
    result: { created: 3, updated: 1, skipped: 1, duplicates_found: 0 },
  });
  assert.equal(h.alerts.length, 1);
  assert.equal(h.alerts[0].title, "Import complete");
  assert.equal(h.alerts[0].message, "Created 3, updated 1, skipped 1.");
  assert.equal(h.alerts[0].buttons, undefined, "no button set when no duplicates");
  assert.deepEqual(
    h.invalidated.sort((a, b) => String(a).localeCompare(String(b))),
    [["contact-duplicate-count"], ["contacts"]],
    "contacts + duplicate-count queries are invalidated",
  );
  assert.equal(h.pushes.length, 0, "no navigation without user action");
}
ok("clean success shows the created/updated/skipped summary and refreshes queries");

// ===========================================================================
// 7b. A successful manual import persists the returned fingerprint under the
//     signed-in user's id, so the NEXT auto-sync of an unchanged address book
//     skips its bulk POST (no redundant re-upload after a manual import).
// ===========================================================================
{
  const h = makeScreenHarness({ user: { id: 42 } });
  h.onSuccess({
    ok: true,
    imported: 5,
    fingerprint: "123:abcd",
    result: { created: 3, updated: 1, skipped: 1, duplicates_found: 0 },
  });
  assert.deepEqual(
    h.persisted,
    [{ userId: 42, fingerprint: "123:abcd" }],
    "manual import persists the fingerprint keyed by the signed-in user id",
  );

  // No signed-in user id → nothing persisted (never write an unkeyed value).
  const h2 = makeScreenHarness({ user: null });
  h2.onSuccess({
    ok: true,
    imported: 1,
    fingerprint: "9:ffff",
    result: { created: 1, updated: 0, skipped: 0, duplicates_found: 0 },
  });
  assert.equal(h2.persisted.length, 0, "no persistence without a signed-in user id");
  assert.equal(h2.alerts.length, 1, "the success alert still shows");
}
ok("manual import persists the fingerprint via setStoredContactSyncFingerprint (per-user)");

// ===========================================================================
// 8. Success WITH duplicates: dupe line + "Review duplicates" button that
//    routes to /contact-duplicates (and a cancel that doesn't).
// ===========================================================================
{
  const h = makeScreenHarness();
  h.onSuccess({
    ok: true,
    imported: 4,
    result: { created: 2, updated: 0, skipped: 2, duplicates_found: 2 },
  });
  assert.equal(h.alerts.length, 1);
  const a = h.alerts[0];
  assert.equal(a.title, "Import complete");
  assert.ok(
    a.message.startsWith("Created 2, updated 0, skipped 2."),
    "summary line still leads the message",
  );
  assert.ok(
    /2 imported contacts look like duplicates/.test(a.message),
    "plural duplicate line is included",
  );
  assert.equal(a.buttons.length, 2, "Later + Review duplicates");
  const cancel = a.buttons.find((b) => b.style === "cancel");
  assert.equal(cancel.text, "Later");
  const review = a.buttons.find((b) => b.text === "Review duplicates");
  assert.ok(review, 'has a "Review duplicates" button');
  review.onPress();
  assert.deepEqual(h.pushes, ["/contact-duplicates"], "button routes to /contact-duplicates");

  // Singular copy for exactly one duplicate.
  const h1 = makeScreenHarness();
  h1.onSuccess({
    ok: true,
    imported: 1,
    result: { created: 1, updated: 0, skipped: 0, duplicates_found: 1 },
  });
  assert.ok(
    /1 imported contact looks like a duplicate/.test(h1.alerts[0].message),
    "singular duplicate line for exactly one",
  );
}
ok('duplicates add the "Review duplicates" button routing to /contact-duplicates');

// ===========================================================================
// 9. onError surfaces the failure (never silent) with a message fallback.
// ===========================================================================
{
  const h = makeScreenHarness();
  h.onError(new Error("HTTP 500"));
  assert.deepEqual(h.alerts[0], { title: "Import failed", message: "HTTP 500", buttons: undefined });

  const h2 = makeScreenHarness();
  h2.onError({});
  assert.equal(h2.alerts[0].message, "Try again", "message-less errors get a fallback");
}
ok("a thrown import surfaces an 'Import failed' alert with the error message");

// ===========================================================================
// 10. Wiring guards — the screen actually uses these pieces.
// ===========================================================================
assert.ok(
  /importDeviceContacts\(\{\s*requestPermission: true,/.test(screenSrc),
  "the header button import PROMPTS for permission (user-initiated)",
);
assert.ok(
  /import \{[\s\S]*?getStoredContactSyncFingerprint,[\s\S]*?importDeviceContacts,[\s\S]*?setStoredContactSyncFingerprint,[\s\S]*?\} from "@\/lib\/deviceContacts"/.test(
    screenSrc,
  ),
  "the screen imports the shared importDeviceContacts + fingerprint helpers",
);
assert.ok(
  /Platform\.OS !== "web" && \(/.test(screenSrc),
  "the import button is hidden on web (native-only flow)",
);
assert.ok(
  /import \{ showAlert \} from "@\/lib\/webAlert"/.test(screenSrc),
  "alerts go through the web-safe showAlert shim (RN Alert is a no-op on web)",
);
ok("screen wiring: prompt-on-tap, native-only button, web-safe alert shim");

console.log(`\n[test-device-contacts-import] all ${passed} checks passed`);
