// Source-driven drift guard for the mobile About screen's static "Meet the
// founder" fallback (task #4448).
//
// The mobile About screen (artifacts/1inme-mobile/app/info/about.tsx) ships a
// hard-coded FOUNDER constant used at first-paint / when offline. Unlike the
// EEFind block, the runtime About content endpoint does NOT return founder copy,
// so this static constant is the ONLY founder copy mobile ever shows. It
// duplicates the product app's canonical About founder defaults:
//
//   1. the product app's PHP source (canonical) —
//      SitePagesContent::aboutExtraDefault()['founder']  (name / role / bio)
//      and the founder image asset path resolved in the web About Blade view
//      (resources/views/public/about.blade.php → $defaultFounderPhoto).
//   2. the mobile FOUNDER constant (artifacts/1inme-mobile/app/info/about.tsx —
//      guarded HERE).
//
// If someone edits #1 (renames the founder, changes the role/bio, or moves the
// founder image) and forgets #2, offline mobile users would silently see stale
// or broken founder details — the exact drift this guard prevents (same class
// as test-about-eefind-sync.mjs for the EEFind block). This test reads BOTH the
// PHP/Blade source and the shipped mobile source at runtime (no hard-coded third
// copy) and fails if the mobile fallback no longer matches the canonical values.
//
// Following the convention in test-about-eefind-sync.mjs we avoid a full TS/RN
// runner: we read the shipped source and parse the real FOUNDER object literal,
// so this exercises what ships.
//
// Run via `node scripts/test-about-founder-sync.mjs` (package script
// `test:about-founder`).

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

const ABOUT_BLADE = resolve(
  root,
  "..",
  "1inme",
  "resources",
  "views",
  "public",
  "about.blade.php",
);

const MOBILE_ABOUT_TSX = join(root, "app", "info", "about.tsx");

// ---------------------------------------------------------------------------
// Small PHP-literal helpers (mirrors test-about-eefind-sync.mjs).
// ---------------------------------------------------------------------------

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
function readPhpString(block, key, context) {
  const re = new RegExp(
    `'${key}'\\s*=>\\s*("(?:[^"\\\\]|\\\\.)*"|'(?:[^'\\\\]|\\\\.)*')`,
  );
  const match = block.match(re);
  if (!match) {
    throw new Error(
      `Could not find the '${key}' string in ${context} — did the shape change?`,
    );
  }
  return decodePhpString(match[1]);
}

// Extract a nested `'key' => [ ... ]` array block by matching brackets.
function extractPhpArrayBlock(block, key, context) {
  const marker = new RegExp(`'${key}'\\s*=>\\s*\\[`);
  const m = block.match(marker);
  if (!m) {
    throw new Error(`Could not find the '${key}' => [...] block in ${context}.`);
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
  throw new Error(`Unterminated '${key}' array in ${context}.`);
}

// ---------------------------------------------------------------------------
// 1. Read the canonical founder copy from aboutExtraDefault()['founder'].
// ---------------------------------------------------------------------------
const php = readFileSync(SITE_PAGES_CONTENT_PHP, "utf8");

function extractMethodBody(src, sig) {
  const start = src.indexOf(sig);
  if (start === -1) {
    throw new Error(`Could not find ${sig} — did the method get renamed?`);
  }
  const nextFn = src.indexOf("public static function", start + sig.length);
  return nextFn === -1 ? src.slice(start) : src.slice(start, nextFn);
}

const aboutExtraBody = extractMethodBody(
  php,
  "public static function aboutExtraDefault(): array",
);

// The 'founder' => [...] array block (NOT the scalar section_titles['founder']
// eyebrow, which readPhpArrayBlock's `=> [` marker skips).
const founderBlock = extractPhpArrayBlock(
  aboutExtraBody,
  "founder",
  "aboutExtraDefault()",
);

const phpFounder = {
  name: readPhpString(founderBlock, "name", "aboutExtraDefault()['founder']"),
  role: readPhpString(founderBlock, "role", "aboutExtraDefault()['founder']"),
  bio: readPhpString(founderBlock, "bio", "aboutExtraDefault()['founder']"),
};

// ---------------------------------------------------------------------------
// 2. Read the canonical founder image path from the web About Blade view.
//    The stored founder 'photo' default is empty; the web view falls back to
//    asset('images/marketing/about/founder.png'), so THAT relative path is the
//    image the mobile fallback must mirror.
// ---------------------------------------------------------------------------
const blade = readFileSync(ABOUT_BLADE, "utf8");
const photoMatch = blade.match(
  /\$defaultFounderPhoto\s*=\s*asset\(\s*'([^']+)'\s*\)/,
);
if (!photoMatch) {
  throw new Error(
    "Could not find $defaultFounderPhoto = asset('...') in about.blade.php — did the founder image fallback change?",
  );
}
// Normalise to a leading-slash absolute path the way asset() renders it.
const phpFounderPhotoPath = photoMatch[1].startsWith("/")
  ? photoMatch[1]
  : `/${photoMatch[1]}`;

// ---------------------------------------------------------------------------
// 3. Parse the shipped mobile FOUNDER object literal.
// ---------------------------------------------------------------------------
const tsx = readFileSync(MOBILE_ABOUT_TSX, "utf8");

function extractObjectLiteral(src, decl) {
  const start = src.indexOf(decl);
  if (start === -1) {
    throw new Error(`Could not find '${decl}' in about.tsx.`);
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

const founderLiteral = extractObjectLiteral(
  tsx,
  "const FOUNDER: FounderBlock =",
);

// name / role / bio are plain string literals (single or double quoted, or a
// template literal with no interpolation) — read them directly. The `photo`
// field is a template literal `${getBaseUrl()}/images/...` so it can't be
// eval'd without the runtime; we extract just the path portion instead.
function readTsString(block, key) {
  const re = new RegExp(
    `\\b${key}\\s*:\\s*("(?:[^"\\\\]|\\\\.)*"|'(?:[^'\\\\]|\\\\.)*'|\`(?:[^\`\\\\]|\\\\.)*\`)`,
  );
  const match = block.match(re);
  if (!match) {
    throw new Error(`Could not find '${key}' in the mobile FOUNDER literal.`);
  }
  let s = match[1].slice(1, -1);
  // Decode the common escapes; mobile copy is plain prose.
  return s
    .replace(/\\n/g, "\n")
    .replace(/\\t/g, "\t")
    .replace(/\\"/g, '"')
    .replace(/\\'/g, "'")
    .replace(/\\`/g, "`")
    .replace(/\\\\/g, "\\");
}

const mobileFounder = {
  name: readTsString(founderLiteral, "name"),
  role: readTsString(founderLiteral, "role"),
  bio: readTsString(founderLiteral, "bio"),
};

// The photo path: extract the literal path after ${getBaseUrl()} in the
// template literal, e.g. `${getBaseUrl()}/images/marketing/about/founder.png`.
const mobilePhotoMatch = founderLiteral.match(
  /photo\s*:\s*`\$\{getBaseUrl\(\)\}([^`]+)`/,
);
if (!mobilePhotoMatch) {
  throw new Error(
    "Could not find the FOUNDER.photo `${getBaseUrl()}...` template literal in about.tsx — did the photo shape change?",
  );
}
const mobileFounderPhotoPath = mobilePhotoMatch[1];

// ---------------------------------------------------------------------------
// 4. Assert the mobile fallback matches the canonical values.
// ---------------------------------------------------------------------------
for (const field of ["name", "role", "bio"]) {
  assert.equal(
    mobileFounder[field],
    phpFounder[field],
    `FOUNDER.${field} must match SitePagesContent::aboutExtraDefault()['founder'].${field}`,
  );
}

assert.equal(
  mobileFounderPhotoPath,
  phpFounderPhotoPath,
  "FOUNDER.photo path must match the web About founder image fallback ($defaultFounderPhoto in about.blade.php)",
);

console.log(
  "ok — mobile About FOUNDER fallback in sync with aboutExtraDefault()['founder'] (name, role, bio) and the web founder image path",
);
