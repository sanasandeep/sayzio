/**
 * Demo-login button-pattern guard for 1inme browser specs.
 *
 * Fails (exit 1) if any `artifacts/1inme/tests/Browser/*.spec.ts` file
 * re-introduces the fragile, button-first demo sign-in pattern instead of
 * importing the single shared token-POST helper
 * (`artifacts/1inme/tests/Browser/login-as-demo.ts`, `loginAsDemo`).
 *
 * Why this exists
 * ---------------
 * The old copy-pasted `loginAsDemo` helpers authenticated by locating a
 * rendered `form[action$="/user/demo-login"]` button and calling
 * `form.submit()`. Whether that demo button renders is environment/data
 * driven, so in an env where it is absent the helper either throws a
 * misleading "demo-login form not found" or (worse) `form?.submit()` silently
 * no-ops — every spec sharing the helper then fails at setup with a login
 * error that looks like the feature under test broke. Task #4664 replaced all
 * of those with one shared helper that POSTs a same-session CSRF `_token`
 * directly, but nothing stopped a future spec from pasting the old
 * button-first pattern back in. This guard catches that at CI time.
 *
 * What counts as an offender (in a spec that does NOT import the shared helper)
 * ----------------------------------------------------------------------------
 *   - An inline definition of `loginAsDemo` (`function loginAsDemo`,
 *     `const loginAsDemo = ...`, etc.) — a copy of the shared helper.
 *   - The button selector `form[action$="/user/demo-login"]` in code — the
 *     signature of grabbing the rendered demo button to submit it.
 *   - The misleading `demo-login form not found` error string.
 *
 * What is SAFE (never flagged)
 * ----------------------------
 *   - Any spec that imports the shared helper (`from "./login-as-demo"`). Once
 *     a file uses the sanctioned helper, an incidental reference to the
 *     selector (e.g. `dashboard-mobile-account` asserting the login form is
 *     visible as proof of a logged-out session) is a legitimate assertion, not
 *     a login mechanism.
 *   - The admin variant (`form[action$="/admin/demo-login"]`,
 *     `admin demo-login form not found`) — a separate concern outside this
 *     helper's scope; the user selector/error patterns are user-scoped.
 *   - Any of the patterns inside a `//` or block comment.
 *   - `login-as-demo.ts` itself — it is not a `*.spec.ts`, so it is never
 *     scanned.
 *
 * Run:  pnpm --filter @workspace/scripts run check:demo-login-button
 */

import { spawnSync } from "node:child_process";
import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

/** Spec roots to scan (relative to repo root). */
export const SCAN_ROOTS: string[] = ["artifacts/1inme/tests/Browser"];

/** Import specifier of the sanctioned shared helper. */
const SHARED_HELPER_IMPORT = /from\s+["']\.\/login-as-demo["']/;

export type Offender = {
  file: string;
  line: number;
  col: number;
  kind: string;
  text: string;
};

/** Each fragile signature the guard bans, with a human-readable label. */
const PATTERNS: { kind: string; re: RegExp }[] = [
  // Inline copy of the shared helper (`function loginAsDemo`, `const loginAsDemo = ...`).
  // `\bloginAsDemo\b` deliberately does NOT match `loginAsDemoAdmin`.
  { kind: "inline loginAsDemo definition", re: /\b(?:function|const|let|var)\s+loginAsDemo\b/g },
  // The rendered demo button selector (user route only — `/admin/demo-login` is out of scope).
  {
    kind: 'form[action$="/user/demo-login"] selector',
    re: /form\[action\$=["']\/user\/demo-login["']\]/g,
  },
  // The misleading error string, but NOT the admin variant ("admin demo-login form not found").
  { kind: '"demo-login form not found" error', re: /(?<!admin )demo-login form not found/g },
];

/** Replace every non-newline char with a space (keeps offsets stable). */
const blankKeepingNewlines = (m: string): string => m.replace(/[^\n]/g, " ");

/**
 * Blank comment spans so the patterns are allowed inside them, preserving
 * length/newlines so line/column numbers stay accurate:
 *   - block comments (including JSDoc)
 *   - line comments `//... <eol>` — but NOT when the `//` is preceded by `:`
 *     (avoids treating `https://` URLs as the start of a comment).
 */
export function blankComments(src: string): string {
  let out = src.replace(/\/\*[\s\S]*?\*\//g, blankKeepingNewlines);
  out = out.replace(
    /(^|[^:])\/\/[^\n]*/g,
    (_m, pre: string) => pre + blankKeepingNewlines(_m.slice(pre.length)),
  );
  return out;
}

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
 * Pure scanner: return every fragile button-first signature that appears in
 * non-comment code within `src`. A file that imports the shared helper is
 * considered compliant and yields no offenders. Exposed for direct unit
 * testing.
 */
export function scanSource(relFile: string, src: string): Offender[] {
  const cleaned = blankComments(src);

  // A spec that imports the sanctioned helper is using the right mechanism;
  // any remaining selector reference is an assertion, not a login.
  if (SHARED_HELPER_IMPORT.test(cleaned)) return [];

  const starts = lineStarts(src);
  const rawLines = src.split("\n");
  const offenders: Offender[] = [];

  for (const { kind, re } of PATTERNS) {
    for (const m of cleaned.matchAll(re)) {
      const idx = m.index ?? 0;
      const line = lineForOffset(starts, idx);
      const col = idx - (starts[line - 1] ?? 0) + 1;
      offenders.push({ file: relFile, line, col, kind, text: rawLines[line - 1] ?? "" });
    }
  }
  offenders.sort((a, b) => a.line - b.line || a.col - b.col);
  return offenders;
}

function listFiles(): string[] {
  const args = ["--files", "-g", "*.spec.ts"];
  for (const g of ["!**/node_modules/**", "!**/build/**", "!**/dist/**"]) {
    args.push("-g", g);
  }
  args.push(...SCAN_ROOTS);

  const res = spawnSync("rg", args, {
    cwd: REPO_ROOT,
    encoding: "utf8",
    maxBuffer: 64 * 1024 * 1024,
  });
  if (res.error) {
    console.error("demo-login-button guard: failed to list files:", res.error.message);
    process.exit(2);
  }
  if (res.status === 2) {
    console.error("demo-login-button guard: ripgrep error:\n" + res.stderr);
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
      "✓ demo-login-button guard passed — no button-first demo-login pattern in browser specs.",
    );
    process.exit(0);
  }

  console.error(
    "✗ demo-login-button guard FAILED — fragile button-first demo-login pattern(s) found:\n",
  );
  for (const o of offenders) {
    console.error(`  ${o.file}:${o.line}:${o.col}: [${o.kind}]  ${o.text.trim()}`);
  }
  console.error(
    `\n${offenders.length} match(es). Authenticating by locating a rendered ` +
      "`form[action$=\"/user/demo-login\"]` button and submitting it is environment/data driven: " +
      "when the button is absent, sign-in fails silently (misleading \"demo-login form not found\") " +
      "across the suite.",
  );
  console.error(
    "Fix: import { loginAsDemo } from \"./login-as-demo\" and call it — it POSTs a same-session " +
      "CSRF token directly and does not depend on the demo button rendering.",
  );
  process.exit(1);
}

// Only run when invoked directly (helpers above are imported by the test suite).
if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
