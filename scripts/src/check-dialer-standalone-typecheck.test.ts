import { describe, it, expect } from "vitest";
import { spawnSync } from "node:child_process";
import { createHash } from "node:crypto";
import { existsSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

/**
 * Meta-test for the `dialer-typecheck` validation gate
 * (check-dialer-standalone-typecheck.ts).
 *
 * A guard like this can silently go false-green if its exit-code plumbing or
 * cwd resolution regresses, so — following the check-factory-columns meta-test
 * pattern — we drive BOTH paths through the real gate as a subprocess:
 *   1. a clean run against the real standalone app must exit 0, and
 *   2. a run with a deliberately poisoned .ts file dropped into
 *      sayzio-dialer-standalone/ must exit non-zero.
 *
 * The gate runs `npm ci` when sayzio-dialer-standalone/node_modules is missing
 * or its lockfile stamp is stale — far too slow for a unit test — so the suite
 * skips itself unless the install cache is already warm (node_modules present
 * AND the sha256 stamp matches package-lock.json). In that cached state the
 * gate goes straight to `tsc --noEmit`, which still takes several seconds over
 * the Expo app; hence the generous per-test timeout.
 *
 * Run: pnpm --filter @workspace/scripts run test
 */

const scriptsRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const repoRoot = path.resolve(scriptsRoot, "..");
const standaloneRoot = path.join(repoRoot, "sayzio-dialer-standalone");
const lockfilePath = path.join(standaloneRoot, "package-lock.json");
const nodeModulesPath = path.join(standaloneRoot, "node_modules");
const stampPath = path.join(nodeModulesPath, ".package-lock.sha256.stamp");
const gateScript = path.join(scriptsRoot, "src", "check-dialer-standalone-typecheck.ts");
const tsxBin = path.join(scriptsRoot, "node_modules", ".bin", "tsx");

// Poison file lives at the standalone root, which is covered by the app
// tsconfig's `**/*.ts` include.
const POISON_FILE = path.join(standaloneRoot, "__gate_meta_test_poison__.ts");

const installCacheWarm = (): boolean => {
  if (!existsSync(nodeModulesPath) || !existsSync(stampPath) || !existsSync(lockfilePath)) {
    return false;
  }
  const lockHash = createHash("sha256").update(readFileSync(lockfilePath)).digest("hex");
  return readFileSync(stampPath, "utf8").trim() === lockHash;
};

const depsReady = installCacheWarm();
if (!depsReady) {
  // eslint-disable-next-line no-console
  console.warn(
    "check-dialer-standalone-typecheck.test: skipping — sayzio-dialer-standalone " +
      "install cache is cold (node_modules missing or lockfile stamp stale); " +
      "run `pnpm --filter @workspace/scripts run check:dialer-typecheck` once to warm it.",
  );
}

// tsc --noEmit over the whole Expo app takes several seconds; leave ample room.
const GATE_TIMEOUT_MS = 180_000;

const runGate = () =>
  spawnSync(tsxBin, [gateScript], {
    cwd: scriptsRoot,
    encoding: "utf8",
    timeout: GATE_TIMEOUT_MS,
    env: process.env,
  });

describe.skipIf(!depsReady)("dialer-typecheck gate meta-test", () => {
  it(
    "exits 0 on the real (clean) standalone app",
    () => {
      // Defensive: a leaked poison file from an earlier aborted run would make
      // the clean run fail for the wrong reason.
      rmSync(POISON_FILE, { force: true });

      const res = runGate();
      const output = `${res.stdout}\n${res.stderr}`;
      expect(res.status, `gate should pass on a clean tree; output:\n${output}`).toBe(0);
      expect(output).toContain("OK — standalone dialer typechecks clean");
      // The cached-install path must have been taken (no slow npm ci mid-test).
      expect(output).toContain("skipping npm ci");
    },
    GATE_TIMEOUT_MS,
  );

  it(
    "exits non-zero when a poisoned .ts file is present",
    () => {
      writeFileSync(
        POISON_FILE,
        [
          "// Temporary poison file written by check-dialer-standalone-typecheck.test.ts.",
          "// If you are reading this in the repo, a test run leaked — delete this file.",
          'const poisoned: number = "not a number";',
          "export default poisoned;",
          "",
        ].join("\n"),
      );
      try {
        const res = runGate();
        const output = `${res.stdout}\n${res.stderr}`;
        expect(
          res.status,
          `gate must fail on a type error; output:\n${output}`,
        ).not.toBe(0);
        expect(res.status).not.toBeNull(); // exited, not killed by timeout/signal
        expect(output).toContain("FAILED — type errors in sayzio-dialer-standalone/");
      } finally {
        rmSync(POISON_FILE, { force: true });
      }
    },
    GATE_TIMEOUT_MS,
  );

  it("cleans up: no poison file left behind", () => {
    expect(existsSync(POISON_FILE)).toBe(false);
  });
});
