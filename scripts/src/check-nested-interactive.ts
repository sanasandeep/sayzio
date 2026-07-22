/**
 * Nested-interactive-element Blade guard.
 *
 * Fails (exit 1) if a `.blade.php` file nests an interactive element
 * (`<button>`, `<a>`, `<input>`, `<select>`, `<textarea>`, `<details>`,
 * `<label>`, `<iframe>`, `<embed>`) inside an open `<button>` or `<a>`.
 *
 * Why this exists
 * ---------------
 * The HTML parser force-closes an open <button> when it meets a nested
 * <button> (and similarly ejects content for <a>-in-<a>). The served HTML
 * looks fine, but the browser re-parents everything after the nested tag —
 * later panels get ejected from their layout column and appear to "float"
 * (this broke the admin Block Defaults editor). Nested interactive content
 * inside <a>/<button> is also invalid HTML per the spec's "interactive
 * content" rule, even when the parser happens to cope (e.g. <button> in <a>).
 *
 * What counts as an offender
 * --------------------------
 *   - Any interactive open tag appearing while a <button> or <a> element is
 *     still open in the same file.
 *
 * What is SAFE (never flagged)
 * ----------------------------
 *   - Markup inside `{{-- --}}` Blade comments, `@verbatim` blocks, HTML
 *     comments, and `<script>`/`<style>` bodies.
 *   - Tag-like text inside quoted attribute values (`@click="a < b"`,
 *     `x-html="'<a>…</a>'"`) — the tokenizer skips quoted attr values.
 *   - `<a>`/`<button>` pairs opened/closed in separate @if/@else branches:
 *     any `@else` / `@elseif` / `@empty` boundary resets the open-element
 *     stack, since only one branch renders at a time.
 *
 * Run:  pnpm --filter @workspace/scripts run check:nested-interactive
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

/** Elements whose open tag starts a "no interactive content inside" region. */
const CONTAINER_TAGS = new Set(["button", "a"]);

/** Interactive elements that must never sit inside an open <button>/<a>. */
const INTERACTIVE_TAGS = new Set([
  "button",
  "a",
  "input",
  "select",
  "textarea",
  "details",
  "label",
  "iframe",
  "embed",
]);

export type Offender = {
  file: string;
  line: number;
  col: number;
  /** The nested interactive tag (e.g. "button"). */
  tag: string;
  /** The enclosing container tag (e.g. "a"). */
  inside: string;
  /** Line the enclosing container was opened on. */
  insideLine: number;
  text: string;
};

/** Replace every non-newline char with a space (keeps offsets stable). */
const blankKeepingNewlines = (m: string): string => m.replace(/[^\n]/g, " ");

/**
 * Blank spans that are never parsed as element markup, preserving
 * length/newlines so line numbers stay accurate:
 *   - `{{-- --}}` Blade comments
 *   - `@verbatim ... @endverbatim` blocks (may hold framework templates)
 *   - HTML comments
 *   - `<script>`/`<style>` block CONTENTS (JS string templates etc.)
 *   - `{{ ... }}` / `{!! ... !}}` Blade echoes (PHP expressions, e.g. `<` compares)
 */
export function blankNonMarkupContexts(src: string): string {
  let out = src.replace(/\{\{--[\s\S]*?--\}\}/g, blankKeepingNewlines);
  out = out.replace(/@verbatim[\s\S]*?@endverbatim/gi, blankKeepingNewlines);
  out = out.replace(/<!--[\s\S]*?-->/g, blankKeepingNewlines);
  out = out.replace(
    /(<(script|style)\b(?:"[^"]*"|'[^']*'|[^"'>])*>)([\s\S]*?)(<\/\2\s*>)/gi,
    (_m, open: string, _tag: string, body: string, close: string) =>
      open + blankKeepingNewlines(body) + close,
  );
  out = out.replace(/@php\b(?!\s*\()[\s\S]*?@endphp/gi, blankKeepingNewlines);
  out = out.replace(/\{!!([\s\S]*?)!!\}/g, blankKeepingNewlines);
  out = out.replace(/\{\{([\s\S]*?)\}\}/g, blankKeepingNewlines);
  return out;
}

/**
 * One HTML tag, skipping quoted attribute values so `<` / `>` inside
 * `@click="..."` or `:x="'<a>'"` never split the tag.
 */
const TAG_RE = /<(\/?)([a-zA-Z][\w-]*)((?:"[^"]*"|'[^']*'|[^"'>])*)>/g;

/**
 * Blade conditional structure. Only one branch renders, so an element
 * opened in one branch and "reopened" after `@else`/`@elseif`/`@empty` is
 * NOT nesting. We snapshot the open-element stack at each conditional
 * opener and RESTORE it at each branch boundary (rather than clearing),
 * so an outer <a>/<button> wrapping the whole conditional keeps counting.
 */
const BRANCH_OPEN_RE = /@(?:if|unless|isset|forelse|empty)\s*\(/g;
const BRANCH_RESET_RE = /@(?:else\b(?!if)|elseif\s*\(|empty\b(?!\s*\())/g;
const BRANCH_CLOSE_RE = /@(?:endif|endunless|endisset|endempty|endforelse)\b/g;

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
 * Pure scanner: return every interactive element nested inside an open
 * `<button>`/`<a>` in `src`. Exposed for direct unit testing.
 */
export function scanSource(relFile: string, src: string): Offender[] {
  const cleaned = blankNonMarkupContexts(src);
  const starts = lineStarts(src);
  const rawLines = src.split("\n");
  const offenders: Offender[] = [];

  type Event =
    | { kind: "tag"; index: number; closing: boolean; tag: string; attrs: string }
    | { kind: "branch-open" | "branch-reset" | "branch-close"; index: number };

  const events: Event[] = [];
  for (const m of cleaned.matchAll(TAG_RE)) {
    events.push({
      kind: "tag",
      index: m.index ?? 0,
      closing: m[1] === "/",
      tag: (m[2] ?? "").toLowerCase(),
      attrs: m[3] ?? "",
    });
  }
  for (const m of cleaned.matchAll(BRANCH_OPEN_RE)) {
    events.push({ kind: "branch-open", index: m.index ?? 0 });
  }
  for (const m of cleaned.matchAll(BRANCH_RESET_RE)) {
    events.push({ kind: "branch-reset", index: m.index ?? 0 });
  }
  for (const m of cleaned.matchAll(BRANCH_CLOSE_RE)) {
    events.push({ kind: "branch-close", index: m.index ?? 0 });
  }
  events.sort((a, b) => a.index - b.index);

  type Open = { tag: string; line: number };
  /** Stack of currently-open container elements. */
  let stack: Open[] = [];
  /** Snapshots of `stack` at each conditional opener, restored at branch boundaries. */
  const snapshots: Open[][] = [];
  for (const ev of events) {
    if (ev.kind !== "tag") {
      if (ev.kind === "branch-open") {
        snapshots.push([...stack]);
      } else if (ev.kind === "branch-reset") {
        const snap = snapshots[snapshots.length - 1];
        if (snap) stack = [...snap];
      } else {
        // branch-close: keep whichever branch's stack we ended in; drop the snapshot.
        snapshots.pop();
      }
      continue;
    }
    const { index, closing, tag, attrs } = ev;
    if (closing) {
      if (CONTAINER_TAGS.has(tag)) {
        // Pop the nearest matching open container (tolerates minor mis-nesting).
        for (let i = stack.length - 1; i >= 0; i--) {
          if (stack[i]?.tag === tag) {
            stack.splice(i, 1);
            break;
          }
        }
      }
      continue;
    }
    const selfClosing = /\/\s*$/.test(attrs);
    const line = lineForOffset(starts, index);
    if (INTERACTIVE_TAGS.has(tag) && stack.length > 0) {
      const enclosing = stack[stack.length - 1]!;
      offenders.push({
        file: relFile,
        line,
        col: index - (starts[line - 1] ?? 0) + 1,
        tag,
        inside: enclosing.tag,
        insideLine: enclosing.line,
        text: rawLines[line - 1] ?? "",
      });
      continue; // don't push a nested container; the parser ejects it anyway
    }
    if (CONTAINER_TAGS.has(tag) && !selfClosing) {
      stack.push({ tag, line });
    }
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
    console.error("nested-interactive guard: failed to list files:", res.error.message);
    process.exit(2);
  }
  if (res.status === 2) {
    console.error("nested-interactive guard: ripgrep error:\n" + res.stderr);
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
    console.log(
      "✓ nested-interactive guard passed — no interactive elements nested in <button>/<a>.",
    );
    process.exit(0);
  }

  console.error(
    "✗ nested-interactive guard FAILED — interactive element(s) nested inside an open <button>/<a>:\n",
  );
  for (const o of offenders) {
    console.error(
      `  ${o.file}:${o.line}:${o.col}: <${o.tag}> inside <${o.inside}> ` +
        `(opened line ${o.insideLine})  ${o.text.trim()}`,
    );
  }
  console.error(
    `\n${offenders.length} match(es). The HTML parser force-closes the outer element at the ` +
      `nested tag, ejecting later markup from its layout column (served HTML looks fine, the ` +
      `rendered page breaks).`,
  );
  console.error(
    "Fix: move the nested control outside the <button>/<a>, or replace it with a " +
      "non-interactive element (e.g. <span role=\"button\" tabindex=\"0\"> with key handlers).",
  );
  process.exit(1);
}

// Only run when invoked directly (helpers above are imported by the test suite).
if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
