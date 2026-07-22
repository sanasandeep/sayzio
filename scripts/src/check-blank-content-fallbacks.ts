/**
 * Blank-content fallback guard.
 *
 * Why this exists
 * ---------------
 * Admin block defaults may be EXPLICITLY blanked ('' / []). Renderers must use
 * null-coalescing (`??` in Blade/PHP), which keeps a keyed empty string, so an
 * intentionally-blank default renders blank. An Elvis fallback (`?:`) treats
 * '' as falsy and RE-INJECTS sample text on blanks. The web block-picker
 * preview was one such offender; the mobile public renderer's `pickStr()`
 * (which collapses '' to null) was another. This guard stops new ones.
 *
 * What counts as an offender
 * --------------------------
 * 1. Blade block-render surfaces (`SCAN_BLADE`): any line reading a block
 *    content key via `$s['...']` (or `$it['...']` list-item reads) followed by
 *    an Elvis `?:` fallback, e.g. `$s['text'] ?: 'Sample text'`.
 * 2. The mobile public biolink renderer: any `pickStr(` call whose key list
 *    contains a CONTENT text key (text/title/label/name/heading/caption/
 *    button_text/question/code) on a line that also has a `?? "..."` string
 *    fallback. Those must use the blank-aware `pickContentStr(` instead.
 *
 * What is SAFE
 * ------------
 *   - `?? 'Sample'` fallbacks in Blade (keyed '' survives `??`).
 *   - `?:` fallbacks to REAL data (e.g. label ?: address) via ALLOWLIST.
 *   - Structural reads (currency, type, align, colors) — not content text.
 *
 * Adding a legit exception: append to ALLOWLIST with a reason — never weaken
 * the matcher.
 *
 * Run:  pnpm --filter @workspace/scripts run check:blank-content-fallbacks
 */

import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

/** Blade block-render surfaces where admin block-default content is rendered. */
const SCAN_BLADE: string[] = [
  "artifacts/1inme/resources/views/common/blocks",
  "artifacts/1inme/resources/views/common/partials/biolink-block-render.blade.php",
  "artifacts/1inme/resources/views/common/biolink.blade.php",
  "artifacts/1inme/resources/views/user/links/partials",
];

const MOBILE_RENDERER = "artifacts/1inme-mobile/app/biolink/[handle].tsx";

/** Content-text keys: a pickStr over these with a string fallback re-injects sample text on blanks. */
const CONTENT_KEYS = new Set([
  "text",
  "title",
  "label",
  "name",
  "heading",
  "caption",
  "button_text",
  "question",
  "code",
]);

/** file (repo-relative) + needle + reason. Fallbacks to REAL data, not sample text. */
const ALLOWLIST: Array<{ file: string; needle: string; reason: string }> = [
  {
    file: "artifacts/1inme/resources/views/common/blocks/map-location.blade.php",
    needle: "$s['label'] ?: ($addr ?: 'location')",
    reason: "alt text falls back to the real address, not sample content",
  },
  {
    file: "artifacts/1inme/resources/views/common/blocks/map-location.blade.php",
    needle: "$s['label'] ?: $addr",
    reason: "caption falls back to the real address, not sample content",
  },
];

const BLADE_ELVIS = /\$(?:s|it)\[['"][a-z0-9_]+['"]\][^;\n?]*\?:/;
const MOBILE_PICKSTR = /\bpickStr\(([^)]*)\)/g;

type Offense = { file: string; line: number; text: string };

function isAllowed(rel: string, lineText: string): boolean {
  return ALLOWLIST.some((a) => a.file === rel && lineText.includes(a.needle));
}

function bladeFiles(): string[] {
  const out: string[] = [];
  for (const root of SCAN_BLADE) {
    const abs = path.join(REPO_ROOT, root);
    if (!fs.existsSync(abs)) continue;
    const stat = fs.statSync(abs);
    if (stat.isFile()) {
      out.push(root);
      continue;
    }
    const walk = (dir: string) => {
      for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
        const p = path.join(dir, e.name);
        if (e.isDirectory()) walk(p);
        else if (e.name.endsWith(".blade.php")) out.push(path.relative(REPO_ROOT, p));
      }
    };
    walk(abs);
  }
  return out;
}

function scan(): Offense[] {
  const offenses: Offense[] = [];

  for (const rel of bladeFiles()) {
    const lines = fs.readFileSync(path.join(REPO_ROOT, rel), "utf8").split("\n");
    lines.forEach((line, i) => {
      if (BLADE_ELVIS.test(line) && !isAllowed(rel, line)) {
        offenses.push({ file: rel, line: i + 1, text: line.trim() });
      }
    });
  }

  const mobileAbs = path.join(REPO_ROOT, MOBILE_RENDERER);
  if (fs.existsSync(mobileAbs)) {
    const lines = fs.readFileSync(mobileAbs, "utf8").split("\n");
    lines.forEach((line, i) => {
      if (!/\?\?\s*"[^"]/.test(line) && !/\|\|\s*"[^"]/.test(line)) return;
      let m: RegExpExecArray | null;
      MOBILE_PICKSTR.lastIndex = 0;
      while ((m = MOBILE_PICKSTR.exec(line)) !== null) {
        const keys = [...m[1].matchAll(/"([a-z0-9_]+)"/g)].map((k) => k[1]);
        if (keys.some((k) => CONTENT_KEYS.has(k)) && !isAllowed(MOBILE_RENDERER, line)) {
          offenses.push({ file: MOBILE_RENDERER, line: i + 1, text: line.trim() });
          break;
        }
      }
    });
  }

  return offenses;
}

const offenses = scan();
if (offenses.length > 0) {
  console.error("Blank-content fallback guard FAILED.\n");
  console.error(
    "These lines re-inject sample text when an admin block default was explicitly blanked ('').\n" +
      "Blade: use `?? 'Sample'` (keeps keyed ''), never `?:`. Mobile: use pickContentStr() for content text keys.\n",
  );
  for (const o of offenses) {
    console.error(`  ${o.file}:${o.line}\n    ${o.text}`);
  }
  console.error(`\n${offenses.length} offense(s). Legit real-data fallbacks go in ALLOWLIST with a reason.`);
  process.exit(1);
}
console.log("check-blank-content-fallbacks: OK (no ?:-style sample-text fallbacks on block content reads).");
