// Regression tests for the mobile biolink editor's INLINE block editing.
//
// Mirrors the web editor's inline/expand pattern (Task: bring inline block
// editing to mobile): tapping a block row in app/links/[id]/blocks/index.tsx
// expands its settings in place (one open at a time, tap again to collapse)
// instead of pushing the full-screen /links/[id]/blocks/[blockId] route.
// The full-screen route still exists (deep links, back-compat) and both
// surfaces share ONE editor implementation — BlockSettingsEditor exported
// from app/links/[id]/blocks/[blockId].tsx.
//
// Following the source-driven convention in test-block-cache.mjs, these
// checks read the real source files and assert on the wiring, so a refactor
// that silently reverts to the modal/full-screen-only flow fails here.
//
// Run via `node scripts/test-inline-block-edit.mjs` (package script
// `test:inline-block-edit`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const indexSrc = readFileSync(
  join(root, "app", "links", "[id]", "blocks", "index.tsx"),
  "utf8",
);
const editorSrc = readFileSync(
  join(root, "app", "links", "[id]", "blocks", "[blockId].tsx"),
  "utf8",
);

let passed = 0;
function ok(cond, label) {
  assert.ok(cond, label);
  passed += 1;
  console.log(`  ok — ${label}`);
}

console.log("[test-inline-block-edit]");

// --- shared editor component --------------------------------------------

ok(
  /export function BlockSettingsEditor\(/.test(editorSrc),
  "editor is exported as a reusable BlockSettingsEditor component",
);
ok(
  /inline\s*=\s*false/.test(editorSrc) && /onDone\?:\s*\(\)\s*=>\s*void/.test(editorSrc),
  "BlockSettingsEditor accepts inline + onDone props",
);
ok(
  /export default function EditBlockScreen\(\)/.test(editorSrc) &&
    /useLocalSearchParams/.test(editorSrc) &&
    /<BlockSettingsEditor[\s\S]{0,200}linkId=\{Number\(idParam\)\}/.test(editorSrc),
  "full-screen route wrapper still exists and delegates to BlockSettingsEditor",
);

// Save must collapse the inline panel (onDone) instead of always popping
// the navigation stack; the screen path keeps router.back().
ok(
  /if \(onDone\) onDone\(\);\s*\n\s*else router\.back\(\);/.test(editorSrc),
  "save success calls onDone in inline mode, router.back() on the screen",
);

// Inline mode must not nest a ScrollView inside the list's ScrollView.
ok(
  /\{inline \? \(\s*\/\/[^\n]*\n[^\n]*\n\s*<View style=\{styles\.bodyInline\}>\{body\}<\/View>\s*\) : \(\s*<ScrollView contentContainerStyle=\{styles\.body\}>\{body\}<\/ScrollView>/.test(
    editorSrc,
  ),
  "inline mode renders a plain View; screen mode keeps its own ScrollView",
);
ok(
  /\{inline \? null : \(\s*<Stack\.Screen/.test(editorSrc),
  "inline mode skips the Stack.Screen header chrome",
);

// --- blocks list wiring ----------------------------------------------------

ok(
  /import \{ BlockSettingsEditor \} from "@\/app\/links\/\[id\]\/blocks\/\[blockId\]";/.test(
    indexSrc,
  ),
  "blocks list imports the shared BlockSettingsEditor",
);
ok(
  /const \[expandedId, setExpandedId\] = useState<number \| null>\(null\);/.test(
    indexSrc,
  ),
  "single expandedId slot — only one block's settings open at a time",
);
ok(
  /setExpandedId\(\(cur\) => \(cur === b\.id \? null : b\.id\)\)/.test(indexSrc),
  "tapping a row toggles expand/collapse for that block",
);
ok(
  !/router\.push\(`\/links\/\$\{id\}\/blocks\/\$\{b\.id\}`/.test(indexSrc),
  "row tap no longer pushes the full-screen block route",
);
ok(
  /<BlockSettingsEditor\s+inline\s+linkId=\{id\}\s+blockId=\{b\.id\}\s+onDone=\{\(\) => setExpandedId\(null\)\}/.test(
    indexSrc,
  ),
  "expanded row renders the inline editor; saving collapses it",
);
ok(
  /\{isExpanded \? \(/.test(indexSrc),
  "inline editor renders only for the expanded block",
);

// Creating a new block expands it inline instead of navigating away.
ok(
  /setHighlightId\(b\.id\);[\s\S]{0,220}setExpandedId\(b\.id\);/.test(indexSrc),
  "newly created blocks open their settings inline",
);

// Deleting the expanded block collapses the lingering editor.
ok(
  /setExpandedId\(\(cur\) => \(cur === blockId \? null : cur\)\);/.test(indexSrc),
  "deleting the expanded block collapses its inline editor",
);

// The list renders ALL blocks (card children included) through the same
// row map — one flat `order.map`, so child blocks inside Card containers
// get the identical expand-in-place behaviour.
ok(
  /order\.map\(\(b, i\) =>/.test(indexSrc) &&
    !/order\.filter\(\(?b\)? ?=> ?!b\.parent_id\)/.test(indexSrc),
  "card children flow through the same row map (same inline behaviour)",
);

console.log(`\n[test-inline-block-edit] all ${passed} checks passed`);
