#!/usr/bin/env node
/**
 * Shared helpers for producing the REAL native (iOS/Android) JavaScript bundle
 * that ships inside the packaged app, so a regression check can inspect what a
 * phone would actually load — not the Expo *web* bundle.
 *
 * Why a separate module from expo-web-server.mjs: the icon-font web harness
 * boots `expo start` (dev) and warms `GET /` until the web shell is serveable.
 * Native font bundling is a different code path — Metro resolves the icon
 * `.ttf` imports for the iOS/Android target and embeds them as packager assets.
 * To exercise THAT path we boot Metro in production mode (`--no-dev --minify`,
 * exactly as scripts/build.js does for the real deploy) and download the
 * platform bundle, then scan it for the bundled font assets + registration.
 *
 * Best-effort contract (same as the web harness): when Metro can't be brought
 * up or the bundle can't be compiled within the wall-clock budget (CI box too
 * slow, port contention, expo missing), the helpers return null so callers SKIP
 * (exit 0) rather than fail CI just because the environment couldn't bundle. A
 * real font/asset/registration regression on a bundle that DID compile still
 * fails the check (exit 1).
 *
 * An explicit NATIVE_BUNDLE_FILE override means "read this already-built bundle
 * from disk" (no Metro boot) — handy for local debugging and for letting CI
 * pre-build a bundle once and reuse it.
 */

import { spawn } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { getFreePort, waitForExpoStatus, stopExpo } from "./expo-web-server.mjs";

// Root of the mobile artifact (one level up from scripts/).
const MOBILE_ROOT = path.resolve(fileURLToPath(import.meta.url), "..", "..");

// Walk up from the mobile artifact to the pnpm workspace root (the directory
// holding pnpm-workspace.yaml). The Metro `.bundle` URL path is relative to it,
// mirroring scripts/build.js's bundle-download contract.
function findWorkspaceRoot(startDir) {
  let dir = startDir;
  while (dir !== path.dirname(dir)) {
    if (fs.existsSync(path.join(dir, "pnpm-workspace.yaml"))) return dir;
    dir = path.dirname(dir);
  }
  return startDir;
}

const WORKSPACE_ROOT = findWorkspaceRoot(MOBILE_ROOT);

// Overall wall-clock budget for bringing a throwaway Metro server from spawn to
// a fully-compiled native bundle in hand. Native production bundling is heavier
// than the web shell (it minifies and resolves every native asset), so this is
// more generous than the web harness's boot deadline — but still bounded so a
// hung Metro can never hang CI: when the budget is blown the caller SKIPs.
export const NATIVE_BUNDLE_DEADLINE_MS = Number(
  process.env.NATIVE_BUNDLE_DEADLINE_MS || 480_000,
);

// Per-attempt timeout for a single bundle fetch. The FIRST fetch is what
// triggers the expensive native compile, so it's given the whole remaining
// budget; we just guard against a single request hanging forever.
const BUNDLE_FETCH_TIMEOUT_MS = NATIVE_BUNDLE_DEADLINE_MS;

// Build the Metro `.bundle` URL for the production native bundle, exactly as
// scripts/build.js does for the real deploy (entry = expo-router/entry, path
// relative to the workspace root, dev=false + minify=true).
function nativeBundleUrl(port, platform) {
  const entryPath = path.resolve(
    MOBILE_ROOT,
    "node_modules",
    "expo-router",
    "entry",
  );
  const bundlePath = path.relative(WORKSPACE_ROOT, entryPath);
  const url = new URL(`http://localhost:${port}/${bundlePath}.bundle`);
  url.searchParams.set("platform", platform);
  url.searchParams.set("dev", "false");
  url.searchParams.set("hot", "false");
  url.searchParams.set("lazy", "false");
  url.searchParams.set("minify", "true");
  return url.toString();
}

// Spawn a throwaway Metro server in PRODUCTION mode on the given port. Returns
// the child process (its own group, so it can be reaped wholesale) or null if
// spawn failed synchronously.
function spawnMetro(port, log) {
  log(`booting a throwaway production Metro on :${port}`);
  const child = spawn(
    "pnpm",
    ["exec", "expo", "start", "--no-dev", "--minify", "--localhost", "--port", String(port)],
    {
      cwd: MOBILE_ROOT,
      detached: true,
      stdio: ["ignore", "ignore", "ignore"],
      env: {
        ...process.env,
        CI: "1",
        BROWSER: "none",
        EXPO_NO_TELEMETRY: "1",
      },
    },
  );
  // Never let the throwaway Metro child keep the harness's event loop alive:
  // a missed exit path would otherwise hang the whole validation run for the
  // child's lifetime. The process "exit" hook still group-kills it.
  child.unref();
  return child;
}

// Download the native production bundle for `platform`, retrying transient
// failures (the first fetch triggers the slow compile) until the shared
// deadline. Returns the bundle text, or null if it never compiled in time.
async function downloadNativeBundle(port, platform, deadlineAt, log) {
  const url = nativeBundleUrl(port, platform);
  log(`fetching the ${platform} production bundle`);
  while (Date.now() < deadlineAt) {
    const controller = new AbortController();
    const budgetLeft = Math.max(1_000, deadlineAt - Date.now());
    const perAttempt = setTimeout(
      () => controller.abort(),
      Math.min(BUNDLE_FETCH_TIMEOUT_MS, budgetLeft),
    );
    try {
      const res = await fetch(url, { signal: controller.signal });
      const text = await res.text().catch(() => "");
      // A compiled bundle is a large JS blob. A non-200, an error page, or a
      // tiny body means Metro is still warming up / errored — retry.
      if (res.ok && text.length > 10_000) return text;
      log(`bundle not ready yet (status ${res.status}, ${text.length} bytes)`);
    } catch {
      // ERR / abort — Metro still compiling or transiently unavailable; retry.
    } finally {
      clearTimeout(perAttempt);
    }
    await new Promise((r) => setTimeout(r, 2_000));
  }
  return null;
}

// Build a manager bound to a caller-supplied `log`. Returns acquireNativeBundle
// (boot Metro + download, or read NATIVE_BUNDLE_FILE) plus teardown registered
// on process "exit" so a booted Metro child is always reaped, even on the
// skip()/fail() paths that process.exit() directly.
export function createNativeBundleManager(log) {
  const bootedChildren = new Set();
  function stopAllChildren() {
    for (const c of bootedChildren) stopExpo(c);
    bootedChildren.clear();
  }
  process.on("exit", stopAllChildren);

  // Acquire a compiled native bundle for `platform`. Returns
  // { bundleText, child } on success, or null when (best-effort) it couldn't be
  // produced in time — callers SKIP on null, never fail.
  async function acquireNativeBundle(platform) {
    const explicitFile = process.env.NATIVE_BUNDLE_FILE || null;
    if (explicitFile) {
      try {
        const bundleText = fs.readFileSync(explicitFile, "utf-8");
        log(`reusing pre-built bundle from ${explicitFile} (${bundleText.length} bytes)`);
        return { bundleText, child: null };
      } catch (e) {
        log(`could not read NATIVE_BUNDLE_FILE (${e?.message ?? e})`);
        return null;
      }
    }

    let port;
    try {
      port = await getFreePort();
    } catch (e) {
      log(`could not allocate a port (${e?.message ?? e})`);
      return null;
    }

    const child = spawnMetro(port, log);
    let spawnFailed = false;
    child.on("error", (e) => {
      spawnFailed = true;
      log(`expo failed to spawn (${e?.message ?? e})`);
    });
    bootedChildren.add(child);

    const deadlineAt = Date.now() + NATIVE_BUNDLE_DEADLINE_MS;
    const metroUp = await waitForExpoStatus(port, deadlineAt);
    if (spawnFailed || !metroUp) {
      log(
        `Metro didn't become ready within ` +
          `${Math.round(NATIVE_BUNDLE_DEADLINE_MS / 1000)}s`,
      );
      stopExpo(child);
      bootedChildren.delete(child);
      return null;
    }

    log("Metro is up; compiling the native bundle (this is the slow part)");
    const bundleText = await downloadNativeBundle(port, platform, deadlineAt, log);
    if (!bundleText) {
      log(
        `the ${platform} bundle didn't compile within ` +
          `${Math.round(NATIVE_BUNDLE_DEADLINE_MS / 1000)}s`,
      );
      stopExpo(child);
      bootedChildren.delete(child);
      return null;
    }

    log(`got the ${platform} bundle (${bundleText.length} bytes)`);
    return { bundleText, child };
  }

  return { acquireNativeBundle, stopAllChildren, stopExpo };
}

export { MOBILE_ROOT, WORKSPACE_ROOT };
