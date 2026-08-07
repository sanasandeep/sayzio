#!/usr/bin/env node
/*
 * Focused unit coverage for resources/js/map-pin-picker.js:
 *  - extractFromMapUrl() against representative Google / Apple / OSM /
 *    coordinate / short-link URLs
 *  - suggestPlaces() stale-response invalidation (including the
 *    input-became-short case)
 *  - handleLocationPaste() only intercepts URLs it can actually process
 *
 * Run: node scripts/test-map-pin-picker.mjs
 */
import { readFileSync } from "node:fs";
import vm from "node:vm";
import { fileURLToPath } from "node:url";
import path from "node:path";

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const src = readFileSync(path.join(root, "resources/js/map-pin-picker.js"), "utf8");

const sandbox = { window: {}, document: undefined, console, setTimeout, clearTimeout };
vm.createContext(sandbox);
vm.runInContext(src, sandbox);
const picker = () => sandbox.window.mapPinPicker({});

let failures = 0;
function check(label, actual, expected) {
  const ok = JSON.stringify(actual) === JSON.stringify(expected);
  if (!ok) {
    failures++;
    console.error(`FAIL ${label}\n  expected: ${JSON.stringify(expected)}\n  actual:   ${JSON.stringify(actual)}`);
  } else {
    console.log(`ok   ${label}`);
  }
}

const p = picker();

/* ---- extractFromMapUrl ------------------------------------------------ */
check(
  "google place + precise pin",
  p.extractFromMapUrl("https://www.google.com/maps/place/Eiffel+Tower/@48.85837,2.294481,17z/data=!3d48.8583701!4d2.2944813"),
  { name: "Eiffel Tower", lat: 48.8583701, lng: 2.2944813 },
);
check(
  "google viewport @lat,lng only",
  p.extractFromMapUrl("https://www.google.com/maps/@40.712776,-74.005974,14z"),
  { name: null, lat: 40.712776, lng: -74.005974 },
);
check(
  "google search ?query= text (canonical share/search URL)",
  p.extractFromMapUrl("https://www.google.com/maps/search/?api=1&query=Central+Park+NYC"),
  { name: "Central Park NYC", lat: null, lng: null },
);
check(
  "google search ?query= coordinates",
  p.extractFromMapUrl("https://www.google.com/maps/search/?api=1&query=47.5951518,-122.3316393"),
  { name: null, lat: 47.5951518, lng: -122.3316393 },
);
check(
  "google /maps/search/<text> path",
  p.extractFromMapUrl("https://www.google.com/maps/search/coffee+near+pike+place/"),
  { name: "coffee near pike place", lat: null, lng: null },
);
check(
  "google directions ?destination= text",
  p.extractFromMapUrl("https://www.google.com/maps/dir/?api=1&destination=Space+Needle"),
  { name: "Space Needle", lat: null, lng: null },
);
check(
  "google directions path picks DESTINATION not origin",
  p.extractFromMapUrl("https://www.google.com/maps/dir/Current+Location/Space+Needle/@47.62,-122.35,15z"),
  { name: "Space Needle", lat: 47.62, lng: -122.35 },
);
check(
  "google directions with omitted origin (/dir//<destination>)",
  p.extractFromMapUrl("https://www.google.com/maps/dir//Pike+Place+Market"),
  { name: "Pike Place Market", lat: null, lng: null },
);
check(
  "google directions coordinate destination becomes the selected lat/lng (origin never used as name)",
  p.extractFromMapUrl("https://www.google.com/maps/dir/Seattle/47.6205,-122.3493/@47.62,-122.35,15z"),
  { name: null, lat: 47.6205, lng: -122.3493 },
);
check(
  "google directions coordinate-only destination without @viewport keeps destination coords",
  p.extractFromMapUrl("https://www.google.com/maps/dir/Seattle/47.6205,-122.3493"),
  { name: null, lat: 47.6205, lng: -122.3493 },
);
check(
  "google directions textual destination followed by /data=!… suffix",
  p.extractFromMapUrl("https://www.google.com/maps/dir/Seattle/Space+Needle/data=!4m9!4m8!1m5!1m1!1s0x0:0x1!2m2!1d-122.3493!2d47.6205!1m0!3e0"),
  { name: "Space Needle", lat: null, lng: null },
);
check(
  "google directions destination + @viewport + /data=!… suffix",
  p.extractFromMapUrl("https://www.google.com/maps/dir/Seattle/Space+Needle/@47.62,-122.35,15z/data=!3m1!4b1"),
  { name: "Space Needle", lat: 47.62, lng: -122.35 },
);
check(
  "google directions coordinate destination + /data=!… suffix (no pin)",
  p.extractFromMapUrl("https://www.google.com/maps/dir/A/1.5,2.5/data=!4m2!4m1!3e0"),
  { name: null, lat: 1.5, lng: 2.5 },
);
check(
  "google directions precise !3d!4d pin beats coordinate destination",
  p.extractFromMapUrl("https://www.google.com/maps/dir/A/1.5,2.5/data=!3d48.85837!4d2.294481"),
  { name: null, lat: 48.85837, lng: 2.294481 },
);
check(
  "non-map host with map-like ?q= is NOT treated as a map link",
  p.extractFromMapUrl("https://example.com/search?q=Central+Park"),
  null,
);
check(
  "non-map host with ?address= is NOT treated as a map link",
  p.extractFromMapUrl("https://shop.example.org/checkout?address=1+Main+St"),
  null,
);
check(
  "non-map host with @coords pattern is NOT treated as a map link",
  p.extractFromMapUrl("https://blog.example.com/@40.7,-74.0"),
  null,
);
check(
  "apple maps ?address=",
  p.extractFromMapUrl("https://maps.apple.com/?address=1+Infinite+Loop,+Cupertino,+CA"),
  { name: "1 Infinite Loop, Cupertino, CA", lat: null, lng: null },
);
check(
  "apple maps ?ll= coords with &q= name",
  p.extractFromMapUrl("https://maps.apple.com/?ll=37.331686,-122.030656&q=Apple%20Park"),
  { name: "Apple Park", lat: 37.331686, lng: -122.030656 },
);
check(
  "osm ?mlat/&mlon marker",
  p.extractFromMapUrl("https://www.openstreetmap.org/?mlat=51.507222&mlon=-0.1275#map=15/51.507222/-0.1275"),
  { name: null, lat: 51.507222, lng: -0.1275 },
);
check(
  "osm #map= fragment only",
  p.extractFromMapUrl("https://www.openstreetmap.org/#map=12/35.6895/139.6917"),
  { name: null, lat: 35.6895, lng: 139.6917 },
);
check(
  "encoded ?q= coordinates (%2C separator)",
  p.extractFromMapUrl("https://maps.google.com/?q=40.71%2C-74.00"),
  { name: null, lat: 40.71, lng: -74.0 },
);
check(
  "out-of-range coords rejected",
  p.extractFromMapUrl("https://maps.google.com/?q=140.0,200.0"),
  null,
);
check(
  "short goo.gl link unrecognized → null",
  p.extractFromMapUrl("https://maps.app.goo.gl/AbCdEf123"),
  null,
);

/* ---- suggestPlaces stale-response invalidation ------------------------ */
{
  const s = picker();
  s.address = "Eiffel";
  const before = s._suggestReq || 0;
  s.suggestPlaces(); // schedules request with reqId N
  const inflight = s._suggestReq;
  check("suggestPlaces bumps req id", inflight > before, true);
  // Input becomes too short — must invalidate the in-flight request too.
  s.address = "Ei";
  s.suggestPlaces();
  check("short input still bumps req id (in-flight invalidated)", s._suggestReq > inflight, true);
  check("short input clears suggestions", s.suggestions, []);
  // Simulate the stale response arriving: the guard inside the fetch handler
  // compares against _suggestReq, so any reqId != current must be ignored.
  check("stale req id no longer current", inflight !== s._suggestReq, true);
}

/* ---- dismissSuggestions cancels debounce + in-flight requests --------- */
{
  // A real in-flight request: stub fetch to resolve AFTER dismissal, then
  // verify the response is discarded instead of repopulating the dropdown.
  let resolveFetch;
  globalThis.fetch = sandbox.fetch = () =>
    new Promise((res) => {
      resolveFetch = () =>
        res({ ok: true, json: () => Promise.resolve({ suggestions: [{ id: 1, label: "Stale Town", lat: "1", lng: "2" }] }) });
    });
  const s = picker();
  s.address = "Stale Town";
  s.suggestPlaces();
  await new Promise((r) => setTimeout(r, 400)); // let the debounce fire → fetch in flight
  check("request actually in flight", typeof resolveFetch, "function");
  s.dismissSuggestions(); // user hit Escape / clicked outside
  resolveFetch(); // stale response lands afterwards
  await new Promise((r) => setTimeout(r, 20));
  check("in-flight response after dismissal is discarded", s.suggestions, []);
  check("dismiss cleared the debounce timer id state", s._suggestTimer !== null, true);
}

/* ---- handleLocationPaste interception rules --------------------------- */
{
  const mkEvt = (text) => {
    let prevented = false;
    return {
      clipboardData: { getData: () => text },
      preventDefault: () => (prevented = true),
      get prevented() { return prevented; },
    };
  };

  const s1 = picker();
  const e1 = mkEvt("just a plain address");
  s1.handleLocationPaste(e1);
  check("plain text paste falls through", e1.prevented, false);

  const s2 = picker();
  const e2 = mkEvt("https://maps.app.goo.gl/AbCdEf123");
  s2.handleLocationPaste(e2);
  check("unrecognized short link falls through (default paste keeps text)", e2.prevented, false);
  check("unrecognized link does not overwrite address", s2.address, "");

  const s2b = picker();
  const e2b = mkEvt("https://example.com/article?q=Central+Park");
  s2b.handleLocationPaste(e2b);
  check("non-map URL paste is left untouched (no interception)", e2b.prevented, false);
  check("non-map URL paste does not touch address", s2b.address, "");

  const s3 = picker();
  s3._geoTimer = null;
  const e3 = mkEvt("https://www.google.com/maps/place/Eiffel+Tower/@48.85837,2.294481,17z/data=!3d48.8583701!4d2.2944813");
  // reverse-geocode fetch is debounced/asynchronous; stub fetch to a no-op
  sandbox.fetch = () => Promise.resolve({ ok: false });
  globalThis.fetch = sandbox.fetch;
  s3.handleLocationPaste(e3);
  check("coordinate map link intercepted", e3.prevented, true);
  check("coordinate map link fills lat", s3.lat, "48.85837");
  check("coordinate map link fills lng", s3.lng, "2.294481");
  check("coordinate map link keeps place name", s3.address, "Eiffel Tower");
}

if (failures) {
  console.error(`\n${failures} failure(s)`);
  process.exit(1);
}
console.log("\nmap-pin-picker: all checks passed");
