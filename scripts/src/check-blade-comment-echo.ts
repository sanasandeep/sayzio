/**
 * Blade-comment-echo guard.
 *
 * Fails (exit 1) if a `.blade.php` file contains a live Blade echo
 * (`{{ ... }}` or `{!! ... !!}`) sitting INSIDE a plain HTML (`<!-- -->`) or
 * C-style (`/* *\/`) comment.
 *
 * Why this exists
 * ---------------
 * Blade only treats `{{-- --}}` as a "comment". A plain `<!-- -->` or `/* *\/`
 * comment is transparent to the Blade compiler: any `{{ $var }}` written inside
 * it — e.g. as illustrative prose, a docs example, or leftover markup — still
 * compiles to a live PHP echo. If that expression references an undefined
 * variable it throws `ErrorException: Undefined variable $var` and 500s the
 * WHOLE page at render time. This bug shipped once (a CSS `/* *\/` comment in
 * events-directory.blade.php held literal `{{ $var }}` prose) and there was no
 * automated guard to catch it before manual QA.
 *
 * What counts as an offender
 * --------------------------
 *   - `{{ ... }}`  (Blade echo)      inside a `<!-- -->` or `/* *\/` comment
 *   - `{!! ... !!}` (Blade raw echo) inside a `<!-- -->` or `/* *\/` comment
 *
 * What is SAFE (never flagged)
 * ----------------------------
 *   - `{{-- ... --}}` — a real Blade comment; the compiler strips it, so its
 *     contents never echo (even a `{{ $x }}` nested inside it is dead code).
 *   - Anything inside an `@verbatim ... @endverbatim` block — Blade does not
 *     compile echoes there, so `{{ $x }}` is emitted literally, not evaluated.
 *   - Echoes OUTSIDE any comment — that is just normal Blade templating.
 *
 * Both `{{-- --}}` spans and `@verbatim` blocks are blanked (newline- and
 * length-preserving) BEFORE comment scanning, so reported line/column numbers
 * still point at the real source location.
 *
 * Run:  pnpm --filter @workspace/scripts run check:blade-comment-echo
 */

import { spawnSync } from "node:child_process";
import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

/** Blade view roots to scan (relative to repo root). */
export const SCAN_ROOTS: string[] = ["artifacts/1inme/resources/views"];

/**
 * Directories/files excluded from the scan. `vendor/` blades are third-party
 * (do-not-touch) published views, out of our control.
 */
const EXCLUDE_GLOBS: string[] = ["!**/vendor/**"];

/** A stray-echo offender: 1-based line/col of the echo opener in the comment. */
export type Offender = { file: string; line: number; col: number; token: string; text: string };

/** An absolute `[start, end)` span within the source (end is exclusive). */
type Span = { start: number; end: number };

/** Replace every non-newline char in a match with a space (keeps offsets stable). */
const blankKeepingNewlines = (m: string): string => m.replace(/[^\n]/g, " ");

/**
 * Blank the two spans where a `{{ }}`/`{!! !!}` does NOT compile to a live echo,
 * so their contents are never mistaken for an offender:
 *   - `{{-- --}}` Blade comments (stripped by the compiler)
 *   - `@verbatim ... @endverbatim` blocks (echo compilation disabled inside)
 * Newlines and length are preserved so downstream offsets/line numbers hold.
 */
export function blankSafeSpans(src: string): string {
  let out = src.replace(/\{\{--[\s\S]*?--\}\}/g, blankKeepingNewlines);
  out = out.replace(/@verbatim[\s\S]*?@endverbatim/gi, blankKeepingNewlines);
  return out;
}

/**
 * Matches the OPENER of a live Blade echo: `{{` (but not `{{--`) or `{!!`.
 * Blade `{{-- --}}` spans are already blanked before this runs, but the
 * negative lookahead keeps the matcher correct on its own.
 */
const ECHO_OPENER_RE = /\{\{(?!--)|\{!!/g;

/** Matches an HTML comment span (`<!-- ... -->`). */
const HTML_COMMENT_RE = /<!--[\s\S]*?-->/g;

/** Matches a `<style>...</style>` or `<script>...</script>` block (content in $2). */
const STYLE_SCRIPT_RE = /<(style|script)\b[^>]*>([\s\S]*?)<\/\1\s*>/gi;

/**
 * Find C-style block comments (`/* *\/`) within a CSS/JS `code` region, tracking
 * string literals so a `/*` or `*\/` that lives INSIDE a quoted string (e.g.
 * `accept: 'image/*'`, `t.endsWith('/*')`, `'*\/*'`) is NOT mistaken for a
 * comment delimiter. Handles `'`, `"` and JS template `` ` `` strings with
 * backslash escapes. Returned spans are absolute (offset by `base`).
 *
 * This is the crux of the guard's precision: a naive `/\*[\s\S]*?\*\//` regex
 * treats `image/*` in an HTML attribute (or `'/*'` in JS) as a comment opener
 * and swallows every real echo up to the next `*\/`, producing false positives.
 */
export function findBlockComments(code: string, base = 0): Span[] {
  const spans: Span[] = [];
  let i = 0;
  let quote: string | null = null;
  while (i < code.length) {
    const c = code[i];
    if (quote) {
      if (c === "\\") {
        i += 2;
        continue;
      }
      if (c === quote) quote = null;
      i++;
      continue;
    }
    if (c === "'" || c === '"' || c === "`") {
      quote = c;
      i++;
      continue;
    }
    if (c === "/" && code[i + 1] === "*") {
      const close = code.indexOf("*/", i + 2);
      const end = close === -1 ? code.length : close + 2;
      spans.push({ start: base + i, end: base + end });
      i = end;
      continue;
    }
    i++;
  }
  return spans;
}

/** Precompute line-start offsets so an absolute index maps to 1-based line/col. */
function lineStarts(src: string): number[] {
  const starts = [0];
  for (let i = 0; i < src.length; i++) {
    if (src[i] === "\n") starts.push(i + 1);
  }
  return starts;
}

/** Binary-search the line-start table for the 1-based line containing `offset`. */
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
 * Return the absolute spans of every plain comment in `src` that a Blade echo
 * could hide inside. Two comment kinds, resolved so they never overlap or
 * double-count:
 *   1. HTML comments (`<!-- -->`) — found first (they can wrap a whole
 *      `<script>`/`<style>` block, whose echoes still compile), then blanked.
 *   2. C-style comments (`/* *\/`) — found ONLY inside the remaining
 *      `<style>`/`<script>` blocks, via the string-aware `findBlockComments`,
 *      so `image/*` in HTML attributes and `/*` inside JS/CSS strings are safe.
 * `src` must already have safe spans blanked (see `blankSafeSpans`).
 */
export function commentSpans(src: string): Span[] {
  const spans: Span[] = [];

  // 1. HTML comments, over the whole source.
  const chars = src.split("");
  for (const m of src.matchAll(HTML_COMMENT_RE)) {
    const start = m.index ?? 0;
    spans.push({ start, end: start + m[0].length });
  }
  // Blank the HTML comments so their contents (which may include a `<script>`
  // with its own `/* */`) are not re-scanned by the style/script pass.
  for (const s of spans) {
    for (let i = s.start; i < s.end; i++) {
      if (chars[i] !== "\n") chars[i] = " ";
    }
  }
  const blanked = chars.join("");

  // 2. C-style comments, only inside <style>/<script> blocks. Content begins
  // right after the opening tag's `>`.
  for (const m of blanked.matchAll(STYLE_SCRIPT_RE)) {
    const content = m[2] ?? "";
    const openLen = m[0].indexOf(">") + 1;
    const contentStart = (m.index ?? 0) + openLen;
    spans.push(...findBlockComments(content, contentStart));
  }
  return spans;
}

/**
 * Pure scanner: return every live Blade echo found inside a plain HTML/C-style
 * comment in `src`. Safe spans (`{{-- --}}`, `@verbatim`) are blanked first.
 * Exposed for direct unit testing so the delicate comment/echo matching can be
 * pinned without going through the filesystem.
 */
export function scanSource(relFile: string, src: string): Offender[] {
  const cleaned = blankSafeSpans(src);
  const starts = lineStarts(src);
  const rawLines = src.split("\n");
  const offenders: Offender[] = [];

  for (const span of commentSpans(cleaned)) {
    const text = cleaned.slice(span.start, span.end);
    for (const em of text.matchAll(ECHO_OPENER_RE)) {
      const abs = span.start + (em.index ?? 0);
      // Blade escaping: `@{{ ... }}` / `@{!! ... !!}` render the braces
      // literally (never compiled), so a single leading `@` disarms the echo.
      // A doubled `@@{{` is a literal `@` followed by a LIVE echo, so only skip
      // when exactly one `@` precedes the opener.
      if (cleaned[abs - 1] === "@" && cleaned[abs - 2] !== "@") continue;
      const line = lineForOffset(starts, abs);
      const col = abs - (starts[line - 1] ?? 0) + 1;
      offenders.push({
        file: relFile,
        line,
        col,
        token: em[0],
        text: rawLines[line - 1] ?? "",
      });
    }
  }
  offenders.sort((a, b) => a.line - b.line || a.col - b.col);
  return offenders;
}

/** List every `.blade.php` file under SCAN_ROOTS (vendor/build excluded). */
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
    console.error("blade-comment-echo guard: failed to list files:", res.error.message);
    process.exit(2);
  }
  if (res.status === 2) {
    console.error("blade-comment-echo guard: ripgrep error:\n" + res.stderr);
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
    console.log("✓ blade-comment-echo guard passed — no live {{ }} / {!! !!} echoes inside plain comments.");
    process.exit(0);
  }

  console.error(
    "✗ blade-comment-echo guard FAILED — live Blade echo(es) found inside plain HTML/CSS comments:\n",
  );
  for (const o of offenders) console.error(`  ${o.file}:${o.line}:${o.col}: ${o.token} ...  ${o.text.trim()}`);
  console.error(
    `\n${offenders.length} match(es). A plain <!-- --> or /* */ comment does NOT hide a Blade echo — ` +
      `it still compiles to a live PHP echo and can 500 the page (e.g. Undefined variable).`,
  );
  console.error(
    "Fix: use a Blade comment `{{-- ... --}}` instead, or escape the braces (e.g. `@{{ ... }}`) so it is not compiled.",
  );
  process.exit(1);
}

// Only run when invoked directly (helpers above are imported by the test suite).
if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
