#!/usr/bin/env node
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const routes = new Set();
function walk(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) walk(p);
    else if (e.name.endsWith(".tsx")) {
      const parts = path
        .relative(path.join(root, "app"), p)
        .replace(/\.tsx$/, "")
        .split(path.sep)
        .filter((p) => !p.startsWith("("));
      if (parts.at(-1) === "index") parts.pop();
      routes.add("/" + parts.join("/"));
    }
  }
}
walk(path.join(root, "app"));
const src = fs
  .readFileSync(path.join(root, "components/DrawerSidebar.tsx"), "utf8")
  .split("const NAV_GROUPS: NavGroup[] = [")[1]
  .split("\n];")[0];
const missing = [];
let checked = 0;
for (const entry of src.matchAll(
  /\{\s*label:\s*"([^"]+)"[^}]*?href:\s*"([^"]+)"[^}]*?\}/g,
)) {
  const [raw, label, href] = entry;
  if (/webPath:/.test(raw) || /soon:\s*true/.test(raw)) continue;
  checked++;
  if (!routes.has(href)) missing.push(`${label}: ${href}`);
}
if (missing.length) {
  console.error("Missing native drawer routes:\n" + missing.join("\n"));
  process.exit(1);
}
console.log(
  `PASS: ${checked} native drawer destinations resolve to app screens. Web-only destinations are explicit.`,
);
