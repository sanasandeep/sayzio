// Regression tests for the mobile event detail screen's host card + map
// (Task #3736, guarded by Task #3749).
//
// The mobile event screen (app/events/[alias].tsx) renders:
//   - a Leaflet MapPreview thumbnail whenever the event has latitude/longitude,
//   - a rich organizer/host card driven by the extended organizer payload
//     (EventOrganizer in lib/api/events.ts): logo/avatar, description, website,
//     contact email/phone, address and socials, shown only when `filled`.
//
// That payload comes from the Laravel API's eventShape()/organizerShape()
// (artifacts/1inme/app/Modules/Api/Controllers/EventTicketApiController.php),
// which mirrors the web event-host-card.blade.php partial. If any of these
// three surfaces drifts, mobile silently drops the host details or the map.
//
// Following the convention in test-map-location-block.mjs we avoid a full TS
// runner and instead assert the real source wires the fields, so the API
// shape, the TS type and the screen can't drift apart. Run via
// `node scripts/test-event-host-map.mjs` (package script `test:event-host-map`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const screenSrc = readFileSync(
  join(root, "app", "events", "[alias].tsx"),
  "utf8",
);
const typesSrc = readFileSync(join(root, "lib", "api", "events.ts"), "utf8");
const apiSrc = readFileSync(
  join(
    root,
    "..",
    "1inme",
    "app",
    "Modules",
    "Api",
    "Controllers",
    "EventTicketApiController.php",
  ),
  "utf8",
);
const bladeSrc = readFileSync(
  join(
    root,
    "..",
    "1inme",
    "resources",
    "views",
    "common",
    "partials",
    "event-host-card.blade.php",
  ),
  "utf8",
);

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// The organizer fields that must stay in lockstep across all three surfaces.
const ORGANIZER_FIELDS = [
  "description",
  "website",
  "contact_name",
  "contact_phone",
  "contact_email",
  "address",
];

// ===========================================================================
// 1. TS type contract (lib/api/events.ts)
// ===========================================================================
console.log("[test-event-host-map] EventOrganizer type");

assert.ok(
  /export type EventOrganizer = \{/.test(typesSrc),
  "EventOrganizer type is declared",
);
assert.ok(
  /\bfilled:\s*boolean;/.test(typesSrc),
  "EventOrganizer exposes the `filled` flag mobile branches on",
);
assert.ok(
  /\blogo:\s*string \| null;/.test(typesSrc),
  "EventOrganizer exposes logo",
);
for (const f of ORGANIZER_FIELDS) {
  assert.ok(
    new RegExp(`\\b${f}:\\s*string \\| null;`).test(typesSrc),
    `EventOrganizer exposes ${f}`,
  );
}
assert.ok(
  /socials:\s*Record<string, string>;/.test(typesSrc),
  "EventOrganizer exposes a socials map",
);
ok("EventOrganizer type carries filled + logo + contact/address/socials fields");

// ===========================================================================
// 2. Laravel API shape (EventTicketApiController)
// ===========================================================================
console.log("[test-event-host-map] API organizerShape");

assert.ok(
  /protected function organizerShape\(/.test(apiSrc),
  "controller has an organizerShape() helper",
);
assert.ok(
  /'organizer' => \$host \? \$this->organizerShape\(\$host\) : null,/.test(
    apiSrc,
  ),
  "eventShape() delegates the organizer payload to organizerShape()",
);
assert.ok(
  /\$profile = \$host->organizerProfile\(\);/.test(apiSrc),
  "organizerShape reads through User::organizerProfile() (never the raw column)",
);
assert.ok(
  /'filled'\s*=> \(bool\) \$profile\['filled'\],/.test(apiSrc),
  "organizerShape emits the `filled` flag from the profile (single source of truth)",
);
// Field-by-field fallback of the display identity to the host account.
assert.ok(
  /'name'\s*=> \$profile\['name'\] !== '' \? \$profile\['name'\] : \$host->name,/.test(
    apiSrc,
  ),
  "name falls back to the host account name when the profile name is blank",
);
// The avatar may be wrapped in PublicStorageUrl::resolve(...) (CDN-aware URL
// resolution); the fallback expression itself is what matters here.
assert.ok(
  /'avatar'\s*=> (?:\\App\\Support\\PublicStorageUrl::resolve\()?\$profile\['logo'\] !== '' \? \$profile\['logo'\] : \$host->avatar\)?,/.test(
    apiSrc,
  ),
  "avatar falls back to the host account avatar when no organizer logo is set",
);
for (const f of ORGANIZER_FIELDS) {
  assert.ok(
    new RegExp(`'${f}'\\s*=> \\$orNull\\(\\$profile\\['${f}'\\]\\),`).test(
      apiSrc,
    ),
    `organizerShape maps ${f} (null when unset, not a stale account value)`,
  );
}
assert.ok(
  /'socials'\s*=> \(object\) \(\$profile\['socials'\] \?\? \[\]\),/.test(apiSrc),
  "organizerShape emits socials as an object (never a JSON array on the wire)",
);
ok("organizerShape mirrors the profile with field-by-field host-account fallback");

// ===========================================================================
// 3. Mobile screen wiring (app/events/[alias].tsx)
// ===========================================================================
console.log("[test-event-host-map] screen wiring");

assert.ok(
  /import \{ MapPreview \} from "@\/components\/MapPreview";/.test(screenSrc),
  "screen imports the MapPreview component",
);
assert.ok(
  /event\.latitude != null && event\.longitude != null \?/.test(screenSrc),
  "map thumbnail is gated on the event having coordinates",
);
assert.ok(
  /<MapPreview lat=\{event\.latitude\} lng=\{event\.longitude\}/.test(screenSrc),
  "map thumbnail is fed the event coordinates",
);
assert.ok(
  /event\.organizer\.filled \?/.test(screenSrc),
  "rich host rows are gated on organizer.filled (not re-derived emptiness)",
);
assert.ok(
  /event\.organizer\.description/.test(screenSrc),
  "screen renders the organizer description",
);
assert.ok(
  /event\.organizer\.website/.test(screenSrc) &&
    /event\.organizer!\.website!/.test(screenSrc),
  "screen renders + links the organizer website",
);
assert.ok(
  /mailto:\$\{event\.organizer!\.contact_email!\}/.test(screenSrc),
  "screen links the contact email via mailto:",
);
assert.ok(
  /tel:\$\{event\.organizer!\.contact_phone!\}/.test(screenSrc),
  "screen links the contact phone via tel:",
);
assert.ok(
  /event\.organizer\.address/.test(screenSrc),
  "screen renders the organizer address",
);
assert.ok(
  /Object\.entries\(event\.organizer\.socials\)/.test(screenSrc),
  "screen iterates the organizer socials map",
);
ok("screen renders the map + all host detail rows behind the filled flag");

// ===========================================================================
// 4. Web partial contract guard (lockstep source of the shape)
//
// organizerShape mirrors the web event-host-card partial; assert the partial
// still branches on the same `filled` flag and reads the same fields, so a
// change on either side that breaks the contract fails here.
// ===========================================================================
console.log("[test-event-host-map] web partial contract");

assert.ok(
  /\$organizer\['filled'\]/.test(bladeSrc),
  "web partial still branches on organizerProfile()['filled']",
);
for (const f of ["description", "website", "contact_email", "contact_phone", "address"]) {
  assert.ok(
    new RegExp(`\\$organizer\\['${f}'\\]`).test(bladeSrc),
    `web partial still reads ${f}`,
  );
}
assert.ok(
  /\$organizer\['socials'\]/.test(bladeSrc),
  "web partial still reads socials",
);
ok("web host-card partial reads the same filled + contact/address/socials fields");

console.log(`\n[test-event-host-map] all ${passed} checks passed`);
