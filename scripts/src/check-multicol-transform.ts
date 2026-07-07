/**
 * Multicol-item transform guard (the "vanishing showcase card" bug).
 *
 * Fails (exit 1) when a CSS rule inside a scanned blade file applies a
 * `transform` (any value other than `none`) or `will-change` containing
 * `transform` to an element that is a DIRECT CHILD of a CSS multi-column
 * container (`columns-*`), outside the `@media (prefers-reduced-motion:
 * reduce)` kill-switch block.
 *
 * Why this exists
 * ---------------
 * The home page "What you can create" showcase cards used to VANISH on hover:
 * a transform (and `will-change: transform`) was applied directly to
 * `.showcase-card`, which is a CSS multi-column item. Transforming a multicol
 * fragment triggers a long-standing browser rendering bug that blanks the
 * hovered card. The fix moved all motion onto the inner wrapper
 * (`.showcase-inner`), but nothing stopped a future style tweak from silently
 * reintroducing a transform on the card box. This guard is that stop.
 *
 * How it decides what a "multicol item" is
 * ----------------------------------------
 * The markup is the source of truth: the guard finds every element whose
 * `class` attribute contains a `columns-<n>` (or responsive `sm:columns-<n>`)
 * utility, then collects the class names AND tag names of that container's
 * DIRECT children. Any CSS rule whose SUBJECT (the last compound of the
 * selector — the element the declarations actually land on) carries one of
 * those classes is treated as targeting a multicol item; a CLASS-LESS subject
 * (`article:hover`, `.showcase-field > article`, `.showcase-field > *`) is
 * matched by its type part against the direct-child tag names instead, so the
 * guard cannot be dodged by dropping the class from the selector.
 *
 * What counts as an offender (per multicol-item subject)
 * ------------------------------------------------------
 *   - `transform: <anything but none>` declared directly on the subject.
 *   - `will-change: …transform…` declared directly on the subject.
 *   - an `animation` / `animation-name` referencing a `@keyframes` block that
 *     itself declares a non-`none` transform — but only when the subject
 *     carries a class EXCLUSIVE to multicol children (e.g. `.showcase-card`).
 *     Shared utilities like `.reveal` legitimately animate transforms on many
 *     non-multicol elements; the showcase opts out of those keyframes with its
 *     own transform-free `showcaseReveal` animation, so flagging the shared
 *     utility itself would be a permanent false positive.
 *
 * What is SAFE (never flagged)
 * ----------------------------
 *   - Anything inside `@media (prefers-reduced-motion: reduce)` — that block
 *     is the kill-switch and only ever sets `none` values anyway.
 *   - `transform: none` (any context) — it cannot trigger the bug.
 *   - Pseudo-element subjects (`.showcase-card::before/::after`) — the pseudo
 *     box is a child of the card, not the multicol fragment itself.
 *   - Transforms on DESCENDANTS of the card (`.showcase-card:hover
 *     .showcase-inner`) — that is exactly the sanctioned fix.
 *   - `transition: transform …` — a transition never applies a transform by
 *     itself.
 *
 * Run:  pnpm --filter @workspace/scripts run check:multicol-transform
 */

import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

/** Blade files scanned by the CLI (relative to repo root). */
export const SCAN_FILES: string[] = ["artifacts/1inme/resources/views/home.blade.php"];

/* -------------------------------------------------------------------------- *
 * Markup side: find multicol containers and their direct-child classes
 * -------------------------------------------------------------------------- */

/** HTML void elements — they never push tag depth. */
const VOID_TAGS = new Set([
  "area", "base", "br", "col", "embed", "hr", "img", "input",
  "link", "meta", "param", "source", "track", "wbr",
]);

/** A `columns-<n>` (or arbitrary `columns-[…]`) utility, optionally responsive. */
const COLUMNS_CLASS_RE = /(?:^|\s)(?:[a-z0-9]+:)?columns-(?:\d+|\[[^\]]+\])(?=\s|$)/;

interface Tag {
  /** `open` (`<div …>`), `close` (`</div>`) or `self` (`<br/>` / void). */
  kind: "open" | "close" | "self";
  name: string;
  classes: string[];
  /** Absolute offset of `<` in the source (for line reporting). */
  index: number;
  rawClassAttr: string;
}

const TAG_RE = /<(\/?)([a-zA-Z][\w-]*)((?:"[^"]*"|'[^']*'|[^>"'])*)>/g;
const CLASS_ATTR_RE = /\bclass\s*=\s*("([^"]*)"|'([^']*)')/i;

/**
 * Split a class attribute into plain class tokens. Tokens containing blade
 * interpolation (`{{ … }}`) or responsive/state variant colons are skipped —
 * neither can appear verbatim as a hand-written CSS class selector.
 */
export function classTokens(attr: string): string[] {
  return attr
    // Replace blade echoes with a sentinel so a partly-dynamic token like
    // `rd-{{ $i }}` is invalidated whole, not truncated into a fake class.
    .replace(/\{\{[\s\S]*?\}\}|\{!![\s\S]*?!!\}/g, "\u0000")
    .split(/\s+/)
    .filter((t) => /^-?[A-Za-z_][\w-]*$/.test(t));
}

/** Tokenize the HTML-ish tag stream of a blade file (markup only, no CSS). */
export function tokenizeTags(src: string): Tag[] {
  const tags: Tag[] = [];
  let m: RegExpExecArray | null;
  TAG_RE.lastIndex = 0;
  while ((m = TAG_RE.exec(src)) !== null) {
    const closing = m[1] === "/";
    const name = (m[2] ?? "").toLowerCase();
    const attrs = m[3] ?? "";
    if (name === "style" || name === "script") {
      // Skip the whole raw-text block so CSS/JS `<`s never confuse the walker.
      if (!closing) {
        const end = src.toLowerCase().indexOf(`</${name}`, TAG_RE.lastIndex);
        if (end !== -1) TAG_RE.lastIndex = end;
      }
      continue;
    }
    const cm = CLASS_ATTR_RE.exec(attrs);
    const rawClassAttr = cm ? (cm[2] ?? cm[3] ?? "") : "";
    const selfClosed = /\/\s*$/.test(attrs) || VOID_TAGS.has(name);
    tags.push({
      kind: closing ? "close" : selfClosed ? "self" : "open",
      name,
      classes: classTokens(rawClassAttr),
      index: m.index,
      rawClassAttr,
    });
  }
  return tags;
}

export interface MulticolInfo {
  /** Classes found on ANY direct child of a `columns-*` container. */
  itemClasses: Set<string>;
  /**
   * The subset of `itemClasses` used ONLY on multicol direct children (never
   * on any other element in the file) — safe to treat as synonymous with "the
   * multicol item box" for the stricter keyframes check.
   */
  exclusiveItemClasses: Set<string>;
  /** Tag names of ANY direct child of a `columns-*` container. */
  itemTags: Set<string>;
  /** Tags used ONLY as multicol direct children across the whole file. */
  exclusiveItemTags: Set<string>;
}

/**
 * Walk the markup: for every `columns-*` container, collect the classes AND
 * tag names of its direct children, then compute which of those are exclusive
 * to multicol children across the whole file.
 */
export function findMulticolItemClasses(src: string): MulticolInfo {
  const tags = tokenizeTags(src);
  const itemClasses = new Set<string>();
  const itemTags = new Set<string>();
  /** Indices (into `tags`) of tags that are multicol direct children. */
  const childTagIdx = new Set<number>();

  for (let i = 0; i < tags.length; i++) {
    const t = tags[i]!;
    if (t.kind !== "open" || !COLUMNS_CLASS_RE.test(" " + t.rawClassAttr)) continue;
    // Walk forward from the container, tracking nesting depth.
    let depth = 0;
    for (let j = i + 1; j < tags.length; j++) {
      const u = tags[j]!;
      if (u.kind === "close") {
        if (depth === 0) break; // container closed
        depth--;
        continue;
      }
      if (depth === 0) {
        childTagIdx.add(j);
        itemTags.add(u.name);
        for (const c of u.classes) itemClasses.add(c);
      }
      if (u.kind === "open") depth++;
    }
  }

  const exclusiveItemClasses = new Set<string>(itemClasses);
  const exclusiveItemTags = new Set<string>(itemTags);
  for (let i = 0; i < tags.length; i++) {
    if (childTagIdx.has(i) || tags[i]!.kind === "close") continue;
    exclusiveItemTags.delete(tags[i]!.name);
    for (const c of tags[i]!.classes) exclusiveItemClasses.delete(c);
  }
  return { itemClasses, exclusiveItemClasses, itemTags, exclusiveItemTags };
}

/* -------------------------------------------------------------------------- *
 * CSS side: nested-aware parse with media context + keyframes registry
 * -------------------------------------------------------------------------- */

export interface CssDecl {
  prop: string;
  value: string;
}

export interface ParsedRule {
  selectors: string[];
  decls: CssDecl[];
  /** Media-query conditions wrapping this rule (innermost last). */
  media: string[];
  /** Absolute offset of the selector start in the ORIGINAL source. */
  index: number;
}

export interface ParsedCss {
  rules: ParsedRule[];
  /** keyframes name → declares a non-`none` transform somewhere. */
  transformKeyframes: Set<string>;
}

/** Blank `/* *\/` comments, preserving offsets/newlines. */
export function blankCssComments(css: string): string {
  return css.replace(/\/\*[\s\S]*?\*\//g, (m) => m.replace(/[^\n]/g, " "));
}

function parseDecls(body: string): CssDecl[] {
  const decls: CssDecl[] = [];
  for (const part of body.split(";")) {
    const colon = part.indexOf(":");
    if (colon === -1) continue;
    const prop = part.slice(0, colon).trim().toLowerCase();
    const value = part.slice(colon + 1).trim();
    if (prop) decls.push({ prop, value });
  }
  return decls;
}

/**
 * Recursive-descent parse of a hand-written CSS string. `base` is the offset
 * of `css` within the original blade source so reported rule indices map back
 * to real line numbers.
 */
export function parseCss(css: string, base = 0): ParsedCss {
  const clean = blankCssComments(css);
  const rules: ParsedRule[] = [];
  const transformKeyframes = new Set<string>();

  const KEYFRAMES_RE = /^@(?:-webkit-|-moz-|-o-|-ms-)?keyframes\s+([\w-]+)/i;

  function walk(start: number, end: number, media: string[]): void {
    let i = start;
    while (i < end) {
      const brace = clean.indexOf("{", i);
      if (brace === -1 || brace >= end) break;
      const header = clean.slice(i, brace).trim();
      const headerStart = i + (clean.slice(i, brace).match(/^\s*/)?.[0].length ?? 0);
      // Find the matching closing brace.
      let depth = 1;
      let j = brace + 1;
      while (j < end && depth > 0) {
        const ch = clean[j];
        if (ch === "{") depth++;
        else if (ch === "}") depth--;
        j++;
      }
      const bodyStart = brace + 1;
      const bodyEnd = j - 1;

      if (header.startsWith("@")) {
        const kf = KEYFRAMES_RE.exec(header);
        if (kf) {
          const body = clean.slice(bodyStart, bodyEnd);
          const hasTransform = [...body.matchAll(/(?:^|[;{])\s*(-webkit-)?transform\s*:\s*([^;}]+)/gi)]
            .some((m) => (m[2] ?? "").trim().replace(/!\s*important\s*$/i, "").trim().toLowerCase() !== "none");
          if (hasTransform) transformKeyframes.add(kf[1]!);
        } else if (/^@media\b/i.test(header)) {
          walk(bodyStart, bodyEnd, [...media, header.replace(/^@media\s*/i, "").trim()]);
        } else if (/^@(supports|layer|scope|container)\b/i.test(header)) {
          walk(bodyStart, bodyEnd, media);
        }
        // other at-rules (@font-face, @import…) carry no rule of interest
      } else if (header) {
        rules.push({
          selectors: header.split(",").map((s) => s.replace(/\s+/g, " ").trim()).filter(Boolean),
          decls: parseDecls(clean.slice(bodyStart, bodyEnd)),
          media,
          index: base + headerStart,
        });
      }
      i = j;
    }
  }

  walk(0, clean.length, []);
  return { rules, transformKeyframes };
}

/** Extract every `<style>` block with its content offset. */
export function styleBlocks(src: string): Array<{ css: string; offset: number }> {
  const out: Array<{ css: string; offset: number }> = [];
  const re = /<style\b[^>]*>([\s\S]*?)<\/style>/gi;
  let m: RegExpExecArray | null;
  while ((m = re.exec(src)) !== null) {
    out.push({ css: m[1] ?? "", offset: m.index + m[0].indexOf(">") + 1 });
  }
  return out;
}

/* -------------------------------------------------------------------------- *
 * Selector subject analysis
 * -------------------------------------------------------------------------- */

/**
 * The SUBJECT compound of a selector: the last compound after any descendant/
 * child/sibling combinator. Declarations land on the subject, so only the
 * subject matters for "is this the multicol item box".
 */
export function subjectCompound(selector: string): string {
  const parts = selector.split(/\s+|\s*[>+~]\s*/).filter(Boolean);
  return parts[parts.length - 1] ?? "";
}

/** Does the subject compound address a pseudo-ELEMENT box (::before etc.)? */
export function subjectIsPseudoElement(subject: string): boolean {
  return /::(before|after|marker|backdrop|placeholder|selection|first-line|first-letter)\b/i.test(subject)
    // legacy single-colon forms
    || /(^|[^:]):(before|after|first-line|first-letter)\b/i.test(subject);
}

/**
 * The class names of the subject compound itself, EXCLUDING classes that only
 * appear inside functional pseudo-class arguments like `:not(.visible)` —
 * those describe a condition, not the box the rule lands on.
 */
export function subjectClasses(subject: string): string[] {
  const stripped = subject.replace(/:[\w-]+\(((?:[^()]|\([^()]*\))*)\)/g, "");
  return [...stripped.matchAll(/\.([A-Za-z0-9_-]+)/g)].map((m) => m[1]!);
}

/**
 * The type (tag) part of the subject compound: a leading tag name, `*`, or
 * `null` when the compound starts with a class/id/attribute/pseudo instead.
 */
export function subjectTag(subject: string): string | null {
  const m = /^([A-Za-z][\w-]*|\*)/.exec(subject.trim());
  return m ? m[1]!.toLowerCase() : null;
}

/* -------------------------------------------------------------------------- *
 * The check itself
 * -------------------------------------------------------------------------- */

export interface Offender {
  file: string;
  line: number;
  selector: string;
  property: string;
  value: string;
  reason: string;
}

const REDUCED_MOTION_RE = /prefers-reduced-motion\s*:\s*reduce/i;

function isNoneValue(value: string): boolean {
  return value.replace(/!\s*important\s*$/i, "").trim().toLowerCase() === "none";
}

function lineOf(src: string, index: number): number {
  let line = 1;
  for (let i = 0; i < index && i < src.length; i++) if (src[i] === "\n") line++;
  return line;
}

/**
 * Pure scanner: return every multicol-item transform offender in a blade
 * source. Exposed for direct unit testing (clean + poisoned fixtures).
 */
export function scanSource(relFile: string, src: string): Offender[] {
  const { itemClasses, exclusiveItemClasses, itemTags, exclusiveItemTags } =
    findMulticolItemClasses(src);
  if (itemClasses.size === 0 && itemTags.size === 0) return [];

  const offenders: Offender[] = [];

  for (const block of styleBlocks(src)) {
    const { rules, transformKeyframes } = parseCss(block.css, block.offset);
    for (const rule of rules) {
      if (rule.media.some((m) => REDUCED_MOTION_RE.test(m))) continue; // kill-switch block
      for (const selector of rule.selectors) {
        const subject = subjectCompound(selector);
        if (subjectIsPseudoElement(subject)) continue;
        const classes = subjectClasses(subject);
        let hitsItem = classes.some((c) => itemClasses.has(c));
        let hitsExclusive = classes.some((c) => exclusiveItemClasses.has(c));
        if (!hitsItem && classes.length === 0) {
          // Class-less subject (`article:hover`, `.showcase-field > article`,
          // `.showcase-field > *`): match by the type part instead, so the
          // guard can't be dodged by dropping the class from the selector.
          const tag = subjectTag(subject);
          if (tag === "*") {
            hitsItem = itemTags.size > 0;
          } else if (tag !== null) {
            hitsItem = itemTags.has(tag);
            hitsExclusive = exclusiveItemTags.has(tag);
          }
        }
        if (!hitsItem) continue;

        for (const d of rule.decls) {
          const prop = d.prop.replace(/^-(webkit|moz|o|ms)-/, "");
          if (prop === "transform" && !isNoneValue(d.value)) {
            offenders.push({
              file: relFile,
              line: lineOf(src, rule.index),
              selector,
              property: d.prop,
              value: d.value,
              reason: "transform on a multicol item",
            });
          } else if (prop === "will-change" && /\btransform\b/i.test(d.value)) {
            offenders.push({
              file: relFile,
              line: lineOf(src, rule.index),
              selector,
              property: d.prop,
              value: d.value,
              reason: "will-change: transform on a multicol item",
            });
          } else if (
            hitsExclusive &&
            (prop === "animation" || prop === "animation-name") &&
            !isNoneValue(d.value)
          ) {
            const names = [...d.value.matchAll(/[A-Za-z_][\w-]*/g)].map((m) => m[0]);
            const bad = names.find((n) => transformKeyframes.has(n));
            if (bad) {
              offenders.push({
                file: relFile,
                line: lineOf(src, rule.index),
                selector,
                property: d.prop,
                value: d.value,
                reason: `animation "${bad}" declares a transform in its @keyframes`,
              });
            }
          }
        }
      }
    }
  }

  offenders.sort((a, b) => a.line - b.line);
  return offenders;
}

/* -------------------------------------------------------------------------- *
 * CLI
 * -------------------------------------------------------------------------- */

function main(): void {
  const offenders: Offender[] = [];
  for (const rel of SCAN_FILES) {
    let src: string;
    try {
      src = fs.readFileSync(path.join(REPO_ROOT, rel), "utf8");
    } catch {
      console.error(`multicol-transform guard: cannot read ${rel}`);
      process.exit(2);
    }
    offenders.push(...scanSource(rel, src));
  }

  if (offenders.length === 0) {
    console.log(
      "✓ multicol-transform guard passed — no transform / will-change: transform on multicol items.",
    );
    process.exit(0);
  }

  console.error(
    "✗ multicol-transform guard FAILED — transform applied to a CSS multi-column item:\n",
  );
  for (const o of offenders) {
    console.error(`  ${o.file}:${o.line}: ${o.selector} { ${o.property}: ${o.value} }  — ${o.reason}`);
  }
  console.error(
    `\n${offenders.length} offender(s). Transforming (or will-change: transform on) a direct child of a ` +
      "`columns-*` container triggers a browser rendering bug that makes the element VANISH on hover " +
      "(the old showcase-card bug).",
  );
  console.error(
    "Fix: move the transform onto an inner wrapper (e.g. `.showcase-card:hover .showcase-inner`), " +
      "never onto the multicol item box itself. `transform: none` and the " +
      "`@media (prefers-reduced-motion: reduce)` kill-switch block are always allowed.",
  );
  process.exit(1);
}

// Only run when invoked directly (helpers above are imported by the test suite).
if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
