// Source-driven drift guard for the mobile backup contact details.
//
// The correct brand contact details (EEFind Private Limited, Banjara Hills,
// hello@sayzio.app, deliberately blank phone, business hours, social links and
// the office map) live in FOUR places that must stay identical:
//
//   1. the product app's PHP source (canonical) —
//      SitePagesContent::contactExtraDefault()
//   2. the web blade /contact page (seeded from #1)
//   3. the marketing site's DEFAULT_CONTACT_CONTENT
//      (artifacts/1inme-com/src/lib/contact-content.ts — guarded by
//       contact-content.sync.test.ts there)
//   4. the mobile DEFAULT_CONTACT_CONTENT
//      (artifacts/1inme-mobile/lib/api/siteContent.ts — guarded HERE)
//
// #3 and #4 are the fallback rendered when GET /api/v1/site/contact is
// unreachable (offline / server down). If either copy drifts from the PHP
// source, that client silently misrepresents the company — wrong city, an old
// support@ address, or (worst) a fake phone number. This test reads BOTH the
// PHP source and the shipped mobile source at runtime (no hard-coded third
// copy) and fails if the mobile fallback no longer matches the canonical
// values for address, email, blank phone, hours, social and map.
//
// Following the convention in test-quick-contact.mjs / test-upgrade-hint.mjs
// we avoid a full TS/RN runner: we read the shipped source and eval the real
// `DEFAULT_CONTACT_CONTENT` object literal, so this exercises what ships.
//
// Run via `node scripts/test-contact-content-sync.mjs` (package script
// `test:contact-content`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join, resolve } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

// The product app's canonical PHP source, relative to this mobile package.
const SITE_PAGES_CONTENT_PHP = resolve(
  root,
  "..",
  "1inme",
  "app",
  "Modules",
  "Common",
  "Support",
  "SitePagesContent.php",
);

const MOBILE_SITE_CONTENT_TS = join(root, "lib", "api", "siteContent.ts");

// ---------------------------------------------------------------------------
// 1. Read the canonical values from SitePagesContent::contactExtraDefault().
// ---------------------------------------------------------------------------
const php = readFileSync(SITE_PAGES_CONTENT_PHP, "utf8");

// Isolate the body of contactExtraDefault(): array so a key lookup can't
// accidentally match an identically-named key in another method.
function extractContactExtraDefaultBody(src) {
  const sig = "public static function contactExtraDefault(): array";
  const start = src.indexOf(sig);
  if (start === -1) {
    throw new Error(
      "Could not find SitePagesContent::contactExtraDefault() — did the method get renamed?",
    );
  }
  const nextFn = src.indexOf("public static function", start + sig.length);
  return nextFn === -1 ? src.slice(start) : src.slice(start, nextFn);
}

const body = extractContactExtraDefaultBody(php);

// Decode a PHP string literal into its runtime value. Double-quoted strings
// interpret escapes like \n; single-quoted strings only interpret \\ and \'.
function decodePhpString(raw) {
  const quote = raw[0];
  const inner = raw.slice(1, -1);
  if (quote === '"') {
    return inner
      .replace(/\\n/g, "\n")
      .replace(/\\r/g, "\r")
      .replace(/\\t/g, "\t")
      .replace(/\\"/g, '"')
      .replace(/\\\\/g, "\\");
  }
  return inner.replace(/\\'/g, "'").replace(/\\\\/g, "\\");
}

// Read a `'key' => "..."` (or '...') scalar string from a block of PHP.
function readPhpString(block, key) {
  const re = new RegExp(
    `'${key}'\\s*=>\\s*("(?:[^"\\\\]|\\\\.)*"|'(?:[^'\\\\]|\\\\.)*')`,
  );
  const match = block.match(re);
  if (!match) {
    throw new Error(
      `Could not find the '${key}' string in contactExtraDefault() — did the shape change?`,
    );
  }
  return decodePhpString(match[1]);
}

// Read a `'key' => 12.34` numeric scalar from a block of PHP.
function readPhpNumber(block, key) {
  const re = new RegExp(`'${key}'\\s*=>\\s*(-?[0-9]+(?:\\.[0-9]+)?)`);
  const match = block.match(re);
  if (!match) {
    throw new Error(
      `Could not find the numeric '${key}' in contactExtraDefault() — did the shape change?`,
    );
  }
  return Number(match[1]);
}

// Extract a nested `'key' => [ ... ]` array block by matching brackets.
function extractPhpArrayBlock(block, key) {
  const marker = new RegExp(`'${key}'\\s*=>\\s*\\[`);
  const m = block.match(marker);
  if (!m) {
    throw new Error(
      `Could not find the '${key}' => [...] block in contactExtraDefault().`,
    );
  }
  const open = block.indexOf("[", m.index);
  let depth = 0;
  for (let i = open; i < block.length; i++) {
    const ch = block[i];
    if (ch === "[") depth++;
    else if (ch === "]") {
      depth--;
      if (depth === 0) return block.slice(open, i + 1);
    }
  }
  throw new Error(`Unterminated '${key}' array in contactExtraDefault().`);
}

const socialBlock = extractPhpArrayBlock(body, "social");
const mapBlock = extractPhpArrayBlock(body, "map");

const phpDefaults = {
  address: readPhpString(body, "address"),
  email: readPhpString(body, "email"),
  phone: readPhpString(body, "phone"),
  hours: readPhpString(body, "hours"),
  social: {
    twitter: readPhpString(socialBlock, "twitter"),
    instagram: readPhpString(socialBlock, "instagram"),
    linkedin: readPhpString(socialBlock, "linkedin"),
    youtube: readPhpString(socialBlock, "youtube"),
    facebook: readPhpString(socialBlock, "facebook"),
  },
  map: {
    lat: readPhpNumber(mapBlock, "lat"),
    lng: readPhpNumber(mapBlock, "lng"),
    zoom: readPhpNumber(mapBlock, "zoom"),
    label: readPhpString(mapBlock, "label"),
  },
};

// Sanity: the phone must be blank in the canonical source (no fake number).
assert.equal(
  phpDefaults.phone,
  "",
  "canonical contactExtraDefault() phone must be blank — a fake number would leak to clients",
);

// ---------------------------------------------------------------------------
// 2. Eval the shipped mobile DEFAULT_CONTACT_CONTENT object literal.
// ---------------------------------------------------------------------------
const ts = readFileSync(MOBILE_SITE_CONTENT_TS, "utf8");

function extractObjectLiteral(src, decl) {
  const start = src.indexOf(decl);
  if (start === -1) {
    throw new Error(`Could not find '${decl}' in siteContent.ts.`);
  }
  const open = src.indexOf("{", start);
  let depth = 0;
  for (let i = open; i < src.length; i++) {
    const ch = src[i];
    if (ch === "{") depth++;
    else if (ch === "}") {
      depth--;
      if (depth === 0) return src.slice(open, i + 1);
    }
  }
  throw new Error(`Unterminated object literal for '${decl}'.`);
}

const literal = extractObjectLiteral(
  ts,
  "export const DEFAULT_CONTACT_CONTENT: ContactContent =",
);

// eslint-disable-next-line no-new-func
const mobileDefault = new Function(`return (${literal});`)();

// ---------------------------------------------------------------------------
// 3. Assert the mobile fallback matches the canonical PHP values.
// ---------------------------------------------------------------------------
for (const field of ["address", "email", "phone", "hours"]) {
  assert.equal(
    mobileDefault[field],
    phpDefaults[field],
    `DEFAULT_CONTACT_CONTENT.${field} must match SitePagesContent::contactExtraDefault()`,
  );
}

// Phone stays blank on the client too (belt and braces).
assert.equal(
  mobileDefault.phone,
  "",
  "mobile DEFAULT_CONTACT_CONTENT.phone must stay blank (no fake number)",
);

for (const key of ["twitter", "instagram", "linkedin", "youtube", "facebook"]) {
  assert.equal(
    mobileDefault.social?.[key],
    phpDefaults.social[key],
    `DEFAULT_CONTACT_CONTENT.social.${key} must match contactExtraDefault()`,
  );
}

for (const key of ["lat", "lng", "zoom"]) {
  assert.equal(
    Number(mobileDefault.map?.[key]),
    phpDefaults.map[key],
    `DEFAULT_CONTACT_CONTENT.map.${key} must match contactExtraDefault()`,
  );
}
assert.equal(
  mobileDefault.map?.label,
  phpDefaults.map.label,
  "DEFAULT_CONTACT_CONTENT.map.label must match contactExtraDefault()",
);

console.log(
  "ok — mobile contact fallback in sync with SitePagesContent::contactExtraDefault() (address, email, blank phone, hours, social, map)",
);
