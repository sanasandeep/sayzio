/**
 * Glued-Blade-directive guard.
 *
 * The failure this catches, from a real outage on the Aurora dashboard:
 *
 *     <span>@if(!empty($price)){{ $price }}/mo@endif</span>
 *
 * Blade's statement compiler matches directives with `\B@(@?\w+...)`. The `\B`
 * means the position before the `@` must NOT be a word boundary, so a `@`
 * sitting immediately after a word character is never treated as a directive.
 * Above, `@endif` follows the `o` of `/mo` and is compiled as literal text.
 * The `@if` before it, preceded by `>`, compiles normally and is then never
 * closed:
 *
 *     syntax error, unexpected end of file, expecting "elseif" or "else" or "endif"
 *
 * Counting directives finds nothing wrong. The pair is right there in the
 * source; Blade simply never saw one of them. That is what makes this worth a
 * guard rather than a code review: the usual check passes.
 *
 * When BOTH halves of a pair are glued the view still compiles, and instead
 * renders the directive to the reader as literal text. Quieter, still a bug.
 *
 * Baseline ratchet
 * ----------------
 * Six of these already exist, all in sentence copy where a conditional follows
 * a word ("...your current plan@if($upgradePlan), upgrade to..."). Fixing them
 * means either inserting whitespace the sentence does not want, ahead of a
 * comma, or restructuring the copy, so they are recorded in
 * `data/blade-glued-directive-baseline.json` rather than edited blind. Any
 * INCREASE fails the build; any DECREASE fails too, with a self-service fix,
 * so the baseline can only shrink deliberately.
 *
 * Fix, when you hit this: build the conditional string in an `@php` block
 * above and echo it, or move the directive off the end of the word. Do not
 * just add a space if the sentence cannot take one.
 *
 * Run:  pnpm --filter @workspace/scripts run check:blade-glued-directive
 *       pnpm --filter @workspace/scripts run check:blade-glued-directive -- --update-baseline
 */

import { spawnSync } from "node:child_process";
import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

export const BASELINE_REL = "scripts/src/data/blade-glued-directive-baseline.json";

/**
 * Every Blade directive whose behaviour changes if it is not compiled. Keyed
 * off a fixed list rather than `\w+` so an email address in copy
 * (`sana@sayzio.app`) or a handle is never mistaken for one.
 */
export const DIRECTIVES = [
  "if", "elseif", "else", "endif",
  "unless", "endunless",
  "isset", "endisset", "empty", "endempty",
  "foreach", "endforeach", "forelse", "endforelse", "for", "endfor",
  "while", "endwhile",
  "switch", "case", "endswitch",
  "php", "endphp",
  "json", "include", "includeIf", "includeWhen", "extends",
  "section", "endsection", "yield", "parent",
  "push", "endpush", "prepend", "endprepend", "stack",
  "can", "endcan", "cannot", "endcannot", "canany", "endcanany",
  "auth", "endauth", "guest", "endguest",
  "error", "enderror",
  "checked", "selected", "disabled", "readonly", "required",
  "csrf", "method",
] as const;

const EXCLUDE_GLOBS = [
  "!**/vendor/**",
  "!**/node_modules/**",
  "!**/build/**",
  "!**/dist/**",
];

export const PATTERN = new RegExp(
  `[A-Za-z0-9_]@(?:${DIRECTIVES.join("|")})\\b`,
  "g",
);

export interface Offender {
  file: string;
  line: number;
  text: string;
}

export function scanSource(rel: string, src: string): Offender[] {
  const out: Offender[] = [];
  const lines = src.split("\n");
  lines.forEach((text, i) => {
    PATTERN.lastIndex = 0;
    if (PATTERN.test(text)) out.push({ file: rel, line: i + 1, text });
  });
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
    console.error("blade-glued-directive guard: failed to list files:", res.error.message);
    process.exit(2);
  }
  if (res.status === 2) {
    console.error("blade-glued-directive guard: ripgrep error:\n" + res.stderr);
    process.exit(2);
  }
  return res.stdout.split("\n").map((l) => l.trim().replace(/^\.\//, "")).filter(Boolean);
}

export function countByFile(offenders: Offender[]): Record<string, number> {
  const counts: Record<string, number> = {};
  for (const o of offenders) counts[o.file] = (counts[o.file] ?? 0) + 1;
  return counts;
}

function readBaseline(): Record<string, number> {
  const p = path.join(REPO_ROOT, BASELINE_REL);
  try {
    return JSON.parse(fs.readFileSync(p, "utf8")) as Record<string, number>;
  } catch {
    return {};
  }
}

function main(): void {
  const update = process.argv.includes("--update-baseline");

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
  const counts = countByFile(offenders);

  if (update) {
    const sorted: Record<string, number> = {};
    for (const k of Object.keys(counts).sort()) sorted[k] = counts[k];
    fs.writeFileSync(
      path.join(REPO_ROOT, BASELINE_REL),
      JSON.stringify(sorted, null, 2) + "\n",
    );
    console.log(`✓ baseline written — ${offenders.length} occurrence(s) across ${Object.keys(sorted).length} file(s).`);
    process.exit(0);
  }

  const baseline = readBaseline();
  const added: Offender[] = [];
  const removed: string[] = [];

  for (const [file, n] of Object.entries(counts)) {
    const was = baseline[file] ?? 0;
    if (n > was) added.push(...offenders.filter((o) => o.file === file));
  }
  for (const [file, was] of Object.entries(baseline)) {
    if ((counts[file] ?? 0) < was) removed.push(file);
  }

  if (added.length === 0 && removed.length === 0) {
    console.log(
      `✓ blade-glued-directive guard passed — ${offenders.length} known occurrence(s), none new.`,
    );
    process.exit(0);
  }

  if (added.length > 0) {
    console.error(
      "✗ blade-glued-directive guard FAILED — a Blade directive is glued to a word character, so Blade will not compile it:\n",
    );
    for (const o of added) console.error(`  ${o.file}:${o.line}: ${o.text.trim()}`);
    console.error(
      "\nBlade matches directives with \\B@(...), so an @ straight after a word character is literal text. " +
        "If its partner IS compiled, the view fails with 'unexpected end of file, expecting endif'. " +
        "If both are glued, the directive renders to the reader as text.",
    );
    console.error(
      "Fix: build the string in an @php block above and echo it, or move the directive off the end of the word. " +
        "Do not just add a space if the sentence cannot take one.",
    );
  }
  if (removed.length > 0) {
    console.error(
      `\n✗ baseline is stale — ${removed.length} file(s) now have fewer occurrences than recorded:`,
    );
    for (const f of removed) console.error(`  ${f}`);
    console.error(
      "\nGood news, someone fixed one. Re-baseline so the count can never drift back up:\n" +
        "  pnpm --filter @workspace/scripts run check:blade-glued-directive -- --update-baseline",
    );
  }
  process.exit(1);
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
