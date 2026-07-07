/**
 * Shared Blade theme-scope detection.
 *
 * Two build-time safety checks — the undefined-CSS-var dark/white fallback guard
 * (`check-undefined-css-var-fallback.ts`) and the light-mode pairing completeness
 * guard (`check-light-mode-pairing.ts`) — both hinge on the SAME question:
 *
 *   "Does this blade page load the app's light/dark theme system?"
 *
 * i.e. does it (directly or transitively through its layout) pull in
 * `common/partials/theme-styles.blade.php`, the source of the app's `:root` /
 * `html.light-mode` tokens and of the `html.light-mode` class toggle?
 *
 *   - The undefined-css-var guard uses it to decide whether a page's
 *     `var(--x, …)` token allowance may include theme-styles' declarations, or
 *     whether an app-scoped page that ships its OWN `<html>`/`<head>` must be
 *     treated as standalone (tokens must be declared locally).
 *   - The pairing guard uses it to decide whether a page actually receives the
 *     `html.light-mode` class at all — a self-contained page's overrides are
 *     dead, so the pairing premise does not hold.
 *
 * The detection helpers live HERE so neither guard imports the internals of the
 * other; both import from this dedicated module and can never quietly disagree on
 * scope again.
 */

import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

/** Workspace root, resolved from this module's location (scripts/src/lib). */
export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../../..");

/** Blade views root both guards scan (repo-relative). */
export const VIEWS_REL = "artifacts/1inme/resources/views";
/** The app shell's canonical `:root` / `html.light-mode` token source. */
export const THEME_STYLES_REL = `${VIEWS_REL}/common/partials/theme-styles.blade.php`;
/**
 * The theme-styles partial keyed the SAME way as entries in the scanned file map
 * (path relative to VIEWS_REL). Used to detect whether a self-contained page
 * pulls it in (directly or transitively) via `@include`.
 */
export const THEME_STYLES_VIEW_REL = "common/partials/theme-styles.blade.php";
/**
 * Lightweight theme-bootstrap partial keyed the same way. Standalone pages that
 * cannot extend the app layout (e.g. RSVP form, ticket page) include this
 * minimal partial instead of the full theme-styles, and it is equally sufficient
 * to make the `html.light-mode` toggle work — so both guards treat it as an
 * entry point into the app's theme system.
 */
export const THEME_BOOTSTRAP_VIEW_REL = "common/partials/theme-bootstrap.blade.php";

/**
 * Strip blade `{{-- --}}`, HTML `<!-- -->` and CSS `/* *\/` comments so a
 * `var(--x, #hex)` or a `--x:` declaration that only appears INSIDE a comment is
 * never counted as a real reference or declaration.
 */
export function stripComments(src: string): string {
  return src
    .replace(/\{\{--[\s\S]*?--\}\}/g, " ")
    .replace(/<!--[\s\S]*?-->/g, " ")
    .replace(/\/\*[\s\S]*?\*\//g, " ");
}

/**
 * Does this source declare its OWN document shell — an `<html …>` or `<head …>`
 * open tag? Such a page renders standalone: it does NOT extend the app layout
 * (which is what supplies common/partials/theme-styles), so the theme-styles
 * token allowance must only be granted if it pulls theme-styles in itself
 * (see `includesThemeStyles`). Comments are assumed already stripped.
 */
export function declaresOwnDocument(src: string): boolean {
  return /<html[\s>]/i.test(src) || /<head[\s>]/i.test(src);
}

/**
 * All Blade view names pulled in by an `@include`-family directive
 * (`@include`, `@includeIf`, `@includeWhen`, `@includeUnless`, `@includeFirst`),
 * returned as file-map keys (path relative to VIEWS_REL, `.blade.php` suffix):
 * `@include('common.partials.theme-styles')` → `common/partials/theme-styles.blade.php`.
 * Dot-view-names are converted to slash paths. Comments are assumed stripped.
 */
export function parseIncludes(src: string): string[] {
  const out: string[] = [];
  const re = /@include(?:If|When|Unless|First)?\s*\(([^)]*)\)/g;
  let m: RegExpExecArray | null;
  while ((m = re.exec(src)) !== null) {
    const args = m[1] as string;
    const strRe = /['"]([^'"]+)['"]/g;
    let s: RegExpExecArray | null;
    while ((s = strRe.exec(args)) !== null) {
      const view = (s[1] as string).trim();
      if (!view || view.includes("$") || view.includes("::")) continue;
      out.push(`${view.replace(/\./g, "/")}.blade.php`);
    }
  }
  return out;
}

/**
 * The Blade layout pulled in by an `@extends(...)` directive, returned as a
 * file-map key (path relative to VIEWS_REL, `.blade.php` suffix):
 * `@extends('user.layouts.app')` → `user/layouts/app.blade.php`. A page has at
 * most one real layout, but the parser returns all string arguments it sees and
 * skips dynamic (`$var`) / namespaced (`pkg::view`) names. Comments assumed
 * stripped.
 */
export function parseExtends(src: string): string[] {
  const out: string[] = [];
  const re = /@extends\s*\(([^)]*)\)/g;
  let m: RegExpExecArray | null;
  while ((m = re.exec(src)) !== null) {
    const args = m[1] as string;
    const strRe = /['"]([^'"]+)['"]/g;
    let s: RegExpExecArray | null;
    while ((s = strRe.exec(args)) !== null) {
      const view = (s[1] as string).trim();
      if (!view || view.includes("$") || view.includes("::")) continue;
      out.push(`${view.replace(/\./g, "/")}.blade.php`);
    }
  }
  return out;
}

/**
 * Every Blade COMPONENT target, returned as file-map keys (path relative to
 * VIEWS_REL, `.blade.php` suffix). Two syntaxes:
 *
 *   - directive form `@component('components.card')` → `components/card.blade.php`
 *     (dot-view-name, resolved exactly like `@include`).
 *   - tag form `<x-app-layout>` → `components/app-layout.blade.php` and
 *     `<x-forms.input>` → `components/forms/input.blade.php` (anonymous/class
 *     components live under `resources/views/components/`; dots nest into
 *     sub-directories, hyphens are kept).
 *
 * Namespaced package components (`<x-pkg::foo>`), Blade's `<x-dynamic-component>`
 * (the target is a runtime `:component` expression, not a static file) and
 * dynamic component names are skipped (not resolvable to a local file).
 * Structural tags like `<x-slot>` map to a `components/slot.blade.php` key that
 * simply isn't in the file map and so is never followed. Comments assumed
 * stripped.
 */
export function parseComponents(src: string): string[] {
  const out: string[] = [];

  const dirRe = /@component\s*\(([^)]*)\)/g;
  let m: RegExpExecArray | null;
  while ((m = dirRe.exec(src)) !== null) {
    const args = m[1] as string;
    const strRe = /['"]([^'"]+)['"]/g;
    let s: RegExpExecArray | null;
    while ((s = strRe.exec(args)) !== null) {
      const view = (s[1] as string).trim();
      if (!view || view.includes("$") || view.includes("::")) continue;
      out.push(`${view.replace(/\./g, "/")}.blade.php`);
    }
  }

  const tagRe = /<x-([A-Za-z0-9._:-]+)/g;
  while ((m = tagRe.exec(src)) !== null) {
    const name = m[1] as string;
    if (!name || name.includes("::") || name === "dynamic-component") continue;
    out.push(`components/${name.replace(/\./g, "/")}.blade.php`);
  }

  return out;
}

/**
 * Does `rel` pull in the theme-styles partial DIRECTLY or TRANSITIVELY? Follows
 * every layout/component composition edge a Blade page can use to reach a shared
 * `<head>`: the `@include`-family chain, the `@extends` layout chain, and the
 * `@component` / `<x-...>` component chains. Guards against cycles via `seen`.
 * Missing targets (dynamic/external/namespaced) are simply not followed.
 */
export function includesThemeStyles(
  rel: string,
  files: Map<string, string>,
  seen: Set<string> = new Set(),
): boolean {
  if (seen.has(rel)) return false;
  seen.add(rel);
  const raw = files.get(rel);
  if (raw === undefined) return false;
  const src = stripComments(raw);
  const targets = [...parseIncludes(src), ...parseExtends(src), ...parseComponents(src)];
  for (const t of targets) {
    if (t === THEME_STYLES_VIEW_REL || t === THEME_BOOTSTRAP_VIEW_REL) return true;
    if (includesThemeStyles(t, files, seen)) return true;
  }
  return false;
}

/** Recursively list `*.blade.php` under `dir`, skipping any `vendor/` directory. */
export function listBladeFiles(dir: string): string[] {
  const out: string[] = [];
  const walk = (abs: string) => {
    for (const entry of fs.readdirSync(abs, { withFileTypes: true })) {
      if (entry.name === "vendor") continue;
      const child = path.join(abs, entry.name);
      if (entry.isDirectory()) walk(child);
      else if (entry.isFile() && entry.name.endsWith(".blade.php")) out.push(child);
    }
  };
  walk(dir);
  return out;
}

/**
 * Memoized read of every non-vendor `*.blade.php` under VIEWS_REL into a map
 * keyed by path relative to VIEWS_REL (`common/partials/theme-styles.blade.php`).
 *
 * Both theme guards (and their live-repo tests) scan the SAME whole views tree.
 * Walking + reading it is the single most expensive step, and the pairing guard
 * previously re-walked it once per configured target. Memoizing here means each
 * process walks and reads the tree at most once: the views are a static build
 * input during any single run (CLI one-shot or a test file's process), so the
 * cache is always valid, it removes the redundant per-target re-walks, and it
 * shrinks the disk-contention window when vitest runs both guard test files in
 * parallel — which is what made the live-repo scans intermittently time out.
 */
let viewsFileMapCache: Map<string, string> | null = null;
export function readViewsFileMap(): Map<string, string> {
  if (viewsFileMapCache) return viewsFileMapCache;
  const viewsAbs = path.join(REPO_ROOT, VIEWS_REL);
  const files = new Map<string, string>();
  for (const abs of listBladeFiles(viewsAbs)) {
    const rel = path.relative(viewsAbs, abs).split(path.sep).join("/");
    files.set(rel, fs.readFileSync(abs, "utf8"));
  }
  viewsFileMapCache = files;
  return files;
}
