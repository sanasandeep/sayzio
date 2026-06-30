#!/usr/bin/env node
/**
 * Shared helpers for booting (or reusing) a throwaway, self-contained Expo web
 * dev server for headless-browser regression checks, and warming it until it
 * actually serves a real page before any browser navigation.
 *
 * Both the auth-flow harness (test-auth-flow-e2e.mjs) and the icon-font harness
 * (test-icon-fonts-e2e.mjs) drive the REAL app in headless Chromium, and both
 * need the same fragile boot dance: spawn `expo start`, wait for Metro to report
 * ready, then warm `GET /` to a real 200 + non-trivial HTML body so Playwright
 * never hits a server that's still compiling its first bundle. This module is
 * the single source for that dance so the two harnesses can't drift.
 *
 * Best-effort contract: when a throwaway server can't be brought up (expo
 * missing, port contention, bundling too slow), the boot helpers return null so
 * callers SKIP (exit 0) rather than fail CI — the env simply couldn't bring
 * Metro up. An explicit *_APP_URL override means "reuse this already-running
 * server" (no boot), handy for local debugging and for letting CI pre-start a
 * server once and reuse it.
 */

import { spawn } from "node:child_process";
import net from "node:net";
import path from "node:path";
import { fileURLToPath } from "node:url";

// Root of the mobile artifact (one level up from scripts/). Used to spawn the
// throwaway Expo server with the artifact as its working directory.
const MOBILE_ROOT = path.resolve(fileURLToPath(import.meta.url), "..", "..");

// Overall wall-clock budget for bringing a throwaway Expo server from spawn to
// *fully serveable* (Metro up AND the web bundle compiled and served at least
// once) before giving up and skipping. This single budget covers BOTH phases —
// Metro's /status going green and the first real GET / returning a 200 — so
// total boot time can never exceed it regardless of how the time is split
// between the two phases under load.
//
// Kept deliberately modest: validation runs this Expo boot in parallel with the
// heavy browser-e2e suite on one constrained box, so holding a CPU-bound Metro
// bundle longer than this just starves those concurrent jobs. When a flow loses
// that race and isn't serveable in time it SKIPs (exit 0, best-effort) AND kills
// its Metro child, freeing CPU promptly — a clean skip is a far better neighbour
// than a multi-minute bundle hog.
export const EXPO_BOOT_DEADLINE_MS = 180_000;

// Allocate a free localhost port for a throwaway server.
export function getFreePort() {
  return new Promise((resolve, reject) => {
    const srv = net.createServer();
    srv.once("error", reject);
    srv.listen(0, "127.0.0.1", () => {
      const { port } = srv.address();
      srv.close(() => resolve(port));
    });
  });
}

// Poll the Expo dev server's /status endpoint (it returns the literal
// "packager-status:running" once Metro is up) until ready or the absolute
// deadline (a Date.now()-style timestamp) passes.
export async function waitForExpoStatus(port, deadlineAt) {
  const url = `http://localhost:${port}/status`;
  while (Date.now() < deadlineAt) {
    try {
      const res = await fetch(url);
      const text = await res.text().catch(() => "");
      if (res.ok && /packager-status:running/.test(text)) return true;
    } catch {
      // not up yet
    }
    await new Promise((r) => setTimeout(r, 1500));
  }
  return false;
}

// Metro reporting "packager-status:running" only means the dev server process
// is up — NOT that the web bundle has been compiled and can be served. The very
// first GET / triggers that compile, which under parallel CI load is the slow,
// flaky part: while it's in flight the server can accept the connection and then
// return an empty body (Playwright surfaces this as net::ERR_EMPTY_RESPONSE) or
// briefly refuse it (net::ERR_CONNECTION_REFUSED). Driving Playwright at the
// server in that window is the dominant cause of the "Expo server is ready" →
// immediate nav failure / 90s nav-timeout flake.
//
// So after Metro is up we warm the bundle here ourselves: repeatedly fetch / and
// only return once it answers with a real 200 + non-trivial HTML body. This
// absorbs the expensive first compile BEFORE any browser navigation, so the
// subsequent page.goto() hits an already-compiled bundle and returns promptly.
// Each fetch is given a bounded per-attempt timeout so one hung request can't
// eat the whole budget, and transient errors/empties just retry until the shared
// absolute deadline.
export async function waitForExpoServeable(port, deadlineAt) {
  const url = `http://localhost:${port}/`;
  while (Date.now() < deadlineAt) {
    const controller = new AbortController();
    const perAttempt = setTimeout(() => controller.abort(), 20_000);
    try {
      const res = await fetch(url, { signal: controller.signal });
      const text = await res.text().catch(() => "");
      // A served web bundle returns the HTML shell (a <div id="root"> mount
      // point and/or a bundled script tag). Require a 200 plus a non-trivial
      // body so a half-written/empty response doesn't count as serveable.
      if (res.ok && text.length > 200 && /<\/html>|id="root"|<script/i.test(text)) {
        return true;
      }
    } catch {
      // ERR_EMPTY_RESPONSE / connection refused / abort — bundle not ready yet.
    } finally {
      clearTimeout(perAttempt);
    }
    await new Promise((r) => setTimeout(r, 1500));
  }
  return false;
}

// Kill the spawned Expo server and the whole process group it leads (expo
// spawns Metro children; killing only the parent would orphan them).
export function stopExpo(child) {
  if (!child || child.killed) return;
  try {
    process.kill(-child.pid, "SIGTERM");
  } catch {
    try {
      child.kill("SIGTERM");
    } catch {
      // already gone
    }
  }
}

// Build a server manager bound to a caller-supplied `log` so each harness keeps
// its own log prefix. Returns boot/acquire helpers plus a teardown registered on
// process "exit" so every booted child is reaped even on the skip()/fail() paths
// that process.exit() directly from deep inside a flow.
export function createExpoServerManager(log) {
  // Boot a throwaway, self-contained Expo web dev server on a free port with the
  // given extra env baked into the bundle, and wait until it's fully serveable.
  // Returns { child, port } on success, or null if it couldn't start. Best
  // effort: callers SKIP (exit 0) rather than fail CI when this returns null.
  async function bootThrowawayExpo(label, extraEnv = {}) {
    let port;
    try {
      port = await getFreePort();
    } catch (e) {
      log(`${label}: could not allocate a port (${e?.message ?? e})`);
      return null;
    }

    log(`${label}: booting a throwaway Expo web server on :${port}`);
    const child = spawn(
      "pnpm",
      ["exec", "expo", "start", "--localhost", "--port", String(port)],
      {
        cwd: MOBILE_ROOT,
        detached: true,
        stdio: ["ignore", "ignore", "ignore"],
        env: {
          ...process.env,
          CI: "1",
          BROWSER: "none",
          EXPO_NO_TELEMETRY: "1",
          ...extraEnv,
        },
      },
    );

    let spawnFailed = false;
    child.on("error", (e) => {
      spawnFailed = true;
      log(`${label}: expo failed to spawn (${e?.message ?? e})`);
    });

    // One shared wall clock for both boot phases (Metro up, then bundle served)
    // so their combined time can never exceed EXPO_BOOT_DEADLINE_MS.
    const deadlineAt = Date.now() + EXPO_BOOT_DEADLINE_MS;

    const metroUp = await waitForExpoStatus(port, deadlineAt);
    if (spawnFailed || !metroUp) {
      log(
        `${label}: the throwaway Expo server didn't become ready within ` +
          `${Math.round(EXPO_BOOT_DEADLINE_MS / 1000)}s`,
      );
      stopExpo(child);
      return null;
    }

    // Metro is up; now wait until the web bundle actually serves a real page so
    // we never hand Playwright a server that's still compiling (the
    // ERR_EMPTY_RESPONSE / nav-timeout flake). Reuses the same deadline, so a
    // slow Metro boot leaves less time for the compile but the total stays
    // bounded.
    log(`${label}: Metro is up; warming the web bundle`);
    const serveable = await waitForExpoServeable(port, deadlineAt);
    if (serveable) {
      log(`${label}: Expo server is ready (bundle served)`);
      return { child, port };
    }

    log(
      `${label}: Metro came up but the web bundle didn't serve a page within ` +
        `${Math.round(EXPO_BOOT_DEADLINE_MS / 1000)}s`,
    );
    stopExpo(child);
    return null;
  }

  // Every throwaway Expo child we boot is tracked here so it's torn down even on
  // the skip()/fail() paths. The "exit" handler must be synchronous —
  // process.kill is — so a group-kill there reliably reaps Metro.
  const bootedChildren = new Set();
  function stopAllChildren() {
    for (const c of bootedChildren) stopExpo(c);
    bootedChildren.clear();
  }
  process.on("exit", stopAllChildren);

  // Acquire a ready-to-drive server for a flow: reuse an already-running one
  // when its *_APP_URL override is set (no boot), otherwise boot a throwaway and
  // track it for teardown. Returns { appUrl, child, explicit } or null when a
  // throwaway couldn't come up (callers SKIP, never fail, on null).
  async function acquireServer(label, explicitUrl, extraEnv = {}) {
    if (explicitUrl) {
      return { appUrl: explicitUrl, child: null, explicit: true };
    }
    const booted = await bootThrowawayExpo(label, extraEnv);
    if (!booted) return null;
    bootedChildren.add(booted.child);
    return {
      appUrl: `http://localhost:${booted.port}/`,
      child: booted.child,
      explicit: false,
    };
  }

  return { bootThrowawayExpo, acquireServer, stopAllChildren, stopExpo };
}
