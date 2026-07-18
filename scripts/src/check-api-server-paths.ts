/**
 * check-api-server-paths.ts
 *
 * Guard: asserts that the api-server's claimed proxy paths do NOT include a
 * broad "/api" or "/api/v1" prefix. A broad claim swallows all Laravel
 * "/api/v1/*" traffic in production, causing 502s because the Express
 * fallthrough proxy cannot reach localhost:5000 in a published deployment.
 *
 * Exits 1 if the paths are dangerously wide.
 */

import { readFileSync } from "node:fs";
import { resolve } from "node:path";

const TOML_PATH = resolve(
  import.meta.dirname,
  "../../artifacts/api-server/.replit-artifact/artifact.toml",
);

// Prefixes that would shadow Laravel's /api/v1/* routes.
// Any path that is a prefix of "/api/v1" (or equals it) is unsafe.
const UNSAFE_PREFIXES = ["/api/v1", "/api/v", "/api"];

function isShadowing(path: string): boolean {
  return UNSAFE_PREFIXES.some(
    (unsafe) =>
      // The claimed path equals the unsafe prefix, or is an ancestor of it.
      // e.g. "/api" shadows "/api/v1"; "/api/v1" shadows "/api/v1/auth".
      path === unsafe ||
      unsafe.startsWith(path + "/") ||
      path === "/api",
  );
}

let toml: string;
try {
  toml = readFileSync(TOML_PATH, "utf8");
} catch (err) {
  console.error(`check-api-server-paths: cannot read ${TOML_PATH}`, err);
  process.exit(1);
}

// Extract the paths = [...] line(s) from the [[services]] block.
// We use a simple regex — the TOML is machine-written and stable.
const pathsMatch = toml.match(/^paths\s*=\s*\[([^\]]*)\]/m);
if (!pathsMatch) {
  console.error(
    "check-api-server-paths: could not find `paths = [...]` in artifact.toml",
  );
  process.exit(1);
}

const rawPaths = pathsMatch[1];
// Parse the quoted strings out of the array literal.
const paths: string[] = [];
const itemRe = /"([^"]+)"/g;
let m: RegExpExecArray | null;
while ((m = itemRe.exec(rawPaths)) !== null) {
  paths.push(m[1]);
}

if (paths.length === 0) {
  console.error(
    "check-api-server-paths: `paths` array is empty — the api-server must claim at least /api/healthz",
  );
  process.exit(1);
}

const shadowing = paths.filter(isShadowing);
if (shadowing.length > 0) {
  console.error(
    [
      "check-api-server-paths: FAIL",
      "",
      "The api-server artifact.toml claims path(s) that shadow Laravel's",
      "/api/v1/* routes:",
      "",
      ...shadowing.map((p) => `  "${p}"`),
      "",
      "In production the Express fallthrough proxy cannot reach localhost:5000,",
      "so every /api/v1/* request from the mobile app returns 502.",
      "",
      "Fix: narrow the paths to only what Express natively handles, e.g.:",
      '  paths = ["/api/healthz", "/api/contact"]',
    ].join("\n"),
  );
  process.exit(1);
}

console.log(
  `check-api-server-paths: OK — claimed paths [${paths.map((p) => `"${p}"`).join(", ")}] do not shadow /api/v1`,
);
