// Smoke test for contactActivityHref — the group→route mapping the
// contact detail screen (app/contacts/[id].tsx) uses to make activity
// items tappable. Covers EVERY group key ContactActivityService.php
// emits, including missing-ref fallbacks to null, and additionally
// asserts each produced href resolves to a real Expo Router screen
// file so a renamed route fails loudly here instead of 404ing on
// device.
//
// Run via `node scripts/test-contact-activity-href.mjs` (wired into
// `test:contact-activity-href` and the test:unit chain). Like
// test-citation-href.mjs, we lift the pure helper out of the shipped
// source instead of pulling in a full TS test runner.

import assert from "node:assert/strict";
import { readFileSync, existsSync, readdirSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");
const src = readFileSync(join(root, "lib", "api", "contacts.ts"), "utf8");

// ---- lift the real function body out of the shipped module ----------------

const m = src.match(/export function contactActivityHref\([\s\S]*?\n\}/m);
assert.ok(m, "could not find contactActivityHref in lib/api/contacts.ts");

const js = m[0]
  .replace(/\(\s*groupKey:\s*string,\s*item:\s*ContactActivityItem,?\s*\)/, "(groupKey, item)")
  .replace(/\):\s*string\s*\|\s*null\s*\{/, ") {")
  .replace(/^export function/, "function");

const contactActivityHref = new Function(
  `${js}\nreturn contactActivityHref;`,
)();

// ---- expected mapping: one case per PHP group key --------------------------
// Keys come from ContactActivityService.php ($push calls). If a new group is
// added there, add it here AND to the switch in contacts.ts.

const CASES = [
  // [groupKey, refs, expected href]
  ["subscriptions", {}, "/subscribers"],
  ["form_submissions", { form_id: 7 }, "/forms/7"],
  ["form_submissions", {}, null],
  ["restaurant_orders", { link_id: 12 }, "/links/12/restaurant-orders"],
  ["restaurant_orders", {}, null],
  ["store_orders", { link_id: 12 }, "/links/12/store-orders"],
  ["store_orders", {}, null],
  ["bookings", { link_id: 5 }, "/links/5/service-booking-dashboard"],
  ["bookings", {}, null],
  ["rsvps", { alias: "my-event" }, "/events/people/my-event"],
  ["rsvps", {}, null],
  ["event_tickets", { alias: "a b" }, "/events/people/a%20b"],
  ["event_tickets", {}, null],
  ["product_orders", {}, "/orders"],
  ["reviews", {}, "/reviews/manage"],
  ["conversations", { thread_id: 33 }, "/inbox/33"],
  ["conversations", {}, null],
  ["invoices", { invoice_id: 44 }, "/invoices/44"],
  ["invoices", {}, null],
  // unknown group → static item
  ["something_new", { link_id: 1 }, null],
];

const item = (refs) => ({ title: "t", subtitle: null, date: null, url: null, refs });

for (const [key, refs, expected] of CASES) {
  const got = contactActivityHref(key, item(refs));
  assert.equal(
    got,
    expected,
    `contactActivityHref(${key}, refs=${JSON.stringify(refs)}) → ${got}, expected ${expected}`,
  );
}

// undefined refs entirely (API omitted the field) must not throw
assert.equal(
  contactActivityHref("form_submissions", { title: "t", subtitle: null, date: null, url: null }),
  null,
  "missing refs object should fall back to null",
);

// ---- every group key ContactActivityService emits is handled --------------

const phpPath = join(
  root,
  "..",
  "1inme",
  "app",
  "Modules",
  "User",
  "Services",
  "Contacts",
  "ContactActivityService.php",
);
if (existsSync(phpPath)) {
  const php = readFileSync(phpPath, "utf8");
  const phpKeys = [...php.matchAll(/\$push\(\s*'([a-z_]+)'/g)].map((x) => x[1]);
  assert.ok(phpKeys.length >= 10, `expected ≥10 PHP group keys, got ${phpKeys.length}`);
  const covered = new Set(CASES.map(([k]) => k));
  for (const k of phpKeys) {
    assert.ok(
      covered.has(k),
      `ContactActivityService.php emits group '${k}' with no case in this test — add it here and to contactActivityHref`,
    );
  }
} else {
  console.warn("WARN: ContactActivityService.php not found; skipping PHP key parity check");
}

// ---- each non-null href resolves to a real Expo Router screen --------------

function routeFileExists(href) {
  const segs = href.replace(/^\//, "").split("/").map((s) => decodeURIComponent(s));
  // walk app/ matching literal segments or [param] dirs/files
  let candidates = [join(root, "app")];
  for (let i = 0; i < segs.length; i++) {
    const last = i === segs.length - 1;
    const next = [];
    for (const dir of candidates) {
      for (const name of [segs[i], null]) {
        if (name !== null) {
          if (last && existsSync(join(dir, `${name}.tsx`))) return true;
          if (last && existsSync(join(dir, name, "index.tsx"))) return true;
          if (existsSync(join(dir, name))) next.push(join(dir, name));
        } else {
          // dynamic segment: any [param].tsx / [param] dir
          for (const entry of readdirSync(dir)) {
            if (!entry.startsWith("[")) continue;
            if (last && entry.endsWith(".tsx")) return true;
            if (!entry.endsWith(".tsx") && existsSync(join(dir, entry))) {
              if (last && existsSync(join(dir, entry, "index.tsx"))) return true;
              next.push(join(dir, entry));
            }
          }
        }
      }
    }
    candidates = next;
    if (candidates.length === 0) return false;
  }
  return false;
}

const hrefs = [...new Set(CASES.map(([, , h]) => h).filter(Boolean))];
for (const href of hrefs) {
  assert.ok(
    routeFileExists(href),
    `no Expo Router screen found under app/ for href ${href} — route renamed or removed?`,
  );
}

console.log(
  `PASS test-contact-activity-href: ${CASES.length + 1} mapping cases, ${hrefs.length} routes resolved`,
);
