// Per-side borders: mobile ⇄ web parity check (Task: confirm per-side
// borders render identically on the phone app and the public web page).
//
// Task #6074 added per-side border editing + instant preview to the Expo
// app, mirroring the server's `BiolinkBlock::buildInlineStyle` fallback
// rules: each side falls back to the shorthand field-by-field, and a side
// is visible only when its resolved style != none AND resolved width > 0.
// React Native supports a single `borderStyle`, approximated from the
// first visible side.
//
// This harness is source-driven end-to-end across BOTH stacks:
//   1. Lifts the REAL save-merge statements from the mobile block editor
//      (`app/links/[id]/blocks/[blockId].tsx`) and runs them against a
//      mixed per-side scenario → the persisted `_style` payload.
//   2. Round-trips that payload through the REAL PHP
//      `BlockStyleSanitizer::sanitize()` (vendor autoload, no app boot)
//      and asserts nothing is dropped or mangled.
//   3. Feeds the sanitized style to the REAL PHP
//      `BiolinkBlock::buildInlineStyle()` → the public web page's CSS.
//   4. Feeds the same sanitized style to the REAL mobile renderer math
//      (`lib/blockVariants.ts` `variantOverlay`, transpiled with the
//      workspace TypeScript compiler) → the RN card border props.
//   5. Asserts side-by-side that web CSS and RN props agree for every
//      side: inherit-from-shorthand, hidden via style=none, hidden via
//      width=0, and an explicit per-side override.
//
// Run via `node scripts/test-side-borders-parity.mjs` (package script
// `test:side-borders-parity`). Requires `php` (present in this repo's
// toolchain — the Laravel app ships alongside).

import assert from "node:assert/strict";
import { execFileSync } from "node:child_process";
import { mkdtempSync, readFileSync, writeFileSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import { fileURLToPath, pathToFileURL } from "node:url";
import { dirname, join } from "node:path";
import { createRequire } from "node:module";
import { runExtractedStatements } from "./lib/extract.mjs";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");
const laravelRoot = join(root, "..", "1inme");
const require_ = createRequire(import.meta.url);
const ts = require_(join(root, "..", "..", "node_modules", "typescript"));

let passed = 0;
function ok(cond, label) {
  assert.ok(cond, label);
  passed += 1;
  console.log(`  ok — ${label}`);
}

console.log("[test-side-borders-parity]");

// ---------------------------------------------------------------------------
// 1. Lift the mobile editor's save merge (the putStyle block) and run it.
// ---------------------------------------------------------------------------
const editorSrc = readFileSync(
  join(root, "app", "links", "[id]", "blocks", "[blockId].tsx"),
  "utf8",
);

const mergeStart = editorSrc.indexOf("const putStyle = (key: string, val: string)");
assert.ok(mergeStart !== -1, "found the save-merge putStyle definition in [blockId].tsx");
const mergeEnd = editorSrc.indexOf("if (Object.keys(styleOut).length > 0)", mergeStart);
assert.ok(mergeEnd !== -1, "found the end of the border save-merge block");
const mergeTs = editorSrc.slice(mergeStart, mergeEnd);
// Sanity: the lifted block still persists all three per-side fields.
for (const f of ["style", "width", "color"]) {
  assert.ok(
    mergeTs.includes(`border_\${side}_${f}`),
    `save merge persists per-side ${f}`,
  );
}
// Strip TS annotations (`: string`, `as const`) so the lifted statements
// evaluate as plain JS.
const mergeJs = ts.transpileModule(mergeTs, {
  compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.ESNext },
}).outputText;

// The mixed scenario (one block, all coverage cases at once):
//   top    → explicit override: dashed 4px #ff0000 (per-side override)
//   right  → hidden via style=none
//   bottom → hidden via width=0
//   left   → fully inherits the shorthand (solid 2px #112233)
const scenario = {
  bdStyle: "solid",
  bdWidth: "2",
  bdColor: "#112233",
  bdRadius: "",
  bdCorners: { tl: "", tr: "", bl: "", br: "" },
  bdSides: {
    top: { style: "dashed", width: "4", color: "#ff0000" },
    right: { style: "none", width: "", color: "" },
    bottom: { style: "", width: "0", color: "" },
    left: { style: "", width: "", color: "" },
  },
};

const styleOut = runExtractedStatements(
  mergeJs,
  "styleOut",
  { styleOut: {}, ...scenario },
  "border save merge",
  { test: "test-side-borders-parity" },
);

// Persisted `_style` keys: non-blank fields stored trimmed, blank fields
// deleted (so left inherits the shorthand purely by absence).
assert.deepEqual(
  styleOut,
  {
    border_style: "solid",
    border_width: "2",
    border_color: "#112233",
    border_top_style: "dashed",
    border_top_width: "4",
    border_top_color: "#ff0000",
    border_right_style: "none",
    border_bottom_width: "0",
  },
  "persisted _style keys match the expected save-merge output",
);
ok(true, "save merge persists exactly the non-blank border fields (left side fully inherited by absence)");

// ---------------------------------------------------------------------------
// 2 + 3. Round-trip through the REAL PHP sanitizer + inline-style builder.
// ---------------------------------------------------------------------------
const phpHarness = `<?php
require ${JSON.stringify(join(laravelRoot, "vendor", "autoload.php"))};
$style = json_decode(stream_get_contents(STDIN), true);
$sanitized = \\App\\Modules\\User\\Support\\BlockStyleSanitizer::sanitize($style);
echo json_encode([
    'sanitized' => $sanitized,
    'css' => \\App\\Modules\\User\\Models\\BiolinkBlock::buildInlineStyle($sanitized),
]);
`;
const tmp = mkdtempSync(join(tmpdir(), "side-borders-"));
let phpOut;
try {
  const phpFile = join(tmp, "roundtrip.php");
  writeFileSync(phpFile, phpHarness);
  phpOut = JSON.parse(
    execFileSync("php", [phpFile], { input: JSON.stringify(styleOut), encoding: "utf8" }),
  );
} finally {
  rmSync(tmp, { recursive: true, force: true });
}

const sanitized = phpOut.sanitized;
// Sanitizer keeps every field (numerics come back as numbers) — nothing
// dropped, nothing mangled.
assert.equal(sanitized.border_style, "solid", "sanitizer keeps shorthand style");
assert.equal(Number(sanitized.border_width), 2, "sanitizer keeps shorthand width");
assert.equal(sanitized.border_color, "#112233", "sanitizer keeps shorthand color");
assert.equal(sanitized.border_top_style, "dashed", "sanitizer keeps top style");
assert.equal(Number(sanitized.border_top_width), 4, "sanitizer keeps top width");
assert.equal(sanitized.border_top_color, "#ff0000", "sanitizer keeps top color");
assert.equal(sanitized.border_right_style, "none", "sanitizer keeps right style=none");
assert.equal(Number(sanitized.border_bottom_width), 0, "sanitizer keeps bottom width=0");
ok(true, "BlockStyleSanitizer round-trips the mobile payload cleanly (no key dropped or mangled)");

// Public web CSS from buildInlineStyle.
const cssDecls = Object.fromEntries(
  phpOut.css
    .split(";")
    .filter((d) => d.startsWith("border-"))
    .map((d) => {
      const i = d.indexOf(":");
      return [d.slice(0, i), d.slice(i + 1)];
    }),
);
assert.equal(cssDecls["border-top"], "4px dashed #ff0000", "web: top uses the explicit override");
assert.equal(cssDecls["border-right"], "none", "web: right hidden via style=none");
assert.equal(cssDecls["border-bottom"], "none", "web: bottom hidden via width=0");
assert.equal(cssDecls["border-left"], "2px solid #112233", "web: left inherits the shorthand");
ok(true, "buildInlineStyle emits the expected per-side CSS (override / none / width-0 / inherit)");

// ---------------------------------------------------------------------------
// 4. Run the REAL mobile renderer math (variantOverlay) on the same style.
// ---------------------------------------------------------------------------
const tmp2 = mkdtempSync(join(tmpdir(), "side-borders-lib-"));
let overlay;
try {
  for (const name of ["blockVariants", "blockTypeRegistry"]) {
    const src = readFileSync(join(root, "lib", `${name}.ts`), "utf8");
    const js = ts.transpileModule(src, {
      compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.ESNext },
    }).outputText.replace(/from\s+["']\.\/blockTypeRegistry["']/g, 'from "./blockTypeRegistry.mjs"');
    writeFileSync(join(tmp2, `${name}.mjs`), js);
  }
  const { variantOverlay } = await import(pathToFileURL(join(tmp2, "blockVariants.mjs")).href);
  // What the phone renderer sees after a refetch: the sanitized style.
  overlay = variantOverlay("link", { _style: sanitized });
} finally {
  rmSync(tmp2, { recursive: true, force: true });
}

assert.ok(overlay, "variantOverlay produced a card overlay");
// Rendered RN card border props, side by side with the web CSS:
assert.equal(overlay.borderTopWidth, 4, "mobile: top width 4 (explicit override)");
assert.equal(overlay.borderTopColor, "#ff0000", "mobile: top color from the override");
assert.equal(overlay.borderRightWidth, 0, "mobile: right hidden via style=none → width 0");
assert.equal(overlay.borderBottomWidth, 0, "mobile: bottom hidden via width=0 → width 0");
assert.equal(overlay.borderLeftWidth, 2, "mobile: left inherits the shorthand width");
assert.equal(overlay.borderLeftColor, "#112233", "mobile: left inherits the shorthand color");
// RN single borderStyle = first visible side's style (top → dashed).
assert.equal(overlay.borderStyle, "dashed", "mobile: single borderStyle approximated from first visible side (top, dashed)");
ok(true, "mobile renderer card props mirror the web CSS on every side");

// ---------------------------------------------------------------------------
// 5. Structural parity: every side the web hides, mobile hides; every side
//    the web shows, mobile shows with matching width + color.
// ---------------------------------------------------------------------------
const cap = (s) => s.charAt(0).toUpperCase() + s.slice(1);
for (const side of ["top", "right", "bottom", "left"]) {
  const web = cssDecls[`border-${side}`];
  const w = overlay[`border${cap(side)}Width`];
  if (web === "none") {
    assert.equal(w, 0, `${side}: web hides it, mobile width must be 0`);
  } else {
    const m = /^(\d+(?:\.\d+)?)px \S+ (\S+)$/.exec(web);
    assert.ok(m, `${side}: web CSS parses as "<w>px <style> <color>"`);
    assert.equal(w, Number(m[1]), `${side}: widths match web=${m[1]} mobile=${w}`);
    assert.equal(
      overlay[`border${cap(side)}Color`],
      m[2],
      `${side}: colors match`,
    );
  }
}
ok(true, "per-side visibility, widths and colors are identical between the web CSS and the RN props");

console.log(`\nAll ${passed} checks passed.`);
