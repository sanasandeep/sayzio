#!/usr/bin/env node
// Regression tests for the transient-environment error classifier that the
// icon-font / native icon-font / auth-flow e2e gates use to decide SKIP vs
// HARD-FAIL on throwaway servers (isTransientEnvError in expo-web-server.mjs).
//
// Task 4041 taught those gates to downgrade environmental slowness (Playwright
// TimeoutError, transient connection errors, starved-box resource errors) to
// SKIP so a starved CI box doesn't fail validation. The risk of any downgrade
// logic is OVER-skipping: if a genuine regression's error ever matched one of
// the transient signatures, CI would silently pass. This test pins the
// classifier's boundaries in both directions:
//
//   SKIP (transient, env-caused):
//     - Playwright TimeoutError (e.name === "TimeoutError")
//     - Playwright "Timeout 30000ms exceeded." step/nav messages
//     - net::ERR_CONNECTION_* / ERR_EMPTY_RESPONSE navigation errors
//     - starved-box errno codes (EMFILE, ENFILE, ENOMEM, EAGAIN, EBUSY,
//       ETIMEDOUT, ECONNRESET, ECONNREFUSED, EPIPE)
//
//   HARD-FAIL (deterministic, regression-shaped):
//     - ENOENT (a missing file is a real problem, e.g. a dropped glyph map)
//     - plain assertion Errors (the fail()/assert paths of the harnesses)
//     - withDeadline fail-fast messages ("... exceeded 90s — failing fast ...")
//       — these are OUR deadline, phrased deliberately NOT to match the
//       Playwright "Timeout <n>ms exceeded" signature
//     - non-Error junk (null, undefined, strings)
//
// Also runs the native gate end-to-end against a deliberately broken pre-built
// bundle via NATIVE_BUNDLE_FILE (no Metro compile — fast) and asserts it exits
// 1: an explicit-bundle run must NEVER be downgraded to SKIP.
//
// Run via `node scripts/test-transient-env-error.mjs` (package script
// `test:transient-env-error`, part of test:unit).

import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { isTransientEnvError } from "./expo-web-server.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

let passed = 0;
function check(name, fn) {
  fn();
  passed += 1;
  console.log(`  ok - ${name}`);
}

function errWithCode(code, message = `${code}: system hiccup`) {
  const e = new Error(message);
  e.code = code;
  return e;
}

console.log("[test-transient-env-error] classifier boundaries");

// --- transient (must SKIP) --------------------------------------------------

check("Playwright TimeoutError (by name) is transient", () => {
  const e = new Error("locator.waitFor: Timeout 15000ms exceeded.");
  e.name = "TimeoutError";
  assert.equal(isTransientEnvError(e), true);
});

check("Playwright 'Timeout <n>ms exceeded' message is transient", () => {
  assert.equal(
    isTransientEnvError(new Error("page.goto: Timeout 90000ms exceeded.")),
    true,
  );
});

for (const sig of [
  "net::ERR_CONNECTION_RESET at http://localhost:8081/",
  "net::ERR_EMPTY_RESPONSE at http://localhost:8081/",
  "net::ERR_CONNECTION_REFUSED at http://localhost:8081/",
  "net::ERR_CONNECTION_CLOSED at http://localhost:8081/",
  "net::ERR_NETWORK_CHANGED at http://localhost:8081/",
]) {
  check(`'${sig.split(" ")[0]}' navigation error is transient`, () => {
    assert.equal(isTransientEnvError(new Error(sig)), true);
  });
}

for (const code of [
  "EMFILE",
  "ENFILE",
  "ENOMEM",
  "EAGAIN",
  "EBUSY",
  "ETIMEDOUT",
  "ECONNRESET",
  "ECONNREFUSED",
  "EPIPE",
]) {
  check(`errno ${code} is transient`, () => {
    assert.equal(isTransientEnvError(errWithCode(code)), true);
  });
}

// --- deterministic (must HARD-FAIL) ------------------------------------------

check("ENOENT (missing file) is NOT transient", () => {
  assert.equal(
    isTransientEnvError(
      errWithCode(
        "ENOENT",
        "ENOENT: no such file or directory, open 'glyphmaps/Ionicons.json'",
      ),
    ),
    false,
  );
});

check("plain assertion Error is NOT transient", () => {
  assert.equal(
    isTransientEnvError(
      new Error("login social glyph 'logo-google' did not resolve"),
    ),
    false,
  );
});

check("node assert AssertionError is NOT transient", () => {
  let caught;
  try {
    assert.equal(1, 2);
  } catch (e) {
    caught = e;
  }
  assert.equal(isTransientEnvError(caught), false);
});

check("withDeadline fail-fast message is NOT transient", () => {
  // Exact phrasing produced by withDeadline() in test-auth-flow-e2e.mjs —
  // deliberately NOT of the Playwright "Timeout <n>ms exceeded" shape.
  assert.equal(
    isTransientEnvError(
      new Error("OTP login flow exceeded 90s — failing fast instead of hanging"),
    ),
    false,
  );
});

check("null / undefined / string junk is NOT transient", () => {
  assert.equal(isTransientEnvError(null), false);
  assert.equal(isTransientEnvError(undefined), false);
  assert.equal(isTransientEnvError({}), false);
  assert.equal(isTransientEnvError(new Error("")), false);
});

check("errno on a non-transient code with transient-looking prose stays hard", () => {
  // Only the whitelisted codes/messages downgrade; a random code doesn't.
  assert.equal(
    isTransientEnvError(errWithCode("EACCES", "permission denied")),
    false,
  );
});

// --- end-to-end: a real icon regression turns the native gate red ------------

console.log(
  "[test-transient-env-error] native gate vs deliberately broken bundle",
);

const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), "broken-native-bundle-"));
const brokenBundle = path.join(tmpDir, "broken-bundle.js");
// A "compiled bundle" that is missing everything the check requires: no
// embedded Ionicons.ttf/Feather.ttf assets, no registered font families.
fs.writeFileSync(
  brokenBundle,
  "// deliberately broken native bundle: no icon fonts here\n" +
    "console.log('hello');\n".repeat(50),
);

try {
  const res = spawnSync(
    process.execPath,
    [path.join(__dirname, "test-native-icon-fonts-e2e.mjs")],
    {
      env: { ...process.env, NATIVE_BUNDLE_FILE: brokenBundle },
      encoding: "utf-8",
      timeout: 60_000,
    },
  );
  const out = `${res.stdout ?? ""}${res.stderr ?? ""}`;
  assert.equal(
    res.status,
    1,
    `expected the native gate to exit 1 on a broken bundle, got ` +
      `${res.status}\n--- output ---\n${out}`,
  );
  assert.ok(
    !/SKIP:/.test(out),
    `the native gate must not SKIP an explicit-bundle regression\n${out}`,
  );
  passed += 2;
  console.log("  ok - broken NATIVE_BUNDLE_FILE run exits 1 (and never SKIPs)");
} finally {
  fs.rmSync(tmpDir, { recursive: true, force: true });
}

console.log(`[test-transient-env-error] PASS (${passed} assertions)`);
