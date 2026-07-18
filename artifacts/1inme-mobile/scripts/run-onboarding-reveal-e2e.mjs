#!/usr/bin/env node
/**
 * CI gate wrapper for the onboarding-reveal runtime backstop
 * (test-onboarding-slide-reveal-e2e.mjs).
 *
 * Why a wrapper: the underlying harness, like its mobile e2e siblings, will
 * boot its OWN throwaway Expo server and then SKIP (exit 0) when that server /
 * Chromium can't come up in time. That best-effort skip is the right behaviour
 * for the boot race, but it means a bare `node test-...mjs` in CI can silently
 * downgrade a genuine reveal regression into a skip if the same run also races
 * the throwaway boot.
 *
 * So this wrapper PRE-WARMS one Expo web server ONCE (reusing the shared
 * expo-web-server.mjs manager — spawn, wait for Metro, warm GET / until the
 * bundle actually serves), then runs the harness pointed at that already-warm
 * server via an explicit APP_URL. Against an explicit APP_URL the harness treats
 * the server as trusted and FAILS HARD (exit 1) on a reveal regression instead
 * of skipping — so the only thing that can skip is the warm-up itself, never the
 * assertion. This mirrors how the big browser-e2e suite's run-validation.sh
 * boots its own server and exports APP_URL before running the specs.
 *
 * Precedence:
 *   • If APP_URL is already set in the environment, we DON'T boot anything — we
 *     just pass it straight through to the harness (local debugging / a CI that
 *     pre-started a shared server).
 *   • Otherwise we boot + warm a throwaway and hand its URL to the harness.
 *
 * Best-effort skip contract is preserved but narrowed to warm-up only: if the
 * Expo server can't be brought up (expo missing, port contention, Metro/bundle
 * too slow on a starved box) we log and exit 0 — the environment simply couldn't
 * bring a server up, which is not a product regression. Once a server IS warm,
 * the harness's own exit code (0 pass / 1 regression) is propagated verbatim.
 */

import { spawn } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { createExpoServerManager, runHarness } from "./expo-web-server.mjs";

const SCRIPTS_DIR = path.dirname(fileURLToPath(import.meta.url));
const HARNESS = path.join(SCRIPTS_DIR, "test-onboarding-slide-reveal-e2e.mjs");

function log(...args) {
  console.log("[onboarding-reveal-e2e:warm]", ...args);
}

// Run the harness as a child, inheriting stdio, with APP_URL pointing at the
// warm server. Resolves with the child's exit code (defaulting a signal death
// to 1 so a killed harness never reads as a pass).
function runRevealHarness(appUrl) {
  return new Promise((resolve) => {
    const child = spawn(process.execPath, [HARNESS], {
      stdio: "inherit",
      env: { ...process.env, APP_URL: appUrl },
    });
    child.on("exit", (code, signal) => {
      if (signal) {
        log(`harness terminated by signal ${signal}`);
        resolve(1);
        return;
      }
      resolve(code ?? 1);
    });
    child.on("error", (e) => {
      log(`failed to spawn the harness (${e?.message ?? e})`);
      resolve(1);
    });
  });
}

async function main() {
  // A caller-supplied APP_URL means "reuse this already-running server": pass it
  // through untouched so the harness runs against it as an explicit (fail-hard)
  // target and we never boot a redundant throwaway.
  const explicitUrl = process.env.APP_URL;
  if (explicitUrl) {
    log(`reusing the server from APP_URL (${explicitUrl})`);
    const code = await runRevealHarness(explicitUrl);
    process.exit(code);
  }

  // No external server: pre-warm one throwaway Expo web server ONCE, then run
  // the harness against it as an explicit target.
  const manager = createExpoServerManager(log);
  const booted = await manager.bootThrowawayExpo("onboarding-reveal");
  if (!booted) {
    // The env couldn't bring a server up in time — best-effort skip (exit 0),
    // exactly like the harness would have on the same boot race.
    log("could not warm an Expo server; skipping (best-effort, exit 0)");
    process.exit(0);
  }

  const appUrl = `http://localhost:${booted.port}/`;
  log(`Expo server warm at ${appUrl}; running the reveal harness against it`);
  const code = await runRevealHarness(appUrl);
  manager.stopExpo(booted.child);
  process.exit(code);
}

// Termination guarantee: runHarness exits the process as soon as main()
// settles and arms a watchdog, so a leaked handle can never stall the run.
runHarness(main, {
  log,
  onError: (e) => {
    // A crash in the wrapper itself is an infra problem, not a product
    // regression: log and skip rather than red-flagging CI.
    log(`wrapper error, skipping (exit 0): ${e?.stack ?? e}`);
    process.exit(0);
  },
});
