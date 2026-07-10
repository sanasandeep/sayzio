/**
 * Demo-login email guard for 1inme browser specs.
 *
 * Fails (exit 1) if any `artifacts/1inme/tests/Browser/*.spec.ts` file hardcodes
 * the demo-login account email as a string literal in seed code instead of
 * interpolating the shared `DEMO_LOGIN_EMAIL` constant
 * (`artifacts/1inme/tests/Browser/demo-account.ts`).
 *
 * Why this exists
 * ---------------
 * The non-prod demo-login route (`AuthController::demoLogin`) authenticates as
 * ONE fixed account. Every browser spec that seeds an owner-scoped fixture must
 * own it as that same account, or the controller owner guard
 * (`$link->user_id !== workspace_owner_id()`) 403s the seeded page and the
 * asserted element is simply absent — a silent, misleading "the feature stopped
 * rendering" failure. Task #4287 routed every spec through `DEMO_LOGIN_EMAIL`,
 * but nothing stops a future spec from pasting a raw email (e.g. the old
 * `demo@1inme.com`, or the literal value of the constant) back into its tinker
 * seed string. This guard catches that at CI time.
 *
 * What counts as an offender
 * --------------------------
 *   - Any banned email literal (see BANNED_EMAILS) appearing in *code* — i.e.
 *     anywhere that is NOT a comment.
 *
 * What is SAFE (never flagged)
 * ----------------------------
 *   - The email inside a `//` line comment or a block comment — including the
 *     PHP `//` comments embedded in the tinker template-literal seed strings,
 *     which is where the specs legitimately reference the account by name.
 *   - `${DEMO_LOGIN_EMAIL}` interpolation (the required pattern) — it contains
 *     no raw email literal, so it is never matched.
 *   - `demo-account.ts` itself is not scanned (only `*.spec.ts`).
 *
 * Run:  pnpm --filter @workspace/scripts run check:demo-login-email
 */

import { spawnSync } from "node:child_process";
import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

/** Spec roots to scan (relative to repo root). */
export const SCAN_ROOTS: string[] = ["artifacts/1inme/tests/Browser"];

/**
 * Email literals that must never be hardcoded in seed code. Includes the
 * legacy wrong account (`demo@1inme.com`) and the current canonical value of
 * `DEMO_LOGIN_EMAIL` — specs must interpolate the constant, not paste the value.
 */
export const BANNED_EMAILS: string[] = ["sazioapp@gmail.com", "demo@1inme.com"];

export type Offender = { file: string; line: number; col: number; email: string; text: string };

/** Replace every non-newline char with a space (keeps offsets stable). */
const blankKeepingNewlines = (m: string): string => m.replace(/[^\n]/g, " ");

/**
 * Blank comment spans so the email is allowed inside them, preserving
 * length/newlines so line/column numbers stay accurate:
 *   - block comments `/* ... *\/`
 *   - line comments `//... <eol>` — but NOT when the `//` is preceded by `:`
 *     (avoids treating `https://` URLs as the start of a comment). This blanks
 *     both JS `//` comments and the PHP `//` comments embedded inside the
 *     backtick tinker seed strings.
 */
export function blankComments(src: string): string {
  let out = src.replace(/\/\*[\s\S]*?\*\//g, blankKeepingNewlines);
  out = out.replace(/(^|[^:])\/\/[^\n]*/g, (_m, pre: string) => pre + blankKeepingNewlines(_m.slice(pre.length)));
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

/** Escape a string for safe use inside a RegExp. */
const escapeRe = (s: string): string => s.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

const EMAIL_RE = new RegExp(BANNED_EMAILS.map(escapeRe).join("|"), "g");

/**
 * Pure scanner: return every banned email literal that appears in non-comment
 * code within `src`. Exposed for direct unit testing.
 */
export function scanSource(relFile: string, src: string): Offender[] {
  const cleaned = blankComments(src);
  const starts = lineStarts(src);
  const rawLines = src.split("\n");
  const offenders: Offender[] = [];

  for (const m of cleaned.matchAll(EMAIL_RE)) {
    const idx = m.index ?? 0;
    const line = lineForOffset(starts, idx);
    const col = idx - (starts[line - 1] ?? 0) + 1;
    offenders.push({
      file: relFile,
      line,
      col,
      email: m[0],
      text: rawLines[line - 1] ?? "",
    });
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
    console.error("demo-login-email guard: failed to list files:", res.error.message);
    process.exit(2);
  }
  if (res.status === 2) {
    console.error("demo-login-email guard: ripgrep error:\n" + res.stderr);
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
      "✓ demo-login-email guard passed — no hardcoded demo-login email in browser spec seed code.",
    );
    process.exit(0);
  }

  console.error(
    "✗ demo-login-email guard FAILED — hardcoded demo-login email(s) in browser spec seed code:\n",
  );
  for (const o of offenders) {
    console.error(`  ${o.file}:${o.line}:${o.col}: '${o.email}'  ${o.text.trim()}`);
  }
  console.error(
    `\n${offenders.length} match(es). A seed that owns its fixture as any account other than the ` +
      "demo-login account trips the owner guard and 403s the seeded page — the asserted element " +
      "is silently absent (looks like the feature stopped rendering).",
  );
  console.error(
    "Fix: import DEMO_LOGIN_EMAIL from ./demo-account and interpolate `${DEMO_LOGIN_EMAIL}` into " +
      "the tinker seed string instead of hardcoding the email.",
  );
  process.exit(1);
}

// Only run when invoked directly (helpers above are imported by the test suite).
if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
