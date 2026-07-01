#!/usr/bin/env node
/*
 * Keeps the marketing site's favicon assets and cache-busting version in
 * lockstep with the Laravel app, which is the single source of truth.
 *
 *   - Icon files are copied from artifacts/1inme/public/ (reference icons).
 *   - The cache-busting ?v= string is derived from the Laravel app's
 *     config('app.icon_version'), NOT hand-edited.
 *
 * Usage:
 *   node scripts/sync-favicons.mjs           # copy icons + rewrite ?v= in index.html
 *   node scripts/sync-favicons.mjs --check   # fail (exit 1) if anything has drifted
 */
import { readFileSync, writeFileSync, copyFileSync, existsSync } from "node:fs";
import { createHash } from "node:crypto";
import { fileURLToPath } from "node:url";
import path from "node:path";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const marketingRoot = path.resolve(__dirname, "..");
const repoRoot = path.resolve(marketingRoot, "..", "..");
const laravelPublic = path.resolve(repoRoot, "artifacts/1inme/public");
const laravelConfig = path.resolve(repoRoot, "artifacts/1inme/config/app.php");
const marketingPublic = path.resolve(marketingRoot, "public");
const indexHtml = path.resolve(marketingRoot, "index.html");

// The shared brand icon binaries. site.webmanifest is intentionally excluded:
// the marketing site is served under a base path so it needs relative icon
// paths, while the Laravel copy uses root-absolute paths.
const ICON_FILES = [
  "favicon.svg",
  "favicon-96x96.png",
  "favicon.ico",
  "apple-touch-icon.png",
  "web-app-manifest-192x192.png",
  "web-app-manifest-512x512.png",
];

// index.html <link> hrefs whose ?v= must track the shared icon version.
const VERSIONED_HREF_RE =
  /(href="\.\/(?:favicon\.svg|favicon-96x96\.png|favicon\.ico|apple-touch-icon\.png|site\.webmanifest))(?:\?v=[^"]*)?"/g;

const checkOnly = process.argv.includes("--check");

function hashFile(file) {
  return createHash("sha256").update(readFileSync(file)).digest("hex");
}

function readIconVersion() {
  const php = readFileSync(laravelConfig, "utf8");
  const m = php.match(/'icon_version'\s*=>\s*'([^']+)'/);
  if (!m) {
    throw new Error(
      `Could not find config('app.icon_version') in ${laravelConfig}`,
    );
  }
  return m[1];
}

const problems = [];
let changed = false;

const iconVersion = readIconVersion();

// 1. Sync icon binaries against the Laravel reference icons.
for (const name of ICON_FILES) {
  const src = path.join(laravelPublic, name);
  const dest = path.join(marketingPublic, name);
  if (!existsSync(src)) {
    problems.push(`reference icon missing: artifacts/1inme/public/${name}`);
    continue;
  }
  const destExists = existsSync(dest);
  const drifted = !destExists || hashFile(src) !== hashFile(dest);
  if (!drifted) continue;

  if (checkOnly) {
    problems.push(
      destExists
        ? `icon out of date: public/${name} differs from artifacts/1inme/public/${name}`
        : `icon missing: public/${name}`,
    );
  } else {
    copyFileSync(src, dest);
    changed = true;
    console.log(`synced icon: public/${name}`);
  }
}

// 2. Sync the cache-busting version in index.html.
const html = readFileSync(indexHtml, "utf8");
const nextHtml = html.replace(VERSIONED_HREF_RE, `$1?v=${iconVersion}"`);
if (nextHtml !== html) {
  if (checkOnly) {
    problems.push(
      `index.html cache-busting version is stale; expected ?v=${iconVersion} (from config('app.icon_version'))`,
    );
  } else {
    writeFileSync(indexHtml, nextHtml);
    changed = true;
    console.log(`updated index.html favicon version to ?v=${iconVersion}`);
  }
}

if (checkOnly) {
  if (problems.length > 0) {
    console.error("Marketing favicon assets have drifted:\n");
    for (const p of problems) console.error(`  - ${p}`);
    console.error(
      "\nRun `pnpm --filter @workspace/1inme-com run sync:favicons` to fix.",
    );
    process.exit(1);
  }
  console.log(
    `Marketing favicons are in sync (version ?v=${iconVersion}).`,
  );
} else if (problems.length > 0) {
  console.error("Could not fully sync favicons:\n");
  for (const p of problems) console.error(`  - ${p}`);
  process.exit(1);
} else if (!changed) {
  console.log(
    `Marketing favicons already in sync (version ?v=${iconVersion}).`,
  );
}
