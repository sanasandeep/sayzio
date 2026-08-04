#!/usr/bin/env node
// Static guard: block EAS builds when a lockfile shipped to the EAS builder
// points at the Replit package firewall (see .agents/memory/eas-android-build.md).
//
// Installing npm packages inside this Replit workspace rewrites lockfile
// "resolved" URLs to http://package-firewall.replit.local/npm/..., a host the
// EAS build worker cannot resolve (ENOTFOUND). npm 10 then dies at
// INSTALL_DEPENDENCIES with the cryptic "Exit handler never called!" and a
// free-plan build is wasted.
//
// This scans every lockfile that ends up in the EAS upload archive (EAS
// archives from the WORKSPACE ROOT, so both app subprojects and the root
// lockfiles matter) and fails when any of them contains the firewall host.
//
// Usage:
//   node scripts/check-eas-lockfile-registry.mjs         # check, exit 1 on hit
//   node scripts/check-eas-lockfile-registry.mjs --fix   # rewrite firewall URLs
//                                                        # back to registry.npmjs.org
//                                                        # (integrity hashes stay valid)

import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const mobileRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const workspaceRoot = path.resolve(mobileRoot, '..', '..');

const FIREWALL_HOST = 'package-firewall.replit.local';
const FIREWALL_NPM_PREFIX = /https?:\/\/package-firewall\.replit\.local\/npm\//g;
const REGISTRY = 'https://registry.npmjs.org/';

// Lockfiles the EAS builder may consume. Missing files are fine (e.g. the
// mobile app currently installs via the root pnpm lockfile) — but if one
// appears later it is covered automatically.
const LOCKFILES = [
  'artifacts/1inme-mobile/package-lock.json',
  'artifacts/1inme-mobile/npm-shrinkwrap.json',
  'sayzio-dialer-standalone/package-lock.json',
  'sayzio-dialer-standalone/npm-shrinkwrap.json',
  'package-lock.json',
  'pnpm-lock.yaml',
];

const fix = process.argv.includes('--fix');
const dirty = [];

for (const rel of LOCKFILES) {
  const abs = path.join(workspaceRoot, rel);
  if (!existsSync(abs)) continue;

  const content = readFileSync(abs, 'utf8');
  if (!content.includes(FIREWALL_HOST)) continue;

  if (fix) {
    const rewritten = content.replace(FIREWALL_NPM_PREFIX, REGISTRY);
    if (rewritten.includes(FIREWALL_HOST)) {
      // Non-/npm/ firewall URL we don't know how to rewrite — fail loudly
      // rather than silently shipping a broken lockfile.
      console.error(
        `eas-lockfile-registry guard: ${rel} still references ${FIREWALL_HOST} after --fix ` +
        '(URL not under /npm/). Fix it by hand.'
      );
      process.exit(1);
    }
    writeFileSync(abs, rewritten);
    console.log(`eas-lockfile-registry guard: rewrote firewall URLs in ${rel} -> ${REGISTRY}`);
  } else {
    const hits = (content.match(new RegExp(FIREWALL_HOST, 'g')) || []).length;
    dirty.push({ rel, hits });
  }
}

if (dirty.length > 0) {
  console.error('eas-lockfile-registry guard FAILED — lockfile(s) point at the Replit package firewall:');
  for (const { rel, hits } of dirty) {
    console.error(`  - ${rel}: ${hits} resolved URL(s) reference ${FIREWALL_HOST}`);
  }
  console.error('');
  console.error('The EAS builder cannot resolve that host; npm ci dies at INSTALL_DEPENDENCIES');
  console.error('with "Exit handler never called!" and the build is wasted.');
  console.error('');
  console.error('Fix (integrity hashes stay valid):');
  console.error('  node artifacts/1inme-mobile/scripts/check-eas-lockfile-registry.mjs --fix');
  console.error('See .agents/memory/eas-android-build.md for background.');
  process.exit(1);
}

console.log('eas-lockfile-registry guard OK: no package-firewall.replit.local URLs in EAS-shipped lockfiles.');
