/**
 * Inline-`@php(...)` guard.
 *
 * The failure this catches, from a real production outage on the marketing
 * home page (September 2026):
 *
 *     @php($usage = LinkTypeUsage::forName($lt['name']))
 *     @if($usage !== '')
 *     ...
 *     @foreach($types as $i => $lt)
 *     @php $slug = Str::slug($lt['name']); @endphp
 *
 * Blade compiles in two passes. BEFORE any directive is compiled,
 * `storeUncompiledBlocks()` pulls out raw php blocks with a lazy
 * `@php(.*?)@endphp` match. That pattern does not know the one-line
 * parenthesised form from the block opener, so it starts at the inline
 * directive above and runs to the FIRST endphp after it — here, one belonging
 * to a completely unrelated block dozens of lines down.
 *
 * Everything in between is then stashed as "raw php" and never sees the
 * directive compiler: the `@if`, the `@foreach` and the `@php` inside it come
 * out as literal text, and `$slug` is never assigned. The view compiles
 * without complaint and throws at RENDER time:
 *
 *     Undefined variable $slug (View: .../create-showcase.blade.php)
 *
 * Which is why this is worth a guard. Every directive is balanced, the file
 * looks right, `php -l` on the source says nothing, and the parenthesised form
 * is valid Blade that works perfectly in the many files here that use it. It
 * only misfires when an endphp appears LATER in the same file, so the same
 * line is fine in one view and a 500 in another, and adding an unrelated
 * php block far below is enough to break a view that was working.
 *
 * So the rule is narrow on purpose: an inline `@php(...)` is only flagged when
 * some `@endphp` follows it in the same file. Nine views use the inline form
 * with no endphp anywhere after it; those are correct and stay.
 *
 * Blade comments are scanned too, not skipped: comment stripping also happens
 * after raw-block extraction, so an `@php(` written inside `{{-- --}}` arms the
 * same trap. (This paragraph is why the comment in create-showcase spells the
 * directive out in words.)
 *
 * Fix, when you hit this: use the block form.
 *
 *     @php
 *         $usage = LinkTypeUsage::forName($lt['name']);
 *     @endphp
 *
 * Run:  pnpm --filter @workspace/scripts run check:blade-php
 */

import { spawnSync } from "node:child_process";
import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

const EXCLUDE_GLOBS = [
  "!**/vendor/**",
  "!**/node_modules/**",
  "!**/build/**",
  "!**/dist/**",
];

/**
 * The one-line form. `(?<!@)` keeps `@@php(` (an escaped literal) out of it,
 * and `\s*\(` matches how Blade itself decides the directive has arguments.
 */
const INLINE = /(?<!@)@php\s*\(/g;
const ENDPHP = /(?<!@)@endphp\b/g;

export interface Offender {
  file: string;
  line: number;
  text: string;
  endphpLine: number;
}

/** Every inline `@php(` in `src` that has an `@endphp` somewhere after it. */
export function scanSource(rel: string, src: string): Offender[] {
  const ends: number[] = [];
  ENDPHP.lastIndex = 0;
  for (let m = ENDPHP.exec(src); m !== null; m = ENDPHP.exec(src)) ends.push(m.index);
  if (ends.length === 0) return [];

  const lineOf = (offset: number) => src.slice(0, offset).split("\n").length;
  const lines = src.split("\n");

  const out: Offender[] = [];
  INLINE.lastIndex = 0;
  for (let m = INLINE.exec(src); m !== null; m = INLINE.exec(src)) {
    const after = ends.find((e) => e > m!.index);
    if (after === undefined) continue;
    const line = lineOf(m.index);
    out.push({
      file: rel,
      line,
      text: (lines[line - 1] ?? "").trim(),
      endphpLine: lineOf(after),
    });
  }
  return out;
}

export function listFiles(): string[] {
  const args = ["--files", "-g", "*.blade.php"];
  for (const g of EXCLUDE_GLOBS) args.push("-g", g);
  args.push(".");

  const res = spawnSync("rg", args, {
    cwd: REPO_ROOT,
    encoding: "utf8",
    maxBuffer: 64 * 1024 * 1024,
  });
  if (res.error) {
    console.error("blade-php guard: failed to list files:", res.error.message);
    process.exit(2);
  }
  if (res.status === 2) {
    console.error("blade-php guard: ripgrep error:\n" + res.stderr);
    process.exit(2);
  }
  return res.stdout
    .split("\n")
    .map((l) => l.trim().replace(/^\.\//, ""))
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
    console.log("✓ blade-php guard passed — no inline @php(...) shadowed by a later @endphp.");
    process.exit(0);
  }

  console.error(
    "✗ blade-php guard FAILED — an inline @php(...) has an @endphp later in the same file:\n",
  );
  for (const o of offenders) {
    console.error(`  ${o.file}:${o.line}: ${o.text}`);
    console.error(`      pairs with the @endphp on line ${o.endphpLine}; everything between stops compiling.`);
  }
  console.error(
    "\nBlade extracts raw php blocks with a lazy @php(.*?)@endphp match before it compiles any " +
      "directive, so the parenthesised one-liner is read as a block opener and swallows the lines " +
      "up to that @endphp. Directives in that span render as literal text and variables assigned " +
      "there are never set, which surfaces as 'Undefined variable' at render time — a 500, not a " +
      "compile error.",
  );
  console.error("\nFix: use the block form.\n\n    @php\n        $x = ...;\n    @endphp\n");
  process.exit(1);
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  main();
}
