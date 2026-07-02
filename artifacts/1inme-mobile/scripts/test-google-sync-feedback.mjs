// Regression test for the mobile Google Contacts sync feedback — the promise
// that every branch of the new sync status envelope produces the right
// user-facing Alert (and never a wrong/empty message).
//
// The sync endpoint now returns a status envelope
//   { status: "synced" | "throttled" | "in_progress", retry_after?, stats }
// with nullable stats (lib/api/contacts.ts: GoogleSyncResult). The screen
// (app/contacts/google-sync.tsx) branches on that envelope inside the sync
// mutation's onSuccess handler to pick the Alert title + body:
//
//   • in_progress → "Sync already running" (a sync is already running)
//   • throttled   → "Already up to date"   (retry_after seconds rendered)
//   • synced + stats → "Sync complete"     (created/updated/deleted/pushed …)
//   • synced + null stats → "Sync complete" ("Your contacts are up to date.")
//
// Nothing else asserts this mapping, so a future change to the envelope or the
// screen could silently regress the throttled / in_progress feedback and users
// would once again see wrong or empty messaging.
//
// Following the convention in test-contact-details.mjs / test-quick-contact.mjs
// we avoid a full TS/RN runner: we read the shipped source and reconstruct the
// REAL onSuccess handler from its exact source text, injecting a mock
// queryClient + Alert so we exercise what ships, not a re-implementation. We
// also pin the api client's sync() shape (raw {data} envelope → GoogleSyncResult)
// so the two halves of the contract stay in lockstep.
//
// Run via `node scripts/test-google-sync-feedback.mjs` (package script
// `test:google-sync`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const screenSrc = readFileSync(
  join(root, "app", "contacts", "google-sync.tsx"),
  "utf8",
);
const apiSrc = readFileSync(join(root, "lib", "api", "contacts.ts"), "utf8");

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// Pull the REAL `onSuccess: (r) => { ... }` handler out of the sync mutation
// and rebuild it as a callable function with injected `qc` + `Alert`. This
// tracks the shipped mapping, not a copy: if the branches or messages drift,
// these assertions fail.
// ---------------------------------------------------------------------------
function extractOnSuccessArrow(src) {
  const anchor = src.indexOf("const syncMut = useMutation(");
  if (anchor === -1) throw new Error("could not find syncMut in google-sync.tsx");
  const key = src.indexOf("onSuccess:", anchor);
  if (key === -1) throw new Error("could not find syncMut onSuccess");
  const arrowStart = src.indexOf("(r) =>", key);
  if (arrowStart === -1) throw new Error("could not find onSuccess arrow");
  const open = src.indexOf("{", arrowStart);
  // Walk to the matching closing brace of the arrow body (respecting string
  // and template literals so a `}` inside a `${...}` doesn't fool us).
  let depth = 0;
  let end = -1;
  let inStr = null; // '"' | "'" | "`"
  for (let i = open; i < src.length; i++) {
    const ch = src[i];
    const prev = src[i - 1];
    if (inStr) {
      if (ch === inStr && prev !== "\\") inStr = null;
      continue;
    }
    if (ch === '"' || ch === "'" || ch === "`") {
      inStr = ch;
      continue;
    }
    if (ch === "{") depth++;
    else if (ch === "}") {
      depth--;
      if (depth === 0) {
        end = i;
        break;
      }
    }
  }
  if (end === -1) throw new Error("unterminated onSuccess body");
  return src.slice(arrowStart, end + 1);
}

const onSuccessArrow = extractOnSuccessArrow(screenSrc);

// Sanity: the extracted source really is the sync feedback handler.
assert.ok(
  /r\.status === "in_progress"/.test(onSuccessArrow) &&
    /r\.status === "throttled"/.test(onSuccessArrow),
  "extracted onSuccess branches on the sync status envelope",
);

// Build a callable handler with injected dependencies. `qc` + `Alert` are free
// vars in the shipped closure, so they become factory params here.
// eslint-disable-next-line no-new-func
const makeHandler = new Function("qc", "Alert", `return ${onSuccessArrow};`);

function run(result) {
  const alerts = [];
  const invalidated = [];
  const qc = {
    invalidateQueries: (arg) => invalidated.push(arg),
  };
  const Alert = {
    alert: (title, body) => alerts.push({ title, body }),
  };
  const handler = makeHandler(qc, Alert);
  handler(result);
  return { alerts, invalidated };
}

const sampleAccount = {
  id: 1,
  account_email: "me@example.com",
  pull_enabled: true,
  push_enabled: true,
  last_sync_status: "ok",
  last_sync_error: null,
  last_synced_at: "2026-07-01T00:00:00Z",
};

// ===========================================================================
// 0. Every branch refreshes the cached status + contacts, so the UI reflects
//    the new state no matter which message it shows.
// ===========================================================================
{
  const { invalidated } = run({
    status: "synced",
    stats: null,
    account: sampleAccount,
  });
  const keys = invalidated.map((i) => JSON.stringify(i.queryKey));
  assert.ok(
    keys.includes('["google-contacts-status"]'),
    "onSuccess invalidates the google-contacts-status query",
  );
  assert.ok(
    keys.includes('["contacts"]'),
    "onSuccess invalidates the contacts query",
  );
}
ok("every sync result refreshes the status + contacts caches");

// ===========================================================================
// 1. in_progress → "Sync already running" (never falls through to a stats /
//    empty-stats message).
// ===========================================================================
{
  const { alerts } = run({
    status: "in_progress",
    stats: null,
    account: sampleAccount,
  });
  assert.equal(alerts.length, 1, "in_progress shows exactly one alert");
  assert.equal(
    alerts[0].title,
    "Sync already running",
    "in_progress uses the 'Sync already running' title",
  );
  assert.ok(
    /already in progress/i.test(alerts[0].body),
    "in_progress body tells the user a sync is already running",
  );
  assert.ok(
    !/Sync complete/.test(alerts[0].title),
    "in_progress never falls through to the 'Sync complete' path",
  );
}
ok("in_progress → 'Sync already running' (no fall-through)");

// ===========================================================================
// 2. throttled → "Already up to date" with the retry_after seconds rendered
//    (rounded, floored at 1), tolerant of a null/omitted retry_after.
// ===========================================================================
{
  // A concrete retry_after is rounded and rendered.
  {
    const { alerts } = run({
      status: "throttled",
      retry_after: 42.4,
      stats: null,
      account: sampleAccount,
    });
    assert.equal(alerts.length, 1, "throttled shows exactly one alert");
    assert.equal(
      alerts[0].title,
      "Already up to date",
      "throttled uses the 'Already up to date' title",
    );
    assert.ok(
      /Try again in 42s\./.test(alerts[0].body),
      `throttled renders the retry_after seconds (got: ${alerts[0].body})`,
    );
  }
  // retry_after rounds up (42.6 → 43).
  {
    const { alerts } = run({
      status: "throttled",
      retry_after: 42.6,
      stats: null,
      account: sampleAccount,
    });
    assert.ok(
      /Try again in 43s\./.test(alerts[0].body),
      "throttled rounds the retry_after seconds",
    );
  }
  // Null retry_after is floored to a sensible 1s (never "in 0s" / NaN).
  {
    const { alerts } = run({
      status: "throttled",
      retry_after: null,
      stats: null,
      account: sampleAccount,
    });
    assert.ok(
      /Try again in 1s\./.test(alerts[0].body),
      "a null retry_after floors to 1s (never 0s / NaN)",
    );
  }
  // Omitted retry_after behaves the same (floored to 1s).
  {
    const { alerts } = run({
      status: "throttled",
      stats: null,
      account: sampleAccount,
    });
    assert.ok(
      /Try again in 1s\./.test(alerts[0].body),
      "an omitted retry_after floors to 1s",
    );
  }
}
ok("throttled → 'Already up to date' with rounded, floored retry_after");

// ===========================================================================
// 3. synced with stats → "Sync complete" itemising created/updated/deleted/
//    pushed, with the optional skipped-cap + error clauses appearing only when
//    those counts are non-zero.
// ===========================================================================
{
  // Full stats with skipped cap + errors → both optional clauses present.
  {
    const { alerts } = run({
      status: "synced",
      stats: {
        created: 3,
        updated: 2,
        deleted: 1,
        pushed: 4,
        errors: 2,
        skipped_capped: 5,
      },
      account: sampleAccount,
    });
    assert.equal(alerts.length, 1, "synced+stats shows exactly one alert");
    assert.equal(alerts[0].title, "Sync complete", "synced uses 'Sync complete'");
    assert.ok(
      /Created 3, updated 2, deleted 1, pushed 4/.test(alerts[0].body),
      `synced body itemises the counts (got: ${alerts[0].body})`,
    );
    assert.ok(
      /5 skipped \(plan cap\)/.test(alerts[0].body),
      "a non-zero skipped_capped renders the plan-cap clause",
    );
    assert.ok(
      /2 error\(s\)/.test(alerts[0].body),
      "a non-zero errors count renders the error clause",
    );
    assert.ok(alerts[0].body.endsWith("."), "the summary ends with a period");
  }
  // Zero skipped_capped + zero errors → neither optional clause appears.
  {
    const { alerts } = run({
      status: "synced",
      stats: {
        created: 1,
        updated: 0,
        deleted: 0,
        pushed: 0,
        errors: 0,
        skipped_capped: 0,
      },
      account: sampleAccount,
    });
    assert.ok(
      /Created 1, updated 0, deleted 0, pushed 0\./.test(alerts[0].body),
      "clean sync itemises counts and ends with a period",
    );
    assert.ok(
      !/skipped/.test(alerts[0].body),
      "no skipped-cap clause when skipped_capped is 0",
    );
    assert.ok(
      !/error/.test(alerts[0].body),
      "no error clause when errors is 0",
    );
  }
}
ok("synced+stats → 'Sync complete' with optional cap/error clauses");

// ===========================================================================
// 4. synced with NULL stats → "Sync complete" / "Your contacts are up to date."
//    (the nullable-stats branch never crashes reading s.created).
// ===========================================================================
{
  const { alerts } = run({
    status: "synced",
    stats: null,
    account: sampleAccount,
  });
  assert.equal(alerts.length, 1, "synced with null stats shows one alert");
  assert.equal(
    alerts[0].title,
    "Sync complete",
    "null-stats success still says 'Sync complete'",
  );
  assert.equal(
    alerts[0].body,
    "Your contacts are up to date.",
    "null stats shows the generic up-to-date message (no crash on s.created)",
  );
}
ok("synced+null stats → 'Sync complete' / 'Your contacts are up to date.'");

// ===========================================================================
// 5. API client contract — sync() posts to the sync endpoint and returns the
//    raw {data} envelope (GoogleSyncResult), matching what the screen consumes.
// ===========================================================================
assert.ok(
  /export type GoogleSyncStatus = "synced" \| "throttled" \| "in_progress";/.test(
    apiSrc,
  ),
  "GoogleSyncStatus enumerates exactly synced / throttled / in_progress",
);
assert.ok(
  /retry_after\?: number \| null;/.test(apiSrc),
  "GoogleSyncResult carries a nullable retry_after",
);
assert.ok(
  /stats: GoogleSyncStats \| null;/.test(apiSrc),
  "GoogleSyncResult carries nullable stats",
);
assert.ok(
  /sync: async \(\): Promise<GoogleSyncResult> => \{[\s\S]*?apiFetch<\{ data: GoogleSyncResult \}>\([\s\S]*?\/contacts\/google\/sync[\s\S]*?method: "POST",[\s\S]*?return res\.data;/.test(
    apiSrc,
  ),
  "sync() POSTs /contacts/google/sync and returns the raw {data} envelope",
);
ok("api client sync() matches the status-envelope contract the screen consumes");

console.log(`\n[test-google-sync-feedback] all ${passed} checks passed`);
