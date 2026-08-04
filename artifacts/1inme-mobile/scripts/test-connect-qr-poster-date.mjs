// Source-driven test for the Connect QR poster date line (Task #6693).
//
// The printable poster must show the event time IN THE EVENT'S TIMEZONE,
// not the device's — a Berlin event printed from a New York phone must say
// 7:00 PM (Europe/Berlin), never 1:00 PM. This lifts the REAL
// `posterDateLine` out of the shipped screen (via `// [extract:...]`
// markers) and exercises it with a device timezone that deliberately
// differs from the event timezone (process.env.TZ is pinned before any
// Intl use).
//
// Run via `node scripts/test-connect-qr-poster-date.mjs` (package script
// `test:connect-qr-poster-date`).

// Pin the "device" timezone BEFORE anything touches Intl/Date formatting.
process.env.TZ = "America/New_York";

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { runExtractedStatements } from "./lib/extract.mjs";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const screenSrc = readFileSync(
  join(root, "app", "events", "connect-qr", "[linkId].tsx"),
  "utf8",
);

let passed = 0;
function ok(cond, label) {
  assert.ok(cond, label);
  passed += 1;
  console.log(`  ok — ${label}`);
}

// Slice the source between the exact extract markers.
function lift(name) {
  const start = `// [extract:${name}:start]`;
  const end = `// [extract:${name}:end]`;
  const a = screenSrc.indexOf(start);
  const b = screenSrc.indexOf(end);
  assert.ok(a !== -1 && b !== -1 && b > a, `extract markers for ${name} exist`);
  return screenSrc.slice(a + start.length, b);
}

// Strip the (deliberately simple) TS annotations so the lifted block
// evaluates as plain JS. Extend explicitly if the screen's annotations grow.
function stripTypes(src) {
  return src
    .replace(/\(\): string \| null =>/g, "() =>")
    .replace(/\(opts: Intl\.DateTimeFormatOptions, timeZone\?: string\)/g, "(opts, timeZone)")
    .replace(/const dateOpts: Intl\.DateTimeFormatOptions =/g, "const dateOpts =")
    .replace(/const timeOpts: Intl\.DateTimeFormatOptions =/g, "const timeOpts =");
}

const liftedSrc = stripTypes(lift("posterDateLine"));

function dateLineFor(event) {
  const data = event === undefined ? undefined : { event };
  return runExtractedStatements(
    liftedSrc,
    "posterDateLine()",
    { data },
    "posterDateLine",
    { test: "test-connect-qr-poster-date" },
  );
}

console.log("[test-connect-qr-poster-date] (device TZ pinned to America/New_York)");

// ---- timed event in a DIFFERENT timezone than the device --------------
// 2030-06-01 17:00 UTC = 19:00 in Berlin (CEST) but 13:00 in New York.
{
  const line = dateLineFor({
    name: "Berlin Meetup",
    start_date: "2030-06-01T17:00:00+00:00",
    all_day: false,
    timezone: "Europe/Berlin",
    location: "Berlin",
  });
  ok(typeof line === "string" && line.includes("(Europe/Berlin)"),
    "timed event carries the event-timezone label");
  ok(/7:00\sPM/.test(line), `time is rendered in the EVENT timezone (got: ${line})`);
  ok(!/1:00\sPM/.test(line), "device-local (New York) time never leaks onto the poster");
  ok(line.includes("June 1, 2030"), "date part renders in the event timezone too");
}

// ---- date can differ across zones: late-night UTC event ---------------
// 2030-01-02 00:30 Tokyo = Jan 1 in New York; the poster must say Jan 2.
{
  const line = dateLineFor({
    name: "Tokyo Countdown",
    start_date: "2030-01-01T15:30:00+00:00", // 00:30 Jan 2 in Asia/Tokyo
    all_day: false,
    timezone: "Asia/Tokyo",
    location: null,
  });
  ok(line.includes("January 2, 2030"),
    `calendar DATE follows the event timezone across midnight (got: ${line})`);
}

// ---- all-day event: date only, no time, still event-zone anchored -----
{
  const line = dateLineFor({
    name: "All Day",
    start_date: "2030-06-01T00:00:00+02:00",
    all_day: true,
    timezone: "Europe/Berlin",
    location: null,
  });
  ok(line.includes("June 1, 2030") && !/\d:\d\d/.test(line),
    "all-day event renders the date only");
}

// ---- unknown zone id: device-local fallback WITHOUT the zone label ----
{
  const line = dateLineFor({
    name: "Weird Zone",
    start_date: "2030-06-01T17:00:00+00:00",
    all_day: false,
    timezone: "Not/A_Zone",
    location: null,
  });
  ok(typeof line === "string" && !line.includes("Not/A_Zone"),
    "invalid zone id falls back without a misleading zone label");
  ok(/1:00\sPM/.test(line), "fallback renders device-local time");
}

// ---- missing/invalid inputs ------------------------------------------
{
  ok(dateLineFor({ name: "No date", start_date: null, all_day: false, timezone: null }) === null,
    "no start_date → null (poster omits the date line)");
  ok(dateLineFor({ name: "Bad date", start_date: "garbage", all_day: false, timezone: null }) === null,
    "unparseable start_date → null");
  ok(dateLineFor(undefined) === null, "no data yet → null");
}

// ---- no timezone provided: device-local, no label ----------------------
{
  const line = dateLineFor({
    name: "Zoneless",
    start_date: "2030-06-01T17:00:00+00:00",
    all_day: false,
    timezone: null,
  });
  ok(/1:00\sPM/.test(line) && !line.includes("("),
    "timezone-less event renders device-local with no zone label");
}

console.log(`[test-connect-qr-poster-date] PASS (${passed} checks)`);
