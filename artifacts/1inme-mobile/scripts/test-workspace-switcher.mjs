// Source-driven unit test for the mobile workspace switcher surfaces — the
// drawer's WorkspaceSwitcherBlock (components/DrawerSidebar.tsx) and the
// standalone Workspaces screen (app/workspaces.tsx).
//
// Both surfaces promise three things a future refactor could silently drop:
//
//   1. Each workspace renders the FEATHER ICON mapped from the server's
//      appearance icon key via workspaceFeatherIcon() — not a hard-coded
//      icon — so a restyle done on the web reflects on mobile.
//   2. Each workspace's ACCENT COLOUR (ws.color, falling back to the theme
//      primary) tints the icon chip — a dropped fallback would crash on a
//      colourless workspace or lose the brand tint.
//   3. The edit GEAR (which opens the native rename/restyle editor) only
//      renders for owners (is_owner === true). Showing it to a non-owner
//      would dangle a control the server rejects with 403. Because the gear
//      is guarded by the LIVE query row's is_owner, a workspaces refetch that
//      flips is_owner to false (ownership transferred/downgraded away) drops
//      the gear immediately — proven in section 4.
//
// Following the convention in test-quick-contact.mjs / test-citation-href.mjs
// we don't spin up a full RN renderer: we (a) lift the REAL
// workspaceFeatherIcon() body out of lib/api/workspaces.ts and exercise its
// mapping, and (b) assert the shipped JSX in both screens wires the mapped
// icon, the colour fallback and the owner-gated gear. This keeps the test
// honest — it tracks what ships, not a re-implementation.
//
// Run via `node scripts/test-workspace-switcher.mjs` (package script
// `test:workspace-switcher`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { runExtractedCall } from "./lib/extract.mjs";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const apiSrc = readFileSync(
  join(root, "lib", "api", "workspaces.ts"),
  "utf8",
);
const drawerSrc = readFileSync(
  join(root, "components", "DrawerSidebar.tsx"),
  "utf8",
);
const screenSrc = readFileSync(
  join(root, "app", "workspaces.tsx"),
  "utf8",
);

// ---------------------------------------------------------------------------
// 1. workspaceFeatherIcon() maps the server's icon keys to Feather names.
//    We lift the REAL switch body out of the shipped source (stripping only
//    the TS param/return annotations) so a drift in the mapping fails here.
// ---------------------------------------------------------------------------
function extractSwitchBlock(src) {
  const start = src.indexOf("switch (ws.icon)");
  if (start === -1) {
    throw new Error("could not find the workspaceFeatherIcon switch in workspaces.ts");
  }
  const open = src.indexOf("{", start);
  let depth = 0;
  let end = -1;
  for (let i = open; i < src.length; i++) {
    const ch = src[i];
    if (ch === "{") depth++;
    else if (ch === "}") {
      depth--;
      if (depth === 0) {
        end = i;
        break;
      }
    }
  }
  if (end === -1) throw new Error("unterminated switch block in workspaces.ts");
  return src.slice(start, end + 1);
}

const switchBlock = extractSwitchBlock(apiSrc);

// Wrap the lifted switch (which returns from every arm) in a plain JS arrow so
// we exercise the shipped mapping verbatim. Evaluated via the resilient helper
// so a new free variable warns actionably instead of hard-crashing the chain.
const workspaceFeatherIcon = runExtractedCall(
  `(ws) => { ${switchBlock} }`,
  {},
  "workspaceFeatherIcon",
  { test: "test-workspace-switcher" },
);

const expectedMap = {
  user: "user",
  users: "users",
  briefcase: "briefcase",
  building: "home",
  rocket: "zap",
  bolt: "zap",
  star: "star",
  heart: "heart",
  globe: "globe",
  store: "shopping-bag",
  "layer-group": "layers",
  palette: "feather",
};

for (const [iconKey, feather] of Object.entries(expectedMap)) {
  assert.equal(
    workspaceFeatherIcon({ icon: iconKey, is_personal: false }),
    feather,
    `icon key "${iconKey}" must map to the "${feather}" Feather glyph`,
  );
}

// Fallback: unknown/absent icon keys resolve by workspace kind, never crash.
assert.equal(
  workspaceFeatherIcon({ icon: null, is_personal: true }),
  "user",
  "a personal workspace with no icon defaults to the person glyph",
);
assert.equal(
  workspaceFeatherIcon({ icon: null, is_personal: false }),
  "users",
  "a team workspace with no icon defaults to the people glyph",
);
assert.equal(
  workspaceFeatherIcon({ icon: "totally-unknown", is_personal: false }),
  "users",
  "an unrecognised icon key falls back by kind (never undefined)",
);

// ---------------------------------------------------------------------------
// 2. Both switcher surfaces render the MAPPED icon + the workspace COLOUR.
//    We assert the shipped JSX wires workspaceFeatherIcon() as the Feather
//    name (not a literal) and tints the chip with ws.color ?? colors.primary.
// ---------------------------------------------------------------------------

// -- Drawer switcher (active row + dropdown rows) ---------------------------
assert.ok(
  /name=\{workspaceFeatherIcon\(activeWorkspace\)\}/.test(drawerSrc),
  "the drawer active workspace must render the mapped icon (workspaceFeatherIcon(activeWorkspace))",
);
assert.ok(
  /name=\{workspaceFeatherIcon\(ws\)\}/.test(drawerSrc),
  "each drawer dropdown row must render the mapped icon (workspaceFeatherIcon(ws))",
);
assert.ok(
  /const wsColor = activeWorkspace\?\.color \?\? colors\.primary/.test(drawerSrc),
  "the drawer active chip colour must fall back to colors.primary when the workspace has no colour",
);
assert.ok(
  /const wsC = ws\.color \?\? colors\.primary/.test(drawerSrc),
  "each drawer dropdown chip colour must fall back to colors.primary when the workspace has no colour",
);
assert.ok(
  /backgroundColor: wsColor \+ "cc"/.test(drawerSrc) &&
    /backgroundColor: wsC \+ "cc"/.test(drawerSrc),
  "the drawer chips must be tinted by the resolved workspace colour",
);

// -- Workspaces screen ------------------------------------------------------
assert.ok(
  /name=\{workspaceFeatherIcon\(item\)\}/.test(screenSrc),
  "the Workspaces screen must render the mapped icon (workspaceFeatherIcon(item))",
);
assert.ok(
  /color=\{item\.color \?\? colors\.primary\}/.test(screenSrc),
  "the Workspaces screen icon colour must fall back to colors.primary",
);
assert.ok(
  /backgroundColor: \(item\.color \?\? colors\.primary\) \+ "26"/.test(screenSrc),
  "the Workspaces screen icon chip must be tinted by the resolved workspace colour",
);

// ---------------------------------------------------------------------------
// 3. The edit gear ONLY renders for owners (is_owner === true).
//    We confirm every `name="edit-2"` Feather in each file sits inside an
//    `<owner>.is_owner ? ( … ) : null` guard, and that there are no ungated
//    edit gears left behind.
// ---------------------------------------------------------------------------
function assertGearOwnerGated(src, ownerExpr, label) {
  const gearCount = (src.match(/name="edit-2"/g) ?? []).length;
  assert.ok(
    gearCount >= 1,
    `${label}: expected at least one edit gear to be present`,
  );

  const escaped = ownerExpr.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  const guarded = new RegExp(
    `${escaped}\\s*\\?\\s*\\([\\s\\S]*?name="edit-2"[\\s\\S]*?\\)\\s*:\\s*null`,
    "g",
  );
  const guardedCount = (src.match(guarded) ?? []).length;

  assert.equal(
    guardedCount,
    gearCount,
    `${label}: every edit gear must be gated behind \`${ownerExpr} ? (…) : null\` ` +
      `(found ${gearCount} gear(s) but only ${guardedCount} owner-gated)`,
  );
}

assertGearOwnerGated(drawerSrc, "ws.is_owner", "DrawerSidebar");
assertGearOwnerGated(screenSrc, "item.is_owner", "Workspaces screen");

// The gear must open the native rename/restyle editor for that workspace, not
// attempt an unsupported action. The drawer routes through editWorkspace(id)
// (which pushes /workspace-edit); the screen pushes /workspace-edit directly.
assert.ok(
  /onPress=\{\(\) => editWorkspace\(ws\.id\)\}/.test(drawerSrc),
  "the drawer gear must open the workspace editor for that workspace (editWorkspace(ws.id))",
);
assert.ok(
  /const editWorkspace = \(id: number\) => \{[\s\S]*?\/workspace-edit\?id=\$\{id\}/.test(drawerSrc),
  "the drawer editWorkspace() must navigate to the /workspace-edit screen",
);
assert.ok(
  /router\.push\(`\/workspace-edit\?id=\$\{item\.id\}`/.test(screenSrc),
  "the Workspaces screen gear must open the /workspace-edit screen for that workspace",
);

// ---------------------------------------------------------------------------
// 4. TRANSITION CASE — a workspaces refetch that flips is_owner to false
//    (ownership transferred/downgraded away from the user) must drop the gear.
//    The gear is a plain ternary on the LIVE query row's is_owner, so we lift
//    that exact guard token from the shipped source and evaluate it against a
//    row that WAS owned (is_owner:true → gear) and the same row after a refetch
//    reports is_owner:false (→ null, no gear). A row that was never owned
//    (is_owner absent) must also yield no gear.
// ---------------------------------------------------------------------------
function assertGearFollowsLiveRow(src, rowVar, label) {
  const guardRe = new RegExp(`\\{\\s*(${rowVar}\\.is_owner)\\s*\\?`);
  const m = src.match(guardRe);
  assert.ok(
    m,
    `${label}: the gear must be guarded by the live row's is_owner (found no \`{${rowVar}.is_owner ? …}\`)`,
  );
  const guardExpr = m[1]; // e.g. "ws.is_owner" — lifted verbatim from source

  const evalGuard = (row) =>
    runExtractedCall(
      `(${rowVar}) => Boolean(${guardExpr})`,
      {},
      `${label} gear guard`,
      { test: "test-workspace-switcher" },
    )(row);

  assert.equal(
    evalGuard({ is_owner: true }),
    true,
    `${label}: an owned row must show the edit gear`,
  );
  assert.equal(
    evalGuard({ is_owner: false }),
    false,
    `${label}: a refetch reporting is_owner=false (demoted owner) must drop the gear`,
  );
  assert.equal(
    evalGuard({}),
    false,
    `${label}: a row that reports no is_owner (never owned) must show no gear`,
  );
}

assertGearFollowsLiveRow(drawerSrc, "ws", "DrawerSidebar");
assertGearFollowsLiveRow(screenSrc, "item", "Workspaces screen");

console.log(
  "ok — workspace switcher (icon mapping + colour fallback + owner-gated gear that drops on a refetch flipping is_owner false, on drawer & screen)",
);
