// Source-driven drift guard for the onboarding intro carousel copy.
//
// The intro slides ship in TWO places that a user can see depending on whether
// the admin-seeding migration has run yet:
//
//   1. artifacts/1inme-mobile/app/onboarding.tsx — FALLBACK_SLIDES, the app's
//      bundled copy shown on a fresh install / before seeding / offline.
//   2. artifacts/1inme/database/seeders/OnboardingSlidesSeeder.php — the
//      canonical seeded copy shown once the admin has seeded.
//
// If these two diverge, the SAME user sees slightly different intro wording
// depending on seeding state (e.g. "single biolink" vs "single Link in Bio",
// "menu, hours" vs "menu, opening hours"). This guard reads BOTH real sources
// at runtime (no hard-coded third copy) and fails if any slide's slug,
// category, title, body OR sort_order drifts between them — bodies included,
// so future body drift is caught too.
//
// Following the convention in test-about-founder-sync.mjs we avoid a full TS/RN
// runner: we read the shipped source and parse the real object/array literals,
// so this exercises exactly what ships.
//
// Run via `node scripts/test-onboarding-slides-sync.mjs` (package script
// `test:onboarding-slides-sync`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join, resolve } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const MOBILE_ONBOARDING_TSX = join(root, "app", "onboarding.tsx");
const SEEDER_PHP = resolve(
  root,
  "..",
  "1inme",
  "database",
  "seeders",
  "OnboardingSlidesSeeder.php",
);

// The scalar fields that must stay in lockstep across both copies.
const FIELDS = ["slug", "category", "title", "body", "sort_order"];

// ---------------------------------------------------------------------------
// Bracket-matched extraction: pull the [...] / {...} block that follows a
// declaration marker, respecting nesting and (naively) skipping brackets that
// appear inside string literals.
// ---------------------------------------------------------------------------
function extractBlock(src, markerIndex, openCh, closeCh) {
  const open = src.indexOf(openCh, markerIndex);
  if (open === -1) throw new Error(`Could not find '${openCh}' after marker.`);
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
      continue;
    }
    if (ch === openCh) depth++;
    else if (ch === closeCh) {
      depth--;
      if (depth === 0) return src.slice(open, i + 1);
    }
  }
  throw new Error("Unterminated block while extracting slides array.");
}

// Split the inner contents of an array block into its top-level element blocks
// (each wrapped in openCh..closeCh), skipping string literals.
function splitTopLevelElements(arrayBlock, openCh, closeCh) {
  const inner = arrayBlock.slice(1, -1); // drop the outer [ ]
  const elements = [];
  let depth = 0;
  let start = -1;
  let quote = null;
  for (let i = 0; i < inner.length; i++) {
    const ch = inner[i];
    if (quote) {
      if (ch === "\\") i++;
      else if (ch === quote) quote = null;
      continue;
    }
    if (ch === '"' || ch === "'" || ch === "`") {
      quote = ch;
      continue;
    }
    if (ch === openCh) {
      if (depth === 0) start = i;
      depth++;
    } else if (ch === closeCh) {
      depth--;
      if (depth === 0) {
        elements.push(inner.slice(start, i + 1));
      }
    }
  }
  return elements;
}

// ---------------------------------------------------------------------------
// String-literal decoders.
// ---------------------------------------------------------------------------
function decodePhpString(raw) {
  const quote = raw[0];
  const body = raw.slice(1, -1);
  if (quote === '"') {
    return body
      .replace(/\\n/g, "\n")
      .replace(/\\r/g, "\r")
      .replace(/\\t/g, "\t")
      .replace(/\\"/g, '"')
      .replace(/\\\\/g, "\\");
  }
  return body.replace(/\\'/g, "'").replace(/\\\\/g, "\\");
}

function decodeJsString(raw) {
  return raw
    .slice(1, -1)
    .replace(/\\n/g, "\n")
    .replace(/\\r/g, "\r")
    .replace(/\\t/g, "\t")
    .replace(/\\"/g, '"')
    .replace(/\\'/g, "'")
    .replace(/\\`/g, "`")
    .replace(/\\\\/g, "\\");
}

// Read a `'key' => "..."` or `'key' => null` (PHP) scalar from an element block.
function readPhpString(block, key, ctx) {
  const reNull = new RegExp(`'${key}'\\s*=>\\s*null`);
  if (reNull.test(block)) return null;
  const re = new RegExp(
    `'${key}'\\s*=>\\s*("(?:[^"\\\\]|\\\\.)*"|'(?:[^'\\\\]|\\\\.)*')`,
  );
  const m = block.match(re);
  if (!m) throw new Error(`Missing PHP string '${key}' in ${ctx}.`);
  return decodePhpString(m[1]);
}

function readPhpNumber(block, key, ctx) {
  const m = block.match(new RegExp(`'${key}'\\s*=>\\s*(-?\\d+)`));
  if (!m) throw new Error(`Missing PHP number '${key}' in ${ctx}.`);
  return Number(m[1]);
}

// Read a `key: "..."` or `key: null` (JS/TS) scalar from an element block.
function readJsString(block, key, ctx) {
  const reNull = new RegExp(`\\b${key}\\s*:\\s*null`);
  if (reNull.test(block)) return null;
  const re = new RegExp(
    `\\b${key}\\s*:\\s*("(?:[^"\\\\]|\\\\.)*"|'(?:[^'\\\\]|\\\\.)*'|\`(?:[^\`\\\\]|\\\\.)*\`)`,
  );
  const m = block.match(re);
  if (!m) throw new Error(`Missing JS string '${key}' in ${ctx}.`);
  return decodeJsString(m[1]);
}

function readJsNumber(block, key, ctx) {
  const m = block.match(new RegExp(`\\b${key}\\s*:\\s*(-?\\d+)`));
  if (!m) throw new Error(`Missing JS number '${key}' in ${ctx}.`);
  return Number(m[1]);
}

// ---------------------------------------------------------------------------
// 1. Parse the seeder's $slides array (PHP, canonical once seeded).
// ---------------------------------------------------------------------------
const php = readFileSync(SEEDER_PHP, "utf8");
const phpMarker = php.indexOf("$slides = [");
assert.ok(phpMarker >= 0, "OnboardingSlidesSeeder should define `$slides = [`.");
const phpArray = extractBlock(php, phpMarker, "[", "]");
const phpElements = splitTopLevelElements(phpArray, "[", "]");

const seederSlides = phpElements.map((block) => ({
  slug: readPhpString(block, "slug", "seeder slide"),
  category: readPhpString(block, "category", "seeder slide"),
  title: readPhpString(block, "title", "seeder slide"),
  body: readPhpString(block, "body", "seeder slide"),
  sort_order: readPhpNumber(block, "sort_order", "seeder slide"),
}));

// ---------------------------------------------------------------------------
// 2. Parse the mobile FALLBACK_SLIDES array (TS, bundled fallback).
// ---------------------------------------------------------------------------
const tsx = readFileSync(MOBILE_ONBOARDING_TSX, "utf8");
const tsxMarker = tsx.indexOf("const FALLBACK_SLIDES");
assert.ok(tsxMarker >= 0, "onboarding.tsx should define FALLBACK_SLIDES.");
// Skip past the type annotation (OnboardingSlide[]) to the assignment array.
const tsxEq = tsx.indexOf("=", tsxMarker);
assert.ok(tsxEq >= 0, "FALLBACK_SLIDES declaration should have an assignment.");
const tsxArray = extractBlock(tsx, tsxEq, "[", "]");
const tsxElements = splitTopLevelElements(tsxArray, "{", "}");

const fallbackSlides = tsxElements.map((block) => ({
  slug: readJsString(block, "slug", "fallback slide"),
  category: readJsString(block, "category", "fallback slide"),
  title: readJsString(block, "title", "fallback slide"),
  body: readJsString(block, "body", "fallback slide"),
  sort_order: readJsNumber(block, "sort_order", "fallback slide"),
}));

// ---------------------------------------------------------------------------
// 3. Assert the two copies are identical (keyed by slug).
// ---------------------------------------------------------------------------
assert.ok(seederSlides.length > 0, "No slides parsed from the seeder.");
assert.ok(fallbackSlides.length > 0, "No slides parsed from FALLBACK_SLIDES.");

const seederBySlug = new Map(seederSlides.map((s) => [s.slug, s]));
const fallbackBySlug = new Map(fallbackSlides.map((s) => [s.slug, s]));

assert.deepEqual(
  [...fallbackBySlug.keys()].sort(),
  [...seederBySlug.keys()].sort(),
  "Onboarding slide slugs differ between FALLBACK_SLIDES and OnboardingSlidesSeeder.",
);

assert.equal(
  fallbackSlides.length,
  seederSlides.length,
  "Onboarding slide count differs between FALLBACK_SLIDES and OnboardingSlidesSeeder.",
);

for (const slug of seederBySlug.keys()) {
  const seeded = seederBySlug.get(slug);
  const bundled = fallbackBySlug.get(slug);
  for (const field of FIELDS) {
    assert.equal(
      bundled[field],
      seeded[field],
      `Slide '${slug}': ${field} drifted.\n` +
        `  FALLBACK_SLIDES (onboarding.tsx): ${JSON.stringify(bundled[field])}\n` +
        `  OnboardingSlidesSeeder.php:       ${JSON.stringify(seeded[field])}`,
    );
  }
}

console.log(
  `ok — all ${seederSlides.length} onboarding intro slides in sync ` +
    `(slug, category, title, body, sort_order) between FALLBACK_SLIDES and OnboardingSlidesSeeder`,
);
