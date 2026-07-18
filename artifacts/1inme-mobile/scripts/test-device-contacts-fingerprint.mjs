// Source-driven test for the deviceContacts fingerprint helpers — the promise
// that an unchanged address book NEVER re-uploads on a cold app start.
//
// test-contact-auto-sync.mjs covers the useContactAutoSync hook with
// lib/deviceContacts.ts fully mocked, so a regression INSIDE the lib (the
// FNV-1a fingerprint over the exact POST payload, the "unchanged"
// short-circuit, the requestPermission:false gate, or the per-user
// AsyncStorage get/set helpers) would be invisible to it — and to users,
// since the only symptom is a silent full re-upload on every restart.
// This test lifts the REAL shipped code out of lib/deviceContacts.ts and
// asserts those exact guarantees:
//
//   1. Fingerprint stability: two imports of an IDENTICAL address book
//      (fresh module instances, like two cold starts) produce the SAME
//      fingerprint string.
//   2. Fingerprint sensitivity: any edit (name, organization, email value or
//      label, phone number, added/removed contact, reordering) changes it.
//   3. Unchanged short-circuit: passing the previous fingerprint back as
//      unchangedFingerprint returns { ok:false, reason:"unchanged",
//      fingerprint } WITHOUT calling bulkImportContacts; a stale fingerprint
//      still POSTs and returns the new fingerprint.
//   4. Permission gate: requestPermission:false (or unset) NEVER calls
//      requestPermissionsAsync — silent background re-syncs must not prompt.
//   5. AsyncStorage helpers: get/set are keyed per user
//      ("contact_sync_fingerprint:<id>"), isolated across users, and
//      best-effort (get → null on storage errors, set swallows them).
//
// The same file ships in artifacts/1inme-mobile and sayzio-dialer-standalone
// (the copies' fingerprint FORMATS legitimately differ, so nothing here
// asserts the exact string shape — only stability, sensitivity and behavior).
// Runs with node only — expo-contacts, AsyncStorage and the API client are
// mocked. Run via `node scripts/test-device-contacts-fingerprint.mjs`
// (package script `test:device-contacts-fingerprint`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const libSrc = readFileSync(
  join(__dirname, "..", "lib", "deviceContacts.ts"),
  "utf8",
);

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// Lift the REAL importDeviceContacts (same extraction as
// test-device-contacts-import.mjs) plus the storage helpers.
// ---------------------------------------------------------------------------
function extractImportFn(src) {
  const start = src.indexOf("export async function importDeviceContacts");
  if (start === -1) throw new Error("could not find importDeviceContacts");
  const sigEnd = src.indexOf("Promise<DeviceImportOutcome>", start);
  if (sigEnd === -1)
    throw new Error("could not find importDeviceContacts return type");
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
  !/[:?]\s*(Promise|ContactImportPayload|DeviceImportOutcome)/.test(
    importFnSrc,
  ),
  "TS annotations were fully stripped from the lifted importDeviceContacts",
);

// The fingerprint must be FNV-1a over the EXACT payload we'd POST — guard the
// constants so a "cheaper" hash swap (more collisions → missed changes) or a
// hash over something other than the payload JSON can't slip in silently.
assert.ok(
  /0x811c9dc5/.test(importFnSrc) && /0x01000193/.test(importFnSrc),
  "fingerprint must stay FNV-1a (offset basis 0x811c9dc5, prime 0x01000193)",
);
assert.ok(
  /JSON\.stringify\(payload\)/.test(importFnSrc),
  "fingerprint must hash the exact JSON payload that would be POSTed",
);
ok("lifted importDeviceContacts still hashes the POST payload with FNV-1a");

// eslint-disable-next-line no-new-func
const makeImporter = new Function(
  "__importExpoContacts",
  "bulkImportContacts",
  `${importFnSrc}\nreturn importDeviceContacts;`,
);

function extractStorageHelpers(src) {
  const constLine = src.match(
    /const CONTACT_SYNC_FINGERPRINT_KEY_PREFIX = "[^"]+";/,
  );
  assert.ok(constLine, "could not find the fingerprint key prefix constant");

  function lift(name, returnType, replacement) {
    const start = src.indexOf(`export async function ${name}`);
    assert.ok(start !== -1, `could not find ${name}`);
    const end = src.indexOf("\n}", start);
    assert.ok(end !== -1, `unterminated ${name} body`);
    return src
      .slice(start, end + 2)
      .replace(
        new RegExp(
          `export async function ${name}\\([\\s\\S]*?\\): ${returnType} \\{`,
        ),
        replacement,
      );
  }

  const getSrc = lift(
    "getStoredContactSyncFingerprint",
    "Promise<string \\| null>",
    "async function getStoredContactSyncFingerprint(userId) {",
  );
  const setSrc = lift(
    "setStoredContactSyncFingerprint",
    "Promise<void>",
    "async function setStoredContactSyncFingerprint(userId, fingerprint) {",
  );
  const combined = `${constLine[0]}\n${getSrc}\n${setSrc}`;
  assert.ok(
    !/[:)]\s*(Promise|number \| string)/.test(combined),
    "TS annotations were fully stripped from the lifted storage helpers",
  );
  return combined;
}

// eslint-disable-next-line no-new-func
const makeStorageHelpers = new Function(
  "AsyncStorage",
  `${extractStorageHelpers(libSrc)}\nreturn { getStoredContactSyncFingerprint, setStoredContactSyncFingerprint };`,
);

// Minimal expo-contacts mock (mirrors test-device-contacts-import.mjs).
function makeContactsModule({
  permission = "granted",
  promptResult = null,
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

// One import run against a fresh module instance (a cold start): returns the
// outcome plus the bulk-POST payloads that were sent.
async function runImport(contacts, opts, { permission = "granted", log = [] } = {}) {
  const apiCalls = [];
  const importDeviceContacts = makeImporter(
    () => Promise.resolve(makeContactsModule({ permission, contacts, log })),
    async (p) => (
      apiCalls.push(p),
      { created: p.length, updated: 0, skipped: 0, duplicates_found: 0 }
    ),
  );
  const out = await importDeviceContacts(opts);
  return { out, apiCalls };
}

const BOOK = [
  {
    name: "Ada Lovelace",
    firstName: "Ada",
    lastName: "Lovelace",
    company: "Analytical Engines",
    emails: [{ email: "ada@example.com", label: "work" }],
    phoneNumbers: [{ number: "+44 1", label: "mobile" }],
  },
  {
    name: "Grace Hopper",
    emails: [{ email: "grace@example.com" }],
    phoneNumbers: [{ number: "+1 555-0100", label: null }],
  },
];
const clone = (v) => JSON.parse(JSON.stringify(v));

// ===========================================================================
// 1. Stability: identical address books across separate cold starts hash to
//    the SAME fingerprint (fresh module + importer each time).
// ===========================================================================
{
  const a = await runImport(clone(BOOK));
  const b = await runImport(clone(BOOK));
  assert.equal(a.out.ok, true);
  assert.equal(b.out.ok, true);
  assert.ok(
    typeof a.out.fingerprint === "string" && a.out.fingerprint.length > 0,
    "successful imports must return a non-empty fingerprint",
  );
  assert.equal(
    a.out.fingerprint,
    b.out.fingerprint,
    "identical payloads MUST produce identical fingerprints — otherwise every cold start re-uploads the whole address book",
  );
}
ok("identical address books produce a stable fingerprint across runs");

// ===========================================================================
// 2. Sensitivity: any edit to the book changes the fingerprint.
// ===========================================================================
{
  const { out: base } = await runImport(clone(BOOK));
  const edits = {
    "renamed contact": (b) => (b[0].name = "Ada L."),
    "changed organization": (b) => (b[0].company = "Babbage & Co"),
    "changed email value": (b) => (b[0].emails[0].email = "ada@new.example"),
    "changed email label": (b) => (b[0].emails[0].label = "home"),
    "changed phone number": (b) => (b[1].phoneNumbers[0].number = "+1 555-0199"),
    "added contact": (b) => b.push({ name: "New Person" }),
    "removed contact": (b) => b.pop(),
    "reordered contacts": (b) => b.reverse(),
  };
  for (const [label, edit] of Object.entries(edits)) {
    const book = clone(BOOK);
    edit(book);
    const { out } = await runImport(book);
    assert.equal(out.ok, true, `${label}: edited book still imports`);
    assert.notEqual(
      out.fingerprint,
      base.fingerprint,
      `${label}: an edited address book must change the fingerprint`,
    );
  }
}
ok("every edit (names, org, emails, labels, phones, add/remove/reorder) changes the fingerprint");

// ===========================================================================
// 3. Unchanged short-circuit: the previous fingerprint skips the bulk POST;
//    a stale one still POSTs and rolls the fingerprint forward.
// ===========================================================================
{
  const first = await runImport(clone(BOOK));
  assert.equal(first.apiCalls.length, 1, "first sync POSTs");

  const second = await runImport(clone(BOOK), {
    requestPermission: false,
    unchangedFingerprint: first.out.fingerprint,
  });
  assert.deepEqual(
    second.out,
    { ok: false, reason: "unchanged", fingerprint: first.out.fingerprint },
    'an unchanged book must return { ok:false, reason:"unchanged" } carrying the fingerprint',
  );
  assert.equal(
    second.apiCalls.length,
    0,
    "the unchanged short-circuit must NOT call bulkImportContacts",
  );

  const edited = clone(BOOK);
  edited[0].name = "Ada B.";
  const third = await runImport(edited, {
    requestPermission: false,
    unchangedFingerprint: first.out.fingerprint,
  });
  assert.equal(third.out.ok, true, "a changed book must still import");
  assert.equal(third.apiCalls.length, 1, "changed book POSTs again");
  assert.notEqual(
    third.out.fingerprint,
    first.out.fingerprint,
    "the new fingerprint rolls forward with the changed payload",
  );
}
ok("unchanged fingerprint skips the POST entirely; a stale one still imports");

// ===========================================================================
// 4. Permission gate: requestPermission:false / unset never prompts.
// ===========================================================================
{
  // Denied + silent: no prompt, "denied", no contact read, no POST.
  for (const opts of [
    undefined,
    { requestPermission: false },
    { requestPermission: false, unchangedFingerprint: "anything" },
  ]) {
    const log = [];
    const { out, apiCalls } = await runImport(clone(BOOK), opts, {
      permission: "denied",
      log,
    });
    assert.deepEqual(out, { ok: false, reason: "denied" });
    assert.ok(
      !log.includes("requestPermissions"),
      "requestPermission:false (or unset) must NEVER call requestPermissionsAsync",
    );
    assert.equal(apiCalls.length, 0, "denied silent sync never POSTs");
  }

  // Already granted + silent: proceeds without ever prompting.
  const log = [];
  const { out } = await runImport(clone(BOOK), { requestPermission: false }, { log });
  assert.equal(out.ok, true, "granted permission lets the silent sync proceed");
  assert.ok(
    !log.includes("requestPermissions"),
    "already-granted silent sync must not prompt either",
  );
}
ok("silent syncs (requestPermission:false/unset) never call requestPermissionsAsync");

// ===========================================================================
// 5. AsyncStorage helpers: per-user keys, isolation, best-effort on errors.
// ===========================================================================
{
  const store = new Map();
  const helpers = makeStorageHelpers({
    getItem: async (k) => (store.has(k) ? store.get(k) : null),
    setItem: async (k, v) => {
      store.set(k, v);
    },
  });

  assert.equal(
    await helpers.getStoredContactSyncFingerprint(1),
    null,
    "no stored fingerprint reads as null",
  );
  await helpers.setStoredContactSyncFingerprint(1, "fp-user1");
  await helpers.setStoredContactSyncFingerprint("2", "fp-user2");
  assert.deepEqual(
    [...store.keys()].sort(),
    ["contact_sync_fingerprint:1", "contact_sync_fingerprint:2"],
    "fingerprints are stored under per-user contact_sync_fingerprint:<id> keys",
  );
  assert.equal(await helpers.getStoredContactSyncFingerprint(1), "fp-user1");
  assert.equal(
    await helpers.getStoredContactSyncFingerprint("2"),
    "fp-user2",
    "each user reads back only their own fingerprint",
  );
  assert.equal(
    await helpers.getStoredContactSyncFingerprint(3),
    null,
    "a third user never inherits another user's fingerprint",
  );
}
{
  // Broken storage: get degrades to null (forcing one re-upload, never a
  // crash) and set swallows the failure.
  const broken = makeStorageHelpers({
    getItem: async () => {
      throw new Error("storage unavailable");
    },
    setItem: async () => {
      throw new Error("storage unavailable");
    },
  });
  assert.equal(
    await broken.getStoredContactSyncFingerprint(1),
    null,
    "storage read errors degrade to null (worst case: one extra upload)",
  );
  await broken.setStoredContactSyncFingerprint(1, "fp-x"); // must not throw
}
ok("storage helpers are per-user keyed and best-effort on storage failures");

console.log(`\n[test-device-contacts-fingerprint] all ${passed} checks passed`);
