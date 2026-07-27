/**
 * Generate the committed version snapshot consumed by the admin
 * "Versions & Releases" page (App\Modules\Admin\Support\VersionRegistry).
 *
 * Surfaces like the mobile app, Zio Dialer, Zio Browser, the extension and the
 * api-server declare their versions in package.json / app.json files that are
 * NOT shipped with (or readable from) the deployed Laravel app, so this script
 * bakes them into artifacts/1inme/version-snapshot.json at merge/CI time.
 * The Laravel side treats a missing/stale snapshot gracefully ("unknown").
 *
 * Run via: pnpm --filter @workspace/scripts run generate:version-snapshot
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..", "..");

function readJson(rel: string): any | null {
  try {
    return JSON.parse(fs.readFileSync(path.join(root, rel), "utf8"));
  } catch {
    return null;
  }
}

function newestDocDate(relDir: string): string | null {
  try {
    const dir = path.join(root, relDir);
    let newest = 0;
    for (const f of fs.readdirSync(dir)) {
      if (!f.endsWith(".md")) continue;
      const st = fs.statSync(path.join(dir, f));
      if (st.mtimeMs > newest) newest = st.mtimeMs;
    }
    return newest ? new Date(newest).toISOString() : null;
  } catch {
    return null;
  }
}

const snapshot = {
  generated_at: new Date().toISOString(),
  surfaces: {
    marketing: readJson("artifacts/1inme-com/package.json")?.version ?? null,
    mobile: readJson("artifacts/1inme-mobile/app.json")?.expo?.version ?? null,
    dialer: readJson("sayzio-dialer-standalone/app.json")?.expo?.version ?? null,
    zio_browser: readJson("artifacts/zio-browser/package.json")?.version ?? null,
    extension: readJson("artifacts/1inme-extension/package.json")?.version ?? null,
    api_server: readJson("artifacts/api-server/package.json")?.version ?? null,
  } as Record<string, string | null>,
  docs_updated_at: newestDocDate("artifacts/1inme/docs"),
};

const outPath = path.join(root, "artifacts/1inme/version-snapshot.json");
fs.writeFileSync(outPath, JSON.stringify(snapshot, null, 2) + "\n");
console.log(`version snapshot written to ${path.relative(root, outPath)}`);
for (const [k, v] of Object.entries(snapshot.surfaces)) {
  console.log(`  ${k}: ${v ?? "(unknown)"}`);
}
