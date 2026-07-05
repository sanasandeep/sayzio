// Regression tests for the mobile Location block (map_location) editor.
//
// The block editor (app/links/[id]/blocks/[blockId].tsx) lets a creator edit a
// map_location block's address / lat / lng / label / zoom via the generic
// string `values` map, pick a point on a map (MapPickerModal), and toggle a
// "Directions" button. Several of these fields must stay in lockstep with the
// PUBLIC web renderer (artifacts/1inme/resources/views/common/blocks/
// map-location.blade.php) or the block silently stops rendering the pin or the
// Directions button:
//
//   - address / lat / lng / label / zoom ride the `{ ...values }` spread into
//     the save payload (they are plain strings).
//   - show_directions is persisted as its OWN boolean (the values map would
//     otherwise stringify it, and `!empty("false")` is true in PHP → the
//     button would never turn off).
//   - clearing the pin writes empty-string lat/lng so the renderer's
//     `is_numeric()` guard falls back to the address.
//
// Following the convention in test-block-cache.mjs / test-citation-href.mjs we
// avoid a full TS runner: the transforms are small and pure, so we model them
// here AND assert the real source wires them, so the model and the component
// can't drift. Run via `node scripts/test-map-location-block.mjs`
// (package script `test:map-location`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const editorSrc = readFileSync(
  join(root, "app", "links", "[id]", "blocks", "[blockId].tsx"),
  "utf8",
);
const bladeSrc = readFileSync(
  join(
    root,
    "..",
    "1inme",
    "resources",
    "views",
    "common",
    "blocks",
    "map-location.blade.php",
  ),
  "utf8",
);

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// Pure transforms — faithful copies of the editor's map_location logic.
// ---------------------------------------------------------------------------

// Hydrate the generic string `values` map from a block's settings, exactly
// like the editor's useEffect (only string/primitive values ride along;
// nested objects such as _style / _link are skipped).
function hydrateValues(settings) {
  const init = {};
  Object.entries(settings ?? {}).forEach(([k, v]) => {
    if (typeof v === "string") init[k] = v;
    else if (v != null && typeof v !== "object") init[k] = String(v);
  });
  return init;
}

// Hydrate the show_directions toggle. Default TRUE — a block saved before the
// field existed still shows the Directions button (mirrors the web default
// `$s['show_directions'] ?? true`). Only an explicit falsey value turns it off.
function hydrateShowDirections(settings) {
  const sd = settings?.show_directions;
  return !(sd === false || sd === 0 || sd === "0" || sd === "false");
}

// Build the map_location slice of the save payload: values spread + the
// boolean show_directions stamped on top.
function buildMapSave(values, mapShowDirections) {
  const nextSettings = { ...values };
  nextSettings.show_directions = mapShowDirections;
  return nextSettings;
}

// The MapPickerModal onPick handler: coords are stringified; the reverse-geocoded
// address only fills an EMPTY address field (never clobbers what the user typed).
function applyPick(prev, p) {
  return {
    ...prev,
    lat: String(p.lat),
    lng: String(p.lng),
    address: p.address && !prev.address?.trim() ? p.address : prev.address,
  };
}

// Clear-coords affordance.
function clearCoords(prev) {
  return { ...prev, lat: "", lng: "" };
}

// Loose model of PHP is_numeric for the renderer's lat/lng precedence guard:
// a non-empty numeric string is a real coordinate; "" / undefined is not.
function isNumericLike(v) {
  if (v === "" || v === null || v === undefined) return false;
  return Number.isFinite(Number(v));
}

// ===========================================================================
// 1. Hydration
// ===========================================================================
console.log("[test-map-location-block] hydration");

{
  const v = hydrateValues({
    address: "1600 Amphitheatre Parkway",
    lat: "37.4220",
    lng: "-122.0841",
    label: "HQ",
    zoom: 15, // numbers coerce to strings for the text field
    _style: { _variant: "x" }, // nested object must be skipped
    show_directions: true, // boolean is NOT a text field
  });
  assert.equal(v.address, "1600 Amphitheatre Parkway");
  assert.equal(v.lat, "37.4220");
  assert.equal(v.lng, "-122.0841");
  assert.equal(v.label, "HQ");
  assert.equal(v.zoom, "15", "numeric zoom coerces to a string for the field");
  assert.equal(v._style, undefined, "nested objects never leak into values");
  // A boolean is not an object, so the generic hydration stringifies it into
  // the values map as "true"/"false". This is HARMLESS only because the save
  // branch overwrites it with the real boolean (see round-trip test below) —
  // if that overwrite ever disappears, PHP's `!empty("false")` would keep the
  // Directions button permanently on.
  assert.equal(
    v.show_directions,
    "true",
    "a boolean rides into values as a STRING; the save branch must overwrite it",
  );
}
ok("values hydrate address/lat/lng/label/zoom as strings, skip nested objects");

// show_directions default-true unless an explicit falsey value is present.
assert.equal(hydrateShowDirections({}), true, "missing → true (legacy default)");
assert.equal(hydrateShowDirections({ show_directions: true }), true);
assert.equal(hydrateShowDirections({ show_directions: "1" }), true);
assert.equal(hydrateShowDirections({ show_directions: false }), false);
assert.equal(hydrateShowDirections({ show_directions: 0 }), false);
assert.equal(hydrateShowDirections({ show_directions: "0" }), false);
assert.equal(hydrateShowDirections({ show_directions: "false" }), false);
ok("show_directions hydrates true by default, off only on explicit falsey values");

// ===========================================================================
// 2. Save payload round-trip
// ===========================================================================
console.log("[test-map-location-block] save payload");

{
  const values = {
    address: "742 Evergreen Terrace",
    lat: "34.0522",
    lng: "-118.2437",
    label: "Studio",
    zoom: "13",
  };
  const out = buildMapSave(values, false);
  // Every text field must survive the save (the web renderer reads them all).
  assert.equal(out.address, "742 Evergreen Terrace");
  assert.equal(out.lat, "34.0522");
  assert.equal(out.lng, "-118.2437");
  assert.equal(out.label, "Studio");
  assert.equal(out.zoom, "13");
  // show_directions must be a real boolean, not a stringified one.
  assert.equal(out.show_directions, false);
  assert.equal(
    typeof out.show_directions,
    "boolean",
    "show_directions must save as a boolean so PHP !empty() can turn it off",
  );
}
ok("save carries address/lat/lng/label/zoom + a boolean show_directions");

// A round-trip (hydrate → save) preserves the directions default.
{
  const settings = { address: "somewhere", zoom: 15 };
  const values = hydrateValues(settings);
  const sd = hydrateShowDirections(settings);
  const saved = buildMapSave(values, sd);
  assert.equal(saved.show_directions, true, "legacy block keeps directions on");
  assert.equal(saved.address, "somewhere");
  assert.equal(saved.zoom, "15");
}
ok("hydrate → save round-trip keeps the legacy directions-on default");

// The save branch MUST overwrite the stringified show_directions from values
// with the real boolean — otherwise `!empty("false")` keeps the button on.
{
  const settings = { show_directions: false };
  const values = hydrateValues(settings);
  assert.equal(
    values.show_directions,
    "false",
    "values carries the stringified boolean",
  );
  const saved = buildMapSave(values, hydrateShowDirections(settings));
  assert.equal(saved.show_directions, false);
  assert.equal(
    typeof saved.show_directions,
    "boolean",
    "the save branch overwrites the stringified value with a real boolean",
  );
}
ok("turning directions OFF round-trips as a real boolean false (not the string \"false\")");

// ===========================================================================
// 3. Map picker + clear-coords behaviour
// ===========================================================================
console.log("[test-map-location-block] picker & clear");

{
  // Empty address gets filled by the reverse-geocoded name.
  const afterPick = applyPick(
    { address: "", lat: "", lng: "" },
    { lat: 40.7128, lng: -74.006, address: "New York, NY" },
  );
  assert.equal(afterPick.lat, "40.7128");
  assert.equal(afterPick.lng, "-74.006");
  assert.equal(afterPick.address, "New York, NY");
  assert.equal(typeof afterPick.lat, "string", "coords persist as strings");
}
ok("picking a point fills empty address + stringifies coords");

{
  // A user-typed address is never clobbered by the geocoder.
  const afterPick = applyPick(
    { address: "My cool spot", lat: "", lng: "" },
    { lat: 1, lng: 2, address: "Reverse geocoded name" },
  );
  assert.equal(afterPick.address, "My cool spot", "typed address is preserved");
  assert.equal(afterPick.lat, "1");
  assert.equal(afterPick.lng, "2");
}
ok("picking a point never overwrites a user-typed address");

{
  const cleared = clearCoords({ address: "still here", lat: "1", lng: "2" });
  assert.equal(cleared.lat, "");
  assert.equal(cleared.lng, "");
  assert.equal(cleared.address, "still here");
  // The renderer must then fall back to the address (empty coords aren't numeric).
  assert.equal(isNumericLike(cleared.lat), false);
  assert.equal(isNumericLike(cleared.lng), false);
}
ok("clearing coords blanks lat/lng so the renderer falls back to the address");

// lat/lng precedence: both numeric → pin wins; otherwise address is used.
assert.equal(isNumericLike("37.42") && isNumericLike("-122.08"), true);
assert.equal(isNumericLike("") && isNumericLike("-122.08"), false);
ok("both-coords-numeric gate matches the renderer's lat/lng precedence rule");

// ===========================================================================
// 4. Component wiring guards (mobile editor)
//
// The model above only matters if the real component actually runs it. These
// source assertions make sure the editor keeps the map_location branch, the
// values spread, the boolean stamp, the picker wiring and the clear affordance.
// ===========================================================================
console.log("[test-map-location-block] mobile editor wiring");

assert.ok(
  /const isMapLocation = block\?\.type === "map_location";/.test(editorSrc),
  "editor detects the map_location block type",
);
assert.ok(
  /const nextSettings: Record<string, unknown> = \{ \.\.\.values \};/.test(
    editorSrc,
  ),
  "save payload starts from the values spread (carries address/lat/lng/label/zoom)",
);
assert.ok(
  /if \(isMapLocation\) \{\s*nextSettings\.show_directions = mapShowDirections;/.test(
    editorSrc,
  ),
  "save stamps show_directions as a boolean under the isMapLocation branch",
);
assert.ok(
  /setMapShowDirections\(!\(sd === false \|\| sd === 0 \|\| sd === "0" \|\| sd === "false"\)\);/.test(
    editorSrc,
  ),
  "editor hydrates show_directions with the default-true rule",
);
assert.ok(
  /initialLat=\{values\.lat \? parseFloat\(values\.lat\) : null\}/.test(
    editorSrc,
  ) &&
    /initialLng=\{values\.lng \? parseFloat\(values\.lng\) : null\}/.test(
      editorSrc,
    ),
  "MapPickerModal is seeded from the current lat/lng",
);
assert.ok(
  /lat: String\(p\.lat\),\s*lng: String\(p\.lng\),\s*address: p\.address && !prev\.address\?\.trim\(\) \? p\.address : prev\.address,/.test(
    editorSrc,
  ),
  "onPick stringifies coords and only fills an empty address",
);
assert.ok(
  /setValues\(\(p\) => \(\{ \.\.\.p, lat: "", lng: "" \}\)\)/.test(editorSrc),
  "clear-coords affordance blanks lat/lng",
);
ok("editor keeps the map_location branch, values spread, boolean stamp, picker + clear wiring");

// ===========================================================================
// 5. Public renderer contract guard (web blade)
//
// The whole point of the shapes above is that the web public page renders
// them. Assert the renderer still reads the same keys with the same rules, so
// a change on either side that breaks the contract fails here.
// ===========================================================================
console.log("[test-map-location-block] web renderer contract");

assert.ok(
  /is_numeric\(\$s\['lat'\] \?\? null\)/.test(bladeSrc) &&
    /is_numeric\(\$s\['lng'\] \?\? null\)/.test(bladeSrc),
  "renderer gates lat/lng on is_numeric (empty string falls back to address)",
);
assert.ok(
  /\$s\['address'\]/.test(bladeSrc),
  "renderer reads the address",
);
assert.ok(
  /\$s\['zoom'\]/.test(bladeSrc),
  "renderer reads the zoom",
);
assert.ok(
  /\$s\['label'\]/.test(bladeSrc),
  "renderer reads the label",
);
assert.ok(
  /@if\(!empty\(\$s\['show_directions'\]\)\)/.test(bladeSrc),
  "renderer shows the Directions button only when show_directions is truthy",
);
ok("web renderer still reads address/lat/lng/zoom/label + show_directions the same way");

console.log(`\n[test-map-location-block] all ${passed} checks passed`);
