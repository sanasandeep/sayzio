#!/usr/bin/env node
// Static guard: catch Expo project relink drift in app.json before it breaks
// an EAS APK build (see .agents/memory/eas-android-build.md).
//
// Past relink incidents this guards against:
//  1. A stale/placeholder `extra.eas.projectId` (non-UUID) makes
//     `eas project:init` think the project is already linked, then GraphQL
//     dies on "Invalid UUID appId".
//  2. `eas project:init --force` re-injects a duplicate ShareExtension block
//     under `extra.eas.build.experimental.ios.appExtensions`; the
//     expo-share-intent plugin adds its own, so ANY copy in app.json makes
//     `expo config --json` exit 1 with ZERO output and every build fails.
//  3. Owner drift: builds must land on the `eefind` account.
//
// Exits non-zero with a plain message per violation.

import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const appJsonPath = path.join(root, 'app.json');

const EXPECTED_OWNER = 'eefind';
const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

const errors = [];

let config;
try {
  config = JSON.parse(readFileSync(appJsonPath, 'utf8'));
} catch (err) {
  console.error(`expo-project-link guard: cannot read/parse ${appJsonPath}: ${err.message}`);
  process.exit(1);
}

const expo = config?.expo;
if (!expo || typeof expo !== 'object') {
  console.error('expo-project-link guard: app.json has no top-level "expo" object');
  process.exit(1);
}

// 1. Owner must be the eefind account.
if (expo.owner !== EXPECTED_OWNER) {
  errors.push(
    `expo.owner is ${JSON.stringify(expo.owner)} — expected "${EXPECTED_OWNER}". ` +
    'Builds on another account draw from the wrong quota/keystore.'
  );
}

// 2. extra.eas.projectId must be a real UUID (placeholders break eas project:init).
const projectId = expo.extra?.eas?.projectId;
if (typeof projectId !== 'string' || !UUID_RE.test(projectId)) {
  errors.push(
    `extra.eas.projectId is ${JSON.stringify(projectId)} — expected a UUID. ` +
    'A missing/placeholder projectId makes eas project:init fail with "Invalid UUID appId".'
  );
}

// 3. No manual appExtensions block: expo-share-intent injects its own
//    ShareExtension; a duplicate makes `expo config --json` exit 1 silently.
if (expo.extra?.eas?.build !== undefined) {
  const experimental = expo.extra.eas.build?.experimental;
  errors.push(
    `extra.eas.build${experimental !== undefined ? '.experimental' : ''} block present in app.json — ` +
    'delete it. eas project:init re-injects a ShareExtension appExtensions entry that duplicates ' +
    'the expo-share-intent plugin\'s own, making `expo config --json` exit 1 with no output ' +
    'and every EAS build fail at "Read app config".'
  );
}

if (errors.length > 0) {
  console.error('expo-project-link guard FAILED for artifacts/1inme-mobile/app.json:');
  for (const e of errors) console.error(`  - ${e}`);
  console.error('See .agents/memory/eas-android-build.md for the relink recipe.');
  process.exit(1);
}

console.log(
  `expo-project-link guard OK: owner="${EXPECTED_OWNER}", projectId=${projectId}, no manual appExtensions block.`
);
