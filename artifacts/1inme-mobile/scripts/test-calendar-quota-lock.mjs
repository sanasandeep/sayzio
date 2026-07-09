// Source-driven guard: the mobile calendar builder's proactive quota lock
// (`quotaLocked` in app/calendars/edit.tsx) must fail OPEN and only trip when
// the creator has truly used up their `max_calendars` allowance.
//
// Background: the calendar builder has TWO create-only proactive locks:
//   (a) a type lock (`isLinkTypeLocked("event")`) — the plan doesn't permit
//       the Calendar page type at all. That path is already guarded by
//       scripts/test-pairing-create-open.mjs.
//   (b) a QUOTA lock (`isQuotaReached("max_calendars", ownedCount)`) — the
//       plan DOES allow calendars but the creator already owns as many as the
//       finite cap permits. This file guards (b), which had no coverage.
//
// The risk this guards: a false positive on the quota lock would show the
// "You've reached your calendar limit" upgrade wall to a paying/free creator
// who is still entitled to create a calendar. It MUST fail open until both the
// plan data AND the owned-calendar count resolve, must ignore followed
// (is_owner=false) calendars, must treat an unlimited (-1) cap as never
// reached, and must never gate EDITING an existing calendar.
//
// Like the sibling tests we don't spin up a full TS/RN runner: we extract the
// REAL shipped logic from source and evaluate it verbatim, so this covers what
// actually ships rather than a hand-copied third implementation.
//   - `numericLimit` + `isQuotaReached` from hooks/usePlanFeatures.ts
//   - the `ownedCount` + `quotaLocked` derivation from app/calendars/edit.tsx
//
// Run via `node scripts/test-calendar-quota-lock.mjs` (package script
// `test:calendar-quota-lock`, chained into `test:unit`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { runExtractedStatements } from "./lib/extract.mjs";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const USE_PLAN_FEATURES_TS = join(root, "hooks", "usePlanFeatures.ts");
const CALENDAR_EDIT_TSX = join(root, "app", "calendars", "edit.tsx");

const read = (p) => readFileSync(p, "utf8");

// ---------------------------------------------------------------------------
// Extract a function body by balancing braces from the first "{".
// ---------------------------------------------------------------------------
function extractFn(src, signature, file) {
  const at = src.indexOf(signature);
  assert.notEqual(at, -1, `${signature} not found in ${file}`);
  const open = src.indexOf("{", at);
  let depth = 0, end = -1;
  for (let i = open; i < src.length; i++) {
    if (src[i] === "{") depth++;
    else if (src[i] === "}") { depth--; if (depth === 0) { end = i + 1; break; } }
  }
  assert.notEqual(end, -1, `could not balance braces for ${signature}`);
  return src.slice(at, end);
}

// ---------------------------------------------------------------------------
// 1. Build the REAL isQuotaReached from usePlanFeatures.ts.
//    `numericLimit` and `isQuotaReached` both read `featureMap`/`ready` from
//    the closure, so we inject them to exercise the shipped logic verbatim.
// ---------------------------------------------------------------------------
const planSrc = read(USE_PLAN_FEATURES_TS);
const numericSrc = extractFn(planSrc, "function numericLimit", "usePlanFeatures.ts")
  .replace("function numericLimit(key: string): number | null", "function numericLimit(key)");
const quotaSrc = extractFn(planSrc, "function isQuotaReached", "usePlanFeatures.ts")
  .replace("function isQuotaReached(key: string, used: number): boolean", "function isQuotaReached(key, used)");

// eslint-disable-next-line no-new-func
const makeIsQuotaReached = new Function(
  "ready",
  "featureMap",
  `${numericSrc}\n${quotaSrc}\n return isQuotaReached;`,
);

// ---------------------------------------------------------------------------
// 2. isQuotaReached behavior directly (the core "only when truly full" gate).
// ---------------------------------------------------------------------------
// (a) Fails OPEN until plan data resolves (ready=false), even if used > cap.
assert.equal(
  makeIsQuotaReached(false, { max_calendars: 1 })("max_calendars", 5),
  false,
  "isQuotaReached must fail OPEN (false) until plan data resolves (ready=false)",
);

// (b) Under a finite cap -> not reached.
const cap2 = makeIsQuotaReached(true, { max_calendars: 2 });
assert.equal(cap2("max_calendars", 0), false, "0 of 2 used must not be 'reached'");
assert.equal(cap2("max_calendars", 1), false, "1 of 2 used must not be 'reached'");

// (c) Cap met or exceeded -> reached (this is the ONLY true case).
assert.equal(cap2("max_calendars", 2), true, "2 of 2 used must be 'reached'");
assert.equal(cap2("max_calendars", 3), true, "3 of 2 used (over) must be 'reached'");

// (d) Unlimited (-1) -> never reached, no matter how many are owned.
assert.equal(
  makeIsQuotaReached(true, { max_calendars: -1 })("max_calendars", 999),
  false,
  "an unlimited (-1) cap must never report the quota as reached",
);

// (e) Plan doesn't declare the key (no cap to enforce) -> fails OPEN.
assert.equal(
  makeIsQuotaReached(true, {})("max_calendars", 999),
  false,
  "a missing cap key must fail OPEN (no cap to enforce)",
);

// (f) A finite cap of 0 (calendars not allowed at all) is reached immediately.
assert.equal(
  makeIsQuotaReached(true, { max_calendars: 0 })("max_calendars", 0),
  true,
  "a cap of 0 must report reached even at 0 owned",
);

// ---------------------------------------------------------------------------
// 3. Extract the REAL ownedCount + quotaLocked derivation from edit.tsx and
//    evaluate the shipped statements verbatim with injected inputs.
// ---------------------------------------------------------------------------
const editSrc = read(CALENDAR_EDIT_TSX);
const ownedCountStmt = (editSrc.match(/const ownedCount =[\s\S]*?;/) || [])[0];
assert.ok(ownedCountStmt, "ownedCount derivation not found in edit.tsx");
const quotaLockedStmt = (editSrc.match(/const quotaLocked =[\s\S]*?;/) || [])[0];
assert.ok(quotaLockedStmt, "quotaLocked derivation not found in edit.tsx");

// Guard the shape the derivation depends on so a refactor can't silently
// change WHAT is counted / WHEN the lock is allowed to trip.
assert.match(
  ownedCountStmt,
  /\.filter\(\(c\) => c\.is_owner\)/,
  "ownedCount must count only OWNED (is_owner) calendars",
);
assert.match(
  quotaLockedStmt,
  /!isEdit/,
  "quotaLocked must be create-only (guarded by !isEdit)",
);
assert.match(
  quotaLockedStmt,
  /calendarsQ\.isSuccess/,
  "quotaLocked must wait for the owned-calendar query to resolve (isSuccess)",
);
assert.match(
  quotaLockedStmt,
  /isQuotaReached\("max_calendars", ownedCount\)/,
  "quotaLocked must gate on isQuotaReached('max_calendars', ownedCount)",
);

// Evaluated via the shared resilient helper (scripts/lib/extract.mjs) so a
// NEW free variable in the derivation warns actionably instead of
// hard-crashing the mobile-unit chain with a raw ReferenceError.
const computeEdit = (isEdit, calendarsQ, plan) =>
  runExtractedStatements(
    `${ownedCountStmt}\n${quotaLockedStmt}`,
    "{ ownedCount, quotaLocked }",
    { isEdit, calendarsQ, plan },
    "ownedCount/quotaLocked",
    { test: "test-calendar-quota-lock" },
  );

// A finite cap of 2, plan data resolved.
const planFull = { isQuotaReached: makeIsQuotaReached(true, { max_calendars: 2 }) };

// (a) Followed (is_owner=false) calendars are EXCLUDED from the count. Here the
//     creator owns exactly 2 (the cap) plus 2 followed — only the owned ones
//     count, so the quota is legitimately reached.
const mixed = [
  { id: 1, is_owner: true },
  { id: 2, is_owner: true },
  { id: 3, is_owner: false },
  { id: 4, is_owner: false },
];
let r = computeEdit(false, { data: mixed, isSuccess: true }, planFull);
assert.equal(r.ownedCount, 2, "followed calendars must be excluded from ownedCount");
assert.equal(r.quotaLocked, true, "owning the full cap (2 of 2) must lock creation");

// (b) Under cap: 1 owned + 2 followed must NOT lock (followed can't push over).
const oneOwned = [
  { id: 1, is_owner: true },
  { id: 3, is_owner: false },
  { id: 4, is_owner: false },
];
r = computeEdit(false, { data: oneOwned, isSuccess: true }, planFull);
assert.equal(r.ownedCount, 1, "only the single owned calendar counts");
assert.equal(r.quotaLocked, false, "1 of 2 owned must NOT lock creation");

// (c) Editing an existing calendar is NEVER gated, even when at/over the cap.
r = computeEdit(true, { data: mixed, isSuccess: true }, planFull);
assert.equal(r.quotaLocked, false, "editing must never be quota-gated (create-only)");

// (d) Fails OPEN until the owned-calendar query resolves (isSuccess=false),
//     regardless of how many are owned.
r = computeEdit(false, { data: undefined, isSuccess: false }, planFull);
assert.equal(r.ownedCount, 0, "unresolved calendars query yields 0 owned");
assert.equal(r.quotaLocked, false, "must fail OPEN until the calendars query resolves");

// (e) Fails OPEN until PLAN data resolves, even with the query resolved and the
//     cap seemingly exceeded — isQuotaReached itself fails open when !ready.
const planNotReady = { isQuotaReached: makeIsQuotaReached(false, { max_calendars: 2 }) };
r = computeEdit(false, { data: mixed, isSuccess: true }, planNotReady);
assert.equal(r.quotaLocked, false, "must fail OPEN until plan data resolves");

// (f) Unlimited plan (-1) never locks, no matter how many are owned.
const planUnlimited = { isQuotaReached: makeIsQuotaReached(true, { max_calendars: -1 }) };
const manyOwned = Array.from({ length: 25 }, (_, i) => ({ id: i, is_owner: true }));
r = computeEdit(false, { data: manyOwned, isSuccess: true }, planUnlimited);
assert.equal(r.ownedCount, 25, "all owned calendars counted");
assert.equal(r.quotaLocked, false, "an unlimited plan must never lock creation");

console.log(
  "test-calendar-quota-lock: OK — calendar quota lock fails open and trips " +
    "only when owned calendars meet/exceed a finite cap.",
);
