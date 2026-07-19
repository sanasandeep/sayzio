/**
 * favicon-sync guard: verifies that all expected favicon / PWA icon files
 * exist in both the 1inme-com marketing site (dist/public/) and the main
 * 1inme app (1inme/public/), keeping them in sync.
 *
 * Run via: node src/check-favicons.mjs
 */

import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// Paths relative to this file (artifacts/1inme-com/src/check-favicons.mjs)
const COM_DIR = path.resolve(__dirname, "../dist/public");
const APP_DIR = path.resolve(__dirname, "../../1inme/public");

const FAVICON_FILES = [
  "favicon.ico",
  "favicon.svg",
  "favicon-96x96.png",
  "apple-touch-icon.png",
  "site.webmanifest",
  "web-app-manifest-192x192.png",
  "web-app-manifest-512x512.png",
];

let allOk = true;

for (const file of FAVICON_FILES) {
  const comPath = path.join(COM_DIR, file);
  const appPath = path.join(APP_DIR, file);

  if (!fs.existsSync(comPath)) {
    console.error(`  ✗ MISSING in 1inme-com/dist/public: ${file}`);
    allOk = false;
  }
  if (!fs.existsSync(appPath)) {
    console.error(`  ✗ MISSING in 1inme/public: ${file}`);
    allOk = false;
  }
}

if (allOk) {
  console.log(
    `✓ favicon-sync guard passed — all ${FAVICON_FILES.length} favicon / PWA icon file(s) present in both 1inme and 1inme-com.`,
  );
  process.exit(0);
} else {
  console.error(`\n✗ favicon-sync: one or more favicon files are missing`);
  process.exit(1);
}
