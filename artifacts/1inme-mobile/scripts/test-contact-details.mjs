// Regression test for the mobile Contact screen's "Contact details" card —
// the promise that it ALWAYS shows the real brand details, even offline.
//
// The screen (app/info/contact.tsx) seeds its state with the correct brand
// defaults (DEFAULT_CONTACT_CONTENT) and fetchContactContent()
// (lib/api/siteContent.ts) always resolves to a renderable ContactContent, so
// a failed / empty fetch shows real details (EEFind, Banjara Hills,
// hello@sayzio.app) — never a blank card and never a fake phone number.
//
// The sibling test-quick-contact.mjs only covers the channel picker / submit
// gating / 422 surfacing; it does NOT touch this fallback behaviour. This test
// pins:
//
//   1. fetchContactContent merges per-field with the defaults on a successful
//      fetch (a non-blank server value wins; a blank/missing one for a
//      must-always-resolve field falls back to the brand default).
//   2. A non-OK / empty / throwing request resolves to DEFAULT_CONTACT_CONTENT
//      wholesale (offline / server-down never blanks the card).
//   3. Phone stays blank — the default has no number and a missing/blank phone
//      never invents one (no fake number).
//   4. The screen always renders <ContactDetailsCard> (seeded from the default,
//      updated from the fetch) so there is no state in which the card is absent.
//
// Following the convention in test-quick-contact.mjs / test-stats-range.mjs we
// avoid a full TS/RN runner: we read the shipped source, strip its (simple) TS
// type annotations and evaluate the REAL DEFAULT_CONTACT_CONTENT literal and
// fetchContactContent body in isolation, injecting a mock getBaseUrl + fetch.
// This keeps the test honest — it exercises what ships, not a re-implementation.
//
// Run via `node scripts/test-contact-details.mjs` (package script
// `test:contact-details`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const apiSrc = readFileSync(
  join(root, "lib", "api", "siteContent.ts"),
  "utf8",
);
const screenSrc = readFileSync(
  join(root, "app", "info", "contact.tsx"),
  "utf8",
);

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// Pull the real DEFAULT_CONTACT_CONTENT literal + fetchContactContent body out
// of the source, strip the (simple) TS annotations, and rebuild a fresh module
// instance per call (fresh cache) with an injected getBaseUrl + fetch.
// ---------------------------------------------------------------------------
function extractDefaultLiteral(src) {
  const start = src.indexOf("export const DEFAULT_CONTACT_CONTENT");
  if (start === -1) throw new Error("could not find DEFAULT_CONTACT_CONTENT");
  const end = src.indexOf("\n};", start);
  if (end === -1) throw new Error("unterminated DEFAULT_CONTACT_CONTENT literal");
  return src.slice(start, end + 3);
}

function extractFetchFn(src) {
  const start = src.indexOf("export async function fetchContactContent");
  if (start === -1) throw new Error("could not find fetchContactContent");
  // Our functions are top-level, so the first newline followed by a
  // column-0 `}` is the closing brace (inner braces are all indented).
  const end = src.indexOf("\n}", start);
  if (end === -1) throw new Error("unterminated fetchContactContent body");
  return src.slice(start, end + 2);
}

const defaultLiteral = extractDefaultLiteral(apiSrc).replace(
  "export const DEFAULT_CONTACT_CONTENT: ContactContent =",
  "const DEFAULT_CONTACT_CONTENT =",
);

const fetchFn = extractFetchFn(apiSrc)
  .replace(
    "export async function fetchContactContent(): Promise<ContactContent> {",
    "async function fetchContactContent() {",
  )
  .replace(" as ContactResponse", "")
  .replace(
    "(value: unknown, fallback: string): string =>",
    "(value, fallback) =>",
  )
  .replace("const content: ContactContent = {", "const content = {");

const factoryBody = `
  let contactCache = null;
  let contactInflight = null;
  ${defaultLiteral}
  ${fetchFn}
  return { fetchContactContent, DEFAULT_CONTACT_CONTENT };
`;

// eslint-disable-next-line no-new-func
const makeModuleFactory = new Function("getBaseUrl", "fetch", factoryBody);

// Build a fresh module (fresh cache/inflight) with the given fetch impl. Every
// test gets its own instance so caching from one case can't leak into another.
const fetchCalls = [];
function makeModule(fetchImpl) {
  return makeModuleFactory(
    () => "http://test.local",
    async (url, opts) => {
      fetchCalls.push({ url, opts });
      return fetchImpl(url, opts);
    },
  );
}

function jsonResponse(data) {
  return { ok: true, json: async () => ({ data }) };
}

// ===========================================================================
// 0. The exported default is itself correct — real brand details, no phone.
// ===========================================================================
{
  const { DEFAULT_CONTACT_CONTENT: DEF } = makeModule(async () =>
    jsonResponse({}),
  );
  assert.equal(DEF.phone, "", "the brand default carries NO phone number");
  assert.ok(
    /EEFind/.test(DEF.address),
    "default address names EEFind Private Limited",
  );
  assert.ok(
    /Banjara Hills/.test(DEF.address),
    "default address names Banjara Hills",
  );
  assert.equal(
    DEF.email,
    "hello@sayzio.app",
    "default support email is hello@sayzio.app",
  );
  assert.ok(DEF.title.trim() !== "", "default has a card title");
  assert.ok(DEF.hours.trim() !== "", "default has business hours");
  // Absolutely no fake digits masquerading as a phone number anywhere.
  assert.equal(
    /^\s*$/.test(DEF.phone),
    true,
    "default phone is blank (never a fake number)",
  );
}
ok("DEFAULT_CONTACT_CONTENT is the real brand detail set with a blank phone");

// ===========================================================================
// 1. Success merges per-field: a non-blank server value overrides, a blank /
//    missing must-always-resolve field falls back to the brand default.
// ===========================================================================
{
  const { fetchContactContent, DEFAULT_CONTACT_CONTENT: DEF } = makeModule(
    async () =>
      jsonResponse({
        // title provided (override), address blank (fallback), email omitted
        // (fallback), hours provided (override).
        title: "Talk to us",
        address: "   ",
        hours: "Always open",
        social: { twitter: "https://x.com/custom", instagram: "" },
        map: { lat: 1.5, lng: 2.5, zoom: 9, label: "Custom HQ" },
      }),
  );

  const c = await fetchContactContent();

  assert.equal(c.title, "Talk to us", "non-blank server title overrides default");
  assert.equal(
    c.address,
    DEF.address,
    "a blank server address falls back to the brand default",
  );
  assert.equal(
    c.email,
    DEF.email,
    "an omitted server email falls back to the brand default",
  );
  assert.equal(c.hours, "Always open", "non-blank server hours overrides default");

  // Social links are authoritative on a successful fetch: provided wins, a
  // cleared/blank one stays blank (an admin who removes one sees it gone).
  assert.equal(c.social.twitter, "https://x.com/custom", "provided social wins");
  assert.equal(c.social.instagram, "", "a cleared social stays blank");
  assert.equal(c.social.linkedin, "", "an omitted social is blank on success");

  // Map merges numeric fields + label.
  assert.deepEqual(
    c.map,
    { lat: 1.5, lng: 2.5, zoom: 9, label: "Custom HQ" },
    "map fields are taken from the server payload",
  );

  // The request actually targeted the public contact endpoint.
  assert.ok(
    fetchCalls.some((f) => /\/api\/v1\/site\/contact$/.test(f.url)),
    "fetchContactContent hits /api/v1/site/contact",
  );
}
ok("success merges per-field: server values override, blanks fall back to defaults");

// ===========================================================================
// 2. Non-OK / empty / throwing requests resolve to the whole brand default —
//    offline / server-down never blanks the card.
// ===========================================================================
{
  // Non-OK (e.g. 500 / 404).
  {
    const { fetchContactContent, DEFAULT_CONTACT_CONTENT: DEF } = makeModule(
      async () => ({ ok: false, json: async () => ({}) }),
    );
    assert.deepEqual(
      await fetchContactContent(),
      DEF,
      "a non-OK response resolves to the whole default",
    );
  }
  // OK but empty body (no `data`).
  {
    const { fetchContactContent, DEFAULT_CONTACT_CONTENT: DEF } = makeModule(
      async () => ({ ok: true, json: async () => ({}) }),
    );
    assert.deepEqual(
      await fetchContactContent(),
      DEF,
      "an empty body (no data) resolves to the whole default",
    );
  }
  // fetch throws (offline).
  {
    const { fetchContactContent, DEFAULT_CONTACT_CONTENT: DEF } = makeModule(
      async () => {
        throw new Error("Network request failed");
      },
    );
    assert.deepEqual(
      await fetchContactContent(),
      DEF,
      "a thrown fetch (offline) resolves to the whole default",
    );
  }
}
ok("non-OK / empty / throwing fetch all resolve to DEFAULT_CONTACT_CONTENT (never blank)");

// ===========================================================================
// 3. Phone stays blank — no fake number is ever invented.
// ===========================================================================
{
  // Success WITHOUT a phone key → phone keeps the (blank) default.
  {
    const { fetchContactContent } = makeModule(async () =>
      jsonResponse({ title: "Hi" }),
    );
    const c = await fetchContactContent();
    assert.equal(c.phone, "", "a missing phone key stays blank (no fake number)");
  }
  // Success with a WHITESPACE phone → trimmed to blank.
  {
    const { fetchContactContent } = makeModule(async () =>
      jsonResponse({ phone: "   " }),
    );
    const c = await fetchContactContent();
    assert.equal(c.phone, "", "a whitespace-only phone is trimmed to blank");
  }
  // Success with a REAL phone → honored (admins can add one later).
  {
    const { fetchContactContent } = makeModule(async () =>
      jsonResponse({ phone: " +91 98765 43210 " }),
    );
    const c = await fetchContactContent();
    assert.equal(
      c.phone,
      "+91 98765 43210",
      "a real server phone is honored (trimmed)",
    );
  }
  // Offline → phone still blank (the default), not fabricated.
  {
    const { fetchContactContent } = makeModule(async () => {
      throw new Error("offline");
    });
    const c = await fetchContactContent();
    assert.equal(c.phone, "", "an offline fetch never invents a phone number");
  }
}
ok("phone is blank unless the server provides a real one (never fabricated)");

// ===========================================================================
// 4. Only successful fetches are cached — a transient failure can be retried
//    on the next mount and then pick up real content.
// ===========================================================================
{
  let calls = 0;
  const { fetchContactContent, DEFAULT_CONTACT_CONTENT: DEF } = makeModule(
    async () => {
      calls += 1;
      if (calls === 1) throw new Error("transient");
      return jsonResponse({ title: "Recovered" });
    },
  );
  const first = await fetchContactContent();
  assert.deepEqual(first, DEF, "the transient failure falls back to the default");
  const second = await fetchContactContent();
  assert.equal(
    second.title,
    "Recovered",
    "a retry after a transient failure picks up real content (failure not cached)",
  );
}
ok("a failed fetch is not cached, so the next mount can retry and recover");

// ===========================================================================
// 5. Screen wiring — the card is ALWAYS rendered, seeded from the default and
//    updated from the fetch, so there is no blank-card state.
// ===========================================================================
assert.ok(
  /import \{[\s\S]*?DEFAULT_CONTACT_CONTENT[\s\S]*?fetchContactContent[\s\S]*?\} from "@\/lib\/api\/siteContent"/.test(
    screenSrc,
  ),
  "the screen imports DEFAULT_CONTACT_CONTENT + fetchContactContent from siteContent",
);
assert.ok(
  /useState<ContactContent>\(\s*DEFAULT_CONTACT_CONTENT,?\s*\)/.test(screenSrc),
  "details state is SEEDED with DEFAULT_CONTACT_CONTENT (real first paint, never blank)",
);
assert.ok(
  /fetchContactContent\(\)\s*\.then\(\(c\)\s*=>\s*\{\s*if \(alive\) setDetails\(c\)/.test(
    screenSrc,
  ),
  "the screen updates details from fetchContactContent on mount",
);
assert.ok(
  /<ContactDetailsCard details=\{details\} colors=\{colors\} \/>/.test(
    screenSrc,
  ),
  "the screen renders <ContactDetailsCard> with the resolved details",
);
assert.ok(
  /function ContactDetailsCard\(/.test(screenSrc),
  "ContactDetailsCard is defined in the screen",
);
// The card guards a blank phone: no empty Phone row when phone === "".
assert.ok(
  /\{phone !== "" \?/.test(screenSrc),
  "the card renders the Phone row only when phone is non-blank (no empty row)",
);
ok("screen seeds the card with the default and always renders ContactDetailsCard");

console.log(`\n[test-contact-details] all ${passed} checks passed`);
