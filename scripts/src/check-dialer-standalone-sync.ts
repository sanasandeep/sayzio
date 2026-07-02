/**
 * Drift checker for the standalone dialer app (sayzio-dialer-standalone/).
 *
 * The standalone app was transplanted from artifacts/1inme-mobile/ and shares
 * no code with it, so main-app dialer changes do NOT flow in automatically.
 * This script detects drift using sayzio-dialer-standalone/sync-manifest.json:
 *
 *   - "identical" entries must stay byte-identical to their main-app source.
 *   - "adapted" entries intentionally differ; we track the sha256 of the
 *     main-app SOURCE at the time of the last sync. If the source changes,
 *     the entry is flagged so a human can review the diff and re-apply the
 *     relevant part to the standalone copy.
 *   - "standaloneOnly" files have no main-app counterpart (documented only).
 *
 * Usage:
 *   pnpm --filter @workspace/scripts run check:dialer-sync           # check
 *   pnpm --filter @workspace/scripts run check:dialer-sync:accept    # re-baseline
 *
 * Run `:accept` ONLY after you have reviewed the source diffs and applied
 * whatever is relevant to the standalone copy (see
 * sayzio-dialer-standalone/SYNC.md for the procedure).
 */
import { createHash } from "node:crypto";
import { existsSync, readFileSync, writeFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const repoRoot = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);
const standaloneRoot = path.join(repoRoot, "sayzio-dialer-standalone");
const manifestPath = path.join(standaloneRoot, "sync-manifest.json");

type ManifestEntry = {
  /** Path relative to sayzio-dialer-standalone/ */
  standalone: string;
  /** Path relative to sourceRoot (artifacts/1inme-mobile/) */
  source: string;
  relation: "identical" | "adapted";
  /** sha256 of the SOURCE file at last sync (adapted entries only) */
  sourceSha256?: string;
  /** Why the file intentionally differs (adapted entries only) */
  note?: string;
};

type Manifest = {
  sourceRoot: string;
  files: ManifestEntry[];
  standaloneOnly: { path: string; note: string }[];
};

const accept = process.argv.includes("--accept");

const manifest: Manifest = JSON.parse(readFileSync(manifestPath, "utf8"));
const sourceRoot = path.join(repoRoot, manifest.sourceRoot);

const sha256 = (p: string) =>
  createHash("sha256").update(readFileSync(p)).digest("hex");

const errors: string[] = [];
let changed = false;

for (const entry of manifest.files) {
  const standalonePath = path.join(standaloneRoot, entry.standalone);
  const sourcePath = path.join(sourceRoot, entry.source);

  if (!existsSync(standalonePath)) {
    errors.push(`MISSING standalone file: ${entry.standalone}`);
    continue;
  }
  if (!existsSync(sourcePath)) {
    errors.push(
      `MISSING main-app source: ${manifest.sourceRoot}/${entry.source} ` +
        `(was it moved/deleted? update sync-manifest.json)`,
    );
    continue;
  }

  if (entry.relation === "identical") {
    if (
      !readFileSync(standalonePath).equals(readFileSync(sourcePath))
    ) {
      errors.push(
        `DRIFT (identical): ${entry.standalone} no longer matches ` +
          `${manifest.sourceRoot}/${entry.source} — copy the main-app file over ` +
          `(or reclassify as "adapted" if the divergence is now intentional).`,
      );
    }
    continue;
  }

  // adapted
  const currentHash = sha256(sourcePath);
  if (entry.sourceSha256 !== currentHash) {
    if (accept) {
      entry.sourceSha256 = currentHash;
      changed = true;
      console.log(`accepted: ${entry.source} -> ${currentHash.slice(0, 12)}…`);
    } else {
      errors.push(
        `DRIFT (adapted): main-app ${manifest.sourceRoot}/${entry.source} changed ` +
          `since the last sync. Diff it against ${entry.standalone}, apply what ` +
          `is relevant, then re-baseline with check:dialer-sync:accept.` +
          (entry.note ? `\n    intentional difference: ${entry.note}` : ""),
      );
    }
  }
}

for (const only of manifest.standaloneOnly) {
  if (!existsSync(path.join(standaloneRoot, only.path))) {
    errors.push(`MISSING standalone-only file: ${only.path}`);
  }
}

if (accept && changed) {
  writeFileSync(manifestPath, JSON.stringify(manifest, null, 2) + "\n");
  console.log(`updated ${path.relative(repoRoot, manifestPath)}`);
}

if (errors.length > 0) {
  console.error(
    `\nStandalone dialer is out of sync with artifacts/1inme-mobile ` +
      `(${errors.length} issue${errors.length === 1 ? "" : "s"}):\n`,
  );
  for (const e of errors) console.error(`  - ${e}\n`);
  console.error(
    "See sayzio-dialer-standalone/SYNC.md for the sync procedure.",
  );
  process.exit(1);
}

console.log(
  `Standalone dialer in sync: ${manifest.files.length} mapped files ` +
    `(+${manifest.standaloneOnly.length} standalone-only) OK.`,
);
