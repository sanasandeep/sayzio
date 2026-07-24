// Source-driven regression test for the mobile dashboard/links load-failure
// fixes (web/app parity task):
//
//   1. The Home dashboard screen (app/(tabs)/index.tsx) must surface a REAL
//      error reason — a 401 gets the "session expired" copy via errorStatus(),
//      anything else appends the ApiError message — and must render a Retry
//      control wired to q.refetch(). The old UI was a bare "Couldn't load
//      dashboard." with no reason and no retry.
//   2. The Links tab (app/(tabs)/links.tsx) must have the same error branch
//      (401 copy + message + Retry → query.refetch()) instead of silently
//      rendering an empty list on failure.
//   3. WorkspaceContext.switchWorkspace must (a) call the server activate
//      endpoint (persists users.active_workspace_id, which now scopes the
//      API links list + dashboard and syncs the web session) and (b)
//      invalidate the "links" and "dashboard" query keys, so switching
//      workspaces refetches every workspace-scoped surface.
//
// Following the convention in test-workspace-switcher.mjs we assert on the
// shipped source so a refactor that drops any of these silently fails here.
//
// Run via `node scripts/test-links-error-state.mjs` (package script
// `test:links-error-state`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const homeSrc = readFileSync(join(root, "app", "(tabs)", "index.tsx"), "utf8");
const linksSrc = readFileSync(join(root, "app", "(tabs)", "links.tsx"), "utf8");
const wsCtxSrc = readFileSync(
  join(root, "contexts", "WorkspaceContext.tsx"),
  "utf8",
);

// ---------------------------------------------------------------------------
// 1. Dashboard screen: 401 copy, real error message, Retry → refetch.
// ---------------------------------------------------------------------------
assert.match(
  homeSrc,
  /import \{ errorStatus \} from "@\/lib\/api"/,
  "index.tsx must import errorStatus to distinguish auth failures",
);
assert.match(
  homeSrc,
  /errorStatus\(q\.error\) === 401/,
  "dashboard error branch must special-case 401",
);
assert.match(
  homeSrc,
  /session has expired/i,
  "401 must surface the session-expired copy",
);
assert.match(
  homeSrc,
  /Couldn't load dashboard\$\{/,
  "non-401 errors must append the real error message",
);
const homeRetry = homeSrc.match(
  /accessibilityLabel="Retry loading dashboard"[\s\S]{0,600}?Retry/,
);
assert.ok(homeRetry, "dashboard error state must render a Retry control");
assert.match(
  homeSrc,
  /onPress=\{\(\) => q\.refetch\(\)\}[\s\S]{0,200}accessibilityLabel="Retry loading dashboard"/,
  "dashboard Retry must call q.refetch()",
);

// ---------------------------------------------------------------------------
// 2. Links tab: same contract.
// ---------------------------------------------------------------------------
assert.match(
  linksSrc,
  /import \{ errorStatus \} from "@\/lib\/api"/,
  "links.tsx must import errorStatus",
);
assert.match(
  linksSrc,
  /query\.error \? \(/,
  "links.tsx must branch on query.error instead of showing an empty list",
);
assert.match(
  linksSrc,
  /errorStatus\(query\.error\) === 401/,
  "links error branch must special-case 401",
);
assert.match(
  linksSrc,
  /Couldn't load your links\$\{/,
  "non-401 link errors must append the real error message",
);
assert.match(
  linksSrc,
  /onPress=\{\(\) => query\.refetch\(\)\}[\s\S]{0,200}accessibilityLabel="Retry loading links"/,
  "links Retry must call query.refetch()",
);

// ---------------------------------------------------------------------------
// 3. Workspace switch: server activate + scoped-query invalidation.
// ---------------------------------------------------------------------------
const switchBody = wsCtxSrc.match(
  /const switchWorkspace = useCallback\(([\s\S]*?)\n  \);/,
);
assert.ok(switchBody, "could not locate switchWorkspace in WorkspaceContext.tsx");
const body = switchBody[1];
assert.match(
  body,
  /await apiSwitchWorkspace\(ws\.id\)/,
  "switchWorkspace must POST the server activate endpoint",
);
for (const key of ["workspaces-list", "links", "dashboard"]) {
  assert.match(
    body,
    new RegExp(`invalidateQueries\\(\\{ queryKey: \\["${key}"\\] \\}\\)`),
    `switchWorkspace must invalidate the "${key}" query key`,
  );
}

console.log("test-links-error-state: all assertions passed");
