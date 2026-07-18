/**
 * Pretest helper: better-sqlite3 ships no prebuilt binary for Node 24, and
 * pnpm installs here skip build scripts, so fresh environments have no native
 * addon and every test that opens a database fails with "Could not locate the
 * bindings file". Detect that case and build the addon from source once.
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
console.log(`ensure-better-sqlite3: native addon missing, building from source in ${pkgDir} (takes a few minutes)...`);

try {
  execSync('npx --yes node-gyp rebuild --release', { cwd: pkgDir, stdio: 'inherit' });
} catch {
  // node-gyp can exit nonzero on a trailing node_gyp_bins lstat ENOENT even
  // though build/Release/better_sqlite3.node was produced fine; verify the
  // artifact instead of trusting the exit code.
  const built = path.join(pkgDir, 'build', 'Release', 'better_sqlite3.node');
  if (!fs.existsSync(built)) {
    console.error('ensure-better-sqlite3: build failed and no binary was produced.');
    process.exit(1);
  }
}

if (!loads()) {
  console.error('ensure-better-sqlite3: build finished but the addon still fails to load.');
  process.exit(1);
}
console.log('ensure-better-sqlite3: build OK, addon loads.');
