// Source-driven drift guard for the mobile About screen's backup EEFind
// parent-company details (task #3270).
//
// The mobile About screen reads its EEFind block (eyebrow, heading, body,
// stats, address, email, WhatsApp, website) at runtime from the product app's
// public GET /api/v1/site/about endpoint, but falls back to a hard-coded
// FALLBACK_EEFIND constant when that fetch fails or returns nothing (offline /
// endpoint unavailable). Those fallback values duplicate the product app's
// canonical About defaults:
//
//   1. the product app's PHP source (canonical) —
//      SitePagesContent::aboutEefindDefault()  (feeds /api/v1/site/about)
//   2. the mobile FALLBACK_EEFIND constant
//      (artifacts/1inme-mobile/app/info/about.tsx — guarded HERE)
//
// If someone updates #1 and forgets #2, a fetch failure on mobile would quietly
// show stale company details (an old address, a dead support@ inbox, wrong
// stats) — the exact silent-drift regression this guard prevents (same class
// as task #3265 for the marketing Contact fallback). This test reads BOTH the
// PHP source and the shipped mobile source at runtime (no hard-coded third
// copy) and fails if the mobile fallback no longer matches the canonical
// values for the fields the screen renders.
//
// Following the convention in test-contact-content-sync.mjs we avoid a full
// TS/RN runner: we read the shipped source and eval the real FALLBACK_EEFIND
// object literal, so this exercises what ships.
//
// Run via `node scripts/test-about-eefind-sync.mjs` (package script
// `test:about-eefind`).

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

const MOBILE_ABOUT_TSX = join(root, "app", "info", "about.tsx");

// ---------------------------------------------------------------------------
// 1. Read the canonical values from SitePagesContent::aboutEefindDefault().
// ---------------------------------------------------------------------------
const php = readFileSync(SITE_PAGES_CONTENT_PHP, "utf8");

// Isolate the body of aboutEefindDefault(): array so a key lookup can't
// accidentally match an identically-named key in another method.
function extractAboutEefindDefaultBody(src) {
  const sig = "public static function aboutEefindDefault(): array";
  const start = src.indexOf(sig);
  if (start === -1) {
    throw new Error(
      "Could not find SitePagesContent::aboutEefindDefault() — did the method get renamed?",
    );
  }
  const nextFn = src.indexOf("public static function", start + sig.length);
  return nextFn === -1 ? src.slice(start) : src.slice(start, nextFn);
}

const body = extractAboutEefindDefaultBody(php);

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
      `Could not find the '${key}' string in aboutEefindDefault() — did the shape change?`,
    );
  }
  return decodePhpString(match[1]);
}

// Extract a nested `'key' => [ ... ]` array block by matching brackets.
function extractPhpArrayBlock(block, key) {
  const marker = new RegExp(`'${key}'\\s*=>\\s*\\[`);
  const m = block.match(marker);
  if (!m) {
    throw new Error(
      `Could not find the '${key}' => [...] block in aboutEefindDefault().`,
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
  throw new Error(`Unterminated '${key}' array in aboutEefindDefault().`);
}

// Format a stat value the way the mobile client renders it (mirrors
// formatStatValue in lib/api/siteContent.ts): a numeric value gets thousands
// separators (e.g. "4000" → "4,000") and the suffix appended ("+"), so
// "4,000+" matches what the screen shows. Non-numeric values pass through.
function formatStatValue(value, suffix) {
  const trimmed = (value ?? "").trim();
  const num = Number(trimmed.replace(/,/g, ""));
  const base =
    trimmed !== "" && Number.isFinite(num) && /^[0-9,]+$/.test(trimmed)
      ? num.toLocaleString("en-US")
      : trimmed;
  return `${base}${(suffix ?? "").trim()}`;
}

// Parse the `stats` array of `['value' => '4000', 'suffix' => '+', 'label' =>
// 'Products']` entries into the same {value, label} shape the screen renders.
function readPhpStats(block) {
  const statsBlock = extractPhpArrayBlock(block, "stats");
  const stats = [];
  const entryRe = /\[([^\]]*)\]/g;
  let m;
  while ((m = entryRe.exec(statsBlock)) !== null) {
    const entry = m[1];
    if (!/'value'\s*=>/.test(entry)) continue;
    stats.push({
      value: formatStatValue(
        readPhpString(entry, "value"),
        readPhpString(entry, "suffix"),
      ),
      label: readPhpString(entry, "label"),
    });
  }
  if (stats.length === 0) {
    throw new Error("Could not parse any stats entries in aboutEefindDefault().");
  }
  return stats;
}

const phpDefaults = {
  eyebrow: readPhpString(body, "eyebrow"),
  heading: readPhpString(body, "heading"),
  body: readPhpString(body, "body"),
  address: readPhpString(body, "address"),
  email: readPhpString(body, "email"),
  whatsapp: readPhpString(body, "whatsapp"),
  website: readPhpString(body, "website"),
  websiteUrl: readPhpString(body, "website_url"),
  stats: readPhpStats(body),
};

// ---------------------------------------------------------------------------
// 2. Eval the shipped mobile FALLBACK_EEFIND object literal.
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

const literal = extractObjectLiteral(tsx, "const FALLBACK_EEFIND: EefindBlock =");

// eslint-disable-next-line no-new-func
const mobileFallback = new Function(`return (${literal});`)();

// ---------------------------------------------------------------------------
// 3. Assert the mobile fallback matches the canonical PHP values.
// ---------------------------------------------------------------------------
// Scalars the screen renders straight from FALLBACK_EEFIND. address + email are
// the headline company-identity fields task #3270 guards against drifting.
for (const field of [
  "eyebrow",
  "heading",
  "body",
  "address",
  "email",
  "whatsapp",
  "website",
  "websiteUrl",
]) {
  assert.equal(
    mobileFallback[field],
    phpDefaults[field],
    `FALLBACK_EEFIND.${field} must match SitePagesContent::aboutEefindDefault()`,
  );
}

// Stats are rendered as value/label pairs; compare count and each entry (with
// the same numeric formatting the client applies to the fetched payload).
assert.equal(
  mobileFallback.stats?.length,
  phpDefaults.stats.length,
  "FALLBACK_EEFIND.stats count must match aboutEefindDefault()",
);
phpDefaults.stats.forEach((stat, i) => {
  assert.equal(
    mobileFallback.stats?.[i]?.value,
    stat.value,
    `FALLBACK_EEFIND.stats[${i}].value must match aboutEefindDefault()`,
  );
  assert.equal(
    mobileFallback.stats?.[i]?.label,
    stat.label,
    `FALLBACK_EEFIND.stats[${i}].label must match aboutEefindDefault()`,
  );
});

console.log(
  "ok — mobile About fallback in sync with SitePagesContent::aboutEefindDefault() (eyebrow, heading, body, stats, address, email, whatsapp, website)",
);
