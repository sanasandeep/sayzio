/**
 * Light-mode pairing completeness guard (generalized).
 *
 * Several 1inme blade pages re-theme dark-tuned markup for the light theme by
 * shipping base CSS rules (dark colors) in their own `<style>` block plus
 * `html.light-mode …` counterparts so the same markup stays legible on the
 * white light-mode surface. The invariant, first documented for the event page
 * (.agents/memory/event-page-shared-rich-content-theming.md) and later seen on
 * the standalone pages migrated to the marketing layout
 * (.agents/memory/standalone-page-to-marketing-layout.md,
 *  .agents/memory/marketing-light-mode-legibility.md):
 *
 *   Every base rule that sets a text/border COLOR (`color` / `border-color`)
 *   MUST have a paired `html.light-mode <same-selector>` rule that sets the
 *   SAME property — otherwise that element keeps its DARK-theme color on the
 *   white light-mode card and washes out (illegible / near-invisible).
 *
 * This regressed by hand more than once on the event page before it was guarded
 * (the tips/pairings sections, then the "Interested" `.btn-outline-success`
 * button). Every re-themed page is one hand-edit away from the same failure, so
 * this guard is generalized to scan a CONFIGURED SET of pages (`TARGETS`), not
 * just the event page. Adding a new page is a one-entry change to `TARGETS`.
 *
 * Two matching modes per target (see `Target`):
 *   - whole-page (no `scopes`): every base color rule on the page must be
 *     paired. Best for pages whose entire custom-class CSS is theme-aware.
 *   - scoped (`scopes: [".wrapper", …]`): only base rules under one of the
 *     given wrapper selectors are checked. Best for a page that also has
 *     intentional always-dark islands (e.g. a hero over a dark image) outside
 *     the re-themed region — scope the check to the light surface only.
 *
 * Theme scope: the pairing premise only holds for pages that actually receive
 * the app's `html.light-mode` toggle — i.e. pages that load the shared theme
 * system (common/partials/theme-styles.blade.php) through their layout. A target
 * that ships its OWN `<html>`/`<head>` and never `@include`s theme-styles is
 * self-contained: the `html.light-mode` class is never toggled onto it, so its
 * overrides are dead and the pairing check would give a false pass/fail. Such a
 * target is reported as a misconfiguration, reusing the same
 * `declaresOwnDocument` / `includesThemeStyles` detection as the sibling
 * undefined-css-var guard so both agree on scope (see `pageIsSelfContained`).
 *
 * `@keyframes` blocks are stripped before parsing so animation percentages
 * (`0% { … }`) are never mistaken for color-carrying selectors.
 *
 * Theme-safe values are auto-accepted: a base rule whose value is purely
 * `var(--…)` theme tokens (no literal fallback) and/or the keywords
 * `transparent` / `inherit` / `currentColor` already flips with (or is neutral
 * to) the theme and can never wash out, so it needs no `html.light-mode` pair
 * and no allowlist entry (see `isThemeSafeColorValue`). This applies to both
 * the TARGETS hard-fail path and the discovery warning pass.
 *
 * Other intentional un-paired rules (a theme-neutral LITERAL accent that reads
 * correctly in both modes, e.g. a blue focus ring, or a white label on a
 * colored badge) go in the target's `allowlist` with a reason — never by
 * weakening the parser.
 *
 * Run:  pnpm --filter @workspace/scripts run check:light-mode-pairing
 *       (add `--explain` to print what is checked and exit 0)
 */

import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";
import {
  VIEWS_REL,
  declaresOwnDocument,
  includesThemeStyles,
  parseExtends,
  readViewsFileMap as readViewsFileMapShared,
  stripComments as stripBladeComments,
} from "./lib/blade-theme-scope.js";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

/** The `html.light-mode ` selector prefix a light-mode override carries. */
const LIGHT_PREFIX = "html.light-mode ";

/**
 * The color-carrying properties whose dark value washes out on the white
 * light-mode card if left un-paired. `background` is deliberately NOT here: a
 * dark background block on the light card is not the washed-out-TEXT failure
 * this guard targets, and many rules set only a subtle translucent background
 * that reads fine in both themes.
 */
export const COLOR_PROPS = ["color", "border-color"] as const;
export type ColorProp = (typeof COLOR_PROPS)[number];

/** A selector+property that intentionally has no light-mode counterpart. */
export type AllowEntry = { selector: string; property: ColorProp; reason: string };

/**
 * An intentional imbalance between a partial's themed inline colors and its
 * light overrides: `inlineWithoutOverride` themed inline declarations of
 * `property` legitimately need NO `html.light-mode` counterpart (a theme-neutral
 * value, or one override intentionally covering several elements).
 */
export type PartialAllowEntry = {
  property: ColorProp;
  inlineWithoutOverride: number;
  reason: string;
};

/**
 * An inline-styled partial a page `@include`s whose dark colors are baked as
 * `style="…{{ $var }}…"` attributes rather than base `<style>` rules — so there
 * is nothing for `findMissingPairs` to selector-pair against (only the light
 * overrides live in the page). Coverage is instead verified by a STRUCTURAL
 * COUNT (see `checkPartial`): every themed inline `color`/`border` in the
 * partial must have exactly one paired `html.light-mode` override targeting the
 * partial's scope classes.
 */
export interface PartialSpec {
  /** Human name used in messages. */
  name: string;
  /** Partial path relative to repo root. */
  file: string;
  /**
   * Class tokens (no leading dot) the partial's themed elements carry and that
   * the page's `html.light-mode` overrides target.
   */
  scopeClasses: string[];
  /** Intentional inline-vs-override count imbalances, each with a reason. */
  allowlist?: PartialAllowEntry[];
}

export interface Target {
  /** Repo-relative path to the blade page. */
  page: string;
  /** Human label used in output. */
  label: string;
  /**
   * Optional wrapper selectors to scope the check to. When omitted/empty the
   * whole page is checked (every base color rule must be paired).
   */
  scopes?: string[];
  /** Intentionally un-paired selector+property rules, each with a reason. */
  allowlist: AllowEntry[];
  /**
   * Inline-styled partials this page includes whose dark colors are baked as
   * `style="…"` attributes (no base `<style>` rule to pair against). Each is
   * verified by a structural inline-vs-override count instead of selector
   * pairing (see `checkPartial`).
   */
  partials?: PartialSpec[];
}

/**
 * The configured set of pages this guard protects. Add a page by appending an
 * entry here (and, if it re-themes a shared partial under a wrapper class, list
 * that wrapper in `scopes`). Keep the `allowlist` reasons specific.
 */
export const TARGETS: Target[] = [
  {
    page: "artifacts/1inme/resources/views/common/event-page.blade.php",
    label: "event page",
    allowlist: [
      {
        selector: ".ev-input:focus",
        property: "border-color",
        reason:
          "blue focus-ring accent (#3d6bff) — the brand accent reads clearly on both the dark and the white input surface, so the focus border is intentionally theme-neutral.",
      },
    ],
    // The event page's right-column tips/pairings partials bake their dark
    // colors as inline style="…{{ $var }}…" attributes (no base <style> rule to
    // selector-pair against), so they are verified by a structural inline-vs-
    // override count instead — see checkPartial. Both regressed by hand before
    // (their light overrides were missing), which is what motivated this guard.
    partials: [
      {
        name: "event-connection-tips",
        file: "artifacts/1inme/resources/views/common/partials/event-connection-tips.blade.php",
        scopeClasses: ["ev-connection-tips", "ev-connection-tip-card"],
      },
      {
        name: "link-type-pairings",
        file: "artifacts/1inme/resources/views/common/partials/link-type-pairings.blade.php",
        scopeClasses: ["ltp-pairings"],
      },
    ],
  },
  {
    page: "artifacts/1inme/resources/views/public/faqs.blade.php",
    label: "FAQs page",
    allowlist: [
      {
        selector: ".faq-card:hover",
        property: "border-color",
        reason: "blue accent hover border (rgba(61,107,255,.35)) — legible on both themes.",
      },
      {
        selector: ".faq-card[open]",
        property: "border-color",
        reason: "blue accent open border (rgba(61,107,255,.5)) — legible on both themes.",
      },
    ],
  },
  {
    page: "artifacts/1inme/resources/views/common/events-directory.blade.php",
    label: "events directory",
    // Whole-page: the below-hero re-themed classes are bare custom selectors
    // (.hashtag-pill, .ev-chip, …) that the light overrides pair with an added
    // `.events-page-body` ancestor (handled by the ancestor-prefixed match).
    // The photographic hero is an intentional always-dark island whose bespoke
    // .hero-slide-* text has no light counterpart on purpose, so it is
    // allowlisted here rather than scoped (base rules are bare, not under a
    // wrapper selector, so `scopes` can't isolate the light surface).
    allowlist: [
      {
        selector: ".hero-slide-badge",
        property: "color",
        reason:
          "featured-slider badge white text sits on a blue→violet gradient over the always-dark photographic hero — intentionally white in both themes.",
      },
      {
        selector: ".hero-slide-date",
        property: "color",
        reason:
          "featured-slider date (rgba(255,255,255,.7)) over the always-dark cover-image scrim — intentionally light in both themes.",
      },
      {
        selector: ".hero-slide-title",
        property: "color",
        reason:
          "featured-slider title white text over the always-dark cover-image scrim — intentionally white in both themes.",
      },
      {
        selector: ".hero-slide-location",
        property: "color",
        reason:
          "featured-slider location (rgba(255,255,255,.6)) over the always-dark cover-image scrim — intentionally light in both themes.",
      },
      {
        selector: ".ev-cat-pill",
        property: "color",
        reason:
          "category pill white text always sits on a solid colored badge background (set inline) — theme-neutral, legible in both modes.",
      },
      {
        selector: ".event-card:hover",
        property: "border-color",
        reason:
          "blue accent hover border (rgba(61,107,255,.4)) — the brand accent reads clearly on both the dark and the white card, so it is intentionally theme-neutral.",
      },
    ],
    // The events directory also includes the same event-connection-tips
    // partial (dark colors baked as inline style="…{{ $var }}…" attributes),
    // so it needs the same structural inline-vs-override count check as the
    // event page — this regressed by hand (the page shipped with none of the
    // tips overrides), which is what motivated adding this entry.
    partials: [
      {
        name: "event-connection-tips",
        file: "artifacts/1inme/resources/views/common/partials/event-connection-tips.blade.php",
        scopeClasses: ["ev-connection-tips", "ev-connection-tip-card"],
      },
    ],
  },
  {
    page: "artifacts/1inme/resources/views/common/creator-events.blade.php",
    label: "creator events page",
    allowlist: [
      {
        selector: ".ev-cat-pill",
        property: "color",
        reason:
          "category pill white text always sits on a solid colored badge background (set inline) — theme-neutral, legible in both modes.",
      },
      {
        selector: ".event-card:hover",
        property: "border-color",
        reason:
          "blue accent hover border (rgba(61,107,255,.4)) — the brand accent reads clearly on both the dark and the white card, so it is intentionally theme-neutral.",
      },
    ],
  },
  {
    // The one genuine wash-out here (`.dcp-chchip` white label on the
    // translucent .glass chip, which turns near-white in light mode) is FIXED
    // with a real `html.light-mode .dcp-chchip { color }` override in the page.
    // Everything below is theme-neutral: the animated dialer PHONE is an
    // always-dark island (its `html.light-mode .dcp-phone` / `.dcp-face`
    // gradients stay dark), so every `.dcp-*` text rule rendered inside the
    // phone screen must KEEP its light color in both themes — adding a
    // light-mode override there would bury it dark-on-dark. Outside the phone,
    // the grid-card icon rides an always-colored tile, the hover border + stat
    // number are the brand-blue accent, all legible on white.
    page: "artifacts/1inme/resources/views/public/dialer-contacts.blade.php",
    label: "Dialer & Contacts page",
    allowlist: [
      { selector: ".dcp-face", property: "color", reason: "phone screen face — always-dark island (kept dark in light mode via html.light-mode .dcp-phone); its white text is correct in both themes." },
      { selector: ".dcp-status", property: "color", reason: "status bar text (#8f9bb8) on the always-dark phone screen." },
      { selector: ".dcp-status i", property: "color", reason: "status bar icon (#8f9bb8) on the always-dark phone screen." },
      { selector: ".dcp-match", property: "color", reason: "T9 match chip label (#dbe4ff) on the always-dark phone screen." },
      { selector: ".dcp-match i", property: "color", reason: "T9 match chip icon (#7a9eff) on the always-dark phone screen." },
      { selector: ".dcp-digit", property: "color", reason: "dialed digits (#fff) on the always-dark phone screen." },
      { selector: ".dcp-key", property: "color", reason: "keypad key glyph (#fff) on the always-dark phone screen." },
      { selector: ".dcp-key .l", property: "color", reason: "keypad key letters (rgba(255,255,255,.45)) on the always-dark phone screen." },
      { selector: ".dcp-sim", property: "color", reason: "SIM call button label (#fff) on an always-colored green/teal gradient button inside the phone." },
      { selector: ".dcp-sim .sb", property: "color", reason: "SIM badge glyph (#fff) on the always-colored SIM button inside the phone." },
      { selector: ".dcp-dialchan", property: "color", reason: "quick-channel icon (#fff) on an always-colored tile inside the phone." },
      { selector: ".dcp-dialchan-label", property: "color", reason: "quick-channel label (rgba(255,255,255,.5)) on the always-dark phone screen." },
      { selector: ".dcp-cid-pill", property: "color", reason: "caller-ID pill label (#dbe4ff) on the always-dark phone screen." },
      { selector: ".dcp-cid-pill i", property: "color", reason: "caller-ID pill icon (#7a9eff) on the always-dark phone screen." },
      { selector: ".dcp-avatar-lg", property: "color", reason: "caller avatar initials (#fff) on an always-colored gradient avatar inside the phone." },
      { selector: ".dcp-call-name", property: "color", reason: "caller name (#fff) on the always-dark phone screen." },
      { selector: ".dcp-call-name i", property: "color", reason: "verified-badge icon (#3d6bff brand accent) on the always-dark phone screen." },
      { selector: ".dcp-call-handle", property: "color", reason: "caller handle (#a8b3cf) on the always-dark phone screen." },
      { selector: ".dcp-call-num", property: "color", reason: "caller number (#cbd5e1) on the always-dark phone screen." },
      { selector: ".dcp-call-status", property: "color", reason: "in-call status (#34d399 green) on the always-dark phone screen." },
      { selector: ".dcp-decline-btn", property: "color", reason: "decline glyph (#fff) on an always-colored red gradient button inside the phone." },
      { selector: ".dcp-answer-btn", property: "color", reason: "answer glyph (#fff) on an always-colored green gradient button inside the phone." },
      { selector: ".dcp-btn-label", property: "color", reason: "call-action label (rgba(255,255,255,.5)) on the always-dark phone screen." },
      { selector: ".dcp-card-icon", property: "color", reason: "grid-card icon (#fff) on an always-colored tile (var --dcp-c) — theme-neutral." },
      { selector: ".dcp-card:hover", property: "border-color", reason: "brand-blue hover border (rgba(61,107,255,.4)) — legible on both themes." },
      { selector: ".dcp-stat .num", property: "color", reason: "brand-blue stat number (#3d6bff, large bold) — legible on the white light-mode surface." },
    ],
  },
  {
    // The strategist's typewriter TERMINAL (`.ms-term`) is an always-dark
    // island: its `html.light-mode .ms-term` override keeps a dark gradient
    // background, so every `.ms-*` text rule inside it must KEEP its light color
    // in both themes. All rules below are inside that terminal (or its own
    // brand-accent), hence theme-neutral.
    page: "artifacts/1inme/resources/views/public/ai-marketing-strategist.blade.php",
    label: "AI Marketing Strategist page",
    allowlist: [
      { selector: ".ms-term-title", property: "color", reason: "terminal title bar (#9ca3af) on the always-dark .ms-term terminal (kept dark in light mode)." },
      { selector: ".ms-term-body", property: "color", reason: "terminal body text (#e5e7eb) on the always-dark .ms-term terminal." },
      { selector: ".ms-goal-text", property: "color", reason: "goal line text (#cbd5e1) inside the always-dark .ms-term terminal." },
      { selector: ".ms-k-head", property: "color", reason: "output heading (#fff) inside the always-dark .ms-term terminal." },
      { selector: ".ms-k-organic", property: "color", reason: "organic-channel line (#d6e0ff) inside the always-dark .ms-term terminal." },
      { selector: ".ms-k-paid", property: "color", reason: "paid-channel line (#ffe2c7) inside the always-dark .ms-term terminal." },
      { selector: ".ms-k-kpi", property: "color", reason: "KPI line (#9ff0c4) inside the always-dark .ms-term terminal." },
      { selector: ".ms-k-dim", property: "color", reason: "dimmed line (#8b93a7) inside the always-dark .ms-term terminal." },
      { selector: ".ms-k-plain", property: "color", reason: "plain output line (#e5e7eb) inside the always-dark .ms-term terminal." },
    ],
  },
  {
    // No genuine wash-out: every custom-class color rule sits on a fixed island.
    // The animated hero RÉSUMÉ PAPER (`.rbp-paper`) and TEMPLATE cards
    // (`.rbp-tpl`) hardcode a white gradient background in both themes, so their
    // dark ink is correct on white; their colored/gradient headers, the status
    // pill (always-dark), the colored step-icon tile, the dark tag pill, and the
    // brand-blue accents (chip / caret / stat number / step watermark) are all
    // legible in both themes. The one light-gray label that DID wash out
    // (`.rbp-stat .lbl`) already has its html.light-mode override in the page.
    page: "artifacts/1inme/resources/views/public/resume-builder.blade.php",
    label: "Resume Builder page",
    allowlist: [
      { selector: ".rbp-paper", property: "color", reason: "résumé paper ink (#0f172a) on the hardcoded white paper gradient (always white in both themes)." },
      { selector: ".rbp-paper-head", property: "color", reason: "paper header text (#fff) on the always-blue gradient header." },
      { selector: ".rbp-tpl", property: "color", reason: "template card ink (#0f172a) on the hardcoded white template gradient (always white)." },
      { selector: ".rbp-tpl-head", property: "color", reason: "template header text (#fff) on an always-colored gradient header (set inline per template)." },
      { selector: ".rbp-tpl-tag", property: "color", reason: "template tag label (#fff) on an always-dark pill (rgba(0,0,0,.55)) over the colored header." },
      { selector: ".rbp-step-icon", property: "color", reason: "step icon glyph (#fff) on an always-colored tile (var --rbp-c)." },
      { selector: ".rbp-step-num", property: "color", reason: "giant step watermark number (brand color at opacity .14) — decorative, faint in both themes." },
      { selector: ".rbp-stat .num", property: "color", reason: "brand-blue stat number (#3d6bff, large bold) — legible on the white light-mode surface." },
      { selector: ".rb-chip", property: "color", reason: "\"AI polished\" chip label (#2342c7 dark-blue accent) on a translucent blue tint — legible on white." },
      { selector: ".rb-type-caret", property: "color", reason: "typing caret (#3d6bff brand accent) inside the always-white résumé paper." },
      { selector: ".rb-status-writing", property: "color", reason: "\"AI writing…\" status label (#fff) on the always-dark status pill (rgba(15,18,28,.85))." },
      { selector: ".rb-status-done", property: "color", reason: "\"AI polished\" status label (#fff) on the always-dark status pill." },
      { selector: ".rb-status-done i", property: "color", reason: "status check icon (#1ed760 green) on the always-dark status pill." },
    ],
  },
  {
    page: "artifacts/1inme/resources/views/common/rsvp-form.blade.php",
    label: "RSVP form page",
    allowlist: [
      {
        selector: ".rsvp-header",
        property: "color",
        reason:
          "white text on the always-present blue-to-purple gradient header — theme-neutral in both modes.",
      },
      {
        selector: ".btn-purple",
        property: "color",
        reason:
          "white text on the always-blue primary action button — theme-neutral in both modes.",
      },
      {
        selector: ".btn-purple:hover",
        property: "color",
        reason:
          "white text on the darker-blue hovered primary button — theme-neutral in both modes.",
      },
    ],
  },
  {
    page: "artifacts/1inme/resources/views/common/rsvp-manage.blade.php",
    label: "RSVP manage page",
    allowlist: [
      {
        selector: ".rsvp-header",
        property: "color",
        reason:
          "white text on the always-present blue-to-purple gradient header — theme-neutral in both modes.",
      },
      {
        selector: ".btn-purple",
        property: "color",
        reason:
          "white text on the always-blue primary action button — theme-neutral in both modes.",
      },
      {
        selector: ".btn-purple:hover",
        property: "color",
        reason:
          "white text on the darker-blue hovered primary button — theme-neutral in both modes.",
      },
    ],
  },
  {
    page: "artifacts/1inme/resources/views/common/event-ticket.blade.php",
    label: "event ticket page",
    allowlist: [
      {
        selector: ".ticket-header",
        property: "color",
        reason:
          "white text on the always-present blue-to-purple gradient header — theme-neutral in both modes.",
      },
    ],
  },
  {
    // Surfaced by the unknown-standalone-page discovery pass: a standalone
    // page (own <html>, @includes theme-styles directly) shown while sign-ups
    // are paused. Its theme-token rules (color:transparent gradient headline,
    // var(--accent) badge, var(--border-glass-light) hover border) are
    // auto-accepted as theme-safe by the parser; only the literal brand-accent
    // icon colors on tinted tiles (legible on both surfaces) need entries.
    page: "artifacts/1inme/resources/views/user/auth/registration-paused.blade.php",
    label: "registration paused page",
    allowlist: [
      {
        selector: ".up-feature:nth-child(1) .ic",
        property: "color",
        reason:
          "periwinkle accent icon (#7d9bff) on its matching tinted tile — legible on both themes.",
      },
      {
        selector: ".up-feature:nth-child(2) .ic",
        property: "color",
        reason:
          "orchid accent icon (#e29bff) on its matching tinted tile — legible on both themes.",
      },
      {
        selector: ".up-feature:nth-child(3) .ic",
        property: "color",
        reason:
          "emerald accent icon (#34d399) on its matching tinted tile — legible on both themes.",
      },
      {
        selector: ".up-feature:nth-child(4) .ic",
        property: "color",
        reason:
          "cyan accent icon (#67e8f9) on its matching tinted tile — legible on both themes.",
      },
    ],
  },
];

function makeIsAllowed(allowlist: AllowEntry[]) {
  return (selector: string, property: ColorProp): boolean =>
    allowlist.some((e) => e.selector === selector && e.property === property);
}

/** Concatenate the contents of every `<style>…</style>` block in the source. */
export function extractStyleBlocks(src: string): string {
  const re = /<style\b[^>]*>([\s\S]*?)<\/style>/gi;
  const parts: string[] = [];
  let m: RegExpExecArray | null;
  while ((m = re.exec(src)) !== null) parts.push(m[1] ?? "");
  return parts.join("\n");
}

/** Strip `/* *\/` CSS comments (blade `{{-- --}}` never appears inside CSS). */
export function stripCssComments(css: string): string {
  return css.replace(/\/\*[\s\S]*?\*\//g, " ");
}

/**
 * Remove `@keyframes` (and vendor-prefixed) blocks — including their nested
 * `0% { … }` steps — so animation percentages are never parsed as
 * color-carrying selectors. Brace-matched so nested braces are handled.
 */
export function stripKeyframes(css: string): string {
  const re = /@(?:-webkit-|-moz-|-o-|-ms-)?keyframes\b/gi;
  let out = "";
  let last = 0;
  let m: RegExpExecArray | null;
  while ((m = re.exec(css)) !== null) {
    out += css.slice(last, m.index);
    const open = css.indexOf("{", m.index);
    if (open === -1) {
      last = m.index;
      break;
    }
    let depth = 1;
    let j = open + 1;
    while (j < css.length && depth > 0) {
      const ch = css[j];
      if (ch === "{") depth++;
      else if (ch === "}") depth--;
      j++;
    }
    last = j;
    re.lastIndex = j;
  }
  out += css.slice(last);
  return out;
}

/** Collapse whitespace in a selector so grouping is stable. */
function normalizeSelector(sel: string): string {
  return sel.replace(/\s+/g, " ").trim();
}

/* -------------------------------------------------------------------------- *
 * Theme-safe values (auto-paired)
 * -------------------------------------------------------------------------- *
 * A base rule whose color VALUE is purely theme tokens can never wash out on
 * the light surface: `var(--…)` custom properties are defined by the shared
 * theme system and already flip with `html.light-mode`, and the keywords
 * `transparent` / `inherit` / `currentColor` carry no dark literal of their
 * own. Such rules are treated as ALREADY PAIRED automatically (both on the
 * TARGETS hard-fail path and the discovery warning pass), so they no longer
 * need hand-written allowlist entries. Literal dark hex/rgb/named values stay
 * fully guarded.
 *
 * One deliberate exception: a `var()` with a LITERAL fallback — e.g.
 * `var(--x, #0b0e14)` — is NOT auto-accepted. If `--x` is never actually
 * declared, the dark fallback always wins in both themes (the classic
 * undefined-css-var-dark-fallback trap, see
 * .agents/memory/undefined-css-var-dark-fallback.md), so it must keep needing
 * an explicit pair or a reasoned allowlist entry. A fallback that is itself a
 * theme token / safe keyword remains safe (checked recursively).
 */

const SAFE_COLOR_KEYWORD = /^(transparent|inherit|currentcolor)$/i;
const VAR_TOKEN = /^var\(\s*--[A-Za-z0-9_-]+\s*(?:,([\s\S]*))?\)$/i;

/** Split a CSS value on top-level whitespace (whitespace inside `(…)` kept). */
function splitValueTokens(value: string): string[] {
  const out: string[] = [];
  let depth = 0;
  let cur = "";
  for (const ch of value) {
    if (ch === "(") depth++;
    else if (ch === ")") depth = Math.max(0, depth - 1);
    if (depth === 0 && /\s/.test(ch)) {
      if (cur) out.push(cur);
      cur = "";
    } else {
      cur += ch;
    }
  }
  if (cur) out.push(cur);
  return out;
}

function isThemeSafeToken(token: string): boolean {
  if (SAFE_COLOR_KEYWORD.test(token)) return true;
  const m = VAR_TOKEN.exec(token);
  if (!m) return false;
  const fallback = m[1];
  // Bare `var(--name)` is safe; a fallback must itself be theme-safe.
  return fallback === undefined || isThemeSafeColorValue(fallback);
}

/**
 * Is `value` composed ONLY of theme tokens (`var(--…)` without a literal
 * fallback) and/or the safe keywords `transparent` / `inherit` /
 * `currentColor`? Such a value flips with (or is neutral to) the theme, so a
 * base rule carrying it needs no `html.light-mode` pair. See the block comment
 * above for the fallback nuance. A blade interpolation (`{{ … }}`) or any
 * literal color makes the value unsafe.
 */
export function isThemeSafeColorValue(value: string): boolean {
  const v = value.replace(/!\s*important\s*$/i, "").trim();
  if (!v) return false;
  const tokens = splitValueTokens(v);
  return tokens.length > 0 && tokens.every(isThemeSafeToken);
}

export interface CssRule {
  selectors: string[];
  /** The color-carrying properties this rule declares. */
  props: Set<ColorProp>;
  /**
   * The subset of `props` whose EVERY declared value in this rule is
   * theme-safe (see `isThemeSafeColorValue`) — treated as already paired by
   * `findMissingPairs`. Light-override counting (`countLightOverrides`) and
   * pairing still use the full `props` set.
   */
  themeSafeProps: Set<ColorProp>;
}

/**
 * Parse the flat (un-nested) rules out of a CSS string into
 * `{ selectors, props, themeSafeProps }`, where `props` is the subset of
 * COLOR_PROPS the rule declares and `themeSafeProps` the sub-subset whose
 * values are purely theme tokens (auto-paired). Comments and `@keyframes`
 * blocks are stripped first. Robust enough for these hand-written `<style>`
 * blocks (nested at-rules other than `@keyframes` — e.g. a bare `@media`
 * wrapper — leave their inner rules intact and only contribute a harmless
 * prop-less wrapper).
 */
export function parseRules(css: string): CssRule[] {
  const clean = stripKeyframes(stripCssComments(css));
  const rules: CssRule[] = [];
  const re = /([^{}]+)\{([^{}]*)\}/g;
  let m: RegExpExecArray | null;
  while ((m = re.exec(clean)) !== null) {
    const selectorList = (m[1] ?? "").trim();
    if (!selectorList) continue;
    const selectors = selectorList.split(",").map(normalizeSelector).filter(Boolean);

    const props = new Set<ColorProp>();
    const unsafe = new Set<ColorProp>();
    for (const decl of (m[2] ?? "").split(";")) {
      const colon = decl.indexOf(":");
      if (colon === -1) continue;
      const prop = decl.slice(0, colon).trim().toLowerCase();
      if ((COLOR_PROPS as readonly string[]).includes(prop)) {
        props.add(prop as ColorProp);
        if (!isThemeSafeColorValue(decl.slice(colon + 1))) unsafe.add(prop as ColorProp);
      }
    }
    const themeSafeProps = new Set([...props].filter((p) => !unsafe.has(p)));
    rules.push({ selectors, props, themeSafeProps });
  }
  return rules;
}

export interface MissingPair {
  /** The base selector (light-prefix stripped for base comparison). */
  selector: string;
  property: ColorProp;
}

interface FindOptions {
  /** Wrapper selectors to scope to; empty/undefined = whole page. */
  scopes?: string[];
  isAllowed?: (selector: string, property: ColorProp) => boolean;
}

/** Is `sel` in scope for the given wrapper list (empty list = whole page)? */
function inScope(sel: string, scopes: string[]): boolean {
  if (scopes.length === 0) return true;
  return scopes.some((s) => sel === s || sel.startsWith(s + " "));
}

/**
 * Does the light map contain an override that pairs `baseSel` for `prop`?
 *
 * A base selector is paired either by an EXACT stripped light selector
 * (`html.light-mode .foo` pairs base `.foo`) or by a light selector that adds
 * one or more ANCESTORS in front of it — an "ancestor-prefixed" / suffix match
 * (`html.light-mode .events-page-body .foo` pairs base `.foo`). The suffix must
 * begin at a descendant-combinator boundary (a leading space), so a genuinely
 * different selector that merely *ends* with the same characters can never
 * masquerade as a pair: `.hashtag-pill` does NOT pair base `.pill` (there is no
 * space before `.pill`), and `.a.border` does NOT pair base `.border`. This
 * keeps the parser from masking genuinely-missing pairs while allowing the
 * common pattern of raising specificity with a body/wrapper ancestor.
 */
function lightHasPairFor(
  light: Map<string, Set<ColorProp>>,
  baseSel: string,
  prop: ColorProp,
): boolean {
  const suffix = " " + baseSel;
  for (const [lightSel, lightProps] of light) {
    if (!lightProps.has(prop)) continue;
    if (lightSel === baseSel || lightSel.endsWith(suffix)) return true;
  }
  return false;
}

/**
 * Analyze parsed rules for base color declarations that lack a paired
 * `html.light-mode <same-selector>` override of the SAME property.
 *
 * For each individual selector in each rule:
 *   - `html.light-mode …`  → record its props under the stripped key (if in scope).
 *   - non-light selector    → record its props under that key (if in scope).
 * Then every in-scope (baseSelector, property) must be paired by a light
 * override — either an exact stripped match or an ancestor-prefixed one (see
 * `lightHasPairFor`).
 */
export function findMissingPairs(rules: CssRule[], options: FindOptions = {}): MissingPair[] {
  const scopes = options.scopes ?? [];
  const isAllowed = options.isAllowed ?? (() => false);
  const base = new Map<string, Set<ColorProp>>();
  const light = new Map<string, Set<ColorProp>>();

  const add = (map: Map<string, Set<ColorProp>>, key: string, props: Set<ColorProp>) => {
    const set = map.get(key) ?? new Set<ColorProp>();
    for (const p of props) set.add(p);
    map.set(key, set);
  };

  for (const rule of rules) {
    // Theme-safe values (purely var(--…) tokens / transparent / inherit /
    // currentColor) already flip with the theme — treat them as paired and
    // only demand overrides for props carrying a literal (dark-capable) value.
    const guardedProps = new Set([...rule.props].filter((p) => !rule.themeSafeProps.has(p)));
    for (const sel of rule.selectors) {
      if (sel.startsWith(LIGHT_PREFIX)) {
        const stripped = sel.slice(LIGHT_PREFIX.length).trim();
        if (inScope(stripped, scopes)) add(light, stripped, rule.props);
      } else if (inScope(sel, scopes)) {
        add(base, sel, guardedProps);
      }
    }
  }

  const missing: MissingPair[] = [];
  for (const [sel, props] of [...base.entries()].sort((a, b) => a[0].localeCompare(b[0]))) {
    for (const prop of COLOR_PROPS) {
      if (!props.has(prop)) continue;
      if (isAllowed(sel, prop)) continue;
      if (!lightHasPairFor(light, sel, prop)) {
        missing.push({ selector: sel, property: prop });
      }
    }
  }
  return missing;
}

/** Full pipeline over a blade source string for one target's options. */
export function checkSource(src: string, options: FindOptions = {}): MissingPair[] {
  return findMissingPairs(parseRules(extractStyleBlocks(src)), options);
}

/* -------------------------------------------------------------------------- *
 * Inline-styled partial coverage (structural count proxy)
 * -------------------------------------------------------------------------- *
 * Some partials a page includes bake their dark theme colors as INLINE
 * `style="…color:{{ $var }}…"` attributes (the `$theme` prop resolves them to
 * dark values), so `findMissingPairs` has no base rule to pair against — only
 * their `html.light-mode` overrides live in the page's `<style>` block. A
 * structural proxy is used instead: every element carrying a THEMED inline
 * `color` (or `border`) must have exactly one paired `html.light-mode` override
 * targeting that partial's scope classes. Adding a themed inline color without
 * its override (or leaving an orphan override) trips the per-property count.
 */

/** The two color buckets compared between a partial and its light overrides. */
export interface Coverage {
  color: number;
  "border-color": number;
}

/**
 * A blade interpolation marks an inline value as THEME-driven (dark on the host
 * page). A literal value (e.g. `inherit`) reads the same in both themes and
 * needs no override, so it is skipped.
 */
const BLADE_INTERP = /\{\{[\s\S]*?\}\}/;

/**
 * Count the THEMED inline `color` and `border`/`border-color` declarations in a
 * partial's `style="…"` attributes. `border` shorthand and `border-color` both
 * fall in the `border-color` bucket. Only theme-variable values are counted —
 * literal values read the same in both themes. The partial's own `<style>`
 * block (hover/transition rules) is deliberately NOT scanned; only inline
 * attributes, which is where the dark colors are baked.
 */
export function countInlineThemedDecls(src: string): Coverage {
  const attrRe = /\bstyle\s*=\s*"([^"]*)"|\bstyle\s*=\s*'([^']*)'/gi;
  const out: Coverage = { color: 0, "border-color": 0 };
  let m: RegExpExecArray | null;
  while ((m = attrRe.exec(src)) !== null) {
    const body = m[1] ?? m[2] ?? "";
    for (const decl of body.split(";")) {
      const colon = decl.indexOf(":");
      if (colon === -1) continue;
      const prop = decl.slice(0, colon).trim().toLowerCase();
      const value = decl.slice(colon + 1);
      if (!BLADE_INTERP.test(value)) continue;
      if (prop === "color") out.color++;
      else if (prop === "border" || prop === "border-color") out["border-color"]++;
    }
  }
  return out;
}

/**
 * Count the `html.light-mode` override rules in a page's parsed rules that
 * target a partial's scope classes and set `color` / `border-color`.
 */
export function countLightOverrides(rules: CssRule[], scopeClasses: string[]): Coverage {
  const out: Coverage = { color: 0, "border-color": 0 };
  for (const rule of rules) {
    for (const sel of rule.selectors) {
      if (!sel.startsWith(LIGHT_PREFIX)) continue;
      const stripped = sel.slice(LIGHT_PREFIX.length);
      if (!scopeClasses.some((c) => stripped.includes("." + c))) continue;
      if (rule.props.has("color")) out.color++;
      if (rule.props.has("border-color")) out["border-color"]++;
    }
  }
  return out;
}

function partialAllowedFor(spec: PartialSpec, property: ColorProp): number {
  return (spec.allowlist ?? [])
    .filter((e) => e.property === property)
    .reduce((n, e) => n + e.inlineWithoutOverride, 0);
}

export interface PartialMismatch {
  partial: string;
  property: ColorProp;
  inline: number;
  overrides: number;
  expected: number;
}

/**
 * Compare one partial's themed inline colors against its `html.light-mode`
 * overrides in the host page. Returns a mismatch per property whose paired
 * override count does not equal the themed inline count (less any allow-listed
 * exceptions).
 */
export function checkPartial(
  partialSrc: string,
  pageSrc: string,
  spec: PartialSpec,
): PartialMismatch[] {
  const inline = countInlineThemedDecls(partialSrc);
  const overrides = countLightOverrides(parseRules(extractStyleBlocks(pageSrc)), spec.scopeClasses);
  const out: PartialMismatch[] = [];
  for (const property of COLOR_PROPS) {
    const expected = inline[property] - partialAllowedFor(spec, property);
    if (overrides[property] !== expected) {
      out.push({
        partial: spec.name,
        property,
        inline: inline[property],
        overrides: overrides[property],
        expected,
      });
    }
  }
  return out;
}

export interface TargetResult {
  target: Target;
  missing: MissingPair[];
  partialMismatches: PartialMismatch[];
  error?: string;
  /**
   * Set when the page does not participate in the app's light/dark toggle (it
   * ships its own `<html>`/`<head>` and never loads theme-styles), so the
   * `html.light-mode` pairing premise this guard checks does not hold — the
   * missing/partial results are meaningless and are skipped. See
   * `pageIsSelfContained`.
   */
  scopeError?: string;
}

/* -------------------------------------------------------------------------- *
 * Theme-scope detection (shared with the undefined-css-var guard)
 * -------------------------------------------------------------------------- *
 * This guard's whole premise — that every base color rule must have a paired
 * `html.light-mode <same-selector>` override — only holds if the page actually
 * PARTICIPATES in the app's light/dark toggle. The `html.light-mode` class is
 * added by the shared theme system (common/partials/theme-styles.blade.php,
 * loaded through the app/site layout). A page that ships its OWN `<html>`/`<head>`
 * and never `@include`s theme-styles (directly or transitively) never gets that
 * class toggled onto it, so any `html.light-mode` override the guard demands (or
 * silently accepts) is dead code — a false fail / false pass.
 *
 * Pages that `@extends` a layout do NOT declare their own document (the `<html>`
 * lives in the layout), so they are correctly treated as in-scope here. The same
 * `declaresOwnDocument` / `includesThemeStyles` detection powers the sibling
 * undefined-css-var guard so both agree on "does this page load the app theme?".
 */

/**
 * Read every non-vendor blade under VIEWS_REL into a rel-keyed map (the keying
 * `includesThemeStyles` expects) so a target's `@include` chain can be followed
 * to decide whether it loads the shared theme system.
 */
export function readViewsFileMap(): Map<string, string> {
  return readViewsFileMapShared();
}

/**
 * Convert a target's repo-relative page path to a VIEWS_REL-relative map key, or
 * `null` if the page lives outside the scanned views tree (its `@include` chain
 * cannot be resolved, so the scope check is skipped for it).
 */
export function targetViewRel(page: string): string | null {
  const prefix = VIEWS_REL + "/";
  return page.startsWith(prefix) ? page.slice(prefix.length) : null;
}

/**
 * Is `rel` a self-contained page — it declares its own `<html>`/`<head>` AND
 * never pulls in common/partials/theme-styles (directly or transitively)? Such a
 * page never receives the app's `html.light-mode` toggle, so this guard's pairing
 * check is not applicable to it. Missing files resolve to `false` (nothing to
 * check).
 */
export function pageIsSelfContained(rel: string, files: Map<string, string>): boolean {
  const raw = files.get(rel);
  if (raw === undefined) return false;
  const src = stripBladeComments(raw);
  return declaresOwnDocument(src) && !includesThemeStyles(rel, files);
}

/* -------------------------------------------------------------------------- *
 * Unknown standalone-page discovery (secondary warning pass)
 * -------------------------------------------------------------------------- *
 * TARGETS only protects pages someone remembered to configure. A NEW standalone
 * page (own `<html>`/`<head>` that opts into the app theme by loading
 * theme-styles or the theme-bootstrap partial — e.g. a future
 * order-confirmation, waitlist, or invite page modeled on rsvp-form) could ship
 * dark base `color:` / `border-color:` rules without their `html.light-mode`
 * pairs and silently regress, because nothing scans it until it is added to
 * TARGETS. This discovery pass closes that gap: it walks EVERY non-vendor blade
 * view, keeps the ones that are standalone AND theme-aware (declaresOwnDocument
 * + includesThemeStyles — the exact profile of the rsvp-form/event-ticket
 * family), skips the ones already configured in TARGETS, and runs the same
 * whole-page pairing check (with NO allowlist) over each.
 *
 * Findings are a WARNING, not a hard fail: an unknown page has no allowlist yet,
 * so a theme-neutral accent (white text on a gradient header) would be a false
 * positive if this failed the build. The warning's job is to steer the new page
 * into TARGETS, where it gets a proper allowlist and hard-fail protection.
 *
 * Layout-extending pages (no own `<html>`) are out of discovery scope on
 * purpose: nearly every app/marketing page extends a themed layout, and most
 * re-style via the shared Tailwind/theme tokens rather than page-local dark
 * CSS — scanning them all would drown the signal in theme-neutral false
 * positives. They are covered by adding them to TARGETS explicitly, as today.
 *
 * Layout SHELLS are excluded too: a view that other views `@extends` (e.g.
 * user/layouts/app.blade.php) declares its own `<html>` but is not a
 * "standalone page" — it is the app chrome, whose light styling flows through
 * the shared theme tokens/partials rather than same-file `html.light-mode`
 * pairs, so pairing-scanning it only produces chrome noise. Exclusion is
 * derived from usage (the union of every view's `@extends` targets), not from
 * a `/layouts/` path convention, so an oddly-placed layout is still excluded.
 */

export interface UnknownPageFinding {
  /** Page path relative to VIEWS_REL. */
  rel: string;
  /** Unpaired base color rules found by the whole-page check (no allowlist). */
  missing: MissingPair[];
}

/**
 * Remove `<script>…</script>` blocks so markup fragments BUILT IN JS STRINGS
 * are never mistaken for the page's own document/styles. Real example: the
 * admin newsletter compose page assembles an email-preview iframe `srcdoc` via
 * string concatenation (`'<html><head><style>body{color:#111}…'`) inside a
 * `<script>` — without stripping, `declaresOwnDocument` sees the `<html>` in
 * the JS string and `extractStyleBlocks` parses the email CSS, producing
 * garbage findings like `' + 'a { color }`. Discovery-only: the configured
 * TARGETS path keeps its exact historical behavior.
 */
export function stripScriptBlocks(src: string): string {
  return src.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, " ");
}

/**
 * Scan every blade view for standalone theme-aware pages NOT configured in
 * TARGETS whose `<style>` blocks set bare `color`/`border-color` without an
 * `html.light-mode` pair. See the block comment above for scope and rationale.
 */
export function discoverUnknownStandalonePages(
  files: Map<string, string>,
  targets: Target[] = TARGETS,
): UnknownPageFinding[] {
  const known = new Set(
    targets.map((t) => targetViewRel(t.page)).filter((r): r is string => r !== null),
  );
  // Layout shells: every view some other view `@extends`. They own an <html>
  // but are app chrome, not standalone pages — see the block comment above.
  const extendedBy = new Set<string>();
  for (const raw of files.values()) {
    for (const layout of parseExtends(stripBladeComments(raw))) extendedBy.add(layout);
  }
  const out: UnknownPageFinding[] = [];
  for (const [rel, raw] of [...files.entries()].sort((a, b) => a[0].localeCompare(b[0]))) {
    if (known.has(rel) || extendedBy.has(rel)) continue;
    const src = stripScriptBlocks(stripBladeComments(raw));
    if (!declaresOwnDocument(src)) continue;
    if (!includesThemeStyles(rel, files)) continue;
    const missing = checkSource(src);
    if (missing.length > 0) out.push({ rel, missing });
  }
  return out;
}

/**
 * Read + check a single configured target. When `files` (the VIEWS_REL-keyed map
 * from `readViewsFileMap`) is supplied, the target is first screened for the
 * theme-scope blind spot: a self-contained page (own `<html>`, no theme-styles)
 * short-circuits to a `scopeError` because the pairing premise does not hold.
 * Omitting `files` skips that screen (kept for the pure `checkSource`-style unit
 * tests that construct a `Target` without a live views tree).
 */
export function checkTarget(target: Target, files?: Map<string, string>): TargetResult {
  if (files) {
    const rel = targetViewRel(target.page);
    if (rel !== null && pageIsSelfContained(rel, files)) {
      return {
        target,
        missing: [],
        partialMismatches: [],
        scopeError:
          "page ships its own <html>/<head> and never @includes " +
          "common/partials/theme-styles.blade.php, so the app's html.light-mode class " +
          "is never toggled onto it — the light-mode pairing premise does not hold and " +
          "any html.light-mode override here is dead. Make the page load theme-styles " +
          "(extend a layout that does), or remove it from TARGETS.",
      };
    }
  }
  const abs = path.join(REPO_ROOT, target.page);
  let src: string;
  try {
    src = fs.readFileSync(abs, "utf8");
  } catch (e) {
    return { target, missing: [], partialMismatches: [], error: (e as Error).message };
  }
  const missing = checkSource(src, {
    scopes: target.scopes,
    isAllowed: makeIsAllowed(target.allowlist),
  });
  const partialMismatches: PartialMismatch[] = [];
  for (const spec of target.partials ?? []) {
    let partialSrc: string;
    try {
      partialSrc = fs.readFileSync(path.join(REPO_ROOT, spec.file), "utf8");
    } catch (e) {
      return { target, missing, partialMismatches, error: `${spec.file}: ${(e as Error).message}` };
    }
    partialMismatches.push(...checkPartial(partialSrc, src, spec));
  }
  return { target, missing, partialMismatches };
}

function describeMode(t: Target): string {
  return t.scopes && t.scopes.length ? `scoped to ${t.scopes.join(", ")}` : "whole page";
}

function printExplain(): void {
  console.log("Light-mode pairing completeness guard\n");
  console.log(
    `Rule:  every base color rule (${COLOR_PROPS.map((p) => `"${p}"`).join(" / ")}) on a`,
  );
  console.log(`       checked page must have a paired "${LIGHT_PREFIX}<same-selector>" rule`);
  console.log("       for the SAME property, or that element keeps its dark color and");
  console.log("       washes out on the white light-mode card.");
  console.log("       Values that are purely theme tokens — var(--…) without a literal");
  console.log("       fallback, transparent, inherit, currentColor — already flip with the");
  console.log("       theme and are auto-accepted (no pair or allowlist entry needed).\n");
  console.log("Checked pages:");
  for (const t of TARGETS) {
    console.log(`  • ${t.label} — ${t.page} (${describeMode(t)})`);
    for (const a of t.allowlist) {
      console.log(`      allow: ${a.selector} { ${a.property} } — ${a.reason}`);
    }
    for (const p of t.partials ?? []) {
      console.log(
        `      inline-styled partial: ${p.name} → scope ${p.scopeClasses.map((c) => "." + c).join(", ")}`,
      );
      for (const e of p.allowlist ?? []) {
        console.log(`          allow: ${e.property} −${e.inlineWithoutOverride} — ${e.reason}`);
      }
    }
  }
  console.log("\nScope:  only pages that load common/partials/theme-styles.blade.php (the");
  console.log("  source of the html.light-mode class) are validated. A target that ships its");
  console.log("  own <html>/<head> and never @includes theme-styles is self-contained — the");
  console.log("  html.light-mode class never toggles onto it, so the pairing premise fails and");
  console.log("  the guard reports it as a misconfiguration (same scope nuance as the");
  console.log("  undefined-css-var guard). Pages that @extends a layout are in-scope.");
  console.log("\nInline-styled partials bake dark colors as style=\"…\" attributes (no base");
  console.log("  rule to pair against), so each themed inline color/border must have exactly");
  console.log("  one paired html.light-mode override for that scope (a structural count).");
  console.log("\nDiscovery pass (warning-only): every OTHER standalone theme-aware blade page");
  console.log("  (ships its own <html>/<head> AND loads theme-styles or theme-bootstrap) is");
  console.log("  also scanned whole-page with no allowlist. Unpaired base color rules on such");
  console.log("  an unknown page print a warning steering it into TARGETS — they never fail");
  console.log("  the build, because an unconfigured page has no allowlist yet and a");
  console.log("  theme-neutral accent would be a false positive.");
  console.log("\nAdd a page: append a { page, label, allowlist } entry to TARGETS in");
  console.log("  scripts/src/check-light-mode-pairing.ts (add `scopes` if the page also");
  console.log("  has intentional always-dark islands outside the re-themed region, or");
  console.log("  `partials` if it includes inline-styled partials).");
}

/**
 * Print the warning-only report for unknown standalone pages with unpaired
 * color rules. Never affects the exit code — the message's job is to steer the
 * page into TARGETS (see the discovery block comment).
 */
function printUnknownPageWarnings(unknown: UnknownPageFinding[]): void {
  if (unknown.length === 0) return;
  console.warn(
    `\n⚠ light-mode-pairing discovery — ${unknown.length} standalone theme-aware page(s) NOT configured in TARGETS have unpaired base color rule(s):\n`,
  );
  for (const u of unknown) {
    console.warn(`  ${VIEWS_REL}/${u.rel}:`);
    for (const m of u.missing) {
      console.warn(`    ${m.selector} { ${m.property} } — no paired ${LIGHT_PREFIX}override`);
    }
  }
  console.warn(
    "\n  These pages load the app theme (own <html>/<head> + theme-styles/theme-bootstrap)",
  );
  console.warn(
    "  but are not protected by this guard yet, so the rules above may wash out in light",
  );
  console.warn(
    "  mode. Add each page to TARGETS in scripts/src/check-light-mode-pairing.ts (with an",
  );
  console.warn(
    "  allowlist entry + reason for any genuinely theme-neutral rule) to get hard-fail",
  );
  console.warn("  protection. This is a warning only — it does not fail the build.");
}

function main(): void {
  if (process.argv.includes("--explain")) {
    printExplain();
    process.exit(0);
  }

  let files: Map<string, string>;
  try {
    files = readViewsFileMap();
  } catch (e) {
    console.error(`✗ light-mode-pairing guard FAILED — cannot scan views: ${(e as Error).message}`);
    process.exit(2);
    return;
  }

  const results = TARGETS.map((t) => checkTarget(t, files));
  const readErrors = results.filter((r) => r.error);
  if (readErrors.length) {
    for (const r of readErrors) {
      console.error(`✗ light-mode-pairing guard FAILED — cannot read ${r.target.page}: ${r.error}`);
    }
    process.exit(2);
  }

  const scopeErrors = results.filter((r) => r.scopeError);
  if (scopeErrors.length) {
    console.error(
      "✗ light-mode-pairing guard FAILED — configured page(s) do not participate in the app light/dark toggle:\n",
    );
    for (const r of scopeErrors) {
      console.error(`  ${r.target.label} (${r.target.page}):`);
      console.error(`    ${r.scopeError}`);
    }
    console.error(
      "\nThis guard only validates pages that load common/partials/theme-styles.blade.php (the source of the html.light-mode class).",
    );
    process.exit(1);
  }

  // Warning-only discovery pass over standalone theme-aware pages that are not
  // configured in TARGETS yet — printed on both the pass and fail paths, and
  // never part of the exit code (see discoverUnknownStandalonePages).
  const unknown = discoverUnknownStandalonePages(files);

  const failed = results.filter((r) => r.missing.length > 0 || r.partialMismatches.length > 0);
  if (failed.length === 0) {
    console.log(
      `✓ light-mode-pairing guard passed — every base color rule across ${TARGETS.length} checked page(s) has its paired "${LIGHT_PREFIX}" override, and every themed inline color in the inline-styled partials has its light override.`,
    );
    printUnknownPageWarnings(unknown);
    process.exit(0);
  }

  console.error("✗ light-mode-pairing guard FAILED — washed-out light-mode gap(s) detected:\n");
  for (const r of failed) {
    console.error(`  ${r.target.label} (${r.target.page}):`);
    for (const m of r.missing) {
      console.error(`    ${m.selector} { ${m.property} }`);
      console.error(`        add:  ${LIGHT_PREFIX}${m.selector} { ${m.property}: <light value>; }`);
    }
    for (const m of r.partialMismatches) {
      const spec = (r.target.partials ?? []).find((p) => p.name === m.partial);
      const scopes = spec ? spec.scopeClasses.map((c) => "." + c).join(" / ") : m.partial;
      console.error(
        `    partial ${m.partial} { ${m.property} }: ${m.inline} themed inline decl(s), ` +
          `${m.overrides} html.light-mode override(s) for ${scopes} (expected ${m.expected}).`,
      );
      if (m.overrides < m.expected) {
        console.error(
          `        a themed inline ${m.property} was added without a paired html.light-mode override — it washes out on the white light-mode card.`,
        );
      } else {
        console.error(
          `        an orphan html.light-mode ${m.property} override has no themed inline peer — remove it or restore the inline color.`,
        );
      }
    }
  }
  console.error(
    `\nEach flagged base rule sets a dark-theme ${COLOR_PROPS.join("/")} with no paired ` +
      `"${LIGHT_PREFIX}" override, and each flagged partial has a themed-inline-vs-override count ` +
      "mismatch — both wash out on the white light-mode card.",
  );
  console.error(
    "Add the matching html.light-mode override, or (if genuinely theme-neutral) add the selector+property to that page's `allowlist` — or, for a partial, an { property, inlineWithoutOverride, reason } entry to that partial's `allowlist` — in scripts/src/check-light-mode-pairing.ts with a reason.",
  );
  console.error("Run `pnpm --filter @workspace/scripts run check:light-mode-pairing -- --explain` for details.");
  printUnknownPageWarnings(unknown);
  process.exit(1);
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
