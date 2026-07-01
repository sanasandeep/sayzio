/**
 * AI tool-name drift guard (admin / back-office blades).
 *
 * The nine customer-facing AI tools now carry an "AI " prefix and are spelled
 * the SAME way in the customer app and the back-office admin screens:
 *
 *   AI Knowledge Bases · AI Coach · AI Voice Assistant · AI Personas ·
 *   AI Companions · AI Marketing Strategist · AI Inbox Agent · AI Brand Kit ·
 *   AI Resume
 *
 * Nothing else enforces that the two sides stay in sync. A future copy edit on
 * the admin side can silently reintroduce a bare / retired name (e.g. "Ask
 * Coach", "Knowledge Bases", "Voice Assistant") and recreate the internal
 * mismatch. This guard fails (exit 1) when an admin blade renders one of the
 * DISTINCTIVE tool-name phrases WITHOUT the required "AI " prefix.
 *
 * What is flagged
 * ---------------
 * A capitalized, multi-word tool-name phrase that is NOT immediately preceded
 * by "AI ":
 *
 *   Knowledge Base(s)    -> AI Knowledge Base(s)
 *   Ask Coach            -> AI Coach            (retired name — always wrong)
 *   Voice Assistant      -> AI Voice Assistant
 *   Marketing Strategist -> AI Marketing Strategist
 *   Inbox Agent          -> AI Inbox Agent
 *   Brand Kit            -> AI Brand Kit
 *   Persona Generator    -> AI Personas / AI Persona Generator
 *
 * Why only these multi-word phrases (and not bare "Personas" / "Companions" /
 * "Coach" / "Resume"): those single words are ordinary entity nouns that appear
 * legitimately all over the admin UI ("All Personas", "Disable Companion",
 * "Coach Defaults", the "Resume / Portfolio" link type). Guarding them would
 * flag correct copy. The distinctive phrases above unambiguously name the tool,
 * so a bare occurrence is real drift.
 *
 * Documented exceptions (respected automatically)
 * -----------------------------------------------
 *   - "AI Chat", "AI Agents", "Chat Widgets", "Site Assistant" — not one of the
 *     nine tools / out of scope; none of the guarded phrases match them.
 *   - Lowercase descriptive prose ("knowledge bases", "voice assistant") — the
 *     phrases are matched case-sensitively, so prose is never flagged.
 *   - Code comments — blade `{{-- --}}` and HTML `<!-- -->` comments are blanked
 *     before scanning (line/column positions preserved).
 *   - Wire tokens — route names (`admin.ask-coach.*`), feature tags
 *     (`ask_coach.*`), CSS classes, etc. are lowercase / underscored / hyphenated
 *     and never match the capitalized phrases.
 *   - Count badges — "{{ $n }} Knowledge Base(s)" stays bare (documented in
 *     .agents/memory/knowledge-bases-display-rename.md); a Knowledge Base(s)
 *     match preceded by a digit or a `}}` blade echo is allowed.
 *   - Anything in the ALLOWLIST below (add with a reason).
 *
 * Scope: every blade under artifacts/1inme/resources/views/admin/**. The
 * do-not-touch vendor/ views are excluded.
 *
 * Run:  pnpm --filter @workspace/scripts run check:ai-tool-names
 *       (add `--explain` to print the guarded phrases + exceptions and exit 0)
 */

import { fileURLToPath, pathToFileURL } from "node:url";
import { spawnSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

/** Admin blade root to scan (relative to repo root). */
const ADMIN_VIEWS_ROOT = "artifacts/1inme/resources/views/admin";

type ToolName = {
  /** The bare / retired form as a regex fragment (case-sensitive, word-bounded). */
  bare: string;
  /** The correct customer-facing spelling to suggest in the error. */
  canonical: string;
  /** Allow the count-badge form ("{{ $n }} Knowledge Base", "5 Knowledge Bases"). */
  countBadge?: boolean;
};

/**
 * Distinctive multi-word tool-name phrases. Order is cosmetic. The matcher
 * requires the phrase NOT be immediately preceded by "AI " (see buildRegex).
 */
const TOOL_NAMES: ToolName[] = [
  { bare: "Knowledge Bases?", canonical: "AI Knowledge Base(s)", countBadge: true },
  { bare: "Ask Coach", canonical: "AI Coach" },
  { bare: "Voice Assistant", canonical: "AI Voice Assistant" },
  { bare: "Marketing Strategist", canonical: "AI Marketing Strategist" },
  { bare: "Inbox Agent", canonical: "AI Inbox Agent" },
  { bare: "Brand Kit", canonical: "AI Brand Kit" },
  { bare: "Persona Generator", canonical: "AI Persona Generator" },
];

/**
 * Bare single-word tool labels that the DISTINCTIVE multi-word scan above
 * deliberately ignores (they double as ordinary entity nouns: "All Personas",
 * "Disable Companion", "Coach Defaults", the "Resume / Portfolio" link type).
 *
 * Those same words ARE the admin sidebar / page-title labels for the tools
 * (`admin.ai-personas`, `admin.ai-companions`, `admin.ask-coach`), so a copy
 * edit could revert a label to its bare form and the multi-word scan would not
 * notice. This second pass closes that gap by looking ONLY at label contexts
 * (section titles, nav-label / sidebar-tooltip spans, and AI-view headings) and
 * flagging a label whose WHOLE text is exactly a bare tool name.
 *
 * Whole-label (not substring) matching is what keeps the entity-noun false
 * positives out: "All Personas", "Coach usage & quality", "Coach Defaults" and
 * "AI Usage" are longer than / different from the bare tool name, so they never
 * match; only a label that is nothing but "Personas" / "Companions" / "Coach" /
 * "Resume" is drift.
 */
const LABEL_TOOL_NAMES: { bare: string; canonical: string }[] = [
  { bare: "Personas", canonical: "AI Personas" },
  { bare: "Companions", canonical: "AI Companions" },
  { bare: "Coach", canonical: "AI Coach" },
  { bare: "Resume", canonical: "AI Resume" },
];

/**
 * Admin view directories (relative to ADMIN_VIEWS_ROOT) whose own page headings
 * name an AI tool. Heading tags are only checked inside these — a bare tool-name
 * heading here is drift, whereas a generic heading elsewhere is not our concern.
 * `coach-defaults/` is intentionally NOT listed: its headings ("Coach Defaults",
 * "Score Presets") are entity nouns, not the AI Coach tool label.
 */
const AI_HEADING_DIRS = ["ai-personas", "ai-companions", "ask-coach", "ai-minds"];

type AllowEntry = { path: string; kind: "file" | "dir"; reason: string };

/**
 * Explicit allow-list of intentional bare occurrences within the admin views.
 * Empty today (the admin surfaces are in sync); documented here so a future
 * intentional exception is recorded with a reason instead of weakening the
 * regex.
 */
const ALLOWLIST: AllowEntry[] = [];

function isAllowed(file: string): boolean {
  const norm = file.split(path.sep).join("/");
  return ALLOWLIST.some((e) =>
    e.kind === "file" ? norm === e.path : norm === e.path || norm.startsWith(e.path + "/"),
  );
}

/**
 * Build the case-sensitive matcher for one tool name. The negative lookbehinds
 * exclude:
 *   - the correct "AI " prefix,
 *   - and, for count-badge-eligible names, a preceding digit or `}}` echo.
 */
function buildRegex(tool: ToolName): RegExp {
  const badges = tool.countBadge ? String.raw`(?<!\d )(?<!\}\} )(?<!\}\}\s)` : "";
  return new RegExp(String.raw`(?<!AI )${badges}\b(?:${tool.bare})\b`, "g");
}

/**
 * Blank out blade `{{-- --}}` and HTML `<!-- -->` comments so their contents
 * are never scanned, while preserving newlines (and column offsets) so reported
 * line/column numbers still point at the real source.
 */
export function blankComments(src: string): string {
  return src.replace(/\{\{--[\s\S]*?--\}\}|<!--[\s\S]*?-->/g, (m) =>
    m.replace(/[^\n]/g, " "),
  );
}

type Offender = { file: string; line: number; col: number; text: string; canonical: string };

/** Scan a single blade file's already-comment-blanked source. */
export function scanSource(relFile: string, src: string): Offender[] {
  const cleaned = blankComments(src);
  const lines = cleaned.split("\n");
  const rawLines = src.split("\n");
  const offenders: Offender[] = [];
  for (const tool of TOOL_NAMES) {
    const re = buildRegex(tool);
    for (let i = 0; i < lines.length; i++) {
      const line = lines[i] ?? "";
      re.lastIndex = 0;
      let m: RegExpExecArray | null;
      while ((m = re.exec(line)) !== null) {
        offenders.push({
          file: relFile,
          line: i + 1,
          col: m.index + 1,
          text: (rawLines[i] ?? "").trim(),
          canonical: tool.canonical,
        });
        if (m.index === re.lastIndex) re.lastIndex++;
      }
    }
  }
  return offenders;
}

/** 1-based line/column of a character index within `src`. */
function lineColAt(src: string, index: number): { line: number; col: number } {
  let line = 1;
  let lastNewline = -1;
  for (let i = 0; i < index; i++) {
    if (src[i] === "\n") {
      line++;
      lastNewline = i;
    }
  }
  return { line, col: index - lastNewline };
}

/** Decode the handful of HTML entities that appear in admin labels. */
function decodeEntities(s: string): string {
  return s
    .replace(/&amp;/g, "&")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/&quot;/g, '"')
    .replace(/&#0*39;|&apos;/g, "'")
    .replace(/&nbsp;/g, " ");
}

/**
 * Reduce a label's inner markup to its plain visible text: strip HTML/blade
 * tags, decode entities, collapse whitespace. Used for WHOLE-label equality so
 * "All Personas" / "Coach usage & quality" never collapse to a bare tool name.
 */
function normalizeLabel(inner: string): string {
  return decodeEntities(inner.replace(/<[^>]*>/g, " "))
    .replace(/\s+/g, " ")
    .trim();
}

/** True when `relFile` lives under one of the AI tool view directories. */
function isAiHeadingView(relFile: string): boolean {
  const norm = relFile.split(path.sep).join("/");
  return AI_HEADING_DIRS.some((dir) => norm.includes(`${ADMIN_VIEWS_ROOT}/${dir}/`));
}

/** Flag a normalized label if it is exactly a bare tool name (never prefixed). */
function checkLabel(
  relFile: string,
  src: string,
  matchIndex: number,
  rawLines: string[],
  label: string,
  offenders: Offender[],
): void {
  const hit = LABEL_TOOL_NAMES.find((t) => t.bare === label);
  if (!hit) return;
  const { line, col } = lineColAt(src, matchIndex);
  offenders.push({
    file: relFile,
    line,
    col,
    text: (rawLines[line - 1] ?? "").trim(),
    canonical: hit.canonical,
  });
}

/**
 * Scan the label contexts of one blade for bare (un-prefixed) tool names:
 *   - `@section('title'|'page-title', '<literal>')`
 *   - `<span class="… nav-label …">…</span>` / `sidebar-tooltip`
 *   - heading tags `<h1>…</h6>` — but only in the AI tool view directories.
 * Only WHOLE-label matches count, so entity-noun labels are never flagged.
 */
export function scanLabelContexts(relFile: string, src: string): Offender[] {
  const cleaned = blankComments(src);
  const rawLines = src.split("\n");
  const offenders: Offender[] = [];

  const sectionRe =
    /@section\(\s*(['"])(title|page-title)\1\s*,\s*(['"])([\s\S]*?)\3\s*\)/g;
  let m: RegExpExecArray | null;
  while ((m = sectionRe.exec(cleaned)) !== null) {
    checkLabel(relFile, cleaned, m.index, rawLines, normalizeLabel(m[4] ?? ""), offenders);
  }

  const spanRe =
    /<span\b[^>]*\bclass="[^"]*\b(?:nav-label|sidebar-tooltip)\b[^"]*"[^>]*>([\s\S]*?)<\/span>/g;
  while ((m = spanRe.exec(cleaned)) !== null) {
    checkLabel(relFile, cleaned, m.index, rawLines, normalizeLabel(m[1] ?? ""), offenders);
  }

  if (isAiHeadingView(relFile)) {
    const headingRe = /<(h[1-6])\b[^>]*>([\s\S]*?)<\/\1>/g;
    while ((m = headingRe.exec(cleaned)) !== null) {
      checkLabel(relFile, cleaned, m.index, rawLines, normalizeLabel(m[2] ?? ""), offenders);
    }
  }

  return offenders;
}

/** List every `*.blade.php` under the admin views root (excluding vendor/). */
function listAdminBlades(): string[] {
  const res = spawnSync(
    "rg",
    ["--files", "-g", "*.blade.php", "-g", "!**/vendor/**", ADMIN_VIEWS_ROOT],
    { cwd: REPO_ROOT, encoding: "utf8", maxBuffer: 32 * 1024 * 1024 },
  );
  if (res.error) {
    console.error("ai-tool-names guard: failed to list blades:", res.error.message);
    process.exit(2);
  }
  return res.stdout.split("\n").map((l) => l.trim()).filter(Boolean);
}

function printExplain(): void {
  console.log("AI tool-name drift guard — guarded phrases (must carry the 'AI ' prefix):\n");
  for (const t of TOOL_NAMES) {
    console.log(`  • ${t.bare.replace("s?", "(s)")}  ->  ${t.canonical}`);
  }
  console.log("\nBare single-word tool labels (must carry 'AI ' in label contexts only):");
  for (const t of LABEL_TOOL_NAMES) {
    console.log(`  • ${t.bare}  ->  ${t.canonical}`);
  }
  console.log(
    `  (label contexts: @section('title'|'page-title'), nav-label/sidebar-tooltip spans,\n   headings <h1..h6> in: ${AI_HEADING_DIRS.join(", ")}. WHOLE-label match only,`,
  );
  console.log("   so 'All Personas' / 'Coach usage & quality' / 'Coach Defaults' / 'AI Usage' pass.)");
  console.log("\nExceptions (never flagged):");
  console.log("  • AI Chat / AI Agents / Chat Widgets / Site Assistant (out of scope)");
  console.log("  • lowercase descriptive prose (matched case-sensitively)");
  console.log("  • blade {{-- --}} and HTML <!-- --> comments (blanked before scan)");
  console.log("  • wire tokens: route names, ask_coach.* feature tags, CSS classes");
  console.log("  • count badges: '{{ $n }} Knowledge Base(s)' (digit / }} before the name)");
  if (ALLOWLIST.length) {
    console.log("\nAllow-listed files:");
    for (const e of ALLOWLIST) console.log(`  • ${e.path}${e.kind === "dir" ? "/**" : ""} — ${e.reason}`);
  }
  console.log(`\nScope: ${ADMIN_VIEWS_ROOT}/**/*.blade.php`);
}

function main(): void {
  if (process.argv.includes("--explain")) {
    printExplain();
    process.exit(0);
  }

  const files = listAdminBlades();
  const offenders: Offender[] = [];
  for (const rel of files) {
    if (isAllowed(rel)) continue;
    let src: string;
    try {
      src = fs.readFileSync(path.join(REPO_ROOT, rel), "utf8");
    } catch {
      continue;
    }
    offenders.push(...scanSource(rel, src));
    offenders.push(...scanLabelContexts(rel, src));
  }

  if (offenders.length === 0) {
    console.log(
      "✓ ai-tool-names guard passed — admin AI tool names carry the 'AI ' prefix (customer/admin vocabulary in sync).",
    );
    process.exit(0);
  }

  console.error("✗ ai-tool-names guard FAILED — bare AI tool name(s) found in admin blades:\n");
  for (const o of offenders) {
    console.error(`  ${o.file}:${o.line}:${o.col}  (use "${o.canonical}")`);
    console.error(`      ${o.text}`);
  }
  console.error(
    `\n${offenders.length} match(es). Admin and customer copy must spell the nine AI tools the same way (with the "AI " prefix).`,
  );
  console.error(
    "If a bare occurrence is genuinely intentional, add the file to ALLOWLIST in scripts/src/check-ai-tool-names.ts with a reason.",
  );
  console.error(
    "Run `pnpm --filter @workspace/scripts run check:ai-tool-names -- --explain` to see guarded phrases and exceptions.",
  );
  process.exit(1);
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
