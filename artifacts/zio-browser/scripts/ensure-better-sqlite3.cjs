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
 *
 * Freshness: whenever the cache is seeded, entries for this platform/arch
 * that no longer match the installed better-sqlite3 version or the current
 * Node ABI are pruned so stale binaries don't accumulate in the repo after
 * a package or Node upgrade. If the addon already loads but the matching
 * cache entry is missing (e.g. right after an upgrade), the cache is
 * re-seeded from the existing build.
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

const pkgDir = path.dirname(require.resolve('better-sqlite3/package.json'));
const pkgVersion = require('better-sqlite3/package.json').version;
const abi = process.versions.modules;
const cacheDir = path.join(__dirname, '..', 'prebuilds');
const cacheName = `better-sqlite3-v${pkgVersion}-abi${abi}-${process.platform}-${process.arch}.node`;
const cachePath = path.join(cacheDir, cacheName);
const builtPath = path.join(pkgDir, 'build', 'Release', 'better_sqlite3.node');

// Cache entries for this platform/arch that don't match the installed
// package version + current Node ABI are dead weight after an upgrade.
function pruneStaleCacheEntries() {
  let entries;
  try {
    entries = fs.readdirSync(cacheDir);
  } catch {
    return;
  }
  const suffix = `-${process.platform}-${process.arch}.node`;
  for (const entry of entries) {
    if (!entry.startsWith('better-sqlite3-') || !entry.endsWith(suffix)) continue;
    if (entry === cacheName) continue;
    try {
      fs.rmSync(path.join(cacheDir, entry));
      console.log(`ensure-better-sqlite3: pruned stale cache entry prebuilds/${entry}.`);
    } catch (err) {
      console.warn(`ensure-better-sqlite3: could not prune prebuilds/${entry} (${err.message}).`);
    }
  }
}

function seedCache() {
  try {
    fs.mkdirSync(cacheDir, { recursive: true });
    fs.copyFileSync(builtPath, cachePath);
    console.log(`ensure-better-sqlite3: cached binary saved to prebuilds/${cacheName}.`);
    pruneStaleCacheEntries();
  } catch (err) {
    console.warn(`ensure-better-sqlite3: could not seed cache (${err.message}); continuing.`);
  }
}

if (loads()) {
  // Addon already works. Keep the committed cache fresh: after a package or
  // Node upgrade the matching entry won't exist yet, so seed it from the
  // existing build (and prune entries that no longer match).
  if (!fs.existsSync(cachePath) && fs.existsSync(builtPath)) {
    console.warn(
      `ensure-better-sqlite3: no cache entry matches better-sqlite3 v${pkgVersion} / Node ABI ${abi}; re-seeding prebuilds/${cacheName} from the existing build.`
    );
    seedCache();
  }
  process.exit(0);
}

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
} else {
  console.warn(
    `ensure-better-sqlite3: no cache entry matches better-sqlite3 v${pkgVersion} / Node ABI ${abi} (${process.platform}-${process.arch}); a source build will run and re-seed the cache.`
  );
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

// Seed the committed cache so future fresh environments skip the compile,
// pruning entries that no longer match the current version/ABI.
seedCache();
