#!/usr/bin/env node
// Guard: extract inline <script> blocks from Blade views, neutralize Blade
// syntax, and fail on JavaScript parse errors (the "blank editor page" class
// of bug: a syntax error in a large inline script silently kills the page).
//
// Usage: node scripts/check-blade-inline-scripts.mjs [rootDir]
//
// Strategy per script block:
//   pass 1: map Blade conditionals to real JS blocks (statement position) and
//           check ALL branches;
//   pass 2 (only if pass 1 fails): keep only the FIRST branch of each Blade
//           conditional (what Blade would render) — handles expression-position
//           @if/@else usage that cannot be modeled as JS statements.
// A block is only reported when both passes fail.

import { readdirSync, readFileSync } from 'node:fs';
import { join, relative } from 'node:path';
import vm from 'node:vm';

const ROOT = process.argv[2] || join(process.cwd(), 'resources', 'views');
const SKIP_MARKER = 'blade-js-check: skip';

// Files where the inline scripts are intentionally not plain JS or are too
// Blade-entangled to statically neutralize. Keep this list short; prefer the
// per-file skip marker comment {{-- blade-js-check: skip --}}.
const ALLOWLIST = new Set([
  // 'user/example/too-dynamic.blade.php',
]);

function* bladeFiles(dir) {
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const p = join(dir, entry.name);
    if (entry.isDirectory()) {
      if (entry.name === 'vendor') continue; // user preference: don't touch views/vendor
      yield* bladeFiles(p);
    } else if (entry.name.endsWith('.blade.php')) {
      yield p;
    }
  }
}

// Apply [start,end,filler] edits at once, preserving every newline in each
// replaced span so line numbers stay accurate.
function applyEdits(str, edits) {
  if (!edits.length) return str;
  edits.sort((a, b) => a.start - b.start);
  let out = '';
  let pos = 0;
  for (const { start, end, filler } of edits) {
    if (start < pos) continue; // overlapping edit, first wins
    out += str.slice(pos, start);
    const span = str.slice(start, end);
    const newlines = (span.match(/\n/g) || []).join('');
    out += filler + newlines;
    pos = end;
  }
  out += str.slice(pos);
  return out;
}

// Find the index just past the balanced closing paren, starting at the index
// of the opening paren. Handles quotes well enough for Blade expressions.
function balancedParenEnd(str, openIdx) {
  let depth = 0;
  let quote = null;
  for (let i = openIdx; i < str.length; i++) {
    const c = str[i];
    if (quote) {
      if (c === '\\') i++;
      else if (c === quote) quote = null;
      continue;
    }
    if (c === "'" || c === '"') quote = c;
    else if (c === '(') depth++;
    else if (c === ')') {
      depth--;
      if (depth === 0) return i + 1;
    }
  }
  return -1;
}

// Blank PHP-only regions across the WHOLE file (before script extraction) so
// literal "<script" strings inside @php / <?php blocks and Blade comments
// can't be mistaken for real script tags.
function blankPhpRegions(raw) {
  const edits = [];
  for (const m of raw.matchAll(/\{\{--[\s\S]*?--\}\}/g)) {
    edits.push({ start: m.index, end: m.index + m[0].length, filler: '' });
  }
  for (const m of raw.matchAll(/@php\b[\s\S]*?@endphp/g)) {
    edits.push({ start: m.index, end: m.index + m[0].length, filler: '' });
  }
  for (const m of raw.matchAll(/<\?php[\s\S]*?\?>/g)) {
    edits.push({ start: m.index, end: m.index + m[0].length, filler: '' });
  }
  return applyEdits(raw, edits);
}

const IF_OPENERS = new Set([
  'if', 'unless', 'isset', 'auth', 'guest', 'production', 'env',
  'can', 'cannot', 'canany', 'error', 'once', 'hassection', 'sectionmissing',
]);
const LOOP_OPENERS = new Set(['foreach', 'forelse', 'for', 'while']);
const IF_ENDERS = new Set([
  'endif', 'endunless', 'endisset', 'endempty', 'endauth', 'endguest',
  'endproduction', 'endenv', 'endcan', 'endcannot', 'endcanany', 'enderror',
  'endonce', 'endhassection', 'endsectionmissing',
]);
const LOOP_ENDERS = new Set(['endforeach', 'endforelse', 'endfor', 'endwhile']);
const ELSE_LIKE = new Set(['else', 'elseif', 'elsecan', 'elsecannot', 'elsecanany']);

// Placeholder that is a valid JS identifier — works in expression, string,
// object-key, and identifier positions alike (unlike `null`).
const ECHO = '__bladeEcho';

// Scan directives in `s` and return a list of {start, end, name, hasArgs}.
function scanDirectives(s) {
  const out = [];
  const dirRe = /@@|@(\w+)/g;
  let m;
  while ((m = dirRe.exec(s)) !== null) {
    if (m[0] === '@@') continue; // escaped literal @
    const name = m[1].toLowerCase();
    let end = m.index + m[0].length;
    let hasArgs = false;
    const after = s.slice(end).match(/^\s*\(/);
    if (after) {
      const openIdx = end + after[0].length - 1;
      const closeIdx = balancedParenEnd(s, openIdx);
      if (closeIdx !== -1) {
        end = closeIdx;
        hasArgs = true;
      }
    }
    out.push({ start: m.index, end, name, hasArgs });
    dirRe.lastIndex = end;
  }
  return out;
}

// Shared pre-processing: echoes -> placeholder identifier.
function neutralizeEchoes(code) {
  let edits = [];
  let s = code.replace(/@(\{\{)/g, ' $1'); // escaped echo ships literally
  for (const m of s.matchAll(/\{!![\s\S]*?!!\}/g)) {
    edits.push({ start: m.index, end: m.index + m[0].length, filler: ECHO });
  }
  s = applyEdits(s, edits);
  edits = [];
  for (const m of s.matchAll(/\{\{[\s\S]*?\}\}/g)) {
    edits.push({ start: m.index, end: m.index + m[0].length, filler: ECHO });
  }
  return applyEdits(s, edits);
}

function directiveFillerCommon(d) {
  if (d.name === 'json' || d.name === 'js') return ECHO;
  if (d.name === 'continue') return 'continue;';
  if (d.name === 'break') return 'break;';
  return ''; // csrf, vite, include, verbatim, class, style, unknown -> blank
}

// Pass 1: conditionals become real JS blocks so alternate branches can't
// collide (e.g. `const a=1;` in both @if and @else); loops are simply blanked
// (their body appears once in source, so no duplication risk) which also
// keeps expression-position loops (object/array literal builders) valid.
function neutralizePass1(code) {
  const s = neutralizeEchoes(code);
  const edits = [];
  for (const d of scanDirectives(s)) {
    let filler;
    if (IF_OPENERS.has(d.name) || (d.name === 'empty' && d.hasArgs)) filler = 'if(true){';
    else if (d.name === 'else') filler = '}else{';
    else if (d.name === 'elseif' || d.name === 'elsecan' || d.name === 'elsecannot' || d.name === 'elsecanany') filler = '}else if(true){';
    else if (d.name === 'empty') filler = '}else{'; // forelse divider (pairs with blanked @forelse? see below)
    else if (IF_ENDERS.has(d.name)) filler = '}';
    else if (LOOP_OPENERS.has(d.name) || LOOP_ENDERS.has(d.name)) filler = '';
    else filler = directiveFillerCommon(d);

    // Special case: @forelse ... @empty ... @endforelse — since loops are
    // blanked, @empty/@endforelse must be blanked too (both fragments appear
    // sequentially; duplication across the two branches is rare in practice).
    if (d.name === 'forelse' || d.name === 'endforelse') filler = '';
    edits.push({ start: d.start, end: d.end, filler });
  }
  return applyEdits(s, edits);
}

// Pass 2: keep only the FIRST branch of every conditional (what Blade renders
// by default); blank @elseif/@else branches wholesale. Handles conditionals
// used in expression position (`var x = @if(c) {...} @else {...} @endif;`).
function neutralizePass2(code) {
  const s = neutralizeEchoes(code);
  const edits = [];
  const stack = []; // per open conditional: { skipFrom: index|null }
  for (const d of scanDirectives(s)) {
    const inSkipped = stack.some((f) => f.skipFrom !== null);
    if (IF_OPENERS.has(d.name) || (d.name === 'empty' && d.hasArgs)) {
      stack.push({ skipFrom: null });
      edits.push({ start: d.start, end: d.end, filler: '' });
    } else if (ELSE_LIKE.has(d.name) || (d.name === 'empty' && !d.hasArgs)) {
      const frame = stack[stack.length - 1];
      if (frame && frame.skipFrom === null) frame.skipFrom = d.start;
      // edit applied when the conditional closes
    } else if (IF_ENDERS.has(d.name) || d.name === 'endforelse') {
      const frame = stack.pop();
      if (frame && frame.skipFrom !== null) {
        edits.push({ start: frame.skipFrom, end: d.end, filler: '' });
      } else {
        edits.push({ start: d.start, end: d.end, filler: '' });
      }
    } else if (d.name === 'forelse') {
      stack.push({ skipFrom: null }); // @empty divider treated as else-like
      edits.push({ start: d.start, end: d.end, filler: '' });
    } else if (!inSkipped) {
      let filler;
      if (LOOP_OPENERS.has(d.name) || LOOP_ENDERS.has(d.name)) filler = '';
      else filler = directiveFillerCommon(d);
      edits.push({ start: d.start, end: d.end, filler });
    }
  }
  // Unclosed frames (shouldn't happen in valid Blade): skip their tails
  for (const frame of stack) {
    if (frame.skipFrom !== null) edits.push({ start: frame.skipFrom, end: s.length, filler: '' });
  }
  return applyEdits(s, edits);
}

// <script ...attrs...>body</script>, where attrs may contain Blade echoes
// (e.g. <script{!! $__attrs($pixel->type) !!}>) or quoted strings with '>'.
const SCRIPT_RE =
  /<script\b((?:\{!![\s\S]*?!!\}|\{\{[\s\S]*?\}\}|"[^"]*"|'[^']*'|[^>"'{])*)>([\s\S]*?)<\/script\s*>/gi;
const JS_TYPES = new Set(['', 'text/javascript', 'application/javascript', 'module']);

function scriptTypeOf(attrs) {
  const m = attrs.match(/\btype\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+))/i);
  return (m ? (m[1] ?? m[2] ?? m[3]) : '').trim().toLowerCase();
}

function lineOf(str, index) {
  let line = 1;
  for (let i = 0; i < index && i < str.length; i++) if (str[i] === '\n') line++;
  return line;
}

function compileCheck(code, isModule, name) {
  try {
    if (isModule && typeof vm.SourceTextModule === 'function') {
      new vm.SourceTextModule(code, { identifier: name });
    } else if (isModule) {
      // Fallback without --experimental-vm-modules: neutralize module-only
      // statements, then compile as a classic script (async wrapper allows
      // top-level await).
      const stripped = code
        .replace(/^\s*import\b[^\n;]*[;\n]?/gm, (t) => t.replace(/[^\n]/g, ' '))
        .replace(/^\s*export\s+default\b/gm, (t) => t.replace(/[^\n]/g, ' '))
        .replace(/^\s*export\b/gm, (t) => t.replace(/[^\n]/g, ' '));
      new vm.Script(`(async () => {${stripped}\n})`, { filename: name });
    } else {
      new vm.Script(code, { filename: name });
    }
    return null;
  } catch (err) {
    if (!(err instanceof SyntaxError)) return null;
    let line = null;
    const stackLine = String(err.stack || '').split('\n')[0];
    const lm = stackLine.match(/:(\d+)\s*$/) || stackLine.match(/:(\d+)\b/);
    if (lm) line = parseInt(lm[1], 10);
    return { message: err.message, line };
  }
}

let checkedBlocks = 0;
let checkedFiles = 0;
const failures = [];

for (const file of bladeFiles(ROOT)) {
  const rel = relative(ROOT, file);
  if (ALLOWLIST.has(rel)) continue;
  const rawOriginal = readFileSync(file, 'utf8');
  if (rawOriginal.includes(SKIP_MARKER)) continue;
  const raw = blankPhpRegions(rawOriginal);
  let sawScript = false;

  for (const m of raw.matchAll(SCRIPT_RE)) {
    const attrs = m[1] || '';
    if (/\bsrc\s*=/i.test(attrs)) continue;
    const type = scriptTypeOf(attrs);
    if (!JS_TYPES.has(type)) continue; // JSON, templates, ld+json, importmap...
    const body = m[2];
    if (!body.trim()) continue;

    sawScript = true;
    checkedBlocks++;
    const realStartLine = lineOf(raw, m.index + m[0].indexOf(body));
    const isModule = type === 'module';

    const err1 = compileCheck(neutralizePass1(body), isModule, rel);
    if (!err1) continue;
    const err2 = compileCheck(neutralizePass2(body), isModule, rel);
    if (!err2) continue;

    const isModuleFallback = isModule && typeof vm.SourceTextModule !== 'function';
    const blockOffset = isModuleFallback ? 1 : 0; // wrapper line added in fallback
    const bladeLine = err1.line != null ? realStartLine + err1.line - 1 - blockOffset : realStartLine;
    failures.push({ file: rel, line: bladeLine, message: err1.message });
  }

  if (sawScript) checkedFiles++;
}

if (failures.length) {
  console.error(`check-blade-inline-scripts: ${failures.length} JS parse error(s) found:\n`);
  for (const f of failures) {
    console.error(`  ${f.file}:${f.line}  ${f.message}`);
  }
  console.error(
    '\nThese inline <script> blocks fail to parse as JavaScript (after Blade' +
      '\nsyntax was neutralized). A syntax error in an inline script silently' +
      '\nkills the whole page (blank editor). Fix the script, or if this is a' +
      '\nfalse positive from heavy Blade interpolation, add the marker comment' +
      `\n{{-- ${SKIP_MARKER} --}} to the file or list it in the ALLOWLIST in` +
      '\nscripts/check-blade-inline-scripts.mjs.'
  );
  process.exit(1);
}

console.log(
  `check-blade-inline-scripts: OK (${checkedBlocks} inline script block(s) across ${checkedFiles} file(s) parsed cleanly)`
);
