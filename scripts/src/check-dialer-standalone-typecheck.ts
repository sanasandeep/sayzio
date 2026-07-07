/**
 * Typecheck gate for the standalone dialer app (sayzio-dialer-standalone/).
 *
 * The standalone app lives OUTSIDE the pnpm workspace (it is an npm-managed
 * Expo app), so root `pnpm run typecheck` never touches it. This gate runs
 * `tsc --noEmit` inside sayzio-dialer-standalone/ and fails on any error.
 *
 * Dependency install is cached: `npm ci` only runs when node_modules is
 * missing or package-lock.json changed since the last install (tracked via
 * a sha256 stamp at node_modules/.package-lock.sha256.stamp).
 *
 * Usage:
 *   pnpm --filter @workspace/scripts run check:dialer-typecheck
 */
import { spawnSync } from "node:child_process";
import { createHash } from "node:crypto";
import { existsSync, readFileSync, writeFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const repoRoot = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);
const standaloneRoot = path.join(repoRoot, "sayzio-dialer-standalone");
const lockfilePath = path.join(standaloneRoot, "package-lock.json");
const nodeModulesPath = path.join(standaloneRoot, "node_modules");
const stampPath = path.join(nodeModulesPath, ".package-lock.sha256.stamp");

if (!existsSync(standaloneRoot)) {
  console.error(`check:dialer-typecheck: missing directory ${standaloneRoot}`);
  process.exit(1);
}
if (!existsSync(lockfilePath)) {
  console.error(`check:dialer-typecheck: missing ${lockfilePath}`);
  process.exit(1);
}

const lockHash = createHash("sha256")
  .update(readFileSync(lockfilePath))
  .digest("hex");

const cached =
  existsSync(nodeModulesPath) &&
  existsSync(stampPath) &&
  readFileSync(stampPath, "utf8").trim() === lockHash;

const run = (cmd: string, args: string[]) =>
  spawnSync(cmd, args, {
    cwd: standaloneRoot,
    stdio: "inherit",
    env: process.env,
  });

if (cached) {
  console.log(
    "check:dialer-typecheck: node_modules up to date (lockfile hash match), skipping npm ci",
  );
} else {
  console.log("check:dialer-typecheck: installing dependencies via npm ci...");
  const install = run("npm", ["ci", "--no-audit", "--no-fund"]);
  if (install.status !== 0) {
    console.error(
      `check:dialer-typecheck: npm ci failed (exit ${install.status ?? "signal"})`,
    );
    process.exit(install.status ?? 1);
  }
  writeFileSync(stampPath, `${lockHash}\n`);
}

console.log(
  "check:dialer-typecheck: running tsc --noEmit in sayzio-dialer-standalone/...",
);
const tsc = run("npx", ["tsc", "--noEmit"]);
if (tsc.status !== 0) {
  console.error(
    `check:dialer-typecheck: FAILED — type errors in sayzio-dialer-standalone/ (exit ${tsc.status ?? "signal"})`,
  );
  process.exit(tsc.status ?? 1);
}
console.log("check:dialer-typecheck: OK — standalone dialer typechecks clean");
