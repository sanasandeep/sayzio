/**
 * Alpine-line-comment-in-attribute guard.
 *
 * Fails (exit 1) if a `.blade.php` file uses `//` single-line JS comments
 * inside a double-quoted Alpine expression attribute (`x-data`, `x-init`,
 * or any `x-*` / `@*` / `:*` handler attribute).
 *
 * Why this exists
 * ---------------
 * Alpine evaluates attribute values as JS expressions. When the browser
 * flattens a multi-line attribute to a single line (which is how HTML
 * attributes work), every `//` comment swallows the rest of the
 * now-single logical line — including closing `)` / `}` — which throws
 * an `Alpine Expression Error: Unexpected token ')'`. The whole Alpine
 * component fails to initialise, so x-text / x-show / @click bindings
 * never run. Use `/* … *\/` block comments inside Alpine attributes
 * instead, or place descriptive comments in Blade `{{-- --}}` comments
 * above the element.
 *
 * What counts as an offender
 * --------------------------
 *   - `//` appearing inside a double-quoted Alpine-ish attribute value
 *     where it cannot be explained as a URL scheme (`://`).
 *   - Alpine-ish attributes: names starting with `x-`, `@`, or `:`.
 *
 * What is SAFE (never flagged)
 * ----------------------------
 *   - `://` — URL scheme separator (`https://`, `mailto://`, etc.).
 *   - `//` inside `@php ... @endphp` blocks (PHP context).
 *   - `//` inside `<script>` / `<style>` block bodies (JS/CSS context).
 *   - `//` inside `{{-- --}}` Blade comments or `<!-- -->` HTML comments.
 *   - `//` inside single-quoted attributes.
 *   - `//` outside any attribute value entirely.
 *
 * Run:  pnpm --filter @workspace/scripts run check:alpine-line-comments
 */

import { spawnSync } from "node:child_process";
import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

/**
 * Scan the entire repository rather than a single app's view directory.
 *
 * Alpine-powered Blade does not only live in `artifacts/1inme/resources/views`
 * — it also ships from other Blade surfaces (standalone Blade apps such as the
 * dialer, package/vendor-published Blade, any future Laravel artifact). A `//`
 * comment inside an Alpine attribute breaks the component identically wherever
 * it lives, so the guard walks every `*.blade.php` in the repo. Third-party
 * composer/npm code we cannot fix is excluded via EXCLUDE_GLOBS below.
 */
export const SCAN_ROOTS: string[] = ["."];

/**
 * Directories we must never flag because we do not own / cannot fix them:
 *   - `**\/vendor/**` — composer third-party packages (e.g. the Laravel
 *     framework exception renderer ships Alpine) AND Laravel-published vendor
 *     views under `resources/views/vendor/` (the user has asked that these not
 *     be modified). Scanning either would produce failures we cannot resolve.
 */
const EXCLUDE_GLOBS: string[] = ["!**/vendor/**"];

export type Offender = {
  file: string;
  line: number;
  col: number;
  attr: string;
  text: string;
};

const blankKeepingNewlines = (m: string): string => m.replace(/[^\n]/g, " ");

/**
 * Blank regions where `//` is safe (PHP blocks, Blade comments, HTML
 * comments, <script>/<style> bodies), preserving length + newlines so
 * line numbers remain accurate.
 */
export function blankSafeContexts(src: string): string {
  let out = src;
  out = out.replace(/\{\{--[\s\S]*?--\}\}/g, blankKeepingNewlines);
  out = out.replace(/@verbatim[\s\S]*?@endverbatim/gi, blankKeepingNewlines);
  out = out.replace(/<!--[\s\S]*?-->/g, blankKeepingNewlines);
  out = out.replace(
    /(<(script|style)\b[^>]*>)([\s\S]*?)(<\/\2\s*>)/gi,
    (_m, open: string, _tag: string, body: string, close: string) =>
      open + blankKeepingNewlines(body) + close,
  );
  out = out.replace(/@php\b[\s\S]*?@endphp\b/gi, blankKeepingNewlines);
  return out;
}

/**
 * Match Alpine-ish double-quoted attribute with a `//` comment inside.
 *
 * The regex captures the attribute name and then uses a non-greedy body
 * match up to the next `"`. Inside the body we require `//` that is NOT
 * preceded by `:` (URL schemes like `https://` are safe).
 *
 * Because the body match is non-greedy, the regex stops at the first
 * closing `"` — which is correct for well-formed HTML. Embedded escaped
 * quotes are uncommon in Alpine expressions and deliberately out of scope
 * for this lightweight guard.
 */
const ALPINE_ATTR_RE =
  /((?:x-[a-zA-Z][\w.-]*|@[a-zA-Z][\w.:-]*|:[a-zA-Z][\w.-]*))\s*=\s*"([\s\S]*?)"/g;

function lineStarts(src: string): number[] {
  const starts = [0];
  for (let i = 0; i < src.length; i++)
    if (src[i] === "\n") starts.push(i + 1);
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
 * Scan `src` for `//` line comments inside double-quoted Alpine attributes.
 * Exposed for direct unit testing.
 */
export function scanSource(relFile: string, src: string): Offender[] {
  const cleaned = blankSafeContexts(src);
  const starts = lineStarts(src);
  const rawLines = src.split("\n");
  const offenders: Offender[] = [];

  for (const m of cleaned.matchAll(ALPINE_ATTR_RE)) {
    const body = m[2] ?? "";
    const attrName = m[1] ?? "";
    const bodyStart = (m.index ?? 0) + m[0].indexOf(body);

    const lineCommentRe = /(?<!:)\/\/[^\n]*/g;
    for (const lc of body.matchAll(lineCommentRe)) {
      const commentOffset = bodyStart + (lc.index ?? 0);
      const line = lineForOffset(starts, commentOffset);
      const col = commentOffset - (starts[line - 1] ?? 0) + 1;
      offenders.push({
        file: relFile,
        line,
        col,
        attr: attrName,
        text: rawLines[line - 1] ?? "",
      });
    }
  }
  offenders.sort((a, b) => a.line - b.line || a.col - b.col);
  return offenders;
}

export function listFiles(): string[] {
  const args = ["--files", "-g", "*.blade.php"];
  for (const g of [
    ...EXCLUDE_GLOBS,
    "!**/node_modules/**",
    "!**/build/**",
    "!**/dist/**",
  ]) {
    args.push("-g", g);
  }
  args.push(...SCAN_ROOTS);

  const res = spawnSync("rg", args, {
    cwd: REPO_ROOT,
    encoding: "utf8",
    maxBuffer: 64 * 1024 * 1024,
  });
  if (res.error) {
    console.error(
      "alpine-line-comments guard: failed to list files:",
      res.error.message,
    );
    process.exit(2);
  }
  if (res.status === 2) {
    console.error(
      "alpine-line-comments guard: ripgrep error:\n" + res.stderr,
    );
    process.exit(2);
  }
  return res.stdout
    .split("\n")
    .map((l) => l.trim())
    .filter(Boolean);
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
      "✓ alpine-line-comments guard passed — no // comments inside Alpine attribute expressions.",
    );
    process.exit(0);
  }

  console.error(
    "✗ alpine-line-comments guard FAILED — // line comments found inside Alpine attribute expression(s):\n",
  );
  for (const o of offenders) {
    console.error(
      `  ${o.file}:${o.line}:${o.col}: ${o.attr}="...//..."  ${o.text.trim()}`,
    );
  }
  console.error(
    `\n${offenders.length} match(es). // comments inside Alpine x-data/x-init/x-*/@ attributes ` +
      `are collapsed to a single line by the browser, causing "Alpine Expression Error: Unexpected token" ` +
      `because // swallows the rest of the line including closing ) and }.`,
  );
  console.error(
    "Fix: replace // with /* … */ block comments inside Alpine attribute values. " +
      "Or move the comment above the element as a Blade {{-- --}} comment.",
  );
  process.exit(1);
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
