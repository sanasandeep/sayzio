#!/usr/bin/env node
// Static guard: catch Expo project relink drift in the standalone dialer's
// app.json before it wastes an EAS APK build (dialer shares the same
// free-plan Android build quota as the main app — see
// .agents/memory/eas-android-build.md).
//
// Sibling of artifacts/1inme-mobile/scripts/check-expo-project-link.mjs.
// Past relink incidents this guards against:
//  1. A stale/placeholder `extra.eas.projectId` (non-UUID) makes
//     `eas project:init` think the project is already linked, then GraphQL
//     dies on "Invalid UUID appId".
//  2. `eas project:init --force` can inject an
//     `extra.eas.build.experimental` block (e.g. duplicate appExtensions);
//     that class of drift makes `expo config --json` exit 1 with ZERO output
//     and every build fails at "Read app config".
//  3. Owner drift: builds must land on the `eefind` account
//     (project @eefind/sayzio-dialer).
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
  console.error(`expo-project-link guard (dialer): cannot read/parse ${appJsonPath}: ${err.message}`);
  process.exit(1);
}

const expo = config?.expo;
if (!expo || typeof expo !== 'object') {
  console.error('expo-project-link guard (dialer): app.json has no top-level "expo" object');
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

// 3. No manual extra.eas.build block: eas project:init --force can inject
//    experimental config (e.g. appExtensions) that breaks `expo config --json`
//    with a silent exit 1.
if (expo.extra?.eas?.build !== undefined) {
  const experimental = expo.extra.eas.build?.experimental;
  errors.push(
    `extra.eas.build${experimental !== undefined ? '.experimental' : ''} block present in app.json — ` +
    'delete it. eas project:init can inject experimental config that makes ' +
    '`expo config --json` exit 1 with no output and every EAS build fail at "Read app config".'
  );
}

if (errors.length > 0) {
  console.error('expo-project-link guard FAILED for sayzio-dialer-standalone/app.json:');
  for (const e of errors) console.error(`  - ${e}`);
  console.error('See .agents/memory/eas-android-build.md for the relink recipe.');
  process.exit(1);
}

console.log(
  `expo-project-link guard OK (dialer): owner="${EXPECTED_OWNER}", projectId=${projectId}, no extra.eas.build block.`
);
