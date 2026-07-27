/**
 * Background-preset swatch freshness guard.
 *
 * The mobile background-preset gallery shows real pre-rendered PNG textures
 * from `artifacts/1inme/public/img/bg-preset-swatches/`. Alongside the PNGs,
 * `manifest.json` maps each preset key to the md5 of the CSS the thumbnail
 * was rendered from, and `BgPresetCatalog::forApi()` only advertises a
 * thumbnail when the manifest hash still matches the live CSS.
 *
 * The failure this guards against
 * --------------------------------
 * If someone edits or adds presets in `BgPresetCatalog.php` and forgets to
 * regenerate the thumbnails, those presets silently degrade to the flat
 * LinearGradient tint approximation — no error anywhere, the swatch just
 * looks wrong/plain on mobile.
 *
 * What this check enforces (fast — dumps the catalog with plain `php`, the
 * class has no framework dependencies; no server, no browser)
 * ---------------------------------------------------------------------------
 *   1. Every preset key in BgPresetCatalog::all() has a manifest entry whose
 *      md5 matches md5 of the preset's live CSS (missing or stale ⇒ FAIL).
 *   2. Every preset has its rendered `{key}.png` on disk, and the file is a
 *      real PNG (valid 8-byte PNG signature) of plausible size (> 200 bytes)
 *      — a zero-byte or truncated/corrupt file from an interrupted generator
 *      run would otherwise pass and render as a blank/broken swatch (missing,
 *      empty, corrupt ⇒ FAIL).
 *   3. Manifest entries for keys the catalog no longer contains are reported
 *      as removable leftovers (also FAIL, so the fix command cleans them up).
 *
 * Fix is one command:
 *   pnpm --filter @workspace/1inme-mobile run generate:bg-preset-swatches
 *
 * Run:  pnpm --filter @workspace/scripts run check:bg-preset-swatches
 */

import { createHash } from "node:crypto";
import { spawnSync } from "node:child_process";
import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

export const CATALOG_REL = "artifacts/1inme/app/Modules/User/Support/BgPresetCatalog.php";
export const SWATCH_DIR_REL = "artifacts/1inme/public/img/bg-preset-swatches";
export const MANIFEST_REL = `${SWATCH_DIR_REL}/manifest.json`;

export const FIX_COMMAND =
  "pnpm --filter @workspace/1inme-mobile run generate:bg-preset-swatches";

export function md5(s: string): string {
  return createHash("md5").update(s).digest("hex");
}

/**
 * Dump the live catalog (key => css) straight from BgPresetCatalog.php with
 * plain `php` — the class has no framework dependencies. Same mechanism the
 * generator script uses, so the guard hashes exactly what it renders.
 */
export function loadCatalogCss(): Record<string, string> {
  const catalogPhp = path.join(REPO_ROOT, CATALOG_REL);
  const code = `require ${JSON.stringify(catalogPhp)}; $out=[]; foreach (\\App\\Modules\\User\\Support\\BgPresetCatalog::all() as $k=>$p) { $out[$k]=$p['css']; } echo json_encode($out);`;
  const res = spawnSync("php", ["-r", code], { encoding: "utf8", timeout: 30_000 });
  if (res.error || res.status !== 0) {
    throw new Error(
      `could not dump BgPresetCatalog: ${res.error?.message || res.stderr || res.status}`,
    );
  }
  const all = JSON.parse(res.stdout) as unknown;
  if (!all || typeof all !== "object" || Array.isArray(all)) {
    throw new Error("catalog dump was not an object");
  }
  return all as Record<string, string>;
}

export type SwatchProblem = { kind: string; key?: string; detail: string };

/** 8-byte PNG file signature: \x89PNG\r\n\x1a\n */
export const PNG_SIGNATURE = Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);

/** Anything smaller than this cannot be a real rendered swatch. */
export const MIN_PNG_BYTES = 200;

/** What the guard needs to know about a swatch PNG on disk. */
export type PngInfo = {
  /** Total file size in bytes. */
  size: number;
  /** First bytes of the file (at least 8) for signature validation. */
  head: Buffer;
};

export function inspectPng(file: string): PngInfo | null {
  if (!fs.existsSync(file)) return null;
  const size = fs.statSync(file).size;
  const fd = fs.openSync(file, "r");
  try {
    const head = Buffer.alloc(Math.min(size, PNG_SIGNATURE.length));
    fs.readSync(fd, head, 0, head.length, 0);
    return { size, head };
  } finally {
    fs.closeSync(fd);
  }
}

/**
 * Pure comparison so the test suite can pin behavior without shelling out.
 * `pngInfo` answers "what does {key}.png look like on disk" (null = missing).
 */
export function checkSwatchFreshness(
  catalogCss: Record<string, string>,
  manifest: Record<string, string>,
  pngInfo: (key: string) => PngInfo | null,
): SwatchProblem[] {
  const problems: SwatchProblem[] = [];

  const keys = Object.keys(catalogCss);
  if (keys.length === 0) {
    problems.push({
      kind: "empty-catalog",
      detail:
        "Parsed zero presets from BgPresetCatalog::all() — the catalog dump changed shape. Update this guard.",
    });
    return problems;
  }

  for (const key of keys) {
    const want = md5(catalogCss[key]);
    const have = manifest[key];
    if (have === undefined) {
      problems.push({
        kind: "missing-manifest-entry",
        key,
        detail: `preset "${key}" has no manifest entry — its thumbnail was never generated, so mobile falls back to the gradient tint.`,
      });
    } else if (have !== want) {
      problems.push({
        kind: "stale-manifest-hash",
        key,
        detail: `preset "${key}" CSS changed since its thumbnail was rendered (manifest md5 ${have} != live ${want}) — mobile silently falls back to the gradient tint.`,
      });
    }
    const info = pngInfo(key);
    if (info === null) {
      problems.push({
        kind: "missing-png",
        key,
        detail: `preset "${key}" is missing its rendered ${key}.png in ${SWATCH_DIR_REL}.`,
      });
    } else if (info.size < MIN_PNG_BYTES) {
      problems.push({
        kind: "empty-png",
        key,
        detail: `preset "${key}"'s ${key}.png is only ${info.size} bytes (< ${MIN_PNG_BYTES}) — an empty/truncated file from an interrupted generator run; it would render as a blank swatch on mobile.`,
      });
    } else if (
      info.head.length < PNG_SIGNATURE.length ||
      !info.head.subarray(0, PNG_SIGNATURE.length).equals(PNG_SIGNATURE)
    ) {
      problems.push({
        kind: "corrupt-png",
        key,
        detail: `preset "${key}"'s ${key}.png does not start with a valid PNG signature — the file is corrupt and would render as a broken swatch on mobile.`,
      });
    }
  }

  for (const key of Object.keys(manifest)) {
    if (!(key in catalogCss)) {
      problems.push({
        kind: "orphan-manifest-entry",
        key,
        detail: `manifest.json contains "${key}" but BgPresetCatalog::all() no longer does — regenerate to drop the leftover entry/PNG.`,
      });
    }
  }

  return problems;
}

export function loadManifest(): Record<string, string> {
  const file = path.join(REPO_ROOT, MANIFEST_REL);
  if (!fs.existsSync(file)) return {};
  const parsed = JSON.parse(fs.readFileSync(file, "utf8")) as unknown;
  if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) return {};
  const out: Record<string, string> = {};
  for (const [k, v] of Object.entries(parsed as Record<string, unknown>)) {
    if (typeof v === "string") out[k] = v;
  }
  return out;
}

function main(): void {
  let catalogCss: Record<string, string>;
  try {
    catalogCss = loadCatalogCss();
  } catch (e) {
    console.error(`bg-preset-swatches guard: ${(e as Error).message}`);
    process.exit(2);
  }

  const manifest = loadManifest();
  const swatchDir = path.join(REPO_ROOT, SWATCH_DIR_REL);
  const pngInfo = (key: string) => inspectPng(path.join(swatchDir, `${key}.png`));

  const problems = checkSwatchFreshness(catalogCss, manifest, pngInfo);

  if (problems.length === 0) {
    console.log(
      `✓ bg-preset-swatches guard passed — all ${Object.keys(catalogCss).length} preset thumbnails are fresh (manifest md5s match the live catalog CSS).`,
    );
    process.exit(0);
  }

  console.error("✗ bg-preset-swatches guard FAILED:\n");
  for (const p of problems) {
    console.error(`  [${p.kind}] ${p.detail}`);
  }
  console.error(
    `\n${problems.length} problem(s). Stale/missing thumbnails make those presets silently fall back to the flat gradient tint on mobile.`,
  );
  console.error(`Fix is one command:\n  ${FIX_COMMAND}\nthen commit the regenerated PNGs + manifest.json.`);
  process.exit(1);
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
