/**
 * Blade @json-in-double-quoted-attribute guard.
 *
 * Fails (exit 1) if a `.blade.php` file uses `@json(...)` inside a
 * double-quoted HTML attribute value (e.g. `x-data="{ items: @json($x) }"`,
 * `@click="pick(@json($opt))"`, `x-show="@json($flag)"`).
 *
 * Why this exists
 * ---------------
 * `@json` emits raw JSON containing literal `"` characters. The browser ends
 * the attribute at the first one, silently truncating the Alpine expression:
 * the component never initialises, or click handlers become broken
 * expressions that do nothing. This shipped twice (Audience Insights stale
 * hint, admin Templates filters) and was only found by hand. The fix is
 * `@js(...)` (Js::from), which escapes for HTML-attribute context.
 *
 * What counts as an offender
 * --------------------------
 *   - `@json(` appearing anywhere inside a double-quoted attribute value
 *     (`attr="...@json(...)..."`), where the attribute sits in HTML markup —
 *     including Alpine names like `x-data`, `x-show`, `@click`, `:style`.
 *
 * What is SAFE (never flagged)
 * ----------------------------
 *   - `@json` inside a single-quoted attribute (`x-data='...@json($x)...'`) —
 *     JSON's double quotes cannot terminate a single-quoted attribute.
 *   - `@json` inside `<script>`/`<style>` blocks — normal, correct usage.
 *   - `@json` outside any attribute.
 *   - `@@json(` — Blade escape; renders literally, never compiled.
 *   - Anything inside `{{-- --}}` Blade comments or `@verbatim` blocks.
 *
 * Run:  pnpm --filter @workspace/scripts run check:blade-json-in-attr
 */

import { spawnSync } from "node:child_process";
import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

/** Blade view roots to scan (relative to repo root). */
export const SCAN_ROOTS: string[] = ["artifacts/1inme/resources/views"];

/** `vendor/` blades are third-party (do-not-touch) published views. */
const EXCLUDE_GLOBS: string[] = ["!**/vendor/**"];

export type Offender = { file: string; line: number; col: number; attr: string; text: string };

/** Replace every non-newline char with a space (keeps offsets stable). */
const blankKeepingNewlines = (m: string): string => m.replace(/[^\n]/g, " ");

/**
 * Blank spans where `@json` is never compiled or never sits in HTML-attribute
 * context, preserving length/newlines so line numbers stay accurate:
 *   - `{{-- --}}` Blade comments (stripped by the compiler)
 *   - `@verbatim ... @endverbatim` blocks (directive compilation disabled)
 *   - `<script>`/`<style>` block CONTENTS (attributes on the tags themselves
 *     are kept — a `<script x-data="@json(...)">` would still be a bug)
 *   - HTML comments (`<!-- -->`) — never parsed as attributes
 */
export function blankNonAttributeContexts(src: string): string {
  let out = src.replace(/\{\{--[\s\S]*?--\}\}/g, blankKeepingNewlines);
  out = out.replace(/@verbatim[\s\S]*?@endverbatim/gi, blankKeepingNewlines);
  out = out.replace(/<!--[\s\S]*?-->/g, blankKeepingNewlines);
  out = out.replace(
    /(<(script|style)\b[^>]*>)([\s\S]*?)(<\/\2\s*>)/gi,
    (_m, open: string, _tag: string, body: string, close: string) =>
      open + blankKeepingNewlines(body) + close,
  );
  return out;
}

/**
 * A double-quoted attribute value containing a live `@json(`:
 *   - attribute name: HTML/Alpine-ish token (letters, digits, `-._:@` — covers
 *     `x-data`, `@click.prevent`, `:style`, `wire:click`)
 *   - `="` then a value with no closing `"` before `@json(`
 *   - `(?<!@)@json` skips the Blade-escaped `@@json`
 */
const ATTR_JSON_RE = /([a-zA-Z@:][\w:.@-]*)\s*=\s*"[^"]*?(?<!@)@json\(/g;

function lineStarts(src: string): number[] {
  const starts = [0];
  for (let i = 0; i < src.length; i++) if (src[i] === "\n") starts.push(i + 1);
  return starts;
}

function lineForOffset(starts: number[], offset: number): number {
  let lo = 0;
  let hi = starts.length - 1;
  while (lo < hi) {
    const mid = (lo + hi + 1) >> 1;
    if ((starts[mid] ?? 0) <= offset) lo = mid;
    else hi = mid - 1;
  }
  return lo + 1;
}

/**
 * Pure scanner: return every `@json(` inside a double-quoted attribute in
 * `src`. Exposed for direct unit testing.
 */
export function scanSource(relFile: string, src: string): Offender[] {
  const cleaned = blankNonAttributeContexts(src);
  const starts = lineStarts(src);
  const rawLines = src.split("\n");
  const offenders: Offender[] = [];

  for (const m of cleaned.matchAll(ATTR_JSON_RE)) {
    // Report position of the `@json(` itself, not the attribute opener.
    const jsonIdx = (m.index ?? 0) + m[0].lastIndexOf("@json(");
    const line = lineForOffset(starts, jsonIdx);
    const col = jsonIdx - (starts[line - 1] ?? 0) + 1;
    offenders.push({
      file: relFile,
      line,
      col,
      attr: m[1] ?? "",
      text: rawLines[line - 1] ?? "",
    });
  }
  offenders.sort((a, b) => a.line - b.line || a.col - b.col);
  return offenders;
}

function listFiles(): string[] {
  const args = ["--files", "-g", "*.blade.php"];
  for (const g of [...EXCLUDE_GLOBS, "!**/node_modules/**", "!**/build/**", "!**/dist/**"]) {
    args.push("-g", g);
  }
  args.push(...SCAN_ROOTS);

  const res = spawnSync("rg", args, {
    cwd: REPO_ROOT,
    encoding: "utf8",
    maxBuffer: 64 * 1024 * 1024,
  });
  if (res.error) {
    console.error("blade-json-in-attr guard: failed to list files:", res.error.message);
    process.exit(2);
  }
  if (res.status === 2) {
    console.error("blade-json-in-attr guard: ripgrep error:\n" + res.stderr);
    process.exit(2);
  }
  return res.stdout.split("\n").map((l) => l.trim()).filter(Boolean);
}

function main(): void {
  const offenders: Offender[] = [];
  for (const rel of listFiles()) {
    let src: string;
    try {
      src = fs.readFileSync(path.join(REPO_ROOT, rel), "utf8");
    } catch {
      continue;
    }
    offenders.push(...scanSource(rel, src));
  }

  if (offenders.length === 0) {
    console.log("✓ blade-json-in-attr guard passed — no @json( inside double-quoted attributes.");
    process.exit(0);
  }

  console.error(
    "✗ blade-json-in-attr guard FAILED — @json( found inside double-quoted HTML attribute(s):\n",
  );
  for (const o of offenders) {
    console.error(`  ${o.file}:${o.line}:${o.col}: ${o.attr}="...@json(..."  ${o.text.trim()}`);
  }
  console.error(
    `\n${offenders.length} match(es). @json emits literal double quotes that TERMINATE the ` +
      `attribute, silently truncating the Alpine expression (dead x-data/@click).`,
  );
  console.error(
    "Fix: use @js(...) instead — it escapes for HTML-attribute context. " +
      "(Or move the payload into a <script> block / single-quoted attribute.)",
  );
  process.exit(1);
}

// Only run when invoked directly (helpers above are imported by the test suite).
if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
