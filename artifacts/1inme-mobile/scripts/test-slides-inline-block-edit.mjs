// Regression tests for the mobile Slides surface's IN-PLACE block editing
// (Task #6391 — web/mobile parity with the web slides editor's chip edit).
//
// After merging with the full native slides editor (Task #6386),
// app/links/[id]/slides.tsx is the native deck editor (slide reorder,
// backgrounds, auto-play, attach/create blocks). This task's contribution:
// tapping an attached block row expands the shared BlockSettingsEditor
// inline beneath it (one open at a time); saves persist through the
// editor's existing PATCH /links/{id}/blocks/{blockId} call — no
// slides-specific write path.
//
// Source-driven checks (same convention as test-inline-block-edit.mjs):
// read the real files and assert on the wiring so a refactor that drops
// in-place editing from the native slides surface fails here.
//
// Run via `node scripts/test-slides-inline-block-edit.mjs` (package script
// `test:slides-inline-block-edit`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const slidesSrc = readFileSync(
  join(root, "app", "links", "[id]", "slides.tsx"),
  "utf8",
);
const editSrc = readFileSync(
  join(root, "app", "links", "[id]", "edit.tsx"),
  "utf8",
);
const apiSrc = readFileSync(join(root, "lib", "api", "slides.ts"), "utf8");

let passed = 0;
function ok(cond, label) {
  assert.ok(cond, label);
  passed += 1;
  console.log(`  ok — ${label}`);
}

console.log("[test-slides-inline-block-edit]");

// --- deck fetch --------------------------------------------------------

ok(
  /getSlideDeck\(/.test(apiSrc) && /\/links\/\$\{id\}\/slides/.test(apiSrc),
  "deck payload comes from GET /api/v1/links/{id}/slides",
);
ok(
  /queryKey: \["slides-deck", id\]/.test(slidesSrc) &&
    /getSlideDeck\(id\)/.test(slidesSrc),
  "slides screen fetches the deck via getSlideDeck",
);

// --- shared inline editor ----------------------------------------------

ok(
  /import \{ BlockSettingsEditor \} from "@\/app\/links\/\[id\]\/blocks\/\[blockId\]";/.test(
    slidesSrc,
  ),
  "slides screen imports the shared BlockSettingsEditor",
);
ok(
  /const \[expandedBlockId, setExpandedBlockId\] = useState<number \| null>\(null\);/.test(
    slidesSrc,
  ),
  "single expandedBlockId slot — one block's editor open at a time",
);
ok(
  /setExpandedBlockId\(expanded \? null : bid\)/.test(slidesSrc),
  "tapping an attached block row toggles its inline editor",
);
ok(
  /<BlockSettingsEditor\s+inline\s+linkId=\{id\}\s+blockId=\{bid\}/.test(slidesSrc),
  "expanded block renders the shared editor inline (saves via existing PATCH)",
);
ok(
  /setExpandedBlockId\(null\);[\s\S]{0,400}queryKey: \["slides-deck", id\]/.test(
    slidesSrc,
  ),
  "onDone collapses the editor and refreshes the deck payload",
);

// --- native deck editing (upstream full editor retained) ----------------

ok(
  /saveSlideDeck\(/.test(slidesSrc) && /detachBlock\(/.test(slidesSrc),
  "native deck editor's save + block detach are retained",
);

// --- entry point ---------------------------------------------------------

ok(
  /Edit slides/.test(editSrc) && /\/links\/\$\{id\}\/slides/.test(editSrc),
  "link edit screen routes slides decks to the native slides surface",
);
ok(
  /Full editor/.test(editSrc) && /\/user\/links\/\$\{id\}\/slides/.test(editSrc),
  "structural web editor stays reachable from the edit screen",
);

console.log(`\n${passed} checks passed`);
