/**
 * Combined Blade/Alpine view-guard runner.
 *
 * Runs all three static view guards in one pass and reports a single,
 * clear pass/fail:
 *
 *   - check:alpine-line-comments — `//` line comments inside a double-quoted
 *     Alpine attribute expression (x-data, x-init, or any x-* / @* / :*
 *     handler). The browser
 *     flattens the attribute to one line, so `//` swallows the rest —
 *     including closing ) / } — killing the whole Alpine component.
 *   - check:blade-json-in-attr — `@json(` inside a double-quoted attribute
 *     (emits literal quotes that truncate x-data/@click and silently kill
 *     Alpine).
 *   - check:blade-comment-echo — live {{ }} / {!! !!} echoes inside plain
 *     HTML/CSS comments.
 *
 * Why this exists
 * ---------------
 * These three guards run in the post-merge pipeline (scripts/post-merge.sh),
 * which only catches offenders AFTER the code is already merged — the merge
 * fails and someone has to fix it and re-run over the distant RDS. Running the
 * same guards locally (via a git pre-push hook, or by hand) catches these
 * silent Alpine-breakers BEFORE they are committed/pushed, saving a round trip
 * through the merge orchestrator.
 *
 * Unlike a shell `&&` chain, this runner always runs all three guards even if
 * an earlier one fails, so contributors see every offender in one go instead
 * of fixing-and-re-running one guard at a time.
 *
 * Run:  pnpm --filter @workspace/scripts run check:view-guards
 */

import { spawnSync } from "node:child_process";
import { fileURLToPath, pathToFileURL } from "node:url";
import path from "node:path";

const REPO_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

/** The three guard npm scripts, run in the same order as post-merge.sh. */
export const GUARDS = [
  "check:alpine-line-comments",
  "check:blade-json-in-attr",
  "check:blade-comment-echo",
] as const;

function runGuard(script: string): boolean {
  const res = spawnSync(
    "pnpm",
    ["--filter", "@workspace/scripts", "run", script],
    { cwd: REPO_ROOT, stdio: "inherit" },
  );
  if (res.error) {
    console.error(`view-guards: failed to run ${script}:`, res.error.message);
    return false;
  }
  return res.status === 0;
}

function main(): void {
  console.log("running blade/alpine view guards...\n");

  const failed: string[] = [];
  for (const script of GUARDS) {
    if (!runGuard(script)) failed.push(script);
  }

  if (failed.length === 0) {
    console.log("\n✓ view-guards passed — all three Blade/Alpine guards clean.");
    process.exit(0);
  }

  console.error(
    `\n✗ view-guards FAILED — ${failed.length} of ${GUARDS.length} guard(s) tripped:`,
  );
  for (const f of failed) console.error(`  - ${f}`);
  console.error(
    "\nFix the offenders listed above before committing/pushing. These break " +
      "Alpine components at runtime but compile and typecheck fine, so they are " +
      "invisible until the page runs (or the merge fails).",
  );
  process.exit(1);
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
