/**
 * Config-secrets guard.
 *
 * An old GitHub personal access token and a contact-inbox admin token were
 * once committed inside `.replit` (they live on in git history; both were
 * revoked/rotated). Nothing structural prevented a future secret from landing
 * in `.replit` or other tracked config again — this guard fails fast BEFORE a
 * new leak is committed.
 *
 * What it does
 * ------------
 * 1. Enumerates git-tracked config-ish files (`.replit`, `replit.nix`,
 *    `*.toml`, `*.yml|yaml`, `*.json`, `*.sh`, `*.env*`, `*.ini`, `*.conf`,
 *    `.github/**`) and scans every line for well-known secret token shapes:
 *    GitHub PATs (`github_pat_…`, `ghp_/gho_/ghu_/ghs_/ghr_…`), AWS access-key
 *    ids (`AKIA…`), OpenAI-style keys (`sk-…`), Slack tokens (`xox…`),
 *    PEM private-key blocks, JWTs, and credentials embedded in URLs.
 * 2. Additionally parses `.replit` `[userenv*]` sections and flags any quoted
 *    value that LOOKS like a secret (long + high-entropy / mixed-charset)
 *    even when it matches no known token prefix. Benign config shapes
 *    (URLs, lowercase bucket/region/domain names, booleans, numbers, plain
 *    words/sentences, obvious placeholders) are exempt so legit values do not
 *    false-positive.
 * 3. A finding is only tolerated when it is explicitly acknowledged in
 *    `ACKNOWLEDGED_FINDINGS` — pinned by file + key + sha256 of the EXACT
 *    value. Rotating the value or adding any new secret trips the guard.
 *
 * Run:  pnpm --filter @workspace/scripts run check:config-secrets
 */

import { fileURLToPath } from "node:url";
import { execFileSync } from "node:child_process";
import crypto from "node:crypto";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

/** Skip anything bigger than this — lockfiles/assets, not hand-edited config. */
const MAX_FILE_BYTES = 1_000_000;

export interface Finding {
  file: string;
  line: number;
  rule: string;
  /** Redacted excerpt — never print the full candidate secret. */
  excerpt: string;
  /** The raw matched value, used only for acknowledgment fingerprinting. */
  value: string;
  /** The config key the value was assigned to, when known (userenv rule). */
  key?: string;
}

/**
 * Known, deliberately-acknowledged findings. Each entry pins the sha256 of
 * the EXACT current value — if the value rotates (or any new secret appears)
 * the guard fails and forces a conscious decision.
 *
 * CONTACT_ADMIN_TOKEN: a shared env var the Replit platform writes back into
 * `.replit` `[userenv.shared]` (it reappears even if stripped here). GitHub
 * pushes are sanitized snapshots that strip it (see the sanitized-snapshot
 * push procedure), so it never reaches the public repo. Long-term fix is to
 * move it to a Replit Secret; until then this exact value is acknowledged.
 */
export const ACKNOWLEDGED_FINDINGS: ReadonlyArray<{
  file: string;
  key: string;
  sha256: string;
}> = [
  {
    file: ".replit",
    key: "CONTACT_ADMIN_TOKEN",
    sha256: "c58d19b4f44e8560417a8547bf5081888989acf02955e1ed8e6a670a365498ba",
  },
];

/** Well-known secret token shapes. Order matters only for reporting. */
export const TOKEN_PATTERNS: ReadonlyArray<{ rule: string; re: RegExp }> = [
  { rule: "github-fine-grained-pat", re: /github_pat_[A-Za-z0-9_]{22,}/g },
  { rule: "github-token", re: /\bgh[pousr]_[A-Za-z0-9]{36,}\b/g },
  { rule: "aws-access-key-id", re: /\bAKIA[0-9A-Z]{16}\b/g },
  { rule: "openai-style-key", re: /\bsk-[A-Za-z0-9_-]{24,}\b/g },
  { rule: "slack-token", re: /\bxox[baeprs]-[A-Za-z0-9-]{10,}\b/g },
  { rule: "private-key-block", re: /-----BEGIN [A-Z ]*PRIVATE KEY-----/g },
  { rule: "jwt", re: /\beyJ[A-Za-z0-9_-]{16,}\.eyJ[A-Za-z0-9_-]{8,}/g },
  { rule: "url-with-credentials", re: /\b[a-z][a-z0-9+.-]*:\/\/[^\s/:@"']+:([^\s@"']{8,})@/gi },
];

/**
 * Obvious placeholder/dummy values must never fail the guard — `.env.example`
 * files and docs legitimately show the SHAPE of a secret.
 */
export function isPlaceholder(value: string): boolean {
  return /(example|placeholder|change[-_]?me|your[-_]?|dummy|sample|redacted|stripped|xxxx|<[^>]+>|\.\.\.)/i.test(
    value,
  );
}

/** Shannon entropy in bits per character. */
export function shannonEntropy(s: string): number {
  if (s.length === 0) return 0;
  const counts = new Map<string, number>();
  for (const ch of s) counts.set(ch, (counts.get(ch) ?? 0) + 1);
  let h = 0;
  for (const n of counts.values()) {
    const p = n / s.length;
    h -= p * Math.log2(p);
  }
  return h;
}

/**
 * Benign config-value shapes that must NOT trip the high-entropy heuristic:
 * URLs, lowercase bucket/domain/region identifiers, booleans/numbers, paths,
 * and human-readable text with spaces.
 */
export function isBenignConfigValue(value: string): boolean {
  if (value.length < 20) return true;
  if (isPlaceholder(value)) return true;
  if (/^https?:\/\//i.test(value) && !/:[^/@]{8,}@/.test(value)) return true;
  if (/\s/.test(value)) return true; // sentences / labels
  if (/^[a-z0-9._\/-]+$/.test(value)) return true; // buckets, regions, domains, paths
  if (/^(true|false|\d+)$/i.test(value)) return true;
  return false;
}

/** Does a quoted `[userenv]` value look like a secret? */
export function looksLikeSecretValue(value: string): boolean {
  if (isBenignConfigValue(value)) return false;
  const mixedCharset =
    /[a-z]/.test(value) && /[A-Z]/.test(value) && /[0-9]/.test(value);
  return shannonEntropy(value) >= 3.5 || (mixedCharset && value.length >= 24);
}

function redact(value: string): string {
  if (value.length <= 8) return "********";
  return `${value.slice(0, 4)}…${value.slice(-2)} (${value.length} chars)`;
}

/** Scan any config file's content for well-known token shapes. */
export function scanForTokenPatterns(file: string, content: string): Finding[] {
  const findings: Finding[] = [];
  const lines = content.split("\n");
  lines.forEach((line, i) => {
    for (const { rule, re } of TOKEN_PATTERNS) {
      re.lastIndex = 0;
      let m: RegExpExecArray | null;
      while ((m = re.exec(line)) !== null) {
        const value = m[0];
        if (isPlaceholder(value)) continue;
        findings.push({ file, line: i + 1, rule, excerpt: redact(value), value });
      }
    }
  });
  return findings;
}

/**
 * Scan `.replit` content: in any `[userenv…]` section, flag quoted values that
 * look like secrets even without a known token prefix.
 */
export function scanReplitUserenv(file: string, content: string): Finding[] {
  const findings: Finding[] = [];
  let inUserenv = false;
  content.split("\n").forEach((line, i) => {
    const section = line.match(/^\s*\[+([^\]]+)\]+\s*$/);
    if (section) {
      inUserenv = section[1].startsWith("userenv");
      return;
    }
    if (!inUserenv) return;
    const kv = line.match(/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*"([^"]*)"\s*$/);
    if (!kv) return;
    const [, key, value] = kv;
    if (looksLikeSecretValue(value)) {
      findings.push({
        file,
        line: i + 1,
        rule: "userenv-high-entropy-value",
        excerpt: `${key} = ${redact(value)}`,
        value,
        key,
      });
    }
  });
  return findings;
}

/** Is this tracked path in scope as "config"? */
export function isConfigFile(rel: string): boolean {
  const base = path.basename(rel);
  if (base === ".replit" || base === "replit.nix" || base === ".replitignore") return true;
  if (rel.startsWith(".github/")) return true;
  if (/(^|\.)env(\.|$)/.test(base)) return true;
  return /\.(toml|ya?ml|json|sh|ini|conf)$/.test(base);
}

export function listConfigFiles(repoRoot = REPO_ROOT): string[] {
  const out = execFileSync("git", ["ls-files"], { cwd: repoRoot, encoding: "utf8" });
  const tracked = out.split("\n").filter(Boolean).filter(isConfigFile);
  // `.replit` is the primary target even if git ever stops tracking it.
  if (!tracked.includes(".replit") && fs.existsSync(path.join(repoRoot, ".replit"))) {
    tracked.push(".replit");
  }
  return tracked.sort();
}

export function isAcknowledged(f: Finding): boolean {
  const hash = crypto.createHash("sha256").update(f.value).digest("hex");
  return ACKNOWLEDGED_FINDINGS.some(
    (a) => a.file === f.file && (f.key === undefined || a.key === f.key) && a.sha256 === hash,
  );
}

export function scanRepo(repoRoot = REPO_ROOT): Finding[] {
  const findings: Finding[] = [];
  for (const rel of listConfigFiles(repoRoot)) {
    const abs = path.join(repoRoot, rel);
    let stat: fs.Stats;
    try {
      stat = fs.statSync(abs);
    } catch {
      continue; // deleted-but-still-listed
    }
    if (!stat.isFile() || stat.size > MAX_FILE_BYTES) continue;
    const content = fs.readFileSync(abs, "utf8");
    findings.push(...scanForTokenPatterns(rel, content));
    if (path.basename(rel) === ".replit") {
      findings.push(...scanReplitUserenv(rel, content));
    }
  }
  return findings.filter((f) => !isAcknowledged(f));
}

function main(): void {
  const findings = scanRepo();

  if (findings.length === 0) {
    console.log(
      "✓ config-secrets guard passed — no secret-looking values found in tracked config files.",
    );
    process.exit(0);
  }

  console.error("✗ config-secrets guard FAILED — possible secrets in tracked config:\n");
  for (const f of findings) {
    console.error(`  [${f.rule}] ${f.file}:${f.line} — ${f.excerpt}`);
  }
  console.error(
    "\nNever commit real credentials. Move the value to a Replit Secret (or rotate it if it" +
      "\nwas already committed), then re-run. If a finding is a false positive, either adjust" +
      "\nthe benign-value exemptions or (for a deliberately-tracked known value) add a pinned" +
      "\nsha256 acknowledgment in ACKNOWLEDGED_FINDINGS in scripts/src/check-config-secrets.ts.",
  );
  process.exit(1);
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  main();
}
