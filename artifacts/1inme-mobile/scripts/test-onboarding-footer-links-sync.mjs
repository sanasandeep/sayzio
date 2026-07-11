// Source-driven drift guard tying the onboarding footer's internal /info links
// to the /info e2e fixture.
//
// The mobile onboarding screen (app/onboarding.tsx) renders a footer built from
// the INFO_LINKS array. Some entries are `kind: "internal"` /info routes
// (About / Help / Privacy / Terms) and one is `kind: "external"` (the Website
// link). Separately, the runtime e2e harness (scripts/test-info-pages-e2e.mjs)
// drives every /info route in a real browser via its PAGES fixture.
//
// These two lists drifted before: the NFC link lived in BOTH the INFO_LINKS
// array and the e2e fixture long after the /info/nfc screen should have been
// removed, and nobody noticed. This guard reads BOTH real sources at runtime
// (no hard-coded third copy) and fails if the set of INTERNAL /info routes in
// INFO_LINKS doesn't exactly match the set of routes exercised by the e2e
// fixture — in either direction. So a footer link added/removed without
// updating the e2e fixture (or vice versa) fails CI before merge.
//
// External INFO_LINKS entries (kind: "external") are intentionally ignored —
// the e2e harness only navigates in-app /info routes, so a Website link has no
// fixture counterpart.
//
// Following the convention in test-onboarding-slides-sync.mjs we avoid a full
// TS/RN runner: we read the shipped source and parse the real array literals,
// so this exercises exactly what ships.
//
// Run via `node scripts/test-onboarding-footer-links-sync.mjs` (package script
// `test:onboarding-footer-links-sync`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const ONBOARDING_TSX = join(root, "app", "onboarding.tsx");
const E2E_FIXTURE = join(root, "scripts", "test-info-pages-e2e.mjs");

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// Bracket-matched extraction: pull the [...] block that follows a declaration
// marker, respecting nesting and skipping brackets inside string literals.
// ---------------------------------------------------------------------------
function extractArrayBlock(src, marker) {
  const markerIndex = src.indexOf(marker);
  assert.ok(markerIndex !== -1, `Could not find "${marker}" in source.`);
  // Anchor on the assignment so a `[` inside a type annotation (e.g. the
  // `InfoLink[]` in `const INFO_LINKS: InfoLink[] = [`) isn't mistaken for the
  // array opener.
  const eq = src.indexOf("=", markerIndex);
  assert.ok(eq !== -1, `Could not find '=' after "${marker}".`);
  const open = src.indexOf("[", eq);
  assert.ok(open !== -1, `Could not find '[' after "${marker}".`);
  let depth = 0;
  let quote = null;
  for (let i = open; i < src.length; i++) {
    const ch = src[i];
    if (quote) {
      if (ch === "\\") {
        i++; // skip escaped char
      } else if (ch === quote) {
        quote = null;
      }
      continue;
    }
    if (ch === '"' || ch === "'" || ch === "`") {
      quote = ch;
    } else if (ch === "[") {
      depth += 1;
    } else if (ch === "]") {
      depth -= 1;
      if (depth === 0) return src.slice(open, i + 1);
    }
  }
  throw new Error(`Could not find matching ']' for "${marker}".`);
}

// ---------------------------------------------------------------------------
// 1. INFO_LINKS internal hrefs (app/onboarding.tsx).
//
// Parse each `{ kind: "...", href/url: "...", label: "..." }` object literal
// out of the INFO_LINKS array. Only `kind: "internal"` entries carry an /info
// route (`href`); `kind: "external"` entries carry a `url` and are ignored.
// Routes are normalised to drop any leading "/" so they can be compared against
// the fixture's slash-less route strings.
// ---------------------------------------------------------------------------
const onboardingSrc = readFileSync(ONBOARDING_TSX, "utf8");
const infoLinksBlock = extractArrayBlock(onboardingSrc, "const INFO_LINKS");

function parseInfoLinks(block) {
  const links = [];
  const objRe = /\{[^{}]*\}/g;
  let m;
  while ((m = objRe.exec(block))) {
    const chunk = m[0];
    const kind = chunk.match(/kind:\s*"(\w+)"/);
    assert.ok(kind, `an INFO_LINKS entry is missing a kind:\n${chunk}`);
    if (kind[1] === "internal") {
      const href = chunk.match(/href:\s*"([^"]+)"/);
      assert.ok(
        href,
        `an internal INFO_LINKS entry is missing an href:\n${chunk}`,
      );
      links.push(href[1]);
    }
  }
  return links;
}

const internalHrefs = parseInfoLinks(infoLinksBlock);
assert.ok(
  internalHrefs.length > 0,
  "INFO_LINKS must contain at least one internal /info link",
);

const normalize = (r) => r.replace(/^\/+/, "");
const footerRoutes = internalHrefs.map(normalize);

// Guard against accidental duplicates in the footer list itself.
assert.equal(
  new Set(footerRoutes).size,
  footerRoutes.length,
  `INFO_LINKS has duplicate internal routes: ${footerRoutes.join(", ")}`,
);

// Every internal href must be an /info/* route (the fixture only covers /info).
for (const href of internalHrefs) {
  assert.match(
    href,
    /^\/info\//,
    `internal INFO_LINKS href "${href}" must be an /info/* route`,
  );
}
ok(`parsed ${footerRoutes.length} internal /info footer routes from INFO_LINKS`);

// ---------------------------------------------------------------------------
// 2. E2E fixture routes (scripts/test-info-pages-e2e.mjs).
//
// Parse each `route: "info/..."` out of the PAGES fixture array.
// ---------------------------------------------------------------------------
const e2eSrc = readFileSync(E2E_FIXTURE, "utf8");
const pagesBlock = extractArrayBlock(e2eSrc, "const PAGES");

const fixtureRoutes = [];
{
  const routeRe = /route:\s*"([^"]+)"/g;
  let m;
  while ((m = routeRe.exec(pagesBlock))) {
    fixtureRoutes.push(normalize(m[1]));
  }
}
assert.ok(
  fixtureRoutes.length > 0,
  "the PAGES e2e fixture must contain at least one route",
);
assert.equal(
  new Set(fixtureRoutes).size,
  fixtureRoutes.length,
  `the PAGES e2e fixture has duplicate routes: ${fixtureRoutes.join(", ")}`,
);
ok(`parsed ${fixtureRoutes.length} routes from the PAGES e2e fixture`);

// ---------------------------------------------------------------------------
// 3. The two sets must match exactly, in both directions.
// ---------------------------------------------------------------------------
const footerSet = new Set(footerRoutes);
const fixtureSet = new Set(fixtureRoutes);

const missingFromFixture = footerRoutes.filter((r) => !fixtureSet.has(r));
assert.deepEqual(
  missingFromFixture,
  [],
  `these internal INFO_LINKS routes have NO e2e fixture case (add them to PAGES in test-info-pages-e2e.mjs): ${missingFromFixture.join(", ")}`,
);

const missingFromFooter = fixtureRoutes.filter((r) => !footerSet.has(r));
assert.deepEqual(
  missingFromFooter,
  [],
  `these e2e fixture routes are no longer in INFO_LINKS (remove them from PAGES in test-info-pages-e2e.mjs, or re-add the footer link): ${missingFromFooter.join(", ")}`,
);

ok(
  `INFO_LINKS internal routes match the e2e fixture exactly: ${[...footerSet].sort().join(", ")}`,
);

console.log(
  `\n[test-onboarding-footer-links-sync] all ${passed} checks passed`,
);
