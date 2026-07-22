/**
 * Admin light-mode dark-only text guard.
 *
 * The admin settings light-mode fixes rely on manually PAIRING dark-only
 * Tailwind text classes (`text-white/40`, `text-amber-300`, …) with the shared
 * `ak-*` legibility helpers defined in `admin/layouts/app.blade.php`
 * (see .agents/memory/admin-light-mode-ak-classes.md). Nothing structural
 * stops a future admin blade from shipping a bare dark-only text class with no
 * paired `ak-*` helper — the text silently washes out on the white light-mode
 * surface.
 *
 * This guard scans every admin blade's `class` attributes for dark-only text
 * tokens (low-opacity/bare `text-white` and light 100–300 tint shades) that
 * have NO accompanying `ak-*` class on the same element and NO legitimate
 * always-colored surface signal (solid ≥500 background, gradient tile,
 * `bg-black`) — those are the documented exemptions: white text on a solid
 * colored button or gradient icon tile must STAY white in light mode.
 *
 * Enforcement is a RATCHET against a committed baseline
 * (`scripts/src/data/admin-light-mode-baseline.json`, per-file violation
 * counts captured when the guard was introduced):
 *   - a file NOT in the baseline (i.e. any NEW admin blade) must have ZERO
 *     violations — this is the guard's whole point;
 *   - a baselined file may never grow its count (regressions fail), and a
 *     shrink prints a hint to re-tighten via `--update-baseline`.
 *
 * Blade ternaries inside `{{ … }}` echoes are checked per quoted string: a
 * dark token inside one ternary branch needs an `ak-*` class in the SAME
 * branch (regex sweeps that put both ak classes outside the ternary were a
 * documented failure mode).
 *
 * Run:  pnpm --filter @workspace/scripts run check:admin-light-mode-text
 *       (add `--update-baseline` to rewrite the baseline from current state,
 *        `--explain` to print what is checked and exit 0)
 */

import { fileURLToPath } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");
export const ADMIN_VIEWS_REL = "artifacts/1inme/resources/views/admin";
export const BASELINE_REL = "scripts/src/data/admin-light-mode-baseline.json";

/**
 * Dark-only text tokens that wash out on the white light-mode surface when
 * left un-paired: bare/low-opacity white text, and the light 100–300 tint
 * shades that are tuned for dark backgrounds. Prefixed variants
 * (`hover:text-white`, `dark:text-…`, `group-hover:…`) are deliberately NOT
 * matched — the guard targets the resting-state wash-out failure.
 */
export const DARK_TOKEN_RE =
  /(?<![\w:./[-])text-white(?:\/\d+)?(?![\w/-])|(?<![\w:./[-])text-(?:amber|emerald|red|blue|sky|green|rose|yellow|orange|teal|cyan|indigo|violet|fuchsia|pink|lime|slate|gray|zinc|neutral|stone)-(?:100|200|300)(?![\w-])/g;

/**
 * Signals that the element sits on an always-colored surface where white/light
 * text is CORRECT in both themes (solid-colored buttons, gradient icon tiles,
 * black overlays) — the documented ak-* exemption categories.
 */
export const SOLID_SURFACE_RE =
  /bg-gradient-to-|(?<![\w-])bg-[a-z]+-(?:500|600|700|800|900|950)(?![\w-])|(?<![\w:-])bg-black(?![\w-])|(?<![\w:-])bg-\[#[0-9a-fA-F]{3,8}\]/;

/** An `ak-*` helper class anywhere in the string. */
export const AK_RE = /(?<![\w-])ak-[\w-]+/;

export interface Violation {
  file: string; // repo-relative
  line: number;
  token: string;
  context: string;
}

/** Strip Blade comments so commented-out markup never counts. */
export function stripBladeComments(src: string): string {
  return src.replace(/\{\{--[\s\S]*?--\}\}/g, (m) => m.replace(/[^\n]/g, " "));
}

/** Blank a region while preserving newlines so line numbers stay stable. */
function blank(s: string): string {
  return s.replace(/[^\n]/g, " ");
}

function lineOf(src: string, index: number): number {
  let line = 1;
  for (let i = 0; i < index && i < src.length; i++) if (src[i] === "\n") line++;
  return line;
}

/**
 * Scan one blade source for un-paired dark-only text tokens inside `class`
 * attributes. Returns violations with 1-based line numbers.
 */
export function scanSource(fileRel: string, rawSrc: string): Violation[] {
  const src = stripBladeComments(rawSrc);
  const out: Violation[] = [];
  const attrRe = /(?<![:\w-])class\s*=\s*("([^"]*)"|'([^']*)')/g;
  let m: RegExpExecArray | null;
  while ((m = attrRe.exec(src))) {
    const value = m[2] ?? m[3] ?? "";
    const valueStart = m.index + m[0].indexOf(m[2] !== undefined ? '"' : "'") + 1;
    // Static part = attribute with Blade echoes blanked (positions preserved).
    const staticPart = value.replace(/\{\{[\s\S]*?\}\}|\{!![\s\S]*?!!\}/g, (e) =>
      e.replace(/[^\n]/g, " "),
    );
    // ak-* in the STATIC part clears the whole element (the shared helper
    // re-themes it). ak-* inside an echo only clears its own quoted string —
    // ternary branches must each carry their own ak class.
    if (AK_RE.test(staticPart)) continue;
    const onSolidSurface = SOLID_SURFACE_RE.test(staticPart);
    if (!onSolidSurface) {
      let t: RegExpExecArray | null;
      DARK_TOKEN_RE.lastIndex = 0;
      while ((t = DARK_TOKEN_RE.exec(staticPart))) {
        out.push({
          file: fileRel,
          line: lineOf(src, valueStart + t.index),
          token: t[0],
          context: value.trim().slice(0, 120),
        });
      }
    }

    // Ternary branches inside echoes: each quoted string carrying a dark
    // token needs ak-* (or a solid-surface signal) in the SAME string.
    const echoRe = /\{\{[\s\S]*?\}\}|\{!![\s\S]*?!!\}/g;
    let e: RegExpExecArray | null;
    while ((e = echoRe.exec(value))) {
      const strRe = /'([^']*)'|"([^"]*)"/g;
      let s: RegExpExecArray | null;
      while ((s = strRe.exec(e[0]))) {
        const str = s[1] ?? s[2] ?? "";
        if (AK_RE.test(str) || SOLID_SURFACE_RE.test(str) || onSolidSurface) continue;
        let t: RegExpExecArray | null;
        DARK_TOKEN_RE.lastIndex = 0;
        while ((t = DARK_TOKEN_RE.exec(str))) {
          out.push({
            file: fileRel,
            line: lineOf(src, valueStart + e.index + s.index),
            token: t[0],
            context: str.trim().slice(0, 120),
          });
        }
      }
    }
  }

  const pushStringViolations = (str: string, absStart: number, extraExempt = false) => {
    if (AK_RE.test(str) || SOLID_SURFACE_RE.test(str) || extraExempt) return;
    let t: RegExpExecArray | null;
    DARK_TOKEN_RE.lastIndex = 0;
    while ((t = DARK_TOKEN_RE.exec(str))) {
      out.push({
        file: fileRel,
        line: lineOf(src, absStart),
        token: t[0],
        context: str.trim().slice(0, 120),
      });
    }
  };

  // Alpine dynamic class bindings (`:class` / `x-bind:class`): each quoted
  // string carrying a dark token needs ak-* (or a solid-surface signal) in
  // the SAME string — unless the element's STATIC class already carries
  // ak-*/solid (the shared helper re-themes the whole element).
  const bindRe = /(?:x-bind)?:class\s*=\s*("([^"]*)"|'([^']*)')/g;
  let b: RegExpExecArray | null;
  while ((b = bindRe.exec(src))) {
    const value = b[2] ?? b[3] ?? "";
    const valueStart = b.index + b[0].indexOf(b[2] !== undefined ? '"' : "'") + 1;
    // Approximate the enclosing tag to honor a static ak-*/solid class.
    const tagStart = src.lastIndexOf("<", b.index);
    const tagEnd = src.indexOf(">", b.index);
    const tag = tagStart >= 0 && tagEnd > tagStart ? src.slice(tagStart, tagEnd) : "";
    const staticClass = /(?<![:\w-])class\s*=\s*("[^"]*"|'[^']*')/.exec(tag)?.[1] ?? "";
    // A bound `:style` painting a background means the element sits on a
    // dynamically-colored surface (icon tiles) — white text is correct there.
    // Checked on the tag itself AND on the immediately-enclosing markup window
    // (the tile pattern puts :style on the parent div, the icon inside it).
    const styleBgRe = /:style\s*=\s*("[^"]*background[^"]*"|'[^']*background[^']*')/;
    const parentWindow = tagStart >= 0 ? src.slice(Math.max(0, tagStart - 400), tagStart) : "";
    const dynamicBg = styleBgRe.test(tag) || styleBgRe.test(parentWindow);
    const tagExempt = AK_RE.test(staticClass) || SOLID_SURFACE_RE.test(staticClass) || dynamicBg;
    const strRe = /'([^']*)'|"([^"]*)"/g;
    let s: RegExpExecArray | null;
    while ((s = strRe.exec(value))) {
      pushStringViolations(s[1] ?? s[2] ?? "", valueStart + s.index, tagExempt);
    }
  }

  // Quoted strings on the right side of `=>` inside @php blocks — this is
  // where match-arm class strings and array'd class fragments live.
  const phpBlockRe = /@php\b([\s\S]*?)@endphp/g;
  let p: RegExpExecArray | null;
  const phpRanges: Array<[number, number]> = [];
  while ((p = phpBlockRe.exec(src))) {
    phpRanges.push([p.index, p.index + p[0].length]);
    const body = p[1];
    const bodyStart = p.index + p[0].indexOf(body);
    const armRe = /=>\s*('([^']*)'|"([^"]*)")/g;
    let a: RegExpExecArray | null;
    while ((a = armRe.exec(body))) {
      const str = a[2] ?? a[3] ?? "";
      pushStringViolations(str, bodyStart + a.index + a[0].indexOf(a[1]));
    }
  }

  // `'inputClass' => '…'` partial args outside @php blocks (e.g. @include
  // argument arrays) — the partial splices the string straight into class="".
  const inputClassRe = /['"]inputClass['"]\s*=>\s*('([^']*)'|"([^"]*)")/g;
  let ic: RegExpExecArray | null;
  while ((ic = inputClassRe.exec(src))) {
    const inPhp = phpRanges.some(([a2, z]) => ic!.index >= a2 && ic!.index < z);
    if (inPhp) continue; // already covered by the @php arm scan
    const str = ic[2] ?? ic[3] ?? "";
    pushStringViolations(str, ic.index + ic[0].indexOf(ic[1]));
  }

  return out;
}

export function listAdminBlades(root = REPO_ROOT): string[] {
  const base = path.join(root, ADMIN_VIEWS_REL);
  const files: string[] = [];
  const walk = (dir: string) => {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
      const p = path.join(dir, entry.name);
      if (entry.isDirectory()) walk(p);
      else if (p.endsWith(".blade.php")) files.push(p);
    }
  };
  walk(base);
  return files.sort();
}

export function scanRepo(root = REPO_ROOT): Map<string, Violation[]> {
  const byFile = new Map<string, Violation[]>();
  for (const abs of listAdminBlades(root)) {
    const rel = path.relative(root, abs);
    const v = scanSource(rel, fs.readFileSync(abs, "utf8"));
    if (v.length) byFile.set(rel, v);
  }
  return byFile;
}

export function readBaseline(root = REPO_ROOT): Record<string, number> {
  const p = path.join(root, BASELINE_REL);
  if (!fs.existsSync(p)) return {};
  return JSON.parse(fs.readFileSync(p, "utf8")) as Record<string, number>;
}

function writeBaseline(byFile: Map<string, Violation[]>, root = REPO_ROOT): void {
  const obj: Record<string, number> = {};
  for (const [file, v] of [...byFile.entries()].sort((a, b) => a[0].localeCompare(b[0])))
    obj[file] = v.length;
  fs.writeFileSync(path.join(root, BASELINE_REL), JSON.stringify(obj, null, 2) + "\n");
}

function main(): number {
  const args = process.argv.slice(2);
  if (args.includes("--explain")) {
    console.log(
      "Scans admin blade class attributes for dark-only text classes (text-white/NN, light 100-300 tints)\n" +
        "that lack a paired ak-* light-mode helper and are not on a solid/gradient colored surface.\n" +
        "New admin blades must be clean; baselined files may only shrink (ratchet).\n" +
        "Rewrite the baseline with --update-baseline after fixing files.",
    );
    return 0;
  }

  const byFile = scanRepo();
  if (args.includes("--update-baseline")) {
    writeBaseline(byFile);
    console.log(`Baseline updated: ${byFile.size} files, ${[...byFile.values()].reduce((a, v) => a + v.length, 0)} allowed violations.`);
    return 0;
  }

  const baseline = readBaseline();
  let failed = false;
  let shrunk = 0;

  for (const [file, violations] of [...byFile.entries()].sort((a, b) => a[0].localeCompare(b[0]))) {
    const allowed = baseline[file] ?? 0;
    if (violations.length > allowed) {
      failed = true;
      const label = allowed === 0 && !(file in baseline) ? "new admin blade" : `baseline ${allowed}`;
      console.error(
        `\nFAIL ${file} — ${violations.length} un-paired dark-only text class(es) (${label}):`,
      );
      for (const v of violations.slice(0, 25))
        console.error(`  ${file}:${v.line}  ${v.token}  in class="${v.context}"`);
      if (violations.length > 25) console.error(`  … and ${violations.length - 25} more`);
    } else if (violations.length < allowed) {
      shrunk++;
    }
  }
  // Files that went fully clean (or were deleted) but still sit in the baseline.
  for (const file of Object.keys(baseline)) {
    if (!byFile.has(file)) shrunk++;
  }

  if (failed) {
    console.error(
      "\nAdmin light-mode text guard FAILED.\n" +
        "Fix: pair each dark-only text class with the matching ak-* helper on the SAME element\n" +
        "(text-white → ak-strong; /45-65 → ak-muted; /20-40 → ak-note; amber/emerald/red/blue tints →\n" +
        "ak-amber/green/red/blue) — inside EACH ternary branch when the class is conditional.\n" +
        "White text on a solid ≥500 colored or gradient background is auto-exempt and needs no change.\n" +
        "See .agents/memory/admin-light-mode-ak-classes.md.",
    );
    return 1;
  }
  if (shrunk > 0) {
    console.log(
      `OK — no regressions. ${shrunk} baselined file(s) improved; consider re-tightening via\n` +
        "pnpm --filter @workspace/scripts run check:admin-light-mode-text -- --update-baseline",
    );
  } else {
    console.log("OK — no un-paired dark-only admin text regressions.");
  }
  return 0;
}

const isDirectRun = process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (isDirectRun) process.exit(main());
