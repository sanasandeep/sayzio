/**
 * Unbalanced-parenthesis copy guard.
 *
 * Fails (exit 1) when user-visible copy in a quoted string literal inside a
 * Blade view has unbalanced parentheses. This catches the class of bug where
 * a bulk copy rewrite (e.g. the em-dash sweep) paired dashes into parentheses
 * across PHP/JS string boundaries — for example a ternary whose two branches
 * ended up as 'Editing existing plan (make sure...' : 'New plan) values...' —
 * producing broken user-visible copy that php -l and blade guards cannot see.
 *
 * What is scanned
 * ---------------
 *   artifacts/1inme/resources/views/{user,admin,common,public,errors,emails}
 *   (resources/views/vendor/ is do-not-touch and excluded)
 *
 * How it works
 * ------------
 *   1. Comments are blanked (C-style, Blade {{-- --}}, HTML <!-- -->, //
 *      lines) while preserving newlines so line numbers stay accurate.
 *   2. Blade output expressions {{ ... }} / {!! ... !!} and directive calls
 *      like @json(...) / @js(...) are blanked too — their contents live in a
 *      different quoting context and were the main false-positive source.
 *   3. Single-quoted string literals in code-like positions are extracted and
 *      grouped into CONCATENATION CHAINS: consecutive literals whose gap text
 *      contains only concatenation-ish tokens (whitespace, . + identifiers,
 *      calls, indexes). A gap containing a ternary ?/:, an assignment, a
 *      statement end, etc. breaks the chain — which is exactly what separates
 *      legit split strings ('(' . $x . ')') from the broken-ternary bug.
 *   4. A chain whose total '(' vs ')' count is unbalanced is an offender if
 *      it contains prose-like text (a space plus several letters). Pure code
 *      fragments like 'rgba(61,107,255,' are ignored.
 *
 * Allowlists: CONTENT_ALLOW predicates (emoticons, regex, code samples) and
 * LINE_ALLOWLIST exact "path:line" entries for cases heuristics can't judge.
 *
 * Run:  pnpm --filter @workspace/scripts run check:unbalanced-parens
 */

import { spawnSync } from "node:child_process";
import { fileURLToPath } from "node:url";
import fs from "node:fs";
import path from "node:path";

const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

/** Scan roots (relative to repo root). vendor/ is excluded via globs. */
const SCAN_TARGETS: string[] = [
  "artifacts/1inme/resources/views/user",
  "artifacts/1inme/resources/views/admin",
  "artifacts/1inme/resources/views/common",
  "artifacts/1inme/resources/views/public",
  "artifacts/1inme/resources/views/errors",
  "artifacts/1inme/resources/views/emails",
];

const EXCLUDE_GLOBS: string[] = ["!**/node_modules/**", "!**/vendor/**"];

/**
 * Per-chain allowlist: the chain is skipped when any predicate matches its
 * combined literal text. Add predicates here for legitimate unbalanced cases.
 */
const CONTENT_ALLOW: Array<(s: string) => boolean> = [
  // Emoticons: :) :( ;) :-) (: etc.
  (s) => /(^|[\s"'])[:;]-?[()]/.test(s) || /\([:;-]|[()]-?[:;]([\s"']|$)/.test(s),
  // Regex-looking strings: /.../flags or escape-heavy char classes
  (s) => /^\/.*\/[a-z]*$/i.test(s) || /\\[dwsDWS]|\\\(|\\\)/.test(s),
  // Mixed-quote JS code fragments: a phantom "literal" formed by an
  // apostrophe inside a double-quoted string (e.g. a regex like /'/g inside
  // "..." concatenation) — `" + expr` / `expr + "` is code, never copy.
  (s) => /"\s*\+|\+\s*"/.test(s),
];

/**
 * Exact allowlist entries as "relative/path.blade.php:LINE" for legitimate
 * cases the heuristics can't classify (a paren pair that intentionally spans
 * a ternary, embedded code samples in docs pages, etc.).
 */
const LINE_ALLOWLIST = new Set<string>([
  // width/transform style string: the '(' opened before a nested ternary is
  // closed after it — legit JS, not broken copy.
  "artifacts/1inme/resources/views/user/layouts/app.blade.php:458",
]);

function blank(m: string): string {
  return m.replace(/[^\n]/g, " ");
}

function blankComments(src: string): string {
  let out = src.replace(/\/\*[\s\S]*?\*\//g, blank);
  out = out.replace(/\{\{--[\s\S]*?--\}\}|<!--[\s\S]*?-->/g, blank);
  out = out.replace(/(^|[^:])(\/\/[^\n]*)/gm, (_m, pre: string, cmt: string) => pre + " ".repeat(cmt.length));
  return out;
}

type Span = { start: number; end: number; inner: string; innerStart: number };

/**
 * Find Blade output expressions {{ ... }} / {!! ... !!} and directive calls
 * @word( balanced-parens ). Their contents are a separate quoting context
 * (e.g. '{{ route('x') }}' inside a JS string), so they are blanked from the
 * outer scan and scanned separately as PHP code (where the broken-ternary
 * bug class actually lives).
 */
function bladeExpressionSpans(src: string): Span[] {
  const spans: Span[] = [];
  const exprRe = /\{\{([\s\S]*?)\}\}|\{!!([\s\S]*?)!!\}/g;
  let m: RegExpExecArray | null;
  while ((m = exprRe.exec(src)) !== null) {
    const inner = m[1] ?? m[2] ?? "";
    const innerStart = m.index + (m[1] !== undefined ? 2 : 3);
    spans.push({ start: m.index, end: m.index + m[0].length, inner, innerStart });
  }
  const dirRe = /@[a-zA-Z_]+\s*\(/g;
  let dm: RegExpExecArray | null;
  while ((dm = dirRe.exec(src)) !== null) {
    // Skip if inside an already-collected span.
    if (spans.some((s) => dm!.index >= s.start && dm!.index < s.end)) continue;
    let depth = 0;
    let i = dm.index + dm[0].length - 1; // at the '('
    let inStr: string | null = null;
    for (; i < src.length; i++) {
      const c = src[i]!;
      if (inStr) {
        if (c === "\\") i++;
        else if (c === inStr) inStr = null;
        continue;
      }
      if (c === "'" || c === '"') inStr = c;
      else if (c === "(") depth++;
      else if (c === ")") {
        depth--;
        if (depth === 0) break;
      }
    }
    if (depth !== 0) continue; // unterminated; leave as-is
    const argStart = dm.index + dm[0].length;
    spans.push({
      start: dm.index,
      end: i + 1,
      inner: src.slice(argStart, i),
      innerStart: argStart,
    });
    dirRe.lastIndex = i + 1;
  }
  return spans.sort((a, b) => a.start - b.start);
}

function blankSpans(src: string, spans: Span[]): string {
  const chars = src.split("");
  for (const s of spans) {
    for (let k = s.start; k < s.end; k++) {
      if (chars[k] !== "\n") chars[k] = " ";
    }
  }
  return chars.join("");
}

type Lit = { start: number; end: number; body: string; line: number };
type Chain = { file: string; line: number; lits: Lit[]; balance: number };

/** Characters that mark a code context right before a quoted literal. */
const CODE_PREFIX = new Set(["(", ",", "=", "[", ".", "?", ":", ">", "+", "{", ";", "!", "&", "|"]);

function isCodeContext(src: string, quoteIdx: number): boolean {
  for (let i = quoteIdx - 1; i >= 0; i--) {
    const c = src[i]!;
    if (c === " " || c === "\t" || c === "\n" || c === "\r") continue;
    return CODE_PREFIX.has(c);
  }
  return false;
}

/**
 * Is the text between two literals pure concatenation glue? Allowed: ws,
 * . + concat, identifiers, $vars, -> and :: member access, ?? and || and &&,
 * calls/indexes, numbers, arithmetic. Forbidden: ternary ? :, assignment =,
 * statement ends ; , double quotes, braces, tags — those end the expression.
 */
function isConcatGap(gap: string): boolean {
  const g = gap.replace(/->|::|\?\?|\|\||&&|=>/g, "");
  if (/[?:;="`<>{}@#]/.test(g)) return false;
  return /^[\s\w$.,+\-*/%!&|()\[\]\\]*$/.test(g);
}

/** Prose-like: contains a space and at least 3 letters (visible copy). */
function isProse(s: string): boolean {
  return /[ \t]/.test(s.trim()) && (s.match(/[a-zA-Z]/g) ?? []).length >= 3;
}

function parenBalance(s: string): number {
  return (s.match(/\(/g) ?? []).length - (s.match(/\)/g) ?? []).length;
}

/**
 * Extract single-quoted literals from `text`, group them into concatenation
 * chains, and return the chains. `lineOffset` maps positions in `text` back
 * to real file lines. When `forceCode` is true every literal is treated as
 * code (used for Blade expression interiors, which are always PHP).
 */
function chainsInText(
  relPath: string,
  text: string,
  lineOffsetOf: (idx: number) => number,
  forceCode: boolean,
): Chain[] {
  const lits: Lit[] = [];
  const re = /'((?:[^'\\\n]|\\.)*)'/g;
  let m: RegExpExecArray | null;
  while ((m = re.exec(text)) !== null) {
    lits.push({
      start: m.index,
      end: m.index + m[0].length,
      body: m[1]!,
      line: lineOffsetOf(m.index),
    });
  }

  const chains: Chain[] = [];
  let cur: Lit[] = [];
  const flush = () => {
    if (cur.length === 0) return;
    // A chain is only considered if its first literal sits in a code context
    // (kills phantom "literals" formed by prose apostrophes in HTML text).
    if (forceCode || isCodeContext(text, cur[0]!.start)) {
      chains.push({
        file: relPath,
        line: cur[0]!.line,
        lits: cur,
        balance: cur.reduce((n, l) => n + parenBalance(l.body), 0),
      });
    }
    cur = [];
  };
  for (const lit of lits) {
    if (cur.length > 0) {
      const gap = text.slice(cur[cur.length - 1]!.end, lit.start);
      if (!isConcatGap(gap)) flush();
    }
    cur.push(lit);
  }
  flush();
  return chains;
}

function scanFile(relPath: string): Chain[] {
  const abs = path.join(REPO_ROOT, relPath);
  let src: string;
  try {
    src = fs.readFileSync(abs, "utf8");
  } catch {
    return [];
  }
  const noComments = blankComments(src);
  const spans = bladeExpressionSpans(noComments);
  const outer = blankSpans(noComments, spans);

  const lineAt = (base: string, offset: number) => (idx: number) =>
    base.slice(0, offset + idx).split("\n").length;

  const chains: Chain[] = [
    // Outer document (HTML/JS/@php) with blade expressions blanked out.
    ...chainsInText(relPath, outer, lineAt(noComments, 0), false),
  ];
  // Blade expression interiors are PHP code — scan them in code context.
  for (const s of spans) {
    chains.push(...chainsInText(relPath, s.inner, lineAt(noComments, s.innerStart), true));
  }

  return chains.filter((c) => {
    if (c.balance === 0) return false;
    const combined = c.lits.map((l) => l.body).join(" ");
    if (!c.lits.some((l) => isProse(l.body))) return false;
    if (CONTENT_ALLOW.some((fn) => fn(combined))) return false;
    if (LINE_ALLOWLIST.has(`${c.file}:${c.line}`)) return false;
    return true;
  });
}

function listFiles(): string[] {
  const args = ["--files", "-g", "*.blade.php"];
  for (const g of EXCLUDE_GLOBS) args.push("-g", g);
  args.push(...SCAN_TARGETS);

  const res = spawnSync("rg", args, {
    cwd: REPO_ROOT,
    encoding: "utf8",
    maxBuffer: 64 * 1024 * 1024,
  });
  if (res.error) {
    console.error("unbalanced-parens guard: failed to list files:", res.error.message);
    process.exit(2);
  }
  if (res.status === 2) {
    console.error("unbalanced-parens guard: ripgrep error:\n" + res.stderr);
    process.exit(2);
  }
  return res.stdout
    .split("\n")
    .map((l) => l.trim())
    .filter(Boolean);
}

function main(): void {
  const files = listFiles();
  if (files.length === 0) {
    console.error("unbalanced-parens guard: no blade files found under scan targets.");
    process.exit(2);
  }
  const offenders: Chain[] = [];
  for (const rel of files) offenders.push(...scanFile(rel));

  if (offenders.length === 0) {
    console.log(
      `\u2713 unbalanced-parens guard passed (${files.length} blade files, no broken quoted-string copy).`,
    );
    process.exit(0);
  }

  console.error(
    "\u2717 unbalanced-parens guard FAILED: quoted string copy with unbalanced parentheses:\n",
  );
  for (const o of offenders) {
    const preview = o.lits.map((l) => `'${l.body}'`).join(" . ");
    console.error(`  ${o.file}:${o.line}: ${preview.slice(0, 220)}`);
  }
  console.error(
    `\n${offenders.length} occurrence(s). A paren pair was likely split across two string literals (e.g. ternary branches) — rejoin the sentence. Legit cases go in CONTENT_ALLOW or LINE_ALLOWLIST in scripts/src/check-unbalanced-parens.ts.`,
  );
  process.exit(1);
}

main();
