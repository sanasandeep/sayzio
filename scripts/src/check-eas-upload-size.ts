/**
 * EAS upload-size guard.
 *
 * The root `.easignore` was trimmed to cut the EAS build upload from 564 MB
 * to ~43 MB. Nothing stops a new large directory (exports, screenshots, a new
 * artifact, media dumps) added at the workspace root from silently
 * re-inflating future uploads. This guard estimates the would-be EAS archive
 * size — the total size of files NOT excluded by the root `.easignore` — and
 * fails above a configurable threshold.
 *
 * Notes on fidelity: EAS uses `.easignore` (when present it fully replaces
 * .gitignore-based exclusion) with gitignore-style semantics. This guard
 * implements the subset of gitignore matching the repo actually uses
 * (root-relative paths, `*`/`**` globs, bare-name patterns matching at any
 * depth, trailing-`/` dir patterns, `!` negation) which is a close-enough
 * proxy: the goal is catching multi-MB regressions, not byte-exact parity.
 * `.git` is always excluded, matching EAS behavior.
 *
 * Threshold: 100 MB by default; override with `EAS_UPLOAD_MAX_MB` env var or
 * `--max-mb <n>`.
 *
 * Run:  pnpm --filter @workspace/scripts run check:eas-upload-size
 */

import { fileURLToPath } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

const DEFAULT_MAX_MB = 100;

export interface IgnoreRule {
  negated: boolean;
  /** Regex tested against the root-relative POSIX path (no leading slash). */
  regex: RegExp;
  /** Pattern only matches directories (trailing slash in the source). */
  dirOnly: boolean;
  /** Exact-path match (no descendant suffix), used for the dir-only file exemption. */
  exactRegex: RegExp;
  source: string;
}

/** Convert one gitignore-style glob into a regex source (no anchors). */
function globToRegexSource(glob: string): string {
  let out = "";
  for (let i = 0; i < glob.length; i++) {
    const ch = glob[i];
    if (ch === "*") {
      if (glob[i + 1] === "*") {
        // `**` — matches across path separators.
        i++;
        // Collapse `**/` to allow zero directories.
        if (glob[i + 1] === "/") {
          i++;
          out += "(?:.*/)?";
        } else {
          out += ".*";
        }
      } else {
        out += "[^/]*";
      }
    } else if (ch === "?") {
      out += "[^/]";
    } else {
      out += ch.replace(/[.+^${}()|[\]\\]/g, "\\$&");
    }
  }
  return out;
}

export function parseEasignore(content: string): IgnoreRule[] {
  const rules: IgnoreRule[] = [];
  for (const rawLine of content.split(/\r?\n/)) {
    let line = rawLine;
    if (!line.trim() || line.trimStart().startsWith("#")) continue;
    line = line.trim();

    let negated = false;
    if (line.startsWith("!")) {
      negated = true;
      line = line.slice(1);
    }

    let dirOnly = false;
    if (line.endsWith("/")) {
      dirOnly = true;
      line = line.slice(0, -1);
    }

    let anchored = line.startsWith("/");
    if (anchored) line = line.slice(1);
    // A slash anywhere (not just leading) anchors the pattern to the root.
    if (line.includes("/")) anchored = true;

    const body = globToRegexSource(line);
    // For unanchored patterns that began with `**/`, the (?:^|/) prefix
    // already covers "any depth", so strip a leading `(?:.*/)?`.
    const unanchoredBody = body.replace(/^\(\?:\.\*\/\)\?/, "");
    const prefix = anchored ? `^${body}` : `(?:^|/)${unanchoredBody}`;

    rules.push({
      negated,
      // Match the path itself, or anything beneath it (dir prefix match).
      regex: new RegExp(`${prefix}(?:/.*)?$`),
      exactRegex: new RegExp(`${prefix}$`),
      dirOnly,
      source: rawLine.trim(),
    });
  }
  return rules;
}

/** Is the root-relative POSIX path ignored by the rules? (last match wins) */
export function isIgnored(relPath: string, isDir: boolean, rules: IgnoreRule[]): boolean {
  let ignored = false;
  for (const rule of rules) {
    if (rule.regex.test(relPath)) {
      // dirOnly patterns still ignore files *under* a matching directory
      // (the regex's `(?:/.*)?` suffix covers that); only a plain FILE whose
      // full path matches the pattern exactly is exempt from a dir-only rule.
      if (rule.dirOnly && !isDir && rule.exactRegex.test(relPath)) continue;
      ignored = !rule.negated;
    }
  }
  return ignored;
}

export interface SizeEntry {
  path: string;
  bytes: number;
}

export interface ScanResult {
  totalBytes: number;
  fileCount: number;
  /** bytes attributed to each top-level entry that contributes anything */
  topLevel: SizeEntry[];
  largestFiles: SizeEntry[];
}

export function scan(root: string, rules: IgnoreRule[]): ScanResult {
  const hasNegation = rules.some((r) => r.negated);
  let totalBytes = 0;
  let fileCount = 0;
  const topLevel = new Map<string, number>();
  const largestFiles: SizeEntry[] = [];

  const addLargest = (entry: SizeEntry) => {
    largestFiles.push(entry);
    largestFiles.sort((a, b) => b.bytes - a.bytes);
    if (largestFiles.length > 15) largestFiles.pop();
  };

  const walk = (dirAbs: string, dirRel: string) => {
    let entries: fs.Dirent[];
    try {
      entries = fs.readdirSync(dirAbs, { withFileTypes: true });
    } catch {
      return;
    }
    for (const entry of entries) {
      const rel = dirRel ? `${dirRel}/${entry.name}` : entry.name;
      if (rel === ".git") continue;
      if (entry.isSymbolicLink()) continue;
      if (entry.isDirectory()) {
        // Prune ignored directories (safe when no negation rules exist).
        if (isIgnored(rel, true, rules) && !hasNegation) continue;
        walk(path.join(dirAbs, entry.name), rel);
      } else if (entry.isFile()) {
        if (isIgnored(rel, false, rules)) continue;
        let size = 0;
        try {
          size = fs.statSync(path.join(dirAbs, entry.name)).size;
        } catch {
          continue;
        }
        totalBytes += size;
        fileCount++;
        const top = rel.includes("/") ? rel.slice(0, rel.indexOf("/")) : rel;
        topLevel.set(top, (topLevel.get(top) ?? 0) + size);
        addLargest({ path: rel, bytes: size });
      }
    }
  };

  walk(root, "");

  return {
    totalBytes,
    fileCount,
    topLevel: [...topLevel.entries()]
      .map(([p, bytes]) => ({ path: p, bytes }))
      .sort((a, b) => b.bytes - a.bytes),
    largestFiles,
  };
}

export function formatMb(bytes: number): string {
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function main() {
  const argv = process.argv.slice(2);
  const flagIdx = argv.indexOf("--max-mb");
  const maxMb =
    flagIdx !== -1 && argv[flagIdx + 1]
      ? Number(argv[flagIdx + 1])
      : process.env.EAS_UPLOAD_MAX_MB
        ? Number(process.env.EAS_UPLOAD_MAX_MB)
        : DEFAULT_MAX_MB;
  if (!Number.isFinite(maxMb) || maxMb <= 0) {
    console.error(`check:eas-upload-size — invalid threshold "${maxMb}"`);
    process.exit(2);
  }

  const easignorePath = path.join(REPO_ROOT, ".easignore");
  if (!fs.existsSync(easignorePath)) {
    console.error(
      "check:eas-upload-size — FAIL: root .easignore is missing. Without it, EAS uploads the entire workspace (previously 564 MB).",
    );
    process.exit(1);
  }

  const rules = parseEasignore(fs.readFileSync(easignorePath, "utf8"));
  const result = scan(REPO_ROOT, rules);
  const maxBytes = maxMb * 1024 * 1024;

  console.log(
    `Estimated EAS upload: ${formatMb(result.totalBytes)} across ${result.fileCount} files (threshold ${maxMb} MB)`,
  );

  if (result.totalBytes <= maxBytes) {
    console.log("check:eas-upload-size — OK");
    return;
  }

  console.error("");
  console.error(
    `check:eas-upload-size — FAIL: estimated upload ${formatMb(result.totalBytes)} exceeds ${maxMb} MB.`,
  );
  console.error("");
  console.error("Largest contributors by top-level entry:");
  for (const entry of result.topLevel.slice(0, 10)) {
    console.error(`  ${formatMb(entry.bytes).padStart(10)}  ${entry.path}`);
  }
  console.error("");
  console.error("Largest individual files not excluded by .easignore:");
  for (const entry of result.largestFiles.slice(0, 10)) {
    console.error(`  ${formatMb(entry.bytes).padStart(10)}  ${entry.path}`);
  }
  console.error("");
  console.error(
    "Fix: add the offending directories/files to the root .easignore (they are not needed inside the EAS build), or raise the threshold consciously via EAS_UPLOAD_MAX_MB.",
  );
  process.exit(1);
}

const isDirectRun =
  process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (isDirectRun) main();
