/**
 * CI passthrough-name parity guard.
 *
 * The database-safety workflows are safe to mark as *required* status checks
 * ONLY because each real job is paired with a "passthrough" companion that
 * reports the EXACT SAME check name when a PR touches nothing under
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
 * check that never reports, with no error anywhere.
 *
 * But per-workflow parity alone is NOT enough. The guard used to only know
 * about a hard-coded list of workflow files. If someone deleted or renamed a
 * whole safety workflow — or branch protection kept a check name marked as
 * *required* that no longer maps to any job — the same deadlock returns and the
 * guard would silently skip the missing file. So the list of *required* check
 * names is now the single source of truth, committed as a small manifest at
 * `.github/required-checks.json`. The guard asserts, across ALL workflows, that
 * every required name is BOTH produced by some real job AND covered by a
 * passthrough companion — failing loudly if a required name has no producer at
 * all (deleted/renamed workflow) or no passthrough (deadlock risk).
 *
 * What it does (fast, static — parses the YAMLs, no CI run required)
 * ---------------------------------------------------------------------
 * 1. Discovers every workflow under `.github/workflows/` (so a renamed file is
 *    still seen). For each it classifies every job as the `changes` detector
 *    (exposes the `onein` output, skipped), a passthrough job (key ends
 *    `-passthrough`), or a real guard job, then expands each job `name:` across
 *    its `strategy.matrix` (both the `include:` and plain-list forms) into
 *    concrete check names.
 * 2. For every workflow that participates in the passthrough scheme, asserts
 *    the set of real check names equals the set of passthrough check names.
 * 3. Loads the committed required-check manifest and asserts every required
 *    name is produced by a real job AND covered by a passthrough SOMEWHERE
 *    across all workflows — the durable guard against a deleted/renamed file.
 * 4. Enforces the MIRROR direction so the guard is not one-way (toothless):
 *    every real check name produced by a safety (passthrough-scheme) workflow
 *    must be EITHER in `requiredChecks` (enforced by branch protection) OR
 *    explicitly acknowledged in `advisoryChecks` in the same manifest. Adding a
 *    brand-new "Schema drift guard" style safety job and forgetting to make it
 *    required — so it runs but a red result never blocks a merge — fails the
 *    guard loudly instead of shipping a toothless check. `advisoryChecks` is the
 *    documented escape hatch for jobs that are intentionally non-blocking.
 *
 * Run:  pnpm --filter @workspace/scripts run check:ci-passthrough-names
 */

import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";
import { parse as parseYaml } from "yaml";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

/** Directory GitHub reads workflow definitions from. */
export const WORKFLOWS_DIR = ".github/workflows";

/**
 * Committed single source of truth for the check names branch protection marks
 * as *required*. Keeping this list separate from the workflow YAMLs is what
 * makes the guard robust to a whole workflow file being deleted or renamed.
 */
export const REQUIRED_CHECKS_MANIFEST = ".github/required-checks.json";

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
  // The `yaml` package parses YAML 1.2, so the `on:` key stays the string
  // "on" (it is NOT coerced to boolean `true` as YAML 1.1 would).
  on?: unknown;
  jobs?: Record<string, JobDef>;
}

/**
 * Does this workflow ever run in a pull-request context (and therefore report
 * a status GitHub could mark *required* and deadlock on)? Only `pull_request`
 * / `pull_request_target` triggers put a check onto a PR. A push-to-main
 * deploy workflow (e.g. `deploy-ec2.yml`) never reports on PRs, so it is
 * DELIBERATELY exempt from the passthrough scheme and from the required-check
 * manifest — marking its check required in branch protection would deadlock
 * every PR, which `assessRequiredCoverage` already fails loudly on.
 */
export function isPrGatingWorkflow(doc: WorkflowDef): boolean {
  const on = doc.on;
  if (typeof on === "string") return on.startsWith("pull_request");
  if (Array.isArray(on)) {
    return on.some((t) => typeof t === "string" && t.startsWith("pull_request"));
  }
  if (on && typeof on === "object") {
    return Object.keys(on).some((t) => t.startsWith("pull_request"));
  }
  return false;
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

/**
 * Discover every workflow definition under `.github/workflows/`. We glob the
 * directory rather than hard-coding filenames so that RENAMING a safety
 * workflow file (keeping its jobs) does not blind the guard — the jobs are
 * still found, and the manifest coverage check keys off check names, not paths.
 */
export function discoverWorkflowFiles(repoRoot = REPO_ROOT): string[] {
  const dir = path.join(repoRoot, WORKFLOWS_DIR);
  let entries: string[];
  try {
    entries = fs.readdirSync(dir);
  } catch {
    return [];
  }
  return entries
    .filter((f) => f.endsWith(".yml") || f.endsWith(".yaml"))
    .map((f) => `${WORKFLOWS_DIR}/${f}`)
    .sort();
}

/**
 * Workflow files that gate merges via required, path-aware passthrough jobs.
 * Retained for callers/tests that want the concrete list; derived from
 * discovery so a renamed file is still reflected.
 */
export const WORKFLOW_FILES = discoverWorkflowFiles();

/**
 * A workflow participates in the passthrough scheme if it has the `changes`
 * detector or any `*-passthrough` job. Per-workflow parity is only meaningful
 * for those — an unrelated future workflow (lint, release, …) has neither and
 * must not trip the `no-passthrough-job` / `no-real-jobs` heuristics.
 */
export function usesPassthroughScheme(doc: WorkflowDef): boolean {
  for (const [jobKey, job] of Object.entries(doc.jobs ?? {})) {
    if (jobKey.endsWith("-passthrough")) return true;
    if (isChangeDetector(job)) return true;
  }
  return false;
}

/** Collect the real-guard and passthrough check names produced by one workflow. */
export function collectCheckNames(doc: WorkflowDef): {
  real: Set<string>;
  passthrough: Set<string>;
} {
  const real = new Set<string>();
  const passthrough = new Set<string>();
  for (const [jobKey, job] of Object.entries(doc.jobs ?? {})) {
    if (isChangeDetector(job)) continue;
    const target = jobKey.endsWith("-passthrough") ? passthrough : real;
    for (const name of jobCheckNames(job)) target.add(name);
  }
  return { real, passthrough };
}

export interface RequiredChecks {
  names: string[];
  /**
   * Check names in a safety workflow that are INTENTIONALLY not required
   * (advisory / non-blocking). Explicitly acknowledged here so the mirror guard
   * can tell "forgot to make it required" from "deliberately advisory".
   */
  advisory: string[];
  problem?: ParityProblem;
}

/** Load + validate the committed required-check manifest. */
export function loadRequiredChecks(repoRoot = REPO_ROOT): RequiredChecks {
  const abs = path.join(repoRoot, REQUIRED_CHECKS_MANIFEST);
  let raw: string;
  try {
    raw = fs.readFileSync(abs, "utf8");
  } catch (e) {
    return {
      names: [],
      advisory: [],
      problem: {
        file: REQUIRED_CHECKS_MANIFEST,
        kind: "manifest-read-error",
        detail: `cannot read the required-check manifest ${REQUIRED_CHECKS_MANIFEST}: ${(e as Error).message}. This manifest is the single source of truth for which checks branch protection requires — restore it.`,
      },
    };
  }

  let parsed: unknown;
  try {
    parsed = JSON.parse(raw);
  } catch (e) {
    return {
      names: [],
      advisory: [],
      problem: {
        file: REQUIRED_CHECKS_MANIFEST,
        kind: "manifest-parse-error",
        detail: `cannot parse ${REQUIRED_CHECKS_MANIFEST} as JSON: ${(e as Error).message}`,
      },
    };
  }

  const list = (parsed as { requiredChecks?: unknown })?.requiredChecks;
  if (!Array.isArray(list) || !list.every((n) => typeof n === "string")) {
    return {
      names: [],
      advisory: [],
      problem: {
        file: REQUIRED_CHECKS_MANIFEST,
        kind: "manifest-shape-error",
        detail: `${REQUIRED_CHECKS_MANIFEST} must contain a "requiredChecks" array of strings.`,
      },
    };
  }

  if (list.length === 0) {
    return {
      names: [],
      advisory: [],
      problem: {
        file: REQUIRED_CHECKS_MANIFEST,
        kind: "manifest-empty",
        detail: `${REQUIRED_CHECKS_MANIFEST} lists no required checks. If branch protection truly requires none, this guard is pointless; otherwise the manifest has drifted out of sync.`,
      },
    };
  }

  // `advisoryChecks` is optional; when present it must be an array of strings.
  const advisoryRaw = (parsed as { advisoryChecks?: unknown })?.advisoryChecks;
  if (
    advisoryRaw !== undefined &&
    (!Array.isArray(advisoryRaw) || !advisoryRaw.every((n) => typeof n === "string"))
  ) {
    return {
      names: [],
      advisory: [],
      problem: {
        file: REQUIRED_CHECKS_MANIFEST,
        kind: "manifest-shape-error",
        detail: `${REQUIRED_CHECKS_MANIFEST} "advisoryChecks" must be an array of strings when present.`,
      },
    };
  }

  return { names: list as string[], advisory: (advisoryRaw as string[]) ?? [] };
}

/**
 * The durable assertion: every *required* check name (per the manifest) must be
 * BOTH produced by some real job AND covered by a passthrough companion,
 * anywhere across all workflows. A missing producer means a workflow was
 * deleted/renamed (or the name was mistyped) so the required check will never
 * report; a missing passthrough means unrelated PRs deadlock.
 */
export function assessRequiredCoverage(
  requiredNames: string[],
  allReal: Set<string>,
  allPassthrough: Set<string>,
): ParityProblem[] {
  const problems: ParityProblem[] = [];
  for (const name of requiredNames) {
    if (!allReal.has(name)) {
      problems.push({
        file: REQUIRED_CHECKS_MANIFEST,
        kind: "required-without-producer",
        detail: `required check "${name}" is not produced by any real guard job in any workflow. A safety workflow was likely deleted or renamed, or the name drifted — every PR now deadlocks forever on a required check that never reports. Restore the job or update the manifest.`,
      });
    }
    if (!allPassthrough.has(name)) {
      problems.push({
        file: REQUIRED_CHECKS_MANIFEST,
        kind: "required-without-passthrough",
        detail: `required check "${name}" has no passthrough companion in any workflow. Any PR that does not touch artifacts/1inme/** will deadlock waiting on it — add a passthrough job that reports "${name}".`,
      });
    }
  }
  return problems;
}

/**
 * The MIRROR assertion: every real check name produced by a safety
 * (passthrough-scheme) workflow must be EITHER required (per the manifest) OR
 * explicitly acknowledged as advisory. A real safety job that is neither is the
 * toothless-guard bug: it runs and reports, but because branch protection never
 * marks it required, a red result silently does not block the merge. This is
 * the exact opposite of the deadlock the coverage check guards against.
 *
 * `safetyRealNames` are the real-guard names from passthrough-scheme workflows
 * only — unrelated workflows (lint, release, …) do not participate in the
 * required-safety net and are intentionally out of scope here.
 */
export function assessRequiredEnforcement(
  safetyRealNames: string[],
  requiredNames: string[],
  advisoryNames: string[],
): ParityProblem[] {
  const problems: ParityProblem[] = [];
  const required = new Set(requiredNames);
  const advisory = new Set(advisoryNames);
  const realSet = new Set(safetyRealNames);

  // Core: a real safety-guard check that is neither required nor advisory.
  for (const name of [...realSet].sort()) {
    if (!required.has(name) && !advisory.has(name)) {
      problems.push({
        file: REQUIRED_CHECKS_MANIFEST,
        kind: "real-not-required",
        detail: `real guard job reports check "${name}" but it is in NEITHER "requiredChecks" NOR "advisoryChecks" in ${REQUIRED_CHECKS_MANIFEST}. The job runs, but if branch protection never marks it required a red result silently does NOT block the merge — a toothless safety check. Add "${name}" to "requiredChecks" (and mark it required in branch protection), or list it under "advisoryChecks" if it is intentionally non-blocking.`,
      });
    }
  }

  // Contradiction: a name declared both required and advisory.
  for (const name of [...advisory].sort()) {
    if (required.has(name)) {
      problems.push({
        file: REQUIRED_CHECKS_MANIFEST,
        kind: "advisory-also-required",
        detail: `check "${name}" is listed in BOTH "requiredChecks" and "advisoryChecks" in ${REQUIRED_CHECKS_MANIFEST}. A check is either enforced or advisory, not both — remove it from one list.`,
      });
    }
  }

  // Stale advisory: an acknowledged name that no real safety job produces.
  for (const name of [...advisory].sort()) {
    if (!realSet.has(name)) {
      problems.push({
        file: REQUIRED_CHECKS_MANIFEST,
        kind: "advisory-without-producer",
        detail: `"advisoryChecks" lists "${name}" but no real guard job in any safety workflow produces it — a renamed or removed job left a stale advisory entry. Remove or fix "${name}".`,
      });
    }
  }

  return problems;
}

/** Read + parse + check every discovered workflow file, then the manifest. */
export function checkAllWorkflows(repoRoot = REPO_ROOT): ParityProblem[] {
  const problems: ParityProblem[] = [];
  const allReal = new Set<string>();
  const allPassthrough = new Set<string>();
  const safetyReal = new Set<string>();

  for (const rel of discoverWorkflowFiles(repoRoot)) {
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

    const { real, passthrough } = collectCheckNames(doc);
    for (const n of real) allReal.add(n);
    for (const n of passthrough) allPassthrough.add(n);

    // Per-workflow parity only applies to workflows using the passthrough scheme.
    // Those same workflows are the safety net whose real jobs are expected to be
    // required, so their real names feed the mirror (enforcement) check.
    if (usesPassthroughScheme(doc)) {
      for (const n of real) safetyReal.add(n);
      problems.push(...checkWorkflowParity(rel, doc));
    }
  }

  // Cross-workflow, manifest-driven coverage — the durable source of truth.
  const required = loadRequiredChecks(repoRoot);
  if (required.problem) {
    problems.push(required.problem);
  } else {
    problems.push(...assessRequiredCoverage(required.names, allReal, allPassthrough));
    // Mirror direction: a real safety job that is never marked required.
    problems.push(
      ...assessRequiredEnforcement([...safetyReal], required.names, required.advisory),
    );
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
    "\nTwo failure modes are guarded here:\n" +
      "  • A required check with no passthrough counterpart deadlocks every PR that does not touch artifacts/1inme/** — keep the real job `name:` and the passthrough matrix in lockstep.\n" +
      "  • A real safety job that is in neither `requiredChecks` nor `advisoryChecks` runs but never blocks a merge (toothless) — add it to " +
      REQUIRED_CHECKS_MANIFEST +
      " and mark it required in branch protection, or list it as advisory.",
  );
  process.exit(1);
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
