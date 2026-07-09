// Source-driven guard for the mobile per-calendar event-limit gate — the
// proactive lock in app/calendars/event.tsx that stops a creator from filling
// in the whole "New event" form only to be bounced by the server 402 when a
// calendar has already used its plan's `max_calendar_events` allowance.
//
// The shipped gate (app/calendars/event.tsx) is exactly:
//
//   const eventsUsed = calQ.data?.calendar.events_count ?? 0;
//   const createLocked =
//     !isEdit && plan.isQuotaReached("max_calendar_events", eventsUsed);
//
// backed by usePlanFeatures' `isQuotaReached` / `numericLimit` (hooks/
// usePlanFeatures.ts). The server counts events PER calendar
// (`$cal->events()->count() >= $cap`, CalendarController::store), so the mobile
// gate compares the plan cap against THIS calendar's `events_count`.
//
// The risk this guards (the reason the task exists): a future change to the
// plan-data shape (`max_calendar_events` key / `features_map`) or the calendar
// payload (`events_count`) could silently regress the gate — either FALSELY
// blocking a creator who is below cap, or FAILING TO WARN one who is at/over
// cap (so they only discover the limit at submit). This test extracts the REAL
// shipped logic (no re-implemented third copy) and exercises it, following the
// convention in test-upgrade-hint.mjs / test-pairing-create-open.mjs.
//
// Run via `node scripts/test-calendar-event-quota.mjs` (package script
// `test:calendar-event-quota`, chained into `test:unit`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { runExtractedStatements } from "./lib/extract.mjs";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const planSrc = readFileSync(join(root, "hooks", "usePlanFeatures.ts"), "utf8");
const eventSrc = readFileSync(
  join(root, "app", "calendars", "event.tsx"),
  "utf8",
);

// Balance braces from the first "{" after `signature` to pull a whole function
// body out of source (same helper shape used by the sibling tests).
function extractFn(src, signature, file) {
  const at = src.indexOf(signature);
  assert.notEqual(at, -1, `${signature} not found in ${file}`);
  const open = src.indexOf("{", at);
  let depth = 0,
    end = -1;
  for (let i = open; i < src.length; i++) {
    if (src[i] === "{") depth++;
    else if (src[i] === "}") {
      depth--;
      if (depth === 0) {
        end = i + 1;
        break;
      }
    }
  }
  assert.notEqual(end, -1, `could not balance braces for ${signature}`);
  return src.slice(at, end);
}

// ---------------------------------------------------------------------------
// 1. The REAL usePlanFeatures gate: numericLimit + isQuotaReached, with
//    `ready` / `featureMap` injected from the closure so we run the shipped
//    bodies verbatim (not a paraphrase).
// ---------------------------------------------------------------------------
const numericLimitSrc = extractFn(
  planSrc,
  "function numericLimit",
  "usePlanFeatures.ts",
).replace("function numericLimit(key: string): number | null", "function numericLimit(key)");
const isQuotaReachedSrc = extractFn(
  planSrc,
  "function isQuotaReached",
  "usePlanFeatures.ts",
).replace(
  "function isQuotaReached(key: string, used: number): boolean",
  "function isQuotaReached(key, used)",
);

// eslint-disable-next-line no-new-func
const makeQuotaGate = new Function(
  "ready",
  "featureMap",
  `${numericLimitSrc}\n${isQuotaReachedSrc}\n return { numericLimit, isQuotaReached };`,
);

// ---------------------------------------------------------------------------
// 2. The REAL createLocked expression from event.tsx, extracted from source so
//    a change to the plan-data key (`max_calendar_events`) or the calendar
//    payload shape (`calQ.data.calendar.events_count`) trips this test.
// ---------------------------------------------------------------------------
const eventsUsedLine = (eventSrc.match(
  /const eventsUsed = [^;]+;/,
) || [])[0];
assert.ok(eventsUsedLine, "`const eventsUsed = ...` not found in event.tsx");
assert.match(
  eventsUsedLine,
  /calQ\.data\?\.calendar\.events_count \?\? 0/,
  "eventsUsed must read `calQ.data?.calendar.events_count ?? 0` — the calendar " +
    "payload shape changed; the per-calendar event gate would read the wrong count.",
);

const createLockedExpr = (eventSrc.match(
  /const createLocked =\s*[^;]+;/,
) || [])[0];
assert.ok(createLockedExpr, "`const createLocked = ...` not found in event.tsx");
assert.match(
  createLockedExpr,
  /!isEdit && plan\.isQuotaReached\("max_calendar_events", eventsUsed\)/,
  "createLocked must be `!isEdit && plan.isQuotaReached(\"max_calendar_events\", " +
    "eventsUsed)` — the gate key or edit-exemption changed.",
);

// Rebuild the shipped gate: inject `calQ`, `plan`, `isEdit` and run the exact
// two source statements so we exercise the real expression, not a copy —
// via the shared resilient helper (scripts/lib/extract.mjs) so a NEW free
// variable warns actionably instead of hard-crashing with a ReferenceError.
const computeCreateLocked = (calQ, plan, isEdit) =>
  runExtractedStatements(
    `${eventsUsedLine}\n${createLockedExpr}`,
    "createLocked",
    { calQ, plan, isEdit },
    "createLocked",
    { test: "test-calendar-event-quota" },
  );

// Helper: build the `calQ` shape the screen sees for a calendar with `n` events.
const calQFor = (n) => ({ data: { calendar: { events_count: n } } });

// ---------------------------------------------------------------------------
// 3. Scenarios (the acceptance criteria).
// ---------------------------------------------------------------------------

// A finite cap of 3, plan data resolved.
const cap3 = makeQuotaGate(true, { max_calendar_events: 3 });
assert.equal(cap3.numericLimit("max_calendar_events"), 3, "cap parsed as 3");

// (a) At the cap (used == cap) → creating a NEW event is locked.
assert.equal(
  computeCreateLocked(calQFor(3), cap3, false),
  true,
  "at the finite cap, creating a new event must be locked",
);
// Over the cap (used > cap) → still locked.
assert.equal(
  computeCreateLocked(calQFor(5), cap3, false),
  true,
  "over the finite cap, creating a new event must be locked",
);

// (b) Below the cap → NOT locked.
assert.equal(
  computeCreateLocked(calQFor(2), cap3, false),
  false,
  "below the finite cap, creating a new event must NOT be locked",
);
assert.equal(
  computeCreateLocked(calQFor(0), cap3, false),
  false,
  "an empty calendar (0 used) below cap must NOT be locked",
);

// (c) Unlimited cap (-1) → NEVER locked, even with many events.
const unlimited = makeQuotaGate(true, { max_calendar_events: -1 });
assert.equal(
  computeCreateLocked(calQFor(9999), unlimited, false),
  false,
  "an unlimited (-1) cap must never lock creation",
);

// (d) Editing an existing event is NEVER locked — even at/over the cap, since
//     an edit adds no new event.
assert.equal(
  computeCreateLocked(calQFor(3), cap3, true),
  false,
  "editing at the cap must not be locked",
);
assert.equal(
  computeCreateLocked(calQFor(50), cap3, true),
  false,
  "editing over the cap must not be locked",
);

// (e) Fail OPEN when plan/count is unresolved:
//   - plan data not ready yet → isQuotaReached returns false → not locked.
const notReady = makeQuotaGate(false, { max_calendar_events: 3 });
assert.equal(
  computeCreateLocked(calQFor(3), notReady, false),
  false,
  "unresolved plan data (ready=false) must fail open — no false barrier",
);
//   - plan resolved but the key is absent (unknown cap) → not locked.
const noKey = makeQuotaGate(true, {});
assert.equal(
  noKey.numericLimit("max_calendar_events"),
  null,
  "absent cap key resolves to null",
);
assert.equal(
  computeCreateLocked(calQFor(3), noKey, false),
  false,
  "an unknown cap (key absent from the plan) must fail open",
);
//   - calendar not loaded yet → events_count defaults to 0 → not locked.
assert.equal(
  computeCreateLocked({ data: undefined }, cap3, false),
  false,
  "before the calendar loads, count defaults to 0 and creation is not locked",
);

// (f) A cap of 0 (link type disabled at the event level) locks immediately —
//     the boundary case of a finite cap with an empty calendar.
const cap0 = makeQuotaGate(true, { max_calendar_events: 0 });
assert.equal(
  computeCreateLocked(calQFor(0), cap0, false),
  true,
  "a 0 cap must lock creation even on an empty calendar",
);

console.log(
  "test-calendar-event-quota: OK — per-calendar event-limit gate locks at/over " +
    "a finite cap, stays open below cap / unlimited / editing / unresolved data.",
);
