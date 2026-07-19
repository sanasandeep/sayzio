/**
 * Sync GitHub branch-protection required status checks from the committed
 * manifest.
 *
 * Branch protection on sanasandeep/sayzio `main` requires the status checks
 * listed in `.github/required-checks.json` — but the JSON file and GitHub's
 * live branch-protection settings are two separate copies. If a PR adds or
 * renames a required check in the JSON, GitHub keeps enforcing the OLD list
 * until someone updates protection, and a red check can silently stop
 * blocking merges.
 *
 * This script makes the manifest the source of truth:
 *   1. Reads `requiredChecks` from `.github/required-checks.json`.
 *   2. GETs the live required_status_checks for `main`.
 *   3. Prints a clear diff (added / removed contexts).
 *   4. PATCHes /repos/{repo}/branches/main/protection/required_status_checks
 *      with the manifest contexts (unless --dry-run or already in sync).
 *
 * It fails loudly (non-zero exit) when GITHUB_TOKEN is missing, the token
 * lacks permission, or any API call fails.
 *
 * Run after editing .github/required-checks.json — and as part of any flow
 * that pushes code to GitHub after a publish:
 *   pnpm --filter @workspace/scripts run sync:branch-protection
 *   pnpm --filter @workspace/scripts run sync:branch-protection -- --dry-run
 */

import { fileURLToPath } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");
export const MANIFEST_PATH = ".github/required-checks.json";
export const GITHUB_REPO = "sanasandeep/sayzio";
export const PROTECTED_BRANCH = "main";

/** Load the requiredChecks contexts from the committed manifest, validating shape. */
export function loadManifestContexts(repoRoot = REPO_ROOT): string[] {
  const abs = path.join(repoRoot, MANIFEST_PATH);
  const raw = fs.readFileSync(abs, "utf8");
  const parsed: unknown = JSON.parse(raw);
  const list = (parsed as { requiredChecks?: unknown })?.requiredChecks;
  if (!Array.isArray(list) || !list.every((n) => typeof n === "string")) {
    throw new Error(`${MANIFEST_PATH} must contain a "requiredChecks" array of strings.`);
  }
  if (list.length === 0) {
    throw new Error(
      `${MANIFEST_PATH} lists no required checks — refusing to wipe branch protection. ` +
        "If you really want zero required checks, change protection manually in GitHub.",
    );
  }
  const seen = new Set<string>();
  for (const name of list) {
    if (seen.has(name)) throw new Error(`${MANIFEST_PATH} lists "${name}" twice.`);
    seen.add(name);
  }
  return list as string[];
}

export interface ContextDiff {
  added: string[];
  removed: string[];
  unchanged: string[];
}

/** Diff desired (manifest) contexts against the live GitHub contexts. */
export function diffContexts(desired: string[], live: string[]): ContextDiff {
  const desiredSet = new Set(desired);
  const liveSet = new Set(live);
  return {
    added: desired.filter((c) => !liveSet.has(c)).sort(),
    removed: live.filter((c) => !desiredSet.has(c)).sort(),
    unchanged: desired.filter((c) => liveSet.has(c)).sort(),
  };
}

interface GithubRequiredStatusChecks {
  strict?: boolean;
  contexts?: string[];
}

async function githubRequest(
  method: string,
  url: string,
  token: string,
  body?: unknown,
): Promise<{ status: number; json: unknown }> {
  const res = await fetch(url, {
    method,
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: "application/vnd.github+json",
      "X-GitHub-Api-Version": "2022-11-28",
      ...(body !== undefined ? { "Content-Type": "application/json" } : {}),
    },
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });
  let json: unknown = null;
  try {
    json = await res.json();
  } catch {
    /* some responses have no body */
  }
  return { status: res.status, json };
}

function explainFailure(status: number, json: unknown): string {
  const message =
    (json as { message?: string } | null)?.message ?? "(no error message from GitHub)";
  if (status === 401) {
    return `HTTP 401 — GITHUB_TOKEN is invalid or expired: ${message}`;
  }
  if (status === 403) {
    return `HTTP 403 — the token lacks permission to administer branch protection on ${GITHUB_REPO} (needs repo administration write): ${message}`;
  }
  if (status === 404) {
    return `HTTP 404 — repo/branch/protection not found (or the token cannot see it). Is branch protection enabled on ${PROTECTED_BRANCH}? ${message}`;
  }
  return `HTTP ${status}: ${message}`;
}

async function main(): Promise<void> {
  const dryRun = process.argv.includes("--dry-run");

  const token = process.env.GITHUB_TOKEN;
  if (!token) {
    console.error(
      "✗ GITHUB_TOKEN is not set. Add the GitHub personal-access token secret before running this sync.",
    );
    process.exit(1);
  }

  let desired: string[];
  try {
    desired = loadManifestContexts();
  } catch (e) {
    console.error(`✗ cannot load ${MANIFEST_PATH}: ${(e as Error).message}`);
    process.exit(1);
  }

  const apiUrl = `https://api.github.com/repos/${GITHUB_REPO}/branches/${PROTECTED_BRANCH}/protection/required_status_checks`;

  let live: GithubRequiredStatusChecks;
  {
    const { status, json } = await githubRequest("GET", apiUrl, token!);
    if (status !== 200) {
      console.error(`✗ failed to read live required status checks: ${explainFailure(status, json)}`);
      process.exit(1);
    }
    live = json as GithubRequiredStatusChecks;
  }

  const liveContexts = live.contexts ?? [];
  const diff = diffContexts(desired!, liveContexts);

  console.log(`Manifest (${MANIFEST_PATH}): ${desired!.length} required check(s)`);
  console.log(`Live (${GITHUB_REPO}@${PROTECTED_BRANCH}): ${liveContexts.length} required check(s)`);
  for (const c of diff.added) console.log(`  + add    "${c}"`);
  for (const c of diff.removed) console.log(`  - remove "${c}"`);
  if (diff.added.length === 0 && diff.removed.length === 0) {
    console.log("✓ branch protection is already in sync with the manifest — nothing to do.");
    return;
  }

  if (dryRun) {
    console.log("(dry run) would PATCH the contexts above; re-run without --dry-run to apply.");
    process.exit(2);
  }

  const { status, json } = await githubRequest("PATCH", apiUrl, token!, {
    // Preserve the live `strict` (require branches up to date) setting.
    strict: live.strict ?? false,
    contexts: desired!,
  });
  if (status !== 200) {
    console.error(`✗ failed to update required status checks: ${explainFailure(status, json)}`);
    process.exit(1);
  }

  const updated = (json as GithubRequiredStatusChecks).contexts ?? [];
  const verify = diffContexts(desired!, updated);
  if (verify.added.length > 0 || verify.removed.length > 0) {
    console.error(
      `✗ GitHub accepted the update but the returned contexts still differ from the manifest (added: ${verify.added.join(", ") || "none"}; removed: ${verify.removed.join(", ") || "none"}).`,
    );
    process.exit(1);
  }

  console.log(
    `✓ branch protection updated — ${diff.added.length} added, ${diff.removed.length} removed; ${GITHUB_REPO}@${PROTECTED_BRANCH} now requires exactly the ${desired!.length} check(s) in ${MANIFEST_PATH}.`,
  );
}

const isDirectRun =
  process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (isDirectRun) {
  main().catch((e) => {
    console.error(`✗ sync-branch-protection failed: ${(e as Error).message}`);
    process.exit(1);
  });
}
