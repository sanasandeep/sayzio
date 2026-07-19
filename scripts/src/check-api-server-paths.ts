/**
 * check-api-server-paths.ts
 *
 * Guard: keeps the three sources of truth about which paths Express natively
 * handles in lockstep:
 *
 *   1. artifacts/api-server/.replit-artifact/artifact.toml  (paths = [...])
 *   2. deploy/ec2/nginx/sayzio.conf                          (Express location blocks)
 *
 * Exits 1 if any of the following are true:
 *   - artifact.toml contains a path that shadows Laravel's /api/v1/* routes.
 *   - The Nginx config has a broad Express proxy location (e.g. `location /api`)
 *     that would swallow all Laravel traffic.
 *   - An artifact.toml path has no corresponding Nginx Express location block.
 *   - The Nginx config has an Express location for a path not in artifact.toml.
 */

import { readFileSync } from "node:fs";
import { resolve } from "node:path";

const TOML_PATH = resolve(
  import.meta.dirname,
  "../../artifacts/api-server/.replit-artifact/artifact.toml",
);

const NGINX_PATH = resolve(
  import.meta.dirname,
  "../../deploy/ec2/nginx/sayzio.conf",
);

// ---------------------------------------------------------------------------
// Unsafe prefix check (artifact.toml)
// ---------------------------------------------------------------------------
// Prefixes that would shadow Laravel's /api/v1/* routes.
const UNSAFE_PREFIXES = ["/api/v1", "/api/v", "/api"];

function isShadowing(path: string): boolean {
  return UNSAFE_PREFIXES.some(
    (unsafe) =>
      path === unsafe ||
      unsafe.startsWith(path + "/") ||
      path === "/api",
  );
}

// ---------------------------------------------------------------------------
// Read artifact.toml
// ---------------------------------------------------------------------------
let toml: string;
try {
  toml = readFileSync(TOML_PATH, "utf8");
} catch (err) {
  console.error(`check-api-server-paths: cannot read ${TOML_PATH}`, err);
  process.exit(1);
}

const pathsMatch = toml.match(/^paths\s*=\s*\[([^\]]*)\]/m);
if (!pathsMatch) {
  console.error(
    "check-api-server-paths: could not find `paths = [...]` in artifact.toml",
  );
  process.exit(1);
}

const rawPaths = pathsMatch[1];
const tomlPaths: string[] = [];
const itemRe = /"([^"]+)"/g;
let m: RegExpExecArray | null;
while ((m = itemRe.exec(rawPaths)) !== null) {
  tomlPaths.push(m[1]);
}

if (tomlPaths.length === 0) {
  console.error(
    "check-api-server-paths: `paths` array is empty — the api-server must claim at least /api/healthz",
  );
  process.exit(1);
}

// previewPath is ALSO routing-significant: the production edge router routes
// by the previewPath prefix even when [[services]].paths is narrow (verified
// July 2026 — previewPath = "/api" sent all /api/v1/* to Express in prod).
const previewMatch = toml.match(/^previewPath\s*=\s*"([^"]+)"/m);
if (!previewMatch) {
  console.error(
    "check-api-server-paths: could not find `previewPath = \"...\"` in artifact.toml",
  );
  process.exit(1);
}
const previewPath = previewMatch[1];
if (isShadowing(previewPath)) {
  console.error(
    [
      "check-api-server-paths: FAIL",
      "",
      `previewPath "${previewPath}" shadows Laravel's /api/v1/* routes in`,
      "production (the edge router routes by the previewPath prefix).",
      "",
      'Fix: pin it to a concrete Express endpoint, e.g. previewPath = "/api/healthz"',
    ].join("\n"),
  );
  process.exit(1);
}

// ---------------------------------------------------------------------------
// Unsafe-prefix check on artifact.toml paths
// ---------------------------------------------------------------------------
const shadowing = tomlPaths.filter(isShadowing);
if (shadowing.length > 0) {
  console.error(
    [
      "check-api-server-paths: FAIL — artifact.toml",
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

// ---------------------------------------------------------------------------
// Read and parse the Nginx config for Express proxy locations
// ---------------------------------------------------------------------------
let nginx: string;
try {
  nginx = readFileSync(NGINX_PATH, "utf8");
} catch (err) {
  console.error(`check-api-server-paths: cannot read ${NGINX_PATH}`, err);
  process.exit(1);
}

/**
 * Extract location paths that proxy to the Express server (127.0.0.1:8080).
 *
 * We parse `location [=] <path> { ... proxy_pass http://127.0.0.1:8080; ... }`
 * blocks. The regex captures the optional `=` modifier and the path.
 *
 * Returns an array of { path, exact } objects.
 */
function parseNginxExpressLocations(
  conf: string,
): Array<{ path: string; exact: boolean }> {
  const results: Array<{ path: string; exact: boolean }> = [];

  // Find all `location` blocks that contain `proxy_pass http://127.0.0.1:8080`
  // Strategy: scan for location headers, then grab the next { ... } block and
  // check if it proxies to :8080.
  const locationRe = /location\s+(=\s+)?(\S+)\s*\{/g;
  let lm: RegExpExecArray | null;

  while ((lm = locationRe.exec(conf)) !== null) {
    const exact = Boolean(lm[1]);
    const path = lm[2];
    const blockStart = lm.index + lm[0].length - 1; // position of opening {

    // Find the matching closing brace.
    let depth = 0;
    let blockEnd = -1;
    for (let i = blockStart; i < conf.length; i++) {
      if (conf[i] === "{") depth++;
      else if (conf[i] === "}") {
        depth--;
        if (depth === 0) {
          blockEnd = i;
          break;
        }
      }
    }

    if (blockEnd === -1) continue;
    const block = conf.slice(blockStart, blockEnd + 1);

    if (block.includes("proxy_pass http://127.0.0.1:8080")) {
      results.push({ path, exact });
    }
  }

  return results;
}

const nginxExpressLocations = parseNginxExpressLocations(nginx);

// ---------------------------------------------------------------------------
// Check for broad Express locations in Nginx (the original bug)
// ---------------------------------------------------------------------------
const broadNginx = nginxExpressLocations.filter((loc) => isShadowing(loc.path));
if (broadNginx.length > 0) {
  console.error(
    [
      "check-api-server-paths: FAIL — EC2 Nginx config",
      "",
      "The Nginx config has Express proxy location(s) that shadow Laravel's",
      "/api/v1/* routes:",
      "",
      ...broadNginx.map((loc) =>
        `  location ${loc.exact ? "= " : ""}${loc.path}  →  proxy_pass http://127.0.0.1:8080`,
      ),
      "",
      "Fix: replace the broad `location /api` block with specific locations",
      "matching only the Express-native paths, e.g.:",
      "  location = /api/healthz { proxy_pass http://127.0.0.1:8080; ... }",
      "  location /api/contact   { proxy_pass http://127.0.0.1:8080; ... }",
      "",
      `Nginx config: ${NGINX_PATH}`,
    ].join("\n"),
  );
  process.exit(1);
}

// ---------------------------------------------------------------------------
// Lockstep check: every artifact.toml path must have a Nginx Express location,
// and every Nginx Express location must correspond to an artifact.toml path.
// ---------------------------------------------------------------------------
const nginxPaths = nginxExpressLocations.map((loc) => loc.path);

// For each artifact.toml path P, there must be a Nginx location whose path
// is either exactly P, or is a prefix of P (e.g. "/api/contact" covers
// "/api/contact" in toml). We use a generous match: Nginx location path must
// be a prefix of the toml path (or equal).
function nginxCoversTomlPath(nginxPath: string, tomlPath: string): boolean {
  return tomlPath === nginxPath || tomlPath.startsWith(nginxPath + "/");
}

const tomlPathsMissingFromNginx = tomlPaths.filter(
  (tp) => !nginxPaths.some((np) => nginxCoversTomlPath(np, tp)),
);

if (tomlPathsMissingFromNginx.length > 0) {
  console.error(
    [
      "check-api-server-paths: FAIL — Nginx missing Express locations",
      "",
      "The following artifact.toml paths have no corresponding Express proxy",
      "location in the Nginx config:",
      "",
      ...tomlPathsMissingFromNginx.map((p) => `  "${p}"`),
      "",
      "Fix: add a `location` block for each missing path in:",
      `  ${NGINX_PATH}`,
    ].join("\n"),
  );
  process.exit(1);
}

// Every Nginx Express location must correspond to at least one artifact.toml path.
const nginxLocationsMissingFromToml = nginxPaths.filter(
  (np) => !tomlPaths.some((tp) => nginxCoversTomlPath(np, tp)),
);

if (nginxLocationsMissingFromToml.length > 0) {
  console.error(
    [
      "check-api-server-paths: FAIL — Nginx has Express locations not in artifact.toml",
      "",
      "The Nginx config proxies to Express for path(s) not listed in artifact.toml:",
      "",
      ...nginxLocationsMissingFromToml.map((p) => `  "${p}"`),
      "",
      "Fix: either add these paths to artifact.toml `paths = [...]`, or remove",
      "the corresponding Express location block(s) from the Nginx config.",
      "",
      `  artifact.toml: ${TOML_PATH}`,
      `  Nginx config:  ${NGINX_PATH}`,
    ].join("\n"),
  );
  process.exit(1);
}

// ---------------------------------------------------------------------------
// All checks passed
// ---------------------------------------------------------------------------
console.log(
  [
    "check-api-server-paths: OK",
    "",
    `  artifact.toml paths:   [${tomlPaths.map((p) => `"${p}"`).join(", ")}]`,
    `  Nginx Express locations: [${nginxPaths.map((p) => `"${p}"`).join(", ")}]`,
    "",
    "  — No unsafe broad /api match. — Lockstep verified.",
  ].join("\n"),
);
