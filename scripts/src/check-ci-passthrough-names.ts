/**
 * CI passthrough-name parity guard.
 *
 * The two database-safety workflows are safe to mark as *required* status
 * checks ONLY because each real job is paired with a "passthrough" companion
 * that reports the EXACT SAME check name when a PR touches nothing under
 * `artifacts/1inme/**`:
 *
 *   - `.github/workflows/laravel-migrations.yml`
 *       real `migrate` matrix  ↔  `migrate-passthrough` matrix
 *   - `.github/workflows/laravel-tests.yml`
 *       real jobs (php artisan test, db safety net, PHP 8.4, migration order)
 *       ↔  `tests-passthrough` matrix `check_name` list
 *
 * The whole safety net hinges on those names matching character-for-character.
 * If someone renames a real job (or adds a new required job) and forgets to
 * update the passthrough list, the passthrough silently stops covering that
 * name — and the next unrelated PR deadlocks forever waiting on a required
 * check that never reports, with no error anywhere. That is exactly the bug
 * this guard exists to catch BEFORE it merges.
 *
 * What it does (fast, static — parses the two YAMLs, no CI run required)
 * ---------------------------------------------------------------------
 * For each workflow file it:
 *   1. Classifies every job as the `changes` detector (has `outputs`, skipped),
 *      a passthrough job (key ends `-passthrough`), or a real guard job.
 *   2. Expands every job `name:` across its `strategy.matrix` (both the
 *      `include:` form and the plain list form) into concrete check names.
 *   3. Asserts the set of real check names equals the set of passthrough check
 *      names — failing loudly on any name covered by one side but not the other.
 *
 * Run:  pnpm --filter @workspace/scripts run check:ci-passthrough-names
 */

import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";
import { parse as parseYaml } from "yaml";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

/** Workflow files that gate merges via required, path-aware passthrough jobs. */
export const WORKFLOW_FILES = [
  ".github/workflows/laravel-migrations.yml",
  ".github/workflows/laravel-tests.yml",
] as const;

type MatrixCombo = Record<string, unknown>;

interface JobDef {
  name?: string;
  outputs?: Record<string, unknown>;
  strategy?: { matrix?: Record<string, unknown> };
}

/**
 * The `changes` detector job is the only job that exposes the `onein` output
 * (its whole purpose is to gate the real/passthrough branch). We key off that
 * exact contract rather than "has any outputs" so a future *real* required job
 * that happens to expose an output is still checked, not silently skipped.
 */
function isChangeDetector(job: JobDef): boolean {
  return !!job.outputs && Object.prototype.hasOwnProperty.call(job.outputs, "onein");
}

interface WorkflowDef {
  jobs?: Record<string, JobDef>;
}

export interface ParityProblem {
  file: string;
  kind: string;
  detail: string;
}

/** Cartesian product of the plain list-valued matrix keys. */
function cartesian(baseKeys: Record<string, unknown[]>): MatrixCombo[] {
  const keys = Object.keys(baseKeys);
  if (keys.length === 0) return [{}];
  let combos: MatrixCombo[] = [{}];
  for (const key of keys) {
    const next: MatrixCombo[] = [];
    for (const combo of combos) {
      for (const value of baseKeys[key]) {
        next.push({ ...combo, [key]: value });
      }
    }
    combos = next;
  }
  return combos;
}

/**
 * Expand a job's matrix into concrete combinations, honoring both the plain
 * list form (`display_name: [PostgreSQL, MySQL]`) and the `include:` form
 * (a list of objects, as used by the real `migrate` matrix).
 */
export function expandMatrix(matrix: Record<string, unknown> | undefined): MatrixCombo[] {
  if (!matrix) return [{}];

  const include = Array.isArray(matrix.include) ? (matrix.include as MatrixCombo[]) : [];

  const baseKeys: Record<string, unknown[]> = {};
  for (const [key, value] of Object.entries(matrix)) {
    if (key === "include" || key === "exclude") continue;
    if (Array.isArray(value)) baseKeys[key] = value;
  }

  let combos = cartesian(baseKeys);

  if (include.length > 0) {
    if (Object.keys(baseKeys).length === 0) {
      // No base axes — each include entry is its own combination.
      combos = include.map((entry) => ({ ...entry }));
    } else {
      // Merge include entries into matching base combos (all shared keys equal);
      // unmatched include entries become additional combinations.
      for (const entry of include) {
        const matches = combos.filter((combo) =>
          Object.entries(entry).every(
            ([k, v]) => !(k in combo) || combo[k] === v,
          ),
        );
        if (matches.length > 0) {
          for (const combo of matches) Object.assign(combo, entry);
        } else {
          combos.push({ ...entry });
        }
      }
    }
  }

  return combos;
}

/** Substitute `${{ matrix.KEY }}` references in a name template. */
export function expandName(template: string, combo: MatrixCombo): string {
  return template.replace(/\$\{\{\s*matrix\.([A-Za-z0-9_]+)\s*\}\}/g, (_full, key: string) => {
    const value = combo[key];
    return value === undefined ? `\${{ matrix.${key} }}` : String(value);
  });
}

/** Expand a single job's `name:` template into every concrete check name. */
export function jobCheckNames(job: JobDef): string[] {
  const template = job.name ?? "";
  const combos = expandMatrix(job.strategy?.matrix);
  return combos.map((combo) => expandName(template, combo));
}

/**
 * Compare real-guard check names against passthrough check names for one parsed
 * workflow document. Returns a list of parity problems (empty === healthy).
 */
export function checkWorkflowParity(file: string, doc: WorkflowDef): ParityProblem[] {
  const problems: ParityProblem[] = [];
  const jobs = doc.jobs ?? {};

  const realNames = new Set<string>();
  const passthroughNames = new Set<string>();
  let sawPassthroughJob = false;
  let sawRealJob = false;

  for (const [jobKey, job] of Object.entries(jobs)) {
    // The `changes` detector reports a status too, but it is never a *required*
    // check — it only exists to gate the real/passthrough branch. Skip it.
    if (isChangeDetector(job)) continue;

    const isPassthrough = jobKey.endsWith("-passthrough");
    const names = jobCheckNames(job);

    for (const name of names) {
      if (name.includes("${{")) {
        problems.push({
          file,
          kind: "unexpanded-name",
          detail: `job "${jobKey}" produced a check name with an unexpanded expression: "${name}". The guard could not resolve its matrix — update expandMatrix/expandName if the workflow uses a new templating form.`,
        });
      }
      if (isPassthrough) {
        passthroughNames.add(name);
        sawPassthroughJob = true;
      } else {
        realNames.add(name);
        sawRealJob = true;
      }
    }
  }

  if (!sawRealJob) {
    problems.push({
      file,
      kind: "no-real-jobs",
      detail:
        "No real guard jobs found (every job was the `changes` detector or a passthrough). The guard's job classification is stale — check the workflow structure.",
    });
  }
  if (!sawPassthroughJob) {
    problems.push({
      file,
      kind: "no-passthrough-job",
      detail:
        "No `*-passthrough` job found. Required checks in this workflow will deadlock any PR that does not touch artifacts/1inme/** — add a passthrough companion that reports the same check names.",
    });
  }

  for (const name of [...realNames].sort()) {
    if (!passthroughNames.has(name)) {
      problems.push({
        file,
        kind: "real-without-passthrough",
        detail: `real job reports check "${name}" but no passthrough job reports it — mark it required and every PR that skips artifacts/1inme/** will deadlock. Add "${name}" to the passthrough matrix.`,
      });
    }
  }

  for (const name of [...passthroughNames].sort()) {
    if (!realNames.has(name)) {
      problems.push({
        file,
        kind: "passthrough-without-real",
        detail: `passthrough job reports check "${name}" but no real job produces it — a renamed/removed real job left a stale passthrough entry. Remove or fix "${name}".`,
      });
    }
  }

  return problems;
}

/** Read + parse + check every configured workflow file. */
export function checkAllWorkflows(repoRoot = REPO_ROOT): ParityProblem[] {
  const problems: ParityProblem[] = [];

  for (const rel of WORKFLOW_FILES) {
    const abs = path.join(repoRoot, rel);
    let raw: string;
    try {
      raw = fs.readFileSync(abs, "utf8");
    } catch (e) {
      problems.push({
        file: rel,
        kind: "read-error",
        detail: `cannot read ${rel}: ${(e as Error).message}`,
      });
      continue;
    }

    let doc: WorkflowDef;
    try {
      doc = parseYaml(raw) as WorkflowDef;
    } catch (e) {
      problems.push({
        file: rel,
        kind: "parse-error",
        detail: `cannot parse ${rel} as YAML: ${(e as Error).message}`,
      });
      continue;
    }

    problems.push(...checkWorkflowParity(rel, doc));
  }

  return problems;
}

function main(): void {
  const problems = checkAllWorkflows();

  if (problems.length === 0) {
    console.log(
      "✓ ci-passthrough-names guard passed — every required check name has a matching passthrough companion (and vice-versa) in both database-safety workflows.",
    );
    process.exit(0);
  }

  console.error("✗ ci-passthrough-names guard FAILED:\n");
  for (const p of problems) {
    console.error(`  [${p.kind}] ${p.file}: ${p.detail}`);
  }
  console.error(
    "\nA required status check whose name has no passthrough counterpart deadlocks every PR that does not touch artifacts/1inme/** — the exact bug Task #3509 fixed. Keep the real job `name:` and the passthrough matrix in lockstep.",
  );
  process.exit(1);
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
