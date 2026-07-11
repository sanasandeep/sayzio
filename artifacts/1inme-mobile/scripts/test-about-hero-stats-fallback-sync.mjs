// Source-driven drift guard for the mobile About screen's BUNDLED hero-stat
// fallback numbers (task #4462).
//
// The mobile About screen reads its animated hero stat row (the count-up
// counters — "120,000+ Creators served", etc.) at runtime from the product
// app's public GET /api/v1/site/about endpoint under `data.hero.stats`, but
// falls back to a hard-coded FALLBACK_HERO_STATS constant when that fetch fails
// or returns no usable rows (offline / endpoint unavailable). Those fallback
// numbers duplicate the product app's canonical About hero defaults:
//
//   1. the product app's PHP source (canonical) —
//      SitePagesContent::aboutExtraDefault()['hero']['stats'] (feeds the
//      /api/v1/site/about `data.hero.stats` payload).
//   2. the mobile FALLBACK_HERO_STATS constant
//      (artifacts/1inme-mobile/components/AboutPage.tsx — guarded HERE).
//
// If someone updates the web hero defaults (#1) and forgets the mobile fallback
// (#2), an offline user would quietly see stale/wrong headline numbers (an old
// creator count, wrong labels) with no error — the exact silent-drift class
// this guard prevents (same class as task #3270's EEFind guard in
// test-about-eefind-sync.mjs, and a companion to the hero-PARSER guard in
// test-about-hero-stats-sync.mjs). This test reads BOTH the PHP source and the
// shipped mobile source at runtime (no hard-coded third copy) and fails if the
// mobile fallback no longer matches the canonical hero-stat defaults.
//
// The comparison mirrors how the values reach the screen: the API
// (SiteContentController::about()) drops per-stat rows flagged `visible => false`
// or with neither a value nor a label, and the mobile parser formats each
// value/suffix via formatStatValue (numeric → thousands separators + suffix).
//
// Run via `node scripts/test-about-hero-stats-fallback-sync.mjs` (package
// script `test:about-hero-stats-fallback`).

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

const MOBILE_ABOUT_TSX = join(root, "components", "AboutPage.tsx");

// ---------------------------------------------------------------------------
// 1. Read the canonical hero stats from
//    SitePagesContent::aboutExtraDefault()['hero']['stats'].
// ---------------------------------------------------------------------------
const php = readFileSync(SITE_PAGES_CONTENT_PHP, "utf8");

// Isolate the body of aboutExtraDefault(): array so a key lookup can't
// accidentally match an identically-named key in another method.
function extractAboutExtraDefaultBody(src) {
  const sig = "public static function aboutExtraDefault(): array";
  const start = src.indexOf(sig);
  if (start === -1) {
    throw new Error(
      "Could not find SitePagesContent::aboutExtraDefault() — did the method get renamed?",
    );
  }
  const nextFn = src.indexOf("public static function", start + sig.length);
  return nextFn === -1 ? src.slice(start) : src.slice(start, nextFn);
}

const body = extractAboutExtraDefaultBody(php);

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
      `Could not find the '${key}' string in aboutExtraDefault() hero stats — did the shape change?`,
    );
  }
  return decodePhpString(match[1]);
}

// Read a boolean `'key' => true|false` from a block of PHP (default true when
// the key is absent — mirrors SiteContentController::about()).
function readPhpVisible(block) {
  const m = block.match(/'visible'\s*=>\s*(true|false)/);
  return m ? m[1] === "true" : true;
}

// Extract a nested `'key' => [ ... ]` array block by matching brackets.
function extractPhpArrayBlock(block, key) {
  const marker = new RegExp(`'${key}'\\s*=>\\s*\\[`);
  const m = block.match(marker);
  if (!m) {
    throw new Error(
      `Could not find the '${key}' => [...] block in aboutExtraDefault().`,
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
  throw new Error(`Unterminated '${key}' array in aboutExtraDefault().`);
}

// Format a stat value the way the mobile client renders it (mirrors
// formatStatValue in lib/api/siteContent.ts): a numeric value gets thousands
// separators (e.g. "120000" → "120,000") and the suffix appended, so
// "120,000+" matches what the screen shows. Non-numeric values pass through.
function formatStatValue(value, suffix) {
  const trimmed = (value ?? "").trim();
  const num = Number(trimmed.replace(/,/g, ""));
  const base =
    trimmed !== "" && Number.isFinite(num) && /^[0-9,]+$/.test(trimmed)
      ? num.toLocaleString("en-US")
      : trimmed;
  return `${base}${(suffix ?? "").trim()}`;
}

// Parse the hero `stats` array of `['value' => '120000', 'suffix' => '+',
// 'label' => 'Creators served', 'visible' => true]` entries into the same
// {value, label} shape the screen renders — mirroring the API's visibility /
// non-empty filtering in SiteContentController::about().
function readPhpHeroStats(extraBody) {
  const heroBlock = extractPhpArrayBlock(extraBody, "hero");
  const statsBlock = extractPhpArrayBlock(heroBlock, "stats");
  const stats = [];
  const entryRe = /\[([^\]]*)\]/g;
  let m;
  while ((m = entryRe.exec(statsBlock)) !== null) {
    const entry = m[1];
    if (!/'value'\s*=>/.test(entry)) continue;
    if (!readPhpVisible(entry)) continue;
    const value = readPhpString(entry, "value").trim();
    const label = readPhpString(entry, "label").trim();
    if (value === "" && label === "") continue;
    stats.push({
      value: formatStatValue(value, readPhpString(entry, "suffix")),
      label,
    });
  }
  if (stats.length === 0) {
    throw new Error(
      "Could not parse any hero stat entries in aboutExtraDefault()['hero']['stats'].",
    );
  }
  return stats;
}

const phpHeroStats = readPhpHeroStats(body);

// ---------------------------------------------------------------------------
// 2. Eval the shipped mobile FALLBACK_HERO_STATS array literal.
// ---------------------------------------------------------------------------
const tsx = readFileSync(MOBILE_ABOUT_TSX, "utf8");

function extractArrayLiteral(src, decl) {
  const start = src.indexOf(decl);
  if (start === -1) {
    throw new Error(`Could not find '${decl}' in AboutPage.tsx.`);
  }
  const open = src.indexOf("[", start);
  let depth = 0;
  for (let i = open; i < src.length; i++) {
    const ch = src[i];
    if (ch === "[") depth++;
    else if (ch === "]") {
      depth--;
      if (depth === 0) return src.slice(open, i + 1);
    }
  }
  throw new Error(`Unterminated array literal for '${decl}'.`);
}

const literal = extractArrayLiteral(
  tsx,
  "const FALLBACK_HERO_STATS: Array<{ value: string; label: string }> =",
);

// eslint-disable-next-line no-new-func
const mobileFallback = new Function(`return (${literal});`)();

// ---------------------------------------------------------------------------
// 3. Assert the mobile fallback matches the canonical hero-stat defaults.
// ---------------------------------------------------------------------------
assert.equal(
  mobileFallback.length,
  phpHeroStats.length,
  "FALLBACK_HERO_STATS count must match aboutExtraDefault()['hero']['stats']",
);
phpHeroStats.forEach((stat, i) => {
  assert.equal(
    mobileFallback[i]?.value,
    stat.value,
    `FALLBACK_HERO_STATS[${i}].value must match aboutExtraDefault() hero stats`,
  );
  assert.equal(
    mobileFallback[i]?.label,
    stat.label,
    `FALLBACK_HERO_STATS[${i}].label must match aboutExtraDefault() hero stats`,
  );
});

console.log(
  "ok — mobile About FALLBACK_HERO_STATS in sync with SitePagesContent::aboutExtraDefault() hero stats (value + label, formatted)",
);
