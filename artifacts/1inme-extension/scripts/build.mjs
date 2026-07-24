#!/usr/bin/env node
/**
 * Build the Zio Extension for one or more browser targets.
 *
 *   node scripts/build.mjs chrome
 *   node scripts/build.mjs firefox
 *   node scripts/build.mjs edge
 *   node scripts/build.mjs all
 *
 * Each target produces:
 *   dist/<browser>/        — unpacked extension (loadable via dev mode)
 *   dist/1inme-extension-<browser>.zip — store-ready zip
 */
import { spawn } from "node:child_process";
import { createWriteStream, cpSync, mkdirSync, rmSync, existsSync, copyFileSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import archiver from "archiver";

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const root = resolve(__dirname, "..");

const TARGETS = ["chrome", "firefox", "edge"];
const requested = (process.argv[2] || "chrome").toLowerCase();
const browsers = requested === "all" ? TARGETS : [requested];

for (const b of browsers) {
  if (!TARGETS.includes(b)) {
    console.error(`Unknown target: ${b}. Use one of: ${TARGETS.join(", ")}, all`);
    process.exit(1);
  }
}

function run(cmd, args, env) {
  return new Promise((resolve, reject) => {
    const proc = spawn(cmd, args, { cwd: root, stdio: "inherit", shell: false, env: { ...process.env, ...env } });
    proc.on("exit", (code) => (code === 0 ? resolve() : reject(new Error(`${cmd} exited ${code}`))));
    proc.on("error", reject);
  });
}

async function zipDir(srcDir, outZip) {
  await new Promise((resolve, reject) => {
    const output = createWriteStream(outZip);
    const archive = archiver("zip", { zlib: { level: 9 } });
    output.on("close", resolve);
    archive.on("error", reject);
    archive.pipe(output);
    archive.directory(srcDir, false);
    archive.finalize();
  });
}

async function buildOne(browser) {
  console.log(`\n▸ Building Zio Extension for ${browser}…`);
  const outDir = join(root, "dist", browser);
  if (existsSync(outDir)) rmSync(outDir, { recursive: true, force: true });

  // Vite build (popup + background + content scripts)
  await run("pnpm", ["exec", "vite", "build"], { EXT_BROWSER: browser });

  // Copy icons
  const iconsDest = join(outDir, "icons");
  mkdirSync(iconsDest, { recursive: true });
  cpSync(join(root, "public", "icons"), iconsDest, { recursive: true });

  // Manifest: chrome/edge share the chrome manifest; firefox uses its own
  const manifestSrc = browser === "firefox"
    ? join(root, "src", "manifest.firefox.json")
    : join(root, "src", "manifest.chrome.json");
  copyFileSync(manifestSrc, join(outDir, "manifest.json"));

  // Zip
  const zipPath = join(root, "dist", `1inme-extension-${browser}.zip`);
  if (existsSync(zipPath)) rmSync(zipPath, { force: true });
  await zipDir(outDir, zipPath);
  console.log(`  ✓ Unpacked: dist/${browser}/`);
  console.log(`  ✓ Zip:      dist/1inme-extension-${browser}.zip`);
}

async function main() {
  for (const b of browsers) {
    await buildOne(b);
  }
  console.log("\n✓ Done.");
}

main().catch((e) => { console.error(e); process.exit(1); });
