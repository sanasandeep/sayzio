/**
 * Pretest helper: better-sqlite3 ships no prebuilt binary for Node 24, and
 * pnpm installs here skip build scripts, so fresh environments have no native
 * addon and every test that opens a database fails with "Could not locate the
 * bindings file".
 *
 * Fast path: copy a known-good compiled addon from the committed cache in
 * ../prebuilds (keyed by package version + Node ABI + platform + arch).
 * Fallback: build from source with node-gyp (takes a few minutes) and then
 * seed the cache so the next fresh environment is near-instant.
 */
const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs');

function loads() {
  try {
    const Database = require('better-sqlite3');
    new Database(':memory:').close();
    return true;
  } catch {
    return false;
  }
}

if (loads()) {
  process.exit(0);
}

const pkgDir = path.dirname(require.resolve('better-sqlite3/package.json'));
const pkgVersion = require('better-sqlite3/package.json').version;
const abi = process.versions.modules;
const cacheDir = path.join(__dirname, '..', 'prebuilds');
const cacheName = `better-sqlite3-v${pkgVersion}-abi${abi}-${process.platform}-${process.arch}.node`;
const cachePath = path.join(cacheDir, cacheName);
const builtPath = path.join(pkgDir, 'build', 'Release', 'better_sqlite3.node');

if (fs.existsSync(cachePath)) {
  console.log(`ensure-better-sqlite3: restoring cached binary ${cacheName}...`);
  fs.mkdirSync(path.dirname(builtPath), { recursive: true });
  fs.copyFileSync(cachePath, builtPath);
  if (loads()) {
    console.log('ensure-better-sqlite3: cached binary OK, addon loads.');
    process.exit(0);
  }
  console.warn('ensure-better-sqlite3: cached binary failed to load; falling back to source build.');
  fs.rmSync(builtPath, { force: true });
}

console.log(`ensure-better-sqlite3: native addon missing, building from source in ${pkgDir} (takes a few minutes)...`);

try {
  execSync('npx --yes node-gyp rebuild --release', { cwd: pkgDir, stdio: 'inherit' });
} catch {
  // node-gyp can exit nonzero on a trailing node_gyp_bins lstat ENOENT even
  // though build/Release/better_sqlite3.node was produced fine; verify the
  // artifact instead of trusting the exit code.
  if (!fs.existsSync(builtPath)) {
    console.error('ensure-better-sqlite3: build failed and no binary was produced.');
    process.exit(1);
  }
}

if (!loads()) {
  console.error('ensure-better-sqlite3: build finished but the addon still fails to load.');
  process.exit(1);
}
console.log('ensure-better-sqlite3: build OK, addon loads.');

// Seed the committed cache so future fresh environments skip the compile.
try {
  fs.mkdirSync(cacheDir, { recursive: true });
  fs.copyFileSync(builtPath, cachePath);
  console.log(`ensure-better-sqlite3: cached binary saved to prebuilds/${cacheName}.`);
} catch (err) {
  console.warn(`ensure-better-sqlite3: could not seed cache (${err.message}); continuing.`);
}
